<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/verify-email/confirm.php
 * Body: { code }
 *
 * Confirms the registration verification code. Until this succeeds, the
 * company's email_verified_at is NULL and refund endpoints return 412.
 *
 * For the set-initial-email flow the pending address lives on the
 * email_verifications row, not on portal_companies: a correct code is what
 * actually writes owner_email. Abandoning verification leaves nothing set.
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
$code = (string)($body['code'] ?? '');
if (!preg_match('/^\d{6}$/', $code)) {
    send_error_response(400, 'Invalid code format.', 'INVALID_CODE_FORMAT');
}

global $pdo;

$stmt = $pdo->prepare("
    SELECT * FROM email_verifications
    WHERE company_id = ? AND purpose = 'registration' AND consumed_at IS NULL
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$company['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    send_error_response(409, 'No active verification code. Request a new one.', 'NO_ACTIVE_CODE');
}
if (strtotime($row['expires_at']) < time()) {
    send_error_response(410, 'Verification code expired.', 'EXPIRED');
}
if ((int)$row['attempts'] >= 5) {
    send_error_response(429, 'Too many attempts. Request a new code.', 'TOO_MANY_ATTEMPTS');
}

$expected = refund_hash_code($code, (string)$company['id']);
if (!hash_equals($row['code_hash'], $expected)) {
    $pdo->prepare("UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?")
        ->execute([$row['id']]);
    audit_log($pdo, (int)$company['id'], 'code_failed', 'owner', null, null, null, [
        'purpose' => 'registration',
    ]);
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'WRONG_CODE',
        'attemptsRemaining' => max(0, 5 - ((int)$row['attempts'] + 1)),
    ]);
    exit;
}

// The pending address from the verification row becomes the owner_email.
$pendingEmail = (string)($row['email'] ?? '');
$writeOwnerEmail = empty($company['owner_email']) && $pendingEmail !== '';

// Another company may hold this address, usually one whose file was deleted locally: nothing tells the server that happened, so the record outlives it.
$previousHolder = null;
if ($writeOwnerEmail) {
    $stmt = $pdo->prepare(
        "SELECT id, company_name FROM portal_companies WHERE owner_email = ? AND id != ?");
    $stmt->execute([$pendingEmail, $company['id']]);
    $previousHolder = $stmt->fetch() ?: null;
}

$pdo->beginTransaction();
$pdo->prepare("UPDATE email_verifications SET consumed_at = NOW() WHERE id = ?")->execute([$row['id']]);
if ($writeOwnerEmail) {
    if ($previousHolder) {
        $pdo->prepare(
            "UPDATE portal_companies SET owner_email = NULL, email_verified_at = NULL WHERE id = ?")
            ->execute([$previousHolder['id']]);
        audit_log($pdo, (int)$previousHolder['id'], 'email_changed', 'owner', null, null, null, [
            'reason' => 'released_to_verified_owner',
            'old' => $pendingEmail,
            'new' => null,
            'moved_to_company_id' => (int)$company['id'],
        ]);
    }

    $pdo->prepare("UPDATE portal_companies SET owner_email = ?, email_verified_at = NOW() WHERE id = ?")
        ->execute([$pendingEmail, $company['id']]);
    audit_log($pdo, (int)$company['id'], 'email_changed', 'owner', null, null, null, [
        'reason' => 'set_initial_email_verified',
        'old' => null,
        'new' => $pendingEmail,
    ]);
} else {
    $pdo->prepare("UPDATE portal_companies SET email_verified_at = NOW() WHERE id = ?")->execute([$company['id']]);
}
audit_log($pdo, (int)$company['id'], 'email_registration_verified', 'owner', null, null, null, []);
$pdo->commit();

$response = [
    'success' => true,
    'verifiedAt' => date('c'),
    'ownerEmail' => $writeOwnerEmail ? $pendingEmail : ($company['owner_email'] ?? null),
];

if ($previousHolder) {
    $previousName = trim((string)($previousHolder['company_name'] ?? ''));
    $response['emailMoved'] = true;
    $response['message'] = $previousName !== ''
        ? sprintf(
            'This email was set up for "%s". Since you confirmed the code, we have moved it to this company and removed it from that one.',
            $previousName)
        : 'This email was set up for another company. Since you confirmed the code, we have moved it to this company and removed it from that one.';
}

send_json_response(200, $response);
