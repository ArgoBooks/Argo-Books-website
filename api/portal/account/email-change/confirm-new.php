<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/email-change/confirm-new.php
 * Body: { change_id, code }
 *
 * Verifies the NEW-email code. Transitions state old_verified → completed,
 * flips portal_companies.owner_email to the new address, and emails the
 * OLD address with a 30-day revert link.
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
$change_id = (int)($body['change_id'] ?? 0);
$code = (string)($body['code'] ?? '');
if ($change_id <= 0 || !preg_match('/^\d{6}$/', $code)) {
    send_error_response(400, 'Invalid input.', 'INVALID_INPUT');
}

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM email_change_requests WHERE id = ? AND company_id = ?");
$stmt->execute([$change_id, $company['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    send_error_response(404, 'Change request not found.', 'NOT_FOUND');
}
if ($row['state'] !== 'old_verified') {
    send_error_response(409, 'Change request is in state ' . $row['state'], 'WRONG_STATE');
}

// Expiry check: same 10-minute window as the old-email code.
if (empty($row['new_email_code_expires_at']) || strtotime($row['new_email_code_expires_at']) < time()) {
    audit_log($pdo, (int)$company['id'], 'code_failed', 'owner', null, null, $change_id, [
        'target' => 'new', 'reason' => 'expired',
    ]);
    send_error_response(410, 'Code expired. Resend a new one.', 'CODE_EXPIRED');
}

// Attempt-counter check: 5 wrong tries cancels the change request.
if ((int)$row['new_email_code_attempts'] >= 5) {
    $pdo->prepare("UPDATE email_change_requests SET state='cancelled' WHERE id = ? AND state = 'old_verified'")
        ->execute([$change_id]);
    audit_log($pdo, (int)$company['id'], 'cancelled_by_system', 'system', null, null, $change_id, [
        'target' => 'new', 'reason' => 'too_many_code_attempts',
    ]);
    send_error_response(429, 'Too many wrong code attempts. Start a new email change.', 'TOO_MANY_ATTEMPTS');
}

$expected = refund_hash_code($code, 'echange-new-' . $change_id);
if (!hash_equals($row['new_email_code_hash'], $expected)) {
    $pdo->prepare("UPDATE email_change_requests SET new_email_code_attempts = new_email_code_attempts + 1 WHERE id = ?")
        ->execute([$change_id]);
    audit_log($pdo, (int)$company['id'], 'code_failed', 'owner', null, null, $change_id, [
        'target' => 'new',
        'attempts' => (int)$row['new_email_code_attempts'] + 1,
    ]);
    send_error_response(401, 'Wrong code.', 'WRONG_CODE');
}

$revert_token = bin2hex(random_bytes(32));

$pdo->beginTransaction();
$pdo->prepare("
    UPDATE email_change_requests
    SET state='completed', new_email_verified_at = NOW(), completed_at = NOW(),
        cancel_token = ?, revert_until = DATE_ADD(NOW(), INTERVAL 30 DAY)
    WHERE id = ?
")->execute([$revert_token, $change_id]);
// email_verified_at goes with it. The code that just passed was delivered to the new address, which is the same proof /verify-email/confirm.php accepts, so the address is verified.
$pdo->prepare("UPDATE portal_companies SET owner_email = ?, email_verified_at = NOW() WHERE id = ?")
    ->execute([$row['new_email'], $company['id']]);

audit_log($pdo, (int)$company['id'], 'email_change_new_verified', 'owner', null, null, $change_id, []);
audit_log($pdo, (int)$company['id'], 'email_changed', 'owner', null, null, $change_id, [
    'old' => $row['old_email'],
    'new' => $row['new_email'],
]);
$pdo->commit();

refund_email_send_change_completed_to_old($row['old_email'], $row['new_email'], $revert_token);

send_json_response(200, [
    'success' => true,
    'state' => 'completed',
    'newEmail' => $row['new_email'],
]);
