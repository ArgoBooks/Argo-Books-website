<?php
declare(strict_types=1);

/**
 * GET|POST /api/developer/account
 *
 * The control plane for the public API, called by Argo Books itself rather than
 * by a developer. It is authenticated by the desktop's own identity (license key
 * for premium, device id for free), the same way api/sync is, because this is
 * where API keys are born and a key cannot authenticate its own creation.
 *
 * GET  returns the account for a company, or 404 if the owner has not enabled
 *      the API for it yet.
 * POST enables it, and is idempotent: enabling twice returns the same account.
 *
 * Body/query both take company_uid, which is the desktop's identifier for the
 * company file the API will feed.
 */

require_once __DIR__ . '/../sync/sync-helper.php';
require_once __DIR__ . '/../v1/lib/ids.php';

set_portal_headers();
require_method(['GET', 'POST']);

$license = authenticate_license_request();
$owner = $license['license_key_hash'] ?? resolve_owner_identity();
if (!$owner) {
    send_error_response(401, 'Unauthorized.', 'UNAUTHORIZED');
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$body = [];
if ($isPost) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
    }
}

$companyUid = trim((string) ($body['company_uid'] ?? $_GET['company_uid'] ?? ''));
if ($companyUid === '' || strlen($companyUid) > 64) {
    send_error_response(400, 'company_uid is required (max 64 chars).', 'INVALID_INPUT');
}

global $pdo;

$stmt = $pdo->prepare(
    'SELECT * FROM api_accounts WHERE owner_identity_hash = ? AND company_uid = ? LIMIT 1'
);
$stmt->execute([$owner, $companyUid]);
$account = $stmt->fetch();

if (!$account && !$isPost) {
    send_error_response(404, 'The API is not enabled for this company.', 'API_NOT_ENABLED');
}

if (!$account) {
    if (!$license) {
        send_error_response(403, 'The Argo Books API is part of Premium.', 'PREMIUM_REQUIRED');
    }
    if (rate_limit_hit('api_account_create_ip', get_client_ip())) {
        send_rate_limited_response('api_account_create_ip');
    }

    $displayName = trim((string) ($body['display_name'] ?? ''));
    $publicId = api_generate_id('acct');

    $pdo->prepare(
        'INSERT INTO api_accounts (public_id, owner_identity_hash, company_uid, subscription_id, display_name)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$publicId, $owner, $companyUid, $license['subscription_id'], substr($displayName, 0, 255)]);

    $stmt->execute([$owner, $companyUid]);
    $account = $stmt->fetch();
}

// A renewal or a re-redeemed key can move the license to a new subscription row, and /v1 checks
// the one recorded here, so keep it current whenever the app calls in with its license.
if ($license && $account['subscription_id'] !== $license['subscription_id']) {
    $pdo->prepare('UPDATE api_accounts SET subscription_id = ? WHERE id = ?')
        ->execute([$license['subscription_id'], (int) $account['id']]);
    $account['subscription_id'] = $license['subscription_id'];
}

// Rename on a repeat POST, so the merchant can correct the label later.
if ($isPost && isset($body['display_name'])) {
    $displayName = substr(trim((string) $body['display_name']), 0, 255);
    if ($displayName !== $account['display_name']) {
        $pdo->prepare('UPDATE api_accounts SET display_name = ? WHERE id = ?')
            ->execute([$displayName, (int) $account['id']]);
        $account['display_name'] = $displayName;
    }
}

$keyCount = $pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE account_id = ? AND revoked_at IS NULL');
$keyCount->execute([(int) $account['id']]);

send_json_response(200, [
    'success'      => true,
    'account_id'   => $account['public_id'],
    'company_uid'  => $account['company_uid'],
    'display_name' => $account['display_name'],
    'is_active'    => (bool) $account['is_active'],
    'active_keys'  => (int) $keyCount->fetchColumn(),
    'base_url'     => rtrim(site_url(), '/') . '/v1',
]);
