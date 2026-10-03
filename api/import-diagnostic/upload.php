<?php
declare(strict_types=1);

/**
 * Import diagnostic upload.
 *
 * POST /api/import-diagnostic/upload.php
 *
 * The app offers this only after an import has failed, and only when the person
 * answers yes to a dialog naming the file. Nothing is sent without that answer,
 * and a successful import never reaches here.
 *
 * The file is written encrypted (AES-256-GCM, the same portal_encrypt the portal
 * uses) under storage/import-diagnostics/, which denies all HTTP access. The row
 * in import_diagnostic_files describes it; the file itself is only ever read back
 * by admin/_actions/import_file_action.php, which deletes it on the way out.
 *
 * No original filename is stored. People name statements after their business.
 */

require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/../../db_connect.php';

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['POST']);

// Same identity the AI proxy uses: a licence for premium, the device id for free.
$license = authenticate_license_request();
$deviceIdHash = null;
if (!$license) {
    $deviceIdHash = authenticate_device_request();
    if (!$deviceIdHash) {
        send_error_response(401, 'Invalid or missing license key.', 'UNAUTHORIZED');
    }
}

$rateLimitId = $license
    ? substr($license['license_key_hash'], 0, 16)
    : substr((string) $deviceIdHash, 0, 16);
if (rate_limit_hit('import_diagnostic', $rateLimitId, 'import_diagnostic')) {
    send_rate_limited_response('import_diagnostic');
}

// ─── Limits ─────────────────────────────────────────────────────────────────
// A bank statement is small. The cap is what a legitimate statement can plausibly
// be, not what the server could cope with, because every byte accepted here is a
// byte of someone's financial data sitting on our disk.
const IMPORT_DIAGNOSTIC_MAX_BYTES = 12 * 1024 * 1024;
const IMPORT_DIAGNOSTIC_RETENTION_DAYS = 7;

/** Kinds we can act on. Anything else is not a statement and is refused. */
const IMPORT_DIAGNOSTIC_KINDS = ['PDF', 'CSV', 'XLSX', 'XLS'];

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    send_error_response(400, 'No body.', 'BAD_REQUEST');
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    send_error_response(400, 'Body must be JSON.', 'BAD_REQUEST');
}

$kind = strtoupper(trim((string) ($body['kind'] ?? '')));
if (!in_array($kind, IMPORT_DIAGNOSTIC_KINDS, true)) {
    send_error_response(400, 'Unsupported file kind.', 'BAD_REQUEST');
}

$reason = trim((string) ($body['reason'] ?? ''));
// The ImportFailed contexts are our own strings, so an allowlist of shape is enough.
if ($reason === '' || !preg_match('/^[a-z0-9:_-]{1,64}$/', $reason)) {
    send_error_response(400, 'Missing or malformed reason.', 'BAD_REQUEST');
}

$contentB64 = (string) ($body['content'] ?? '');
if ($contentB64 === '') {
    send_error_response(400, 'No file content.', 'BAD_REQUEST');
}
// Reject on the encoded length first, so an oversized body is refused before it
// is expanded in memory.
if (strlen($contentB64) > (int) (IMPORT_DIAGNOSTIC_MAX_BYTES * 1.4)) {
    send_error_response(413, 'File is too large.', 'PAYLOAD_TOO_LARGE');
}

$content = base64_decode($contentB64, true);
if ($content === false || $content === '') {
    send_error_response(400, 'File content is not valid base64.', 'BAD_REQUEST');
}
if (strlen($content) > IMPORT_DIAGNOSTIC_MAX_BYTES) {
    send_error_response(413, 'File is too large.', 'PAYLOAD_TOO_LARGE');
}

// Taken from the authenticated header, never from the body. It is the same
// sha256(X-Device-Id) that api/track-app-event.php files an install under, so a
// file here joins to the device on the user activity tab.
$rawDeviceId = (string) ($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
$deviceHash = $rawDeviceId !== '' ? hash('sha256', $rawDeviceId) : '';

$appVersion = trim((string) ($body['appVersion'] ?? ''));
if ($appVersion !== '' && !preg_match('/^[0-9][0-9.]{0,19}$/', $appVersion)) {
    $appVersion = '';
}

$pageCount = isset($body['pageCount']) ? (int) $body['pageCount'] : 0;
if ($pageCount < 1 || $pageCount > 9999) {
    $pageCount = 0;
}

// ─── Store ──────────────────────────────────────────────────────────────────
$dir = __DIR__ . '/../../storage/import-diagnostics';
if (!is_dir($dir)) {
    send_error_response(500, 'Storage is unavailable.', 'STORAGE_UNAVAILABLE');
}

$storageName = bin2hex(random_bytes(32));
$path = $dir . '/' . $storageName . '.enc';

try {
    $sealed = portal_encrypt($content);
} catch (RuntimeException $e) {
    error_log('import-diagnostic: encryption unavailable: ' . $e->getMessage());
    send_error_response(500, 'Storage is unavailable.', 'STORAGE_UNAVAILABLE');
}

// Written with no group or world access: the encryption is the real protection,
// but there is no reason for the file to be readable by anything else either.
if (file_put_contents($path, $sealed, LOCK_EX) === false) {
    error_log('import-diagnostic: could not write ' . $storageName);
    send_error_response(500, 'Storage is unavailable.', 'STORAGE_UNAVAILABLE');
}
@chmod($path, 0600);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO import_diagnostic_files
            (storage_name, device_hash, failure_reason, file_kind, file_bytes, page_count,
             app_version, environment, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $stmt->execute([
        $storageName,
        $deviceHash !== '' ? $deviceHash : null,
        $reason,
        $kind,
        strlen($content),
        $pageCount > 0 ? $pageCount : null,
        $appVersion !== '' ? $appVersion : null,
        current_environment(),
        IMPORT_DIAGNOSTIC_RETENTION_DAYS,
    ]);
} catch (PDOException $e) {
    // A file with no row is invisible to the purge, so it goes now rather than
    // lingering as an orphan nobody deletes.
    @unlink($path);
    error_log('import-diagnostic: insert failed: ' . $e->getMessage());
    send_error_response(500, 'Storage is unavailable.', 'STORAGE_UNAVAILABLE');
}

send_json_response([
    'success'      => true,
    'retentionDays' => IMPORT_DIAGNOSTIC_RETENTION_DAYS,
]);
