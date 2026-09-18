<?php
/**
 * Google Sheets Export Proxy Endpoint
 *
 * POST /api/google/sheets/export - Create Google Spreadsheet with data and charts
 *
 * Receives spreadsheet data from the Argo Books app, creates a Google Sheet
 * using the company's stored OAuth tokens, and returns the spreadsheet URL.
 *
 * Free feature: authentication uses device ID.
 */

require_once __DIR__ . '/../google-helper.php';

// Load environment variables
require_once __DIR__ . '/../../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['POST']);

// Authenticate via device ID (free feature)
$authContext = authenticate_google_request();
if (!$authContext) {
    send_error_response(401, 'Missing device identifier.', 'UNAUTHORIZED');
}

// Counted per device and IP together. The device hash used to be passed as the bucket
// prefix, which gave every device its own namespace, so rotating the header reset the count.
$ip = get_client_ip();
$rateLimitId = substr($authContext['device_id_hash'], 0, 16) . ':' . $ip;
if (rate_limit_hit('sheets_export', $rateLimitId)) {
    send_rate_limited_response('sheets_export');
}

$tokenRow = get_google_tokens($authContext);

if (empty($tokenRow['google_refresh_token'])) {
    send_error_response(403, 'Google Sheets not connected. Please authorize via Settings.', 'NOT_AUTHENTICATED');
}

// Decrypt tokens
try {
    $accessToken = google_decrypt($tokenRow['google_access_token']);
    $refreshToken = google_decrypt($tokenRow['google_refresh_token']);
} catch (RuntimeException $e) {
    error_log('Failed to decrypt Google tokens: ' . $e->getMessage());
    send_error_response(500, 'Failed to decrypt stored credentials. Please re-authorize Google Sheets.', 'DECRYPT_ERROR');
}
$tokenExpires = $tokenRow['google_token_expires'];

// Refresh token if expired
if (empty($accessToken) || (!empty($tokenExpires) && strtotime($tokenExpires) <= time())) {
    $accessToken = refresh_google_token($refreshToken, $authContext);
    if (!$accessToken) {
        send_error_response(403, 'Google authorization expired. Please re-authorize via Settings.', 'TOKEN_EXPIRED');
    }
}

// Parse request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
}

if (empty($data['title'])) {
    send_error_response(400, 'Missing required field: title', 'MISSING_FIELDS');
}

$title = $data['title'];
$sheets = $data['sheets'] ?? [];
$chartConfig = $data['chartConfig'] ?? null;
$shareAsReader = $data['shareAsReader'] ?? false;

if (empty($sheets)) {
    send_error_response(400, 'At least one sheet is required.', 'MISSING_FIELDS');
}

// Step 1: Create spreadsheet
$spreadsheet = [
    'properties' => ['title' => $title],
    'sheets' => [],
];

foreach ($sheets as $i => $sheet) {
    $spreadsheet['sheets'][] = [
        'properties' => [
            'title' => $sheet['name'] ?? 'Sheet' . ($i + 1),
            'sheetId' => $i,
        ],
    ];
}

$createResponse = googleApiRequest($accessToken, 'POST', 'https://sheets.googleapis.com/v4/spreadsheets', $spreadsheet);
if (!$createResponse || !isset($createResponse['spreadsheetId'])) {
    send_error_response(502, 'Failed to create spreadsheet.', 'UPSTREAM_ERROR');
}

$spreadsheetId = $createResponse['spreadsheetId'];
$spreadsheetUrl = $createResponse['spreadsheetUrl'] ?? "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";

// Step 2: Write data to each sheet
foreach ($sheets as $i => $sheet) {
    $sheetName = $sheet['name'] ?? 'Sheet' . ($i + 1);
    $headers = $sheet['headers'] ?? [];
    $rows = $sheet['rows'] ?? [];

    $values = [];
    if (!empty($headers)) {
        $values[] = $headers;
    }
    foreach ($rows as $row) {
        $values[] = $row;
    }

    if (!empty($values)) {
        $range = "'" . str_replace("'", "''", $sheetName) . "'!A1";
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($range) . "?valueInputOption=RAW";
        $writeResult = googleApiRequest($accessToken, 'PUT', $url, ['values' => $values]);
        if ($writeResult === null || isset($writeResult['error'])) {
            $errorDetail = isset($writeResult['error']) ? json_encode($writeResult['error']) : 'API request failed';
            error_log('Google Sheets data write failed for sheet "' . $sheetName . '": ' . $errorDetail);
            send_error_response(502, 'Failed to write data to spreadsheet.', 'DATA_WRITE_ERROR');
        }
    }
}

// Step 3: Format header row + create chart via single batchUpdate
$firstSheet = $sheets[0] ?? null;
$sheetId = 0;
$numRows = count($firstSheet['rows'] ?? []) + 1; // +1 for header
$numCols = count($firstSheet['headers'] ?? []);

$batchRequests = [];

// Bold header row with background color
if ($numCols > 0) {
    $batchRequests[] = [
        'repeatCell' => [
            'range' => [
                'sheetId' => $sheetId,
                'startRowIndex' => 0,
                'endRowIndex' => 1,
                'startColumnIndex' => 0,
                'endColumnIndex' => $numCols,
            ],
            'cell' => [
                'userEnteredFormat' => [
                    'textFormat' => ['bold' => true],
                    'backgroundColor' => [
                        'red' => 0.937, 'green' => 0.937, 'blue' => 0.957, 'alpha' => 1,
                    ],
                ],
            ],
            'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
        ],
    ];
}

// Chart positioned to the right of data
if ($chartConfig && $numRows > 1 && $numCols >= 2) {
    $chartType = mapChartType($chartConfig['type'] ?? 'column');

    $sourceRange = function ($col) use ($sheetId, $numRows) {
        return [
            'sourceRange' => [
                'sources' => [[
                    'sheetId' => $sheetId,
                    'startRowIndex' => 0,
                    'endRowIndex' => $numRows,
                    'startColumnIndex' => $col,
                    'endColumnIndex' => $col + 1,
                ]],
            ],
        ];
    };

    if ($chartType === 'PIE') {
        $chartSpec = [
            'title' => $chartConfig['title'] ?? $title,
            'pieChart' => [
                'legendPosition' => 'BOTTOM_LEGEND',
                'domain' => $sourceRange(0),
                'series' => $sourceRange(1),
            ],
        ];
    } else {
        $basicChart = [
            'chartType' => $chartType,
            'legendPosition' => 'BOTTOM_LEGEND',
            'domains' => [[
                'domain' => $sourceRange(0),
            ]],
            'series' => array_map(function ($colIdx) use ($sourceRange) {
                return [
                    'series' => $sourceRange($colIdx),
                ];
            }, range(1, $numCols - 1)),
            'headerCount' => 1,
        ];

        if ($chartType === 'LINE') {
            $basicChart['lineSmoothing'] = true;
        }

        $chartSpec = [
            'title' => $chartConfig['title'] ?? $title,
            'basicChart' => $basicChart,
        ];
    }

    $batchRequests[] = [
        'addChart' => [
            'chart' => [
                'spec' => $chartSpec,
                'position' => [
                    'overlayPosition' => [
                        'anchorCell' => [
                            'sheetId' => $sheetId,
                            'rowIndex' => 1,
                            'columnIndex' => $numCols + 1,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

if (!empty($batchRequests)) {
    $batchResult = googleApiRequest($accessToken, 'POST',
        "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate",
        ['requests' => $batchRequests]
    );
    if ($batchResult === null) {
        error_log('Google Sheets batchUpdate failed for spreadsheet: ' . $spreadsheetId . '; requests: ' . json_encode($batchRequests));
    }
}

// Step 4: Share if requested
if ($shareAsReader) {
    $permission = [
        'type' => 'anyone',
        'role' => 'reader',
    ];
    googleApiRequest($accessToken, 'POST',
        "https://www.googleapis.com/drive/v3/files/{$spreadsheetId}/permissions",
        $permission
    );
}

send_json_response(200, [
    'success' => true,
    'spreadsheetUrl' => $spreadsheetUrl,
    'spreadsheetId' => $spreadsheetId,
    'timestamp' => date('c'),
]);

// --- Helper Functions ---

/**
 * Make an authenticated Google API request.
 */
function googleApiRequest(string $accessToken, string $method, string $url, ?array $body = null): ?array
{
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
    } elseif ($method === 'PUT') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode >= 400) {
        error_log("Google API error ({$method} {$url}): HTTP {$httpCode} - " . substr($response ?: '', 0, 1000));
        return null;
    }

    return json_decode($response, true);
}

/**
 * Map app chart type names to Google Sheets chart types.
 */
function mapChartType(string $type): string
{
    return match (strtolower($type)) {
        'line', 'spline' => 'LINE',
        'column', 'bar' => 'COLUMN',
        'pie' => 'PIE',
        'area' => 'AREA',
        'scatter', 'stepline' => 'SCATTER',
        default => 'COLUMN',
    };
}
