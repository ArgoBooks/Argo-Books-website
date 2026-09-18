<?php
/**
 * POST /api/sync/pair/claim - the phone redeems a manually-typed short code
 * and uploads its public key, in exchange for a device token.
 * Auth: none (the short code is the credential).
 * Body: { "code": "...", "phone_public_key": "...", "device_label": "..." }
 * Returns: { success, company_uid, company_label, device_token }
 */
require_once __DIR__ . '/../sync-helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../')->safeLoad();

set_portal_headers();
require_method(['POST']);

$ip = get_client_ip();
if (rate_limit_hit('sync_claim', $ip)) {
    send_rate_limited_response('sync_claim');
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
}

$code = trim((string) ($data['code'] ?? ''));
$phonePublicKey = trim((string) ($data['phone_public_key'] ?? ''));
$deviceLabel = substr(trim((string) ($data['device_label'] ?? '')), 0, 255);

// Generic error for every "code didn't work" case below: don't tell the
// caller whether it was wrong, expired, or already used.
$invalidCode = static function () {
    send_error_response(400, 'That code is not valid or has expired.', 'INVALID_CODE');
};

if ($code === '' || strlen($code) > 64) {
    $invalidCode();
}
if ($phonePublicKey === '' || strlen($phonePublicKey) > 2000 || !preg_match('/^[A-Za-z0-9+\/=]+$/', $phonePublicKey)) {
    send_error_response(400, 'phone_public_key must be a non-empty base64 string.', 'INVALID_KEY');
}

$result = claim_pairing_code($code, $phonePublicKey, $deviceLabel);
if (!$result) {
    $invalidCode();
}

send_json_response(200, [
    'success' => true,
    'company_uid' => $result['company_uid'],
    'company_label' => $result['company_label'],
    'device_token' => $result['device_token'],
]);
