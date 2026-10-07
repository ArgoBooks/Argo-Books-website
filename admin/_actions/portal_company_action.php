<?php
declare(strict_types=1);

/**
 * Admin actions for portal_companies: lock, unlock, and admin-revert of an
 * email change. Each action requires a freeform reason and writes to
 * refund_audit_log with actor_type='admin'.
 *
 * Bound to: POST /admin/_actions/portal_company_action.php
 * Session-authenticated (admin_logged_in) + CSRF-protected.
 */

require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../api/portal/_audit.php';
require_once __DIR__ . '/../../email_sender.php';

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$action = (string)($_POST['action'] ?? '');
$reason = trim((string)($_POST['reason'] ?? ''));
$admin_id = (string)($_SESSION['admin_user_id'] ?? $_SESSION['admin_username'] ?? 'unknown_admin');

if ($reason === '') {
    header('Location: /admin/payments/index.php?msg=missing_reason#companies');
    exit;
}

global $pdo;

switch ($action) {
    case 'lock':
        $cid = (int)($_POST['company_id'] ?? 0);
        if ($cid <= 0) { header('Location: /admin/payments/index.php?msg=missing_company#companies'); exit; }
        $pdo->prepare("UPDATE portal_companies SET locked = 1, lock_reason = ?, locked_at = NOW() WHERE id = ?")
            ->execute([$reason, $cid]);
        audit_log($pdo, $cid, 'account_locked', 'admin', $admin_id, null, null, ['reason' => $reason]);
        header('Location: /admin/payments/index.php?msg=locked#companies');
        break;

    case 'unlock':
        $cid = (int)($_POST['company_id'] ?? 0);
        if ($cid <= 0) { header('Location: /admin/payments/index.php?msg=missing_company#companies'); exit; }
        $pdo->prepare("UPDATE portal_companies SET locked = 0, lock_reason = NULL, locked_at = NULL WHERE id = ?")
            ->execute([$cid]);
        audit_log($pdo, $cid, 'account_unlocked', 'admin', $admin_id, null, null, ['reason' => $reason]);
        header('Location: /admin/payments/index.php?msg=unlocked#companies');
        break;

    case 'revert_email':
        $changeId = (int)($_POST['email_change_id'] ?? 0);
        if ($changeId <= 0) { header('Location: /admin/payments/index.php?msg=missing_change#companies'); exit; }

        $stmt = $pdo->prepare("SELECT * FROM email_change_requests WHERE id = ?");
        $stmt->execute([$changeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { header('Location: /admin/payments/index.php?msg=change_not_found#companies'); exit; }
        if ($row['state'] !== 'completed') {
            header('Location: /admin/payments/index.php?msg=cannot_revert_state#companies'); exit;
        }
        if (!empty($row['revert_until']) && strtotime($row['revert_until']) < time()) {
            header('Location: /admin/payments/index.php?msg=revert_window_expired#companies'); exit;
        }

        $pdo->beginTransaction();
        // Stale-token guard: only revert if the company is currently on the new_email from THIS change request.
        $upd = $pdo->prepare(
            "UPDATE portal_companies SET owner_email = ?
             WHERE id = ? AND owner_email = ?"
        );
        $upd->execute([$row['old_email'], $row['company_id'], $row['new_email']]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            header('Location: /admin/payments/index.php?msg=revert_superseded#companies');
            exit;
        }
        $pdo->prepare("UPDATE email_change_requests SET state='reverted', reverted_at = NOW(), cancel_token = NULL WHERE id = ?")
            ->execute([$changeId]);
        audit_log($pdo, (int)$row['company_id'], 'email_reverted', 'admin', $admin_id, null, $changeId, [
            'reason' => $reason,
            'reverted_to' => $row['old_email'],
            'reverted_from' => $row['new_email'],
        ]);
        $pdo->commit();

        // Notify both addresses
        $oldSafe = htmlspecialchars($row['old_email']);
        $newSafe = htmlspecialchars($row['new_email']);
        $reasonSafe = htmlspecialchars($reason);
        send_styled_email($row['old_email'], 'Email change reverted by support',
            "<p>Your Argo Books portal email has been reverted to <strong>$oldSafe</strong> by support.</p>"
            . "<p>Reason: $reasonSafe</p>", 'blue');
        send_styled_email($row['new_email'], 'Email change reverted by support',
            "<p>The change to <strong>$newSafe</strong> was reverted by support.</p>"
            . "<p>Reason: $reasonSafe</p>", 'blue');

        header('Location: /admin/payments/index.php?msg=email_reverted#companies');
        break;

    default:
        header('Location: /admin/payments/index.php?msg=unknown_action#companies');
        break;
}
