<?php
// profit-analyzer/upload.php
//
// Upload endpoint for the Profit Analyzer. Validates the file, enforces the
// per-IP rate limit, runs the analysis, and returns the computed result.
//
// The analysis step (file -> NormalizedData) is a PHP port of the desktop
// importer's analysis, living in lib/import/. pa_analyze() (pipeline.php) reads
// the file, runs the Gemini-backed sheet-type + column-mapping analysis, and
// returns the NormalizedData contract that the analytics engine consumes.

header('Content-Type: application/json; charset=utf-8');

// The analysis makes several sequential Gemini calls; give it room. Keep running
// even if the client disconnects (e.g. the user hits Cancel) so the analysis
// completes and still records the daily usage — Cancel must not be a way to dodge
// the rate limit.
@set_time_limit(300);
ignore_user_abort(true);

require_once __DIR__ . '/../rate_limit_helper.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/import/pipeline.php';

const PA_MAX_BYTES = 5 * 1024 * 1024;       // 5 MB
const PA_ALLOWED_EXT = ['xlsx', 'csv'];

function pa_fail(int $code, string $error, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    pa_fail(405, 'method_not_allowed', 'Use POST to upload a file.');
}

// --- Rate limit (the limit message IS the pitch). Check only here; we record
// AFTER a successful analysis so failed/invalid uploads (which never hit the
// paid AI) don't burn a user's daily quota. Loopback (local dev) is exempt so
// testing isn't capped — real visitors are never on 127.0.0.1. ---
$ip = get_client_ip();
$paLocal = in_array($ip, ['127.0.0.1', '::1'], true);
if (!$paLocal && rate_limit_exceeded('profit_analyzer', $ip)) {
    header('Retry-After: ' . rate_limit_window('profit_analyzer'));
    pa_fail(429, 'rate_limited',
        "You've used your free analyses for today. Want unlimited? Try Argo Books free.",
        ['cta' => '/downloads/?source=profit-analyzer-limit&utm_source=profit-analyzer&utm_medium=tool']);
}

// --- File validation ---
$file = $_FILES['file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    pa_fail(400, 'no_file', 'No spreadsheet was uploaded.');
}
if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
    pa_fail(400, 'upload_error', 'The upload did not complete. Please try again.');
}
if (($file['size'] ?? 0) > PA_MAX_BYTES) {
    pa_fail(413, 'too_large', 'That file is over 5 MB. Trim it down or split it and try again.');
}
$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, PA_ALLOWED_EXT, true)) {
    pa_fail(415, 'bad_type', 'Please upload an .xlsx or .csv spreadsheet.');
}

// Move out of PHP's transient upload slot so we control deletion.
$tmp = tempnam(sys_get_temp_dir(), 'pa_');
if (!$tmp || !move_uploaded_file($file['tmp_name'], $tmp)) {
    pa_fail(500, 'io_error', 'Could not read the uploaded file. Please try again.');
}

try {
    $normalized = pa_analyze($tmp, $ext, $file['name'] ?? '');
    $analytics = pa_compute_analytics($normalized);
} catch (Throwable $e) {
    @unlink($tmp);
    error_log('profit-analyzer analysis failed: ' . $e->getMessage());
    pa_fail(422, 'unreadable', "We couldn't make sense of that spreadsheet. Try a cleaner export.");
}

// Delete the raw upload immediately after analysis (Option A: store nothing).
@unlink($tmp);

// Only successful analyses count toward the daily quota (local dev exempt).
if (!$paLocal) {
    rate_limit_record('profit_analyzer', $ip);
}

// Return both the chart-ready analytics and the full NormalizedData. Under
// Option A the server stores nothing, so the client keeps `normalized` for the
// session and posts it back to download.php / email.php to build the cleaned
// spreadsheet on demand.
echo json_encode(['ok' => true, 'analytics' => $analytics, 'normalized' => $normalized]);
