<?php
/**
 * Portal Preferences API Endpoint
 *
 * PUT /api/portal/preferences - Update this company's email preferences
 *
 * Requires API key authentication (Argo Books -> Server).
 *
 * Expects JSON body with any subset of:
 *   { "sendPaymentReminders": true, "emailOwnerOnPayment": true }
 *
 * Both keys are optional and only present keys are applied, so the desktop can
 * push one toggle without having to know the state of the other.
 *
 * These preferences live on the server rather than only in the .argo file
 * because the jobs that read them (cron/portal_invoice_reminders.php and the
 * payment webhooks) run while Argo Books is closed.
 */

require_once __DIR__ . '/portal-helper.php';

set_portal_headers();
require_method(['PUT']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
}

if (!is_array($data)) {
    send_error_response(400, 'Request body must be a JSON object.', 'INVALID_BODY');
}

$hasReminders = array_key_exists('sendPaymentReminders', $data);
$hasOwnerEmail = array_key_exists('emailOwnerOnPayment', $data);

if (!$hasReminders && !$hasOwnerEmail) {
    send_error_response(
        400,
        'Provide at least one of: sendPaymentReminders, emailOwnerOnPayment.',
        'MISSING_FIELDS'
    );
}

$companyId = $company['id'];

try {
    if ($hasReminders) {
        $remindersEnabled = filter_var($data['sendPaymentReminders'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        // reminders_enabled_at is the cutoff the dunning cron reads, so switching reminders on must not release a backlog of invoices that went overdue.
        $stmt = $pdo->prepare(
            'UPDATE portal_companies
             SET reminders_enabled_at = CASE
                     WHEN reminders_enabled = 0 AND ? = 1 THEN NOW()
                     ELSE reminders_enabled_at
                 END,
                 reminders_enabled = ?
             WHERE id = ?'
        );
        $stmt->execute([$remindersEnabled, $remindersEnabled, $companyId]);
    }

    if ($hasOwnerEmail) {
        $notifyOwner = filter_var($data['emailOwnerOnPayment'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE portal_companies SET notify_owner_on_payment = ? WHERE id = ?');
        $stmt->execute([$notifyOwner, $companyId]);
    }
} catch (\PDOException $e) {
    error_log('Portal preferences update DB error: ' . $e->getMessage());
    send_error_response(500, 'Failed to update preferences. Please try again.', 'DB_ERROR');
}

// Echo the resolved state rather than what was asked for, so the client stores
// the server's view (including the cutoff it just armed) instead of guessing.
$stmt = $pdo->prepare(
    'SELECT reminders_enabled, reminders_enabled_at, notify_owner_on_payment, email_verified_at
     FROM portal_companies WHERE id = ? LIMIT 1'
);
$stmt->execute([$companyId]);
$row = $stmt->fetch();

send_json_response(200, [
    'success' => true,
    'preferences' => [
        'sendPaymentReminders' => (bool)($row['reminders_enabled'] ?? false),
        'remindersEnabledAt' => portal_iso_datetime($row['reminders_enabled_at'] ?? null),
        'emailOwnerOnPayment' => (bool)($row['notify_owner_on_payment'] ?? false),
        'ownerEmailVerified' => !empty($row['email_verified_at']),
    ],
    'message' => 'Preferences updated successfully',
    'timestamp' => date('c')
]);
