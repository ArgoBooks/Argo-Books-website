<?php
/**
 * Bank statement PDF extraction endpoint.
 *
 * POST /api/bank/extract.php
 *
 * Receives a PDF bank statement (multipart field "statement") from the Argo Books
 * desktop app, uploads it to the Gemini Files API, asks the model to extract the
 * transaction rows, and returns them as structured JSON. Open to the free tier within
 * the monthly AI import allowance, which the desktop app checks and counts around it.
 * Stores nothing past the request. The Gemini API key lives server-side.
 *
 * The rows are checked against the opening and closing balances the statement prints. When
 * they don't add up and the app will still be waiting, the model is asked to look again.
 *
 * Response shape (consumed by PdfStatementExtractor.ParseRows in the desktop app):
 *   { "success": true, "lines": [ { "date": "YYYY-MM-DD", "description": "...", "amount": -12.34 } ],
 *     "check": { "status": "balanced|fixed|off|unchecked", "gap": 0.00 } }
 *
 * Mirrors the proven PDF upload/poll/generate/cleanup flow in api/ai/completions.php.
 */

require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/../ai/_timing.php';
require_once __DIR__ . '/_statement_check.php';

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

@set_time_limit(300);
ignore_user_abort(true);

set_portal_headers();
require_method(['POST']);

const BS_MAX_BYTES = 15 * 1024 * 1024; // 15 MB upload ceiling
// Thinking shares this allowance with the answer, and the app stops waiting after 120 seconds, which is about this many tokens.
const BS_MAX_OUTPUT_TOKENS = 32768;
const BS_MIN_SECONDS_FOR_SECOND_PASS = 45;

// --- 1. Auth: a licence, or the device id for the free tier ---
// The app offers PDF import to free users inside their monthly allowance.
$license = authenticate_license_request();
$deviceIdHash = null;
if (!$license) {
    $deviceIdHash = authenticate_device_request();
    if (!$deviceIdHash) {
        send_error_response(401, 'A valid license or device id is required for PDF statement import.', 'UNAUTHORIZED');
    }
}

// --- 2. Rate limit per identity: 30 PDF extractions per 15 minutes. ---
$rateLimitId = $license
    ? substr($license['license_key_hash'], 0, 16)
    : substr($deviceIdHash, 0, 16);
if (rate_limit_hit('bank_extract', $rateLimitId)) {
    send_rate_limited_response('bank_extract');
}

// A per-IP ceiling for the free path only, as on the AI proxy, because a device id is self-asserted and can be rotated.
if (!$license) {
    $clientIp = get_client_ip();
    if (rate_limit_hit('bank_extract_ip', $clientIp, 'bank_ip')) {
        send_rate_limited_response('bank_extract_ip');
    }
}

// PHP empties $_FILES when the body exceeds post_max_size; surface a clear message.
if (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    send_error_response(413, 'That PDF is too large. Please use a file under 15 MB.', 'TOO_LARGE');
}

// --- 3. File validation ---
$file = $_FILES['statement'] ?? null;
$uploadErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if (!$file || $uploadErr === UPLOAD_ERR_NO_FILE) {
    send_error_response(400, 'No PDF statement was uploaded.', 'NO_FILE');
}
if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
    send_error_response(413, 'That PDF is too large. Please use a file under 15 MB.', 'TOO_LARGE');
}
if ($uploadErr !== UPLOAD_ERR_OK) {
    send_error_response(400, 'The upload did not complete. Please try again.', 'UPLOAD_ERROR');
}
if (($file['size'] ?? 0) > BS_MAX_BYTES) {
    send_error_response(413, 'That PDF is over 15 MB. Please use a smaller file.', 'TOO_LARGE');
}

$pdfBytes = @file_get_contents($file['tmp_name']);
if ($pdfBytes === false || $pdfBytes === '') {
    send_error_response(500, 'Could not read the uploaded file. Please try again.', 'IO_ERROR');
}
// Cheap content check: a PDF begins with "%PDF-".
if (substr($pdfBytes, 0, 5) !== '%PDF-') {
    send_error_response(415, 'That file does not look like a PDF.', 'BAD_TYPE');
}

// Page count is the strongest up-front predictor of extraction time; capture it for
// the timing priors (best-effort heuristic, null when it can't be determined).
$pageCount = bs_pdf_page_count($pdfBytes);

// --- 4. Config ---
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
if ($geminiKey === '') {
    send_error_response(500, 'Extraction is not configured on the server.', 'CONFIG_ERROR');
}
$model = $_ENV['GEMINI_MODEL_EXTRACTION'] ?? 'gemini-3.5-flash';

// --- 5. Upload the PDF to the Gemini Files API (inline_data only supports images). ---
$fileName = bs_upload_pdf($geminiKey, $pdfBytes);
if ($fileName === null) {
    send_error_response(502, 'Failed to upload the PDF to the extraction service.', 'UPSTREAM_ERROR');
}
$pollCount = 0;
if (!bs_wait_active($geminiKey, $fileName, $pollCount)) {
    bs_delete_file($geminiKey, $fileName);
    send_error_response(502, 'The extraction service could not process the PDF.', 'UPSTREAM_ERROR');
}

// --- 6. Ask the model to extract the rows. ---
// The app says how long it waits for an answer. Versions before 2.0.24 don't, and wait 120 seconds.
$clientWaitSeconds = max(60, min(290, (int) ($_POST['waitSeconds'] ?? 120)));
$requestStart = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

$first = bs_call_gemini($geminiKey, $model, $fileName, bs_extract_prompt(), bs_seconds_left($requestStart, $clientWaitSeconds));

$timingBase = [
    'model' => $model,
    'size_feature' => strlen($pdfBytes),
    'page_count' => $pageCount,
    'input_bytes' => strlen($pdfBytes),
    'mime' => 'application/pdf',
    'max_output_tokens' => BS_MAX_OUTPUT_TOKENS,
    'poll_count' => $pollCount,
    'caller' => $rateLimitId,
    'tier' => $license ? 'premium' : 'free',
];
$firstTiming = ['operation' => 'bank_pdf_extract'] + bs_timing_fields($first) + $timingBase;

// An answer cut off at the token limit is half-written JSON, which would otherwise read as a statement with no transactions.
if ($first['content'] !== null && $first['finish'] === 'MAX_TOKENS') {
    bs_delete_file($geminiKey, $fileName);
    ai_timing_record($firstTiming + ['success' => false]);
    send_error_response(422, 'This statement is too long to read in one go. Try splitting it into smaller files, such as one month each.', 'TOO_LONG');
}
if ($first['content'] === null || ($first['finish'] !== null && $first['finish'] !== 'STOP')) {
    bs_delete_file($geminiKey, $fileName);
    if ($first['elapsed_ms'] !== null) {
        ai_timing_record($firstTiming + ['success' => false]);
    }
    send_error_response(502, 'The extraction service had trouble reading that PDF.', 'UPSTREAM_ERROR');
}

$parsed = bs_parse_statement($first['content']);
$check = bs_check_balance($parsed);
$checkStatus = $check['status'];
$lines = $parsed['lines'];

// A second look takes about as long as the first, so it runs only when the app will still be waiting for the answer.
$secondsLeft = bs_seconds_left($requestStart, $clientWaitSeconds);
if ($check['status'] === 'off' && $secondsLeft >= BS_MIN_SECONDS_FOR_SECOND_PASS) {
    $second = bs_call_gemini($geminiKey, $model, $fileName, bs_verify_prompt($parsed, $check['gap']), $secondsLeft);
    $secondParsed = $second['content'] !== null && $second['finish'] === 'STOP' ? bs_parse_statement($second['content']) : null;
    $secondCheck = $secondParsed !== null && $secondParsed['lines'] !== [] ? bs_check_balance($secondParsed) : null;

    ai_timing_record(['operation' => 'bank_pdf_verify'] + bs_timing_fields($second) + $timingBase + [
        'success' => $secondCheck !== null,
        'check_result' => $secondCheck['status'] ?? null,
        'check_gap' => $secondCheck['gap'] ?? null,
    ]);

    if ($secondCheck !== null && $secondCheck['status'] === 'balanced') {
        $lines = $secondParsed['lines'];
        $check = $secondCheck;
        $checkStatus = 'fixed';
    } elseif ($secondCheck !== null && $secondCheck['status'] === 'off' && abs($secondCheck['gap']) < abs($check['gap'])) {
        $lines = $secondParsed['lines'];
        $check = $secondCheck;
    }
}
bs_delete_file($geminiKey, $fileName); // store nothing past this point

ai_timing_record($firstTiming + ['success' => true, 'check_result' => $checkStatus, 'check_gap' => $check['gap']]);

// A valid-but-empty result (no transactions found) is still success: the client
// shows its own "no transactions" message. Hard failures returned errors above.
send_json_response(200, [
    'success' => true,
    'lines' => $lines,
    'check' => ['status' => $checkStatus, 'gap' => $check['gap']],
    'timing' => [
        'elapsed_ms' => $first['elapsed_ms'] ?? 0,
        'prompt_tokens' => $first['usage']['promptTokenCount'] ?? 0,
        'output_tokens' => $first['usage']['candidatesTokenCount'] ?? 0,
        'load_factor' => ai_timing_load_factor(),
    ],
    'timestamp' => date('c'),
]);

// ---------------------------------------------------------------------------

/**
 * Uploads PDF bytes to the Gemini Files API. Returns the file resource name
 * (e.g. "files/abc123") on success, or null on failure.
 */
function bs_upload_pdf(string $geminiKey, string $pdfBytes): ?string
{
    $uploadUrl = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
    $boundary = bin2hex(random_bytes(16));
    $metadataJson = json_encode(['file' => ['displayName' => 'statement.pdf']]);
    $multipartBody = "--{$boundary}\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadataJson . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: application/pdf\r\n\r\n"
        . $pdfBytes . "\r\n"
        . "--{$boundary}--\r\n";

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $multipartBody,
        CURLOPT_HTTPHEADER => [
            "x-goog-api-key: {$geminiKey}",
            "Content-Type: multipart/related; boundary={$boundary}",
            'X-Goog-Upload-Protocol: multipart',
            'Content-Length: ' . strlen($multipartBody),
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $httpCode !== 200) {
        error_log('[bank-extract] Files API upload failed (' . $httpCode . '): ' . substr((string)$resp, 0, 500));
        return null;
    }
    $data = json_decode($resp, true);
    $name = $data['file']['name'] ?? null;
    if (empty($name)) {
        error_log('[bank-extract] Files API returned no file name: ' . substr($resp, 0, 500));
        return null;
    }
    return $name;
}

/**
 * Polls the uploaded file until it reaches ACTIVE state (Gemini processes uploads
 * asynchronously). Returns true once ACTIVE, false on FAILED or timeout (~7.5s).
 */
function bs_wait_active(string $geminiKey, string $fileName, int &$pollCount = 0): bool
{
    $statusUrl = "https://generativelanguage.googleapis.com/v1beta/{$fileName}";
    for ($i = 0; $i < 15; $i++) {
        $pollCount = $i + 1;
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["x-goog-api-key: {$geminiKey}"],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $state = json_decode((string)$resp, true)['state'] ?? '';
        if ($state === 'ACTIVE') {
            return true;
        }
        if ($state === 'FAILED') {
            error_log('[bank-extract] file processing FAILED: ' . substr((string)$resp, 0, 500));
            return false;
        }
        usleep(500000); // 500ms
    }
    return false;
}

/** Deletes the uploaded file from Gemini storage (best effort). */
function bs_delete_file(string $geminiKey, string $fileName): void
{
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/{$fileName}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ["x-goog-api-key: {$geminiKey}"],
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
}

/** Seconds until the app stops waiting, keeping a few back to send the answer. */
function bs_seconds_left(float $requestStart, int $clientWaitSeconds): int
{
    return (int) floor($clientWaitSeconds - 5 - (microtime(true) - $requestStart));
}

/** The timing columns for one Gemini call. */
function bs_timing_fields(array $call): array
{
    return [
        'prompt_tokens' => $call['usage']['promptTokenCount'] ?? null,
        'output_tokens' => $call['usage']['candidatesTokenCount'] ?? null,
        'thought_tokens' => $call['usage']['thoughtsTokenCount'] ?? null,
        'finish_reason' => $call['finish'],
        'elapsed_ms' => $call['elapsed_ms'] ?? 0,
    ];
}

function bs_extract_prompt(): string
{
    return <<<'PROMPT'
Extract every transaction row from this statement. Return JSON of this exact shape:

{ "openingBalance": number or null, "closingBalance": number or null, "creditCard": boolean,
  "accountCount": number, "lines": [ { "date": "YYYY-MM-DD", "description": "string", "amount": number } ] }

Rules:
- One object per real transaction. SKIP headers, column labels, page numbers, and
  summary rows such as opening balance, closing balance, totals, and subtotals.
- "amount" is a signed number. Money IN (deposits, credits, payments received,
  interest, refunds) is POSITIVE. Money OUT (withdrawals, debits, card payments,
  fees, transfers out) is NEGATIVE.
- For statements with separate Debit and Credit columns: a Credit value is POSITIVE,
  a Debit value is NEGATIVE. Ignore any running Balance column.
- "date" must be ISO format YYYY-MM-DD. If a row omits the year, infer it from the
  statement period. If a date cannot be determined, omit that row.
- "description" is the transaction narrative, trimmed of extra whitespace.
- "openingBalance" and "closingBalance" are the balances the statement prints for the
  start and end of the period, such as "Balance forward" or "Beginning balance" and
  "Ending balance" or "Closing balance". Copy them as printed, and use null when the
  statement does not print one. An overdrawn balance is negative.
- "creditCard" is true for a credit card statement, where the balance is the amount owed.
- "accountCount" is how many separate accounts have transactions listed in this document.
- If the document contains no transactions, return { "lines": [] }.
PROMPT;
}

/** Asks for the rows again, telling the model how far its first list is from the printed closing balance. */
function bs_verify_prompt(array $parsed, float $gap): string
{
    $rows = json_encode(['lines' => $parsed['lines']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $opening = number_format((float) $parsed['opening'], 2, '.', '');
    $closing = number_format((float) $parsed['closing'], 2, '.', '');
    $gapText = number_format(abs($gap), 2, '.', '');
    $formula = $parsed['credit_card']
        ? 'The opening balance minus the sum of the amounts'
        : 'The opening balance plus the sum of the amounts';

    return <<<PROMPT
These transactions were extracted from this statement, but they do not add up. {$formula}
should equal the closing balance of {$closing}, starting from the opening balance of {$opening},
and it is {$gapText} away from it.

{$rows}

Go through the statement again, page by page. Find transactions that are missing, listed twice,
have the wrong amount, or have money in and money out the wrong way round. Return the complete
corrected list in this exact JSON shape:

{ "openingBalance": number or null, "closingBalance": number or null, "creditCard": boolean,
  "accountCount": number, "lines": [ { "date": "YYYY-MM-DD", "description": "string", "amount": number } ] }

Money IN is POSITIVE and money OUT is NEGATIVE. Skip opening balance, closing balance, total and
subtotal rows.
PROMPT;
}

/**
 * Calls Gemini generateContent referencing the uploaded PDF.
 *
 * @return array{content: ?string, finish: ?string, elapsed_ms: ?int, usage: ?array}
 */
function bs_call_gemini(string $geminiKey, string $model, string $fileName, string $prompt, int $timeoutSeconds): array
{
    $result = ['content' => null, 'finish' => null, 'elapsed_ms' => null, 'usage' => null];
    $fileUri = "https://generativelanguage.googleapis.com/v1beta/{$fileName}";

    $system = 'You extract transactions from a bank or credit-card statement PDF. '
        . 'Always respond with valid JSON only, no markdown, matching the requested schema.';

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [
                ['file_data' => ['file_uri' => $fileUri, 'mime_type' => 'application/pdf']],
                ['text' => $prompt],
            ],
        ]],
        'system_instruction' => ['parts' => [['text' => $system]]],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => BS_MAX_OUTPUT_TOKENS,
            'responseMimeType' => 'application/json',
        ],
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["x-goog-api-key: {$geminiKey}", 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => max(10, $timeoutSeconds),
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $result['elapsed_ms'] = (int) round((microtime(true) - $t0) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $httpCode !== 200) {
        error_log('[bank-extract] Gemini error ' . $httpCode . ': ' . substr((string)$resp, 0, 500));
        return $result;
    }
    $data = json_decode($resp, true);
    $result['usage'] = $data['usageMetadata'] ?? null;
    $candidate = $data['candidates'][0] ?? [];
    $result['finish'] = $candidate['finishReason'] ?? null;
    if ($result['finish'] !== null && $result['finish'] !== 'STOP') {
        error_log('[bank-extract] non-STOP finishReason=' . $result['finish']);
    }
    $result['content'] = $candidate['content']['parts'][0]['text'] ?? null;
    return $result;
}

/**
 * Best-effort page count from raw PDF bytes, used only as a timing size-feature.
 * Counts "/Type /Page" objects (the word boundary excludes the "/Pages" tree node).
 * Returns null when it cannot be determined; never throws.
 */
function bs_pdf_page_count(string $pdfBytes): ?int
{
    if (@preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes, $m)) {
        $count = count($m[0]);
        return $count > 0 ? $count : null;
    }
    return null;
}
