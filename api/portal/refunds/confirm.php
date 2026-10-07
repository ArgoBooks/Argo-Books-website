<?php
declare(strict_types=1);

/**
 * POST /api/portal/refunds/confirm.php
 *
 * Body: { request_id, code }
 *
 * Verifies the 6-digit code, then runs the velocity engine and transitions
 * the refund_request to either:
 *   - processing → executes provider refund inline → completed/failed
 *   - cooling_off (delayed tier; cooling-off promoter cron will execute later)
 *   - failed (hard_block; account is also locked)
 */

require_once __DIR__ . '/../portal-helper.php';
require_once __DIR__ . '/../_audit.php';
require_once __DIR__ . '/../_idempotency.php';
require_once __DIR__ . '/../_refund_helpers.php';
require_once __DIR__ . '/_velocity.php';

set_portal_headers();
require_method(['POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}
refund_ensure_company_active($company);

global $pdo;
$raw = file_get_contents('php://input') ?: '';

// require_key=true: code confirmation can trigger the provider refund call.
// A retry without the header could fire the provider call twice.
with_idempotency($pdo, (int)$company['id'], $raw, function() use ($pdo, $company, $raw) {
    $body = json_decode($raw, true);
    $request_id = (int)($body['request_id'] ?? 0);
    $code = (string)($body['code'] ?? '');
    if ($request_id <= 0 || !preg_match('/^\d{6}$/', $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'INVALID_INPUT']);
        return;
    }

    $request = refund_load_request($pdo, (int)$company['id'], $request_id);
    refund_assert_state($request['state'], ['pending_code'], 'confirm code');

    $stmt = $pdo->prepare("
        SELECT * FROM refund_email_codes
        WHERE refund_request_id = ? AND consumed_at IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $code_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$code_row) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'NO_ACTIVE_CODE']);
        return;
    }
    if (strtotime($code_row['expires_at']) < time()) {
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'CODE_EXPIRED']);
        return;
    }
    $attempt = refund_claim_code_attempt($pdo, (int)$code_row['id']);
    if ($attempt === null) {
        $cancel = $pdo->prepare("UPDATE refund_requests SET state='cancelled', state_reason='too_many_code_attempts', updated_at=NOW() WHERE id = ? AND state = 'pending_code'");
        $cancel->execute([$request_id]);
        if ($cancel->rowCount() > 0) {
            audit_log($pdo, (int)$company['id'], 'cancelled_by_system', 'system', null, $request_id, null, ['reason' => 'too_many_code_attempts']);
        }
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'TOO_MANY_ATTEMPTS']);
        return;
    }

    $expected = refund_hash_code($code, (string)$request_id);
    if (!hash_equals($code_row['code_hash'], $expected)) {
        audit_log($pdo, (int)$company['id'], 'code_failed', 'owner', null, $request_id, null, [
            'attempts' => $attempt,
        ]);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'WRONG_CODE',
            'attemptsRemaining' => max(0, 5 - $attempt),
        ]);
        return;
    }

    // Only one request may consume the code, or two correct guesses sent in
    // parallel would both go on to issue the refund.
    $consume = $pdo->prepare("UPDATE refund_email_codes SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL");
    $consume->execute([$code_row['id']]);
    if ($consume->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'NO_ACTIVE_CODE']);
        return;
    }
    audit_log($pdo, (int)$company['id'], 'code_verified', 'owner', null, $request_id, null, []);

    // ----- Velocity check -----
    $velocity = refund_assess_velocity($pdo, $company, (int)$request['amount_cents']);
    audit_log($pdo, (int)$company['id'], 'velocity_tier_assigned', 'system', null, $request_id, null, $velocity);

    if ($velocity['tier'] === 'hard_block') {
        // lock_reason is what the user sees on every SUBSEQUENT refund attempt (surfaced as the 423 ACCOUNT_LOCKED message in refund_ensure_company_active).
        $userFriendlyLockReason = 'Refunds on this account are paused while our automated safety check reviews recent activity. The system sometimes flags legitimate refunds. Email contact@argorobots.com and we will resume refunds within one business day.';
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE portal_companies SET locked = 1, lock_reason = ?, locked_at = NOW() WHERE id = ?")
            ->execute([$userFriendlyLockReason, $company['id']]);
        $pdo->prepare("UPDATE refund_requests SET state='failed', state_reason='hard_block', velocity_tier=?, updated_at=NOW() WHERE id = ?")
            ->execute([$velocity['tier'], $request_id]);
        audit_log($pdo, (int)$company['id'], 'account_locked', 'system', null, $request_id, null, $velocity);
        audit_log($pdo, (int)$company['id'], 'failed', 'system', null, $request_id, null, ['reason' => 'hard_block', 'velocity_reason' => $velocity['reason'] ?? null]);
        $pdo->commit();

        // Best effort inside try/catch, because the lock is already written and an SMTP hiccup must not break the lock-down path.
        try {
            refund_notify_admin_of_hard_block($company, $request, $velocity, $request_id);
        } catch (\Throwable $e) {
            error_log('Hard-block admin notification failed: ' . $e->getMessage());
        }
        // Also send a heads-up to the merchant's owner_email so they have a permanent inbox record even if they closed the modal.
        if (!empty($company['owner_email'])) {
            try {
                refund_email_send_hard_block($company['owner_email'], $request);
            } catch (\Throwable $e) {
                error_log('Hard-block user notification failed: ' . $e->getMessage());
            }
        }

        http_response_code(423);
        echo json_encode([
            'success' => false,
            'state' => 'failed',
            'velocityTier' => $velocity['tier'],
            'errorCode' => 'HARD_BLOCK',
            'message' => 'This refund was flagged by our automated safety check. The system sometimes flags legitimate refunds. Please email contact@argorobots.com and we will review and process this refund within one business day. Other parts of your account continue to work normally.',
        ]);
        return;
    }

    if ($velocity['tier'] === 'delayed') {
        $cancel_token = bin2hex(random_bytes(32));
        $pdo->prepare("
            UPDATE refund_requests
            SET state='cooling_off', velocity_tier=?, cooling_off_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                cancel_token = ?, updated_at=NOW()
            WHERE id = ?
        ")->execute([$velocity['tier'], (int)$velocity['cooling_off_seconds'], $cancel_token, $request_id]);
        audit_log($pdo, (int)$company['id'], 'cooling_off_started', 'system', null, $request_id, null, $velocity);

        // Re-fetch for the email helper
        $req_row = refund_load_request($pdo, (int)$company['id'], $request_id);
        refund_email_send_cooling_off($company['owner_email'], $req_row, $cancel_token);

        echo json_encode([
            'success' => true,
            'state' => 'cooling_off',
            'velocityTier' => $velocity['tier'],
            'coolingOffSeconds' => (int)$velocity['cooling_off_seconds'],
        ]);
        return;
    }

    // normal or soft_warn → process inline
    $pdo->prepare("UPDATE refund_requests SET state='processing', velocity_tier=?, updated_at=NOW() WHERE id = ?")
        ->execute([$velocity['tier'], $request_id]);
    audit_log($pdo, (int)$company['id'], 'processing', 'system', null, $request_id, null, []);

    refund_execute_against_provider($pdo, $company, $request_id);

    $stmt = $pdo->prepare("SELECT state, state_reason, provider_refund_id FROM refund_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $final = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $final['state'] === 'completed',
        'state' => $final['state'],
        'velocityTier' => $velocity['tier'],
        'message' => $final['state_reason'] ?? null,
        'providerRefundId' => $final['provider_refund_id'] ?? null,
    ]);
}, true);
