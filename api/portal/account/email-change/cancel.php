<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/email-change/cancel.php
 * Body: { change_id }
 *
 * User-initiated abort of an in-flight email change.
 * Allowed in 'pending' or 'old_verified' only.
 */

require_once __DIR__ . '/../../portal-helper.php';
require_once __DIR__ . '/../../_audit.php';

set_portal_headers();
require_method(['POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

$body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$change_id = (int)($body['change_id'] ?? 0);
if ($change_id <= 0) {
    send_error_response(400, 'Missing change_id.', 'MISSING_ID');
}

global $pdo;
$stmt = $pdo->prepare("SELECT id, state FROM email_change_requests WHERE id = ? AND company_id = ?");
$stmt->execute([$change_id, $company['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    send_error_response(404, 'Not found.', 'NOT_FOUND');
}
if (!in_array($row['state'], ['pending','old_verified'], true)) {
    send_error_response(409, 'Cannot cancel in state ' . $row['state'], 'WRONG_STATE');
}

// State-guarded, because confirm-old.php or confirm-new.php can move this row between the SELECT above and this UPDATE.
$upd = $pdo->prepare("UPDATE email_change_requests SET state='cancelled' WHERE id = ? AND state IN ('pending','old_verified')");
$upd->execute([$change_id]);
if ($upd->rowCount() === 0) {
    send_error_response(409, 'Email change was finalized before the cancellation could complete.', 'STATE_CONFLICT');
}
audit_log($pdo, (int)$company['id'], 'cancelled_by_user', 'owner', null, null, $change_id, []);

send_json_response(200, ['success' => true, 'state' => 'cancelled']);
