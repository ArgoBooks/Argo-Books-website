<?php
declare(strict_types=1);

/**
 * POST /api/developer/keys/rename
 *
 * Change the label on a key. The label is the only part of a key that is ever
 * editable: the secret is not stored, the hint is derived from it, and the
 * scopes decide what the key can do, so letting those change under a key that
 * integrations are already using would be a security change disguised as a
 * rename.
 *
 * Body: { "company_uid": "...", "key_id": "key_...", "label": "Shopify store" }
 */

require_once __DIR__ . '/../sync/sync-helper.php';

set_portal_headers();
require_method(['POST']);

$owner = resolve_owner_identity();
if (!$owner) {
    send_error_response(401, 'Unauthorized.', 'UNAUTHORIZED');
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    send_error_response(400, 'Invalid JSON body.', 'INVALID_JSON');
}

$companyUid = trim((string) ($body['company_uid'] ?? ''));
$keyId = trim((string) ($body['key_id'] ?? ''));
$label = substr(trim((string) ($body['label'] ?? '')), 0, 100);

if ($companyUid === '' || $keyId === '') {
    send_error_response(400, 'company_uid and key_id are required.', 'INVALID_INPUT');
}

if ($label === '') {
    // An empty label would make the row fall back to showing the hint, which is indistinguishable from a key that was never named.
    send_error_response(400, 'label cannot be empty.', 'INVALID_INPUT');
}

global $pdo;

// Same uniqueness rule as creating one, minus the key being renamed: renaming a key
// to what it already says has to stay a no-op rather than a collision.
$dupe = $pdo->prepare(
    'SELECT 1 FROM api_keys k
       JOIN api_accounts a ON a.id = k.account_id
      WHERE a.owner_identity_hash = ?
        AND a.company_uid = ?
        AND k.revoked_at IS NULL
        AND k.public_id <> ?
        AND LOWER(k.label) = LOWER(?)
      LIMIT 1'
);
$dupe->execute([$owner, $companyUid, $keyId, $label]);
if ($dupe->fetchColumn() !== false) {
    send_error_response(409, "There is already a key called '$label'.", 'KEY_LABEL_TAKEN');
}

// The join through api_accounts is what stops one owner renaming another
// owner's key by guessing an id.
$stmt = $pdo->prepare(
    'UPDATE api_keys k
       JOIN api_accounts a ON a.id = k.account_id
        SET k.label = ?
      WHERE k.public_id = ?
        AND k.revoked_at IS NULL
        AND a.owner_identity_hash = ?
        AND a.company_uid = ?'
);
$stmt->execute([$label, $keyId, $owner, $companyUid]);

// rowCount() is 0 both for "no such key" and for "renamed to what it already
// said", so confirm the key is really there before reporting a failure.
if ($stmt->rowCount() === 0) {
    $check = $pdo->prepare(
        'SELECT 1 FROM api_keys k
           JOIN api_accounts a ON a.id = k.account_id
          WHERE k.public_id = ?
            AND k.revoked_at IS NULL
            AND a.owner_identity_hash = ?
            AND a.company_uid = ?
          LIMIT 1'
    );
    $check->execute([$keyId, $owner, $companyUid]);

    if ($check->fetchColumn() === false) {
        // Already revoked and never existed are reported the same way on
        // purpose: distinguishing them would let a caller probe for valid ids.
        send_error_response(404, 'No active key with that id on this company.', 'KEY_NOT_FOUND');
    }
}

send_json_response(200, ['success' => true, 'key_id' => $keyId, 'label' => $label]);
