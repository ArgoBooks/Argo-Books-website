<?php
/**
 * Bank statement PDF extraction endpoint.
 *
 * POST /api/bank/extract.php
 *
 * Receives a PDF bank statement (multipart field "statement") from the Argo Books
 * desktop app, uploads it to the Gemini Files API, asks the model to extract the
 * transaction rows, and returns them as structured JSON. Premium (license) gated.
 * Stores nothing past the request. The Gemini API key lives server-side.
 *
 * Response shape (consumed by PdfStatementExtractor.ParseRows in the desktop app):
 *   { "success": true, "lines": [ { "date": "YYYY-MM-DD", "description": "...", "amount": -12.34 } ] }
 *
 * Mirrors the proven PDF upload/poll/generate/cleanup flow in api/ai/completions.php.
 */

require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/../ai/_timing.php';

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

@set_time_limit(180);
ignore_user_abort(true);

set_portal_headers();
require_method(['POST']);

const BS_MAX_BYTES = 15 * 1024 * 1024; // 15 MB upload ceiling

// --- 1. Auth: premium license required (the desktop PDF import is premium-gated). ---
$license = authenticate_license_request();
if (!$license) {
    send_error_response(401, 'A valid license is required for PDF statement import.', 'UNAUTHORIZED');
}

// --- 2. Rate limit per license: 30 PDF extractions per 15 minutes. ---
$rateLimitId = substr($license['license_key_hash'], 0, 16);
if (rate_limit_hit('bank_extract', $rateLimitId)) {
    send_rate_limited_response('bank_extract');
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
$extractElapsedMs = null;
$extractUsage = null;
$content = bs_call_gemini($geminiKey, $model, $fileName, $extractElapsedMs, $extractUsage);
bs_delete_file($geminiKey, $fileName); // store nothing past this point

if ($content === null) {
    send_error_response(502, 'The extraction service had trouble reading that PDF.', 'UPSTREAM_ERROR');
}

$lines = bs_normalize_lines($content);

// Record the server-measured timing (best-effort; never breaks the response).
ai_timing_record([
    'operation' => 'bank_pdf_extract',
    'model' => $model,
    'size_feature' => strlen($pdfBytes),
    'page_count' => $pageCount,
    'input_bytes' => strlen($pdfBytes),
    'mime' => 'application/pdf',
    'prompt_tokens' => $extractUsage['promptTokenCount'] ?? null,
    'output_tokens' => $extractUsage['candidatesTokenCount'] ?? null,
    'max_output_tokens' => 16000,
    'finish_reason' => 'STOP',
    'elapsed_ms' => $extractElapsedMs ?? 0,
    'poll_count' => $pollCount,
    'success' => true,
]);

// A valid-but-empty result (no transactions found) is still success: the client
// shows its own "no transactions" message. Hard failures returned errors above.
send_json_response(200, [
    'success' => true,
    'lines' => $lines,
    'timing' => [
        'elapsed_ms' => $extractElapsedMs ?? 0,
        'prompt_tokens' => $extractUsage['promptTokenCount'] ?? 0,
        'output_tokens' => $extractUsage['candidatesTokenCount'] ?? 0,
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

/**
 * Calls Gemini generateContent referencing the uploaded PDF. Returns the JSON text or null.
 * Reports the server-measured wall time (ms) and Gemini usageMetadata via by-ref params
 * for the timing priors.
 */
function bs_call_gemini(string $geminiKey, string $model, string $fileName, ?int &$elapsedMs = null, ?array &$usage = null): ?string
{
    $fileUri = "https://generativelanguage.googleapis.com/v1beta/{$fileName}";

    $system = 'You extract transactions from a bank or credit-card statement PDF. '
        . 'Always respond with valid JSON only, no markdown, matching the requested schema.';

    $prompt = <<<'PROMPT'
Extract every transaction row from this statement. Return JSON of this exact shape:

{ "lines": [ { "date": "YYYY-MM-DD", "description": "string", "amount": number } ] }

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
- If the document contains no transactions, return { "lines": [] }.
PROMPT;

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
            'maxOutputTokens' => 16000,
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
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $elapsedMs = (int) round((microtime(true) - $t0) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false || $httpCode !== 200) {
        error_log('[bank-extract] Gemini error ' . $httpCode . ': ' . substr((string)$resp, 0, 500));
        return null;
    }
    $data = json_decode($resp, true);
    $usage = $data['usageMetadata'] ?? null;
    $candidate = $data['candidates'][0] ?? [];
    // gemini-2.5-flash spends hidden "thinking" tokens out of maxOutputTokens; log any
    // non-STOP finish so truncation on a very long statement is visible, not silent.
    $finish = $candidate['finishReason'] ?? null;
    if ($finish !== null && $finish !== 'STOP') {
        error_log('[bank-extract] non-STOP finishReason=' . $finish);
    }
    return $candidate['content']['parts'][0]['text'] ?? null;
}

/**
 * Parses the model's JSON content into clean line rows. Tolerant of a bare array,
 * a { "lines": [...] } wrapper, and string amounts. Returns a list of
 * [ 'date' => 'YYYY-MM-DD', 'description' => string, 'amount' => float ].
 */
function bs_normalize_lines(string $content): array
{
    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return [];
    }
    $rows = $decoded['lines'] ?? (array_is_list($decoded) ? $decoded : []);
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $desc = trim((string)($row['description'] ?? ''));
        $rawAmount = $row['amount'] ?? null;
        if ($rawAmount === null || !is_numeric($rawAmount)) {
            continue; // a row with no usable amount is not a transaction
        }
        $amount = (float)$rawAmount;

        $date = trim((string)($row['date'] ?? ''));
        $ts = $date !== '' ? strtotime($date) : false;
        $isoDate = $ts !== false ? date('Y-m-d', $ts) : '';

        if ($desc === '' && $isoDate === '') {
            continue;
        }
        $out[] = [
            'date' => $isoDate,
            'description' => $desc,
            'amount' => $amount,
        ];
    }
    return $out;
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
