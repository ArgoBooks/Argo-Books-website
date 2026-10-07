<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/set-initial-email.php
 * Body: { email }
 *
 * Begins first-time owner email setup for a portal company that currently
 * has none. This is the "complete registration" path for accounts that
 * registered with an empty email. The email is NOT written to owner_email
 * here: it is held as a pending value on the email_verifications row, and a
 * 6-digit verification code is emailed to it. Only when the caller confirms
 * the code via /verify-email/confirm.php does owner_email get set (along
 * with email_verified_at).
 *
 * Why this design: a typo in the typed email (or a malicious attacker
 * setting an email they don't actually own) shouldn't stick. If the user
 * abandons verification, nothing is set and they can simply retry with a
 * corrected address. The matching VerifyEmailModal on the desktop pops
 * automatically after this endpoint succeeds.
 *
 * Refuses (409) if owner_email is already set: in that case, the caller must
 * use the 4-step email-change flow which verifies both old and new addresses.
 */

require_once __DIR__ . '/../portal-helper.php';
require_once __DIR__ . '/../_audit.php';
require_once __DIR__ . '/../_refund_helpers.php';

set_portal_headers();
require_method(['POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

$body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
$email = filter_var(trim((string)($body['email'] ?? '')), FILTER_VALIDATE_EMAIL);
if (!$email) {
    send_error_response(400, 'Invalid email address.', 'INVALID_EMAIL');
}

global $pdo;

// Refuse if owner_email is already set: must go through Change flow.
if (!empty($company['owner_email'])) {
    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'       => false,
        'errorCode'     => 'OWNER_EMAIL_ALREADY_SET',
        'message'       => 'Owner email is already set on this portal account. Use the Change flow to update it.',
        'ownerEmail'    => $company['owner_email'],
        'maskedEmail'   => refund_mask_email($company['owner_email']),
        'timestamp'     => date('c'),
    ]);
    exit;
}

// No rejection for an address already in use: a company file deleted on someone's computer never reaches the server, so its record would hold their address for good.

// Throttle code sends. Same limits as /verify-email/request.php: max 3 codes per rolling 24h, 60s apart.
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c, MAX(created_at) AS latest
    FROM email_verifications
    WHERE company_id = ? AND purpose = 'registration'
      AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$company['id']]);
$throttle = $stmt->fetch(PDO::FETCH_ASSOC);
if ((int)$throttle['c'] >= 3) {
    send_error_response(429, 'Maximum verification attempts reached. Try again later.', 'MAX_RESENDS');
}
if ($throttle['latest'] && (time() - strtotime($throttle['latest'])) < 60) {
    send_error_response(429, 'Please wait at least 60 seconds between attempts.', 'TOO_SOON');
}

$pdo->beginTransaction();
try {
    // Invalidate any prior unconsumed registration codes so only the latest
    // pending email/code pair can be confirmed.
    $pdo->prepare("UPDATE email_verifications SET consumed_at = COALESCE(consumed_at, NOW()) WHERE company_id = ? AND purpose = 'registration' AND consumed_at IS NULL")
        ->execute([$company['id']]);

    // Issue verification code (purpose='registration' so the existing /verify-email/confirm.php endpoint accepts it).
    $code = refund_generate_code();
    $hash = refund_hash_code($code, (string)$company['id']);
    $pdo->prepare("INSERT INTO email_verifications (company_id, email, purpose, code_hash, expires_at) VALUES (?, ?, 'registration', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
        ->execute([$company['id'], $email, $hash]);

    audit_log($pdo, (int)$company['id'], 'code_sent', 'system', null, null, null, [
        'purpose' => 'registration',
        'trigger' => 'set_initial_email',
    ]);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('set-initial-email DB error: ' . $e->getMessage());
    send_error_response(500, 'Could not set owner email.', 'SERVER_ERROR');
}

// Send the verification email AFTER commit (don't roll back if SMTP hiccups;
// user can resend via /verify-email/request.php).
refund_email_send_registration_code($email, $code);

send_json_response(200, [
    'success' => true,
    'ownerEmail' => $email,
    'maskedEmail' => refund_mask_email($email),
]);
