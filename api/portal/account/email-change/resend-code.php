<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/email-change/resend-code.php
 * Body: { change_id, target: 'old' | 'new' }
 *
 * Re-issues the verification code for the specified leg of the change flow.
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
$target = (string)($body['target'] ?? '');
if ($change_id <= 0 || !in_array($target, ['old','new'], true)) {
    send_error_response(400, 'Invalid input.', 'INVALID_INPUT');
}

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM email_change_requests WHERE id = ? AND company_id = ?");
$stmt->execute([$change_id, $company['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    send_error_response(404, 'Not found.', 'NOT_FOUND');
}

$expected_state = $target === 'old' ? 'pending' : 'old_verified';
if ($row['state'] !== $expected_state) {
    send_error_response(409, 'Wrong state for this resend target.', 'WRONG_STATE');
}

// Per-change-request throttle. Count code_sent audit entries (initial issue + any resends) in the last hour for this change_id.
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM refund_audit_log
    WHERE email_change_request_id = ?
      AND event_type = 'code_sent'
      AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute([$change_id]);
if ((int)$stmt->fetchColumn() >= 5) {
    send_error_response(429, 'Too many verification codes requested in the past hour. Please wait.', 'TOO_MANY_CODES');
}

$code = refund_generate_code();
$salt = ($target === 'old' ? 'echange-old-' : 'echange-new-') . $change_id;
$hash = refund_hash_code($code, $salt);
$hashCol = $target === 'old' ? 'old_email_code_hash' : 'new_email_code_hash';
$expiresCol = $target === 'old' ? 'old_email_code_expires_at' : 'new_email_code_expires_at';
$attemptsCol = $target === 'old' ? 'old_email_code_attempts' : 'new_email_code_attempts';

// Reset expiry to the standard 10-minute window and zero the attempt counter
// so the user starts fresh against the new code.
$pdo->prepare("UPDATE email_change_requests SET $hashCol = ?, $expiresCol = DATE_ADD(NOW(), INTERVAL 10 MINUTE), $attemptsCol = 0 WHERE id = ?")
    ->execute([$hash, $change_id]);
audit_log($pdo, (int)$company['id'], 'code_sent', 'owner', null, null, $change_id, [
    'target' => $target,
    'resend' => true,
]);

if ($target === 'old') {
    refund_email_send_change_old_code($row['old_email'], $code, $row['new_email']);
} else {
    refund_email_send_change_new_code($row['new_email'], $code);
}

send_json_response(200, ['success' => true]);
