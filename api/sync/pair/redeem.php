<?php
/**
 * POST /api/sync/pair/redeem - the phone redeems a pairing token from the QR.
 * Auth: none (the pairing token is the credential).
 * Body: { "pairing_token": "...", "device_label": "..." }
 * Returns: { success, device_token, company_uid, company_label }
 */
require_once __DIR__ . '/../sync-helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../')->safeLoad();

set_portal_headers();
require_method(['POST']);

$ip = get_client_ip();
if (rate_limit_hit('sync_redeem', $ip)) {
    send_rate_limited_response('sync_redeem');
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
}
$token = trim((string) ($data['pairing_token'] ?? ''));
$deviceLabel = substr(trim((string) ($data['device_label'] ?? '')), 0, 255);
if ($token === '') {
    send_error_response(400, 'pairing_token is required.', 'INVALID_TOKEN');
}

$binding = consume_pairing_token($token);
if (!$binding) {
    send_error_response(410, 'This pairing code has expired or was already used.', 'PAIRING_EXPIRED');
}

global $pdo;
$deviceToken = bin2hex(random_bytes(32));
$pdo->prepare(
    'INSERT INTO mobile_sync_devices (device_token_hash, owner_identity_hash, company_uid, device_label, last_seen_at)
     VALUES (?, ?, ?, ?, NOW())'
)->execute([
    hash('sha256', $deviceToken),
    $binding['owner_identity_hash'],
    $binding['company_uid'],
    $deviceLabel,
]);

send_json_response(200, [
    'success' => true,
    'device_token' => $deviceToken,
    'company_uid' => $binding['company_uid'],
    'company_label' => $binding['company_label'],
]);
