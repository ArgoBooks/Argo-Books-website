<?php
declare(strict_types=1);

/**
 * GET|POST /api/developer/keys
 *
 * Key management for the public API, called by Argo Books Settings.
 *
 * GET  lists the account's keys as hints (ab_1a2b...wxyz) plus scopes and last
 *      use. The secrets themselves are not stored, so they cannot be listed.
 * POST mints a new key and returns the secret exactly once. If the merchant
 *      loses it they revoke and mint another; there is no recovery, which is
 *      the whole point of storing only the hash.
 *
 * Authenticated by the desktop's own identity, not by an API key.
 */

require_once __DIR__ . '/../sync/sync-helper.php';
require_once __DIR__ . '/../v1/lib/ids.php';

set_portal_headers();
require_method(['GET', 'POST']);

$owner = resolve_owner_identity();
if (!$owner) {
    send_error_response(401, 'Unauthorized.', 'UNAUTHORIZED');
}

global $pdo;

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$body = [];
if ($isPost) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
    }
}

$companyUid = trim((string) ($body['company_uid'] ?? $_GET['company_uid'] ?? ''));
if ($companyUid === '') {
    send_error_response(400, 'company_uid is required.', 'INVALID_INPUT');
}

$accountStmt = $pdo->prepare(
    'SELECT id FROM api_accounts WHERE owner_identity_hash = ? AND company_uid = ? LIMIT 1'
);
$accountStmt->execute([$owner, $companyUid]);
$accountId = $accountStmt->fetchColumn();

if ($accountId === false) {
    send_error_response(404, 'The API is not enabled for this company.', 'API_NOT_ENABLED');
}
$accountId = (int) $accountId;

if (!$isPost) {
    $list = $pdo->prepare(
        'SELECT public_id, key_hint, label, scopes, last_used_at, revoked_at, created_at
           FROM api_keys WHERE account_id = ? ORDER BY id DESC'
    );
    $list->execute([$accountId]);

    $keys = array_map(static function (array $row): array {
        return [
            'id'           => $row['public_id'],
            'hint'         => $row['key_hint'],
            'label'        => $row['label'],
            'scopes'       => array_map('trim', explode(',', (string) $row['scopes'])),
            'last_used_at' => $row['last_used_at'],
            'revoked_at'   => $row['revoked_at'],
            'created_at'   => $row['created_at'],
        ];
    }, $list->fetchAll());

    send_json_response(200, ['success' => true, 'keys' => $keys]);
}

// --- create -----------------------------------------------------------------

if (!authenticate_license_request()) {
    send_error_response(403, 'The Argo Books API is part of Premium.', 'PREMIUM_REQUIRED');
}

$activeStmt = $pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE account_id = ? AND revoked_at IS NULL');
$activeStmt->execute([$accountId]);
if ((int) $activeStmt->fetchColumn() >= 10) {
    send_error_response(
        429,
        'This company already has 10 active API keys. Revoke one before creating another.',
        'KEY_LIMIT_REACHED'
    );
}

if (rate_limit_hit('api_key_create_ip', get_client_ip())) {
    send_rate_limited_response('api_key_create_ip');
}

$label = substr(trim((string) ($body['label'] ?? '')), 0, 100);

// The name is all that tells two keys apart in the app, since the secret is shown once and the hint is a few characters. Checked here because the app hides its own key.
if ($label !== '') {
    $dupe = $pdo->prepare(
        'SELECT 1 FROM api_keys WHERE account_id = ? AND revoked_at IS NULL AND LOWER(label) = LOWER(?) LIMIT 1'
    );
    $dupe->execute([$accountId, $label]);
    if ($dupe->fetchColumn() !== false) {
        send_error_response(409, "There is already a key called '$label'.", 'KEY_LABEL_TAKEN');
    }
}

// Scopes are an allow-list, not free text: a typo that silently granted write
// access would be a security bug rather than a validation error.
$requested = $body['scopes'] ?? ['read', 'write'];
if (is_string($requested)) {
    $requested = array_map('trim', explode(',', $requested));
}
if (!is_array($requested) || $requested === []) {
    send_error_response(400, 'scopes must be a non-empty array.', 'INVALID_INPUT');
}
foreach ($requested as $scope) {
    if (!in_array($scope, ['read', 'write'], true)) {
        send_error_response(400, "Unknown scope '$scope'. Valid scopes are read and write.", 'INVALID_SCOPE');
    }
}
$scopes = implode(',', array_values(array_unique($requested)));

$secret = api_generate_secret_key();

$pdo->prepare(
    'INSERT INTO api_keys (account_id, public_id, key_hash, key_hint, label, scopes)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([
    $accountId,
    api_generate_id('key'),
    hash('sha256', $secret),
    api_key_hint($secret),
    $label,
    $scopes,
]);

send_json_response(201, [
    'success' => true,
    // The only time this value ever exists outside the caller's memory.
    'secret'  => $secret,
    'hint'    => api_key_hint($secret),
    'label'   => $label,
    'scopes'  => explode(',', $scopes),
    'notice'  => 'Copy this key now. It is stored only as a hash and cannot be shown again.',
]);
