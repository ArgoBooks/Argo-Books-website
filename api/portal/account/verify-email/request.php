<?php
declare(strict_types=1);

/**
 * POST /api/portal/account/verify-email/request.php
 *
 * Re-sends the registration verification code to the company's owner_email.
 * Limits: max 3 codes per company, no more often than once per 60s.
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

if (!empty($company['email_verified_at'])) {
    send_json_response(200, ['success' => true, 'message' => 'already_verified']);
}

global $pdo;

// A rolling 24 hour window, because an unverified company stays unverified and a lifetime cap would lock it out for good.
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c, MAX(created_at) AS latest
    FROM email_verifications
    WHERE company_id = ? AND purpose = 'registration'
      AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$company['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ((int)$row['c'] >= 3) {
    send_error_response(429, 'Maximum verification attempts reached. Try again later.', 'MAX_RESENDS');
}
if ($row['latest'] && (time() - strtotime($row['latest'])) < 60) {
    send_error_response(429, 'Please wait at least 60 seconds between resends.', 'TOO_SOON');
}

// owner_email is not written until the code is confirmed, so the address to resend to lives on the latest verification row.
$targetEmail = (string)($company['owner_email'] ?? '');
if ($targetEmail === '') {
    $stmt = $pdo->prepare("
        SELECT email FROM email_verifications
        WHERE company_id = ? AND purpose = 'registration' AND email IS NOT NULL AND email != ''
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$company['id']]);
    $targetEmail = (string)($stmt->fetchColumn() ?: '');
}
if ($targetEmail === '') {
    send_error_response(409, 'No pending email to verify. Set the owner email first.', 'NO_PENDING_EMAIL');
}

// Invalidate any prior unconsumed codes
$pdo->prepare("UPDATE email_verifications SET consumed_at = COALESCE(consumed_at, NOW()) WHERE company_id = ? AND purpose = 'registration' AND consumed_at IS NULL")
    ->execute([$company['id']]);

$code = refund_generate_code();
$hash = refund_hash_code($code, (string)$company['id']);
$pdo->prepare("INSERT INTO email_verifications (company_id, email, purpose, code_hash, expires_at) VALUES (?, ?, 'registration', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
    ->execute([$company['id'], $targetEmail, $hash]);

audit_log($pdo, (int)$company['id'], 'code_sent', 'owner', null, null, null, [
    'purpose' => 'registration',
    'resend' => true,
]);
refund_email_send_registration_code($targetEmail, $code);

send_json_response(200, ['success' => true, 'maskedEmail' => refund_mask_email($targetEmail)]);
