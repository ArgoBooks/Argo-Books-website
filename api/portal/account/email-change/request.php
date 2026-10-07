<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/email-change/request.php
 * Body: { new_email, password_verified? }
 *
 * Begins a 4-step email change. Sends a verification code to the OLD email.
 * State: NEW row in 'pending'.
 */

require_once __DIR__ . '/../../portal-helper.php';
require_once __DIR__ . '/../../_audit.php';
require_once __DIR__ . '/../../_refund_helpers.php';

set_portal_headers();
require_method(['POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

$body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$new_email = filter_var(trim((string)($body['new_email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$new_email) {
    send_error_response(400, 'Invalid email address.', 'INVALID_EMAIL');
}

global $pdo;

// No rejection for an address already in use, as in set-initial-email.php: it moves to whoever proves they can receive mail at it.

// 24h cooldown between completed changes
$stmt = $pdo->prepare("SELECT MAX(completed_at) FROM email_change_requests WHERE company_id = ? AND state = 'completed'");
$stmt->execute([$company['id']]);
$last = $stmt->fetchColumn();
if ($last && (time() - strtotime($last)) < 86400) {
    $retry = 86400 - (time() - strtotime($last));
    send_error_response(429, 'Email was changed less than 24h ago. Try again later.', 'COOLDOWN_ACTIVE');
}

// Cancel any other pending change for this company
$pdo->prepare("UPDATE email_change_requests SET state='cancelled' WHERE company_id = ? AND state IN ('pending','old_verified')")
    ->execute([$company['id']]);

$password_verified = !empty($body['password_verified']) ? 1 : 0;

// The old-email step lets the current owner veto a change, which only means something once that address has been proven to reach them.
$oldAddressProven = !empty($company['owner_email']) && !empty($company['email_verified_at']);

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO email_change_requests (company_id, old_email, new_email, password_verified, state) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([
    $company['id'],
    $company['owner_email'],
    $new_email,
    $password_verified,
    $oldAddressProven ? 'pending' : 'old_verified',
]);
$change_id = (int)$pdo->lastInsertId();

$code = null;
$newCode = null;
if ($oldAddressProven) {
    $code = refund_generate_code();
    $hash = refund_hash_code($code, 'echange-old-' . $change_id);
    // 10-minute expiry + attempt counter reset, mirroring the refund-code flow.
    $pdo->prepare("
        UPDATE email_change_requests
        SET old_email_code_hash = ?,
            old_email_code_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
            old_email_code_attempts = 0
        WHERE id = ?
    ")->execute([$hash, $change_id]);
} else {
    // confirm-old.php is what normally issues the new-address code, so skipping that step means issuing it here or the request sits in old_verified with no code ever sent.
    $newCode = refund_generate_code();
    $newHash = refund_hash_code($newCode, 'echange-new-' . $change_id);
    $pdo->prepare("
        UPDATE email_change_requests
        SET new_email_code_hash = ?,
            new_email_code_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
            new_email_code_attempts = 0
        WHERE id = ?
    ")->execute([$newHash, $change_id]);
}

audit_log($pdo, (int)$company['id'], 'email_change_requested', 'owner', null, null, $change_id, [
    'new_email' => $new_email,
    'password_verified' => (bool)$password_verified,
    'old_address_proven' => $oldAddressProven,
]);
if ($oldAddressProven) {
    audit_log($pdo, (int)$company['id'], 'code_sent', 'system', null, null, $change_id, ['target' => 'old']);
} else {
    audit_log($pdo, (int)$company['id'], 'email_change_old_skipped', 'system', null, null, $change_id, [
        'reason' => 'old_address_never_verified',
    ]);
}
$pdo->commit();

if ($oldAddressProven) {
    refund_email_send_change_old_code($company['owner_email'], $code, $new_email);
} else {
    refund_email_send_change_new_code($new_email, $newCode);
}

send_json_response(200, [
    'success' => true,
    'changeId' => $change_id,
    'state' => $oldAddressProven ? 'pending' : 'old_verified',
    'maskedOldEmail' => refund_mask_email($company['owner_email']),
    // False means no code went to the old address and one has already gone to the new
    // one, so the app should go straight to asking for that code.
    'oldEmailVerificationRequired' => $oldAddressProven,
    'maskedNewEmail' => $oldAddressProven ? null : refund_mask_email($new_email),
]);
