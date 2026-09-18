<?php
/**
 * POST /api/sync/pair/create - desktop asks for a pairing token to embed in its QR,
 * plus a short code the phone can type in manually.
 * Auth: desktop owner (license key or device id).
 * Body: { "company_uid": "...", "company_label": "..." }
 * Returns: { success, pairing_token, short_code, expires_in_seconds }
 */
require_once __DIR__ . '/../sync-helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../')->safeLoad();

set_portal_headers();
require_method(['POST']);

$owner = resolve_owner_identity();
if (!$owner) {
    send_error_response(401, 'Missing or invalid license key or device id.', 'UNAUTHORIZED');
}

$ip = get_client_ip();
if (rate_limit_hit('sync_pair', $ip)) {
    send_rate_limited_response('sync_pair');
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
}
$companyUid = trim((string) ($data['company_uid'] ?? ''));
$companyLabel = trim((string) ($data['company_label'] ?? ''));
if ($companyUid === '' || strlen($companyUid) > 64) {
    send_error_response(400, 'company_uid is required.', 'INVALID_COMPANY');
}
if (strlen($companyLabel) > 255) {
    $companyLabel = substr($companyLabel, 0, 255);
}

$pairing = create_pairing_token($owner, $companyUid, $companyLabel);
send_json_response(200, [
    'success' => true,
    'pairing_token' => $pairing['token'],
    'short_code' => $pairing['short_code'],
    'expires_in_seconds' => 600,
]);
