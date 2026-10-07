<?php
declare(strict_types=1);

require_once __DIR__ . '/_audit.php';
require_once __DIR__ . '/../../email_sender.php';

// --- Code generation, hashing, and email helpers ---------------

/** 6-digit zero-padded cryptographically-random verification code. */
function refund_generate_code(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * SHA-256 of (code || '|' || salt). The salt is the request_id (or change_id)
 * so a DB dump can't be precomputed.
 */
function refund_hash_code(string $code, string $salt): string {
    return hash('sha256', $code . '|' . $salt);
}

/**
 * Spend one guess on a refund verification code. The limit check and the
 * increment are one UPDATE, so parallel requests that all read the same count
 * can't each get a guess. Returns the attempt number used, or null once $max
 * guesses are spent.
 */
function refund_claim_code_attempt(PDO $pdo, int $code_id, int $max = 5): ?int {
    $upd = $pdo->prepare("UPDATE refund_email_codes SET attempts = attempts + 1 WHERE id = ? AND attempts < ?");
    $upd->execute([$code_id, $max]);
    if ($upd->rowCount() === 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT attempts FROM refund_email_codes WHERE id = ?");
    $stmt->execute([$code_id]);
    return (int)$stmt->fetchColumn();
}

/** Mask an email for display (ev**@argobooks.app). */
function refund_mask_email(string $email): string {
    $at = strpos($email, '@');
    if ($at === false) return $email;
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    if (strlen($local) <= 2) return $local[0] . '***@' . $domain;
    return substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
}

// --- State-machine guards -------------------------------------

/**
 * Reject the request with 409 if the current state isn't in $allowed.
 * Echoes JSON body and calls exit(). Caller does not return.
 */
function refund_assert_state(string $current, array $allowed, string $action): void {
    if (!in_array($current, $allowed, true)) {
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'ILLEGAL_STATE_TRANSITION',
            'message' => "Cannot $action while in state $current",
            'current_state' => $current,
            'allowed_states' => $allowed,
        ]);
        exit;
    }
}

/**
 * Gate /refunds/* endpoints: account must be unlocked AND email-verified.
 * Echoes JSON + exits on failure.
 */
function refund_ensure_company_active(array $company): void {
    if (!empty($company['locked'])) {
        http_response_code(423);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'ACCOUNT_LOCKED',
            'message' => $company['lock_reason'] ?: 'Refunds are temporarily disabled. Contact support.',
        ]);
        exit;
    }
    if (empty($company['email_verified_at'])) {
        http_response_code(412);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'EMAIL_NOT_VERIFIED',
            'message' => 'Verify your email before issuing refunds.',
        ]);
        exit;
    }
    // Verification codes go to owner_email, so without one the code goes nowhere and the user is stranded on the verify step. Said plainly, rather than issuing a code that cannot arrive.
    if (empty($company['owner_email'])) {
        http_response_code(412);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'OWNER_EMAIL_NOT_SET',
            'message' => 'No owner email is on file for this portal account, so we can\'t send the refund verification code. Set your owner email in Settings → Payment Portal first.',
        ]);
        exit;
    }
}

/** Load a refund_request scoped to the company; 404 + exit if missing. */
function refund_load_request(PDO $pdo, int $company_id, int $request_id): array {
    $stmt = $pdo->prepare("SELECT * FROM refund_requests WHERE id = ? AND company_id = ?");
    $stmt->execute([$request_id, $company_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'REQUEST_NOT_FOUND']);
        exit;
    }
    return $row;
}

// --- Email senders for the refund flow ------------------------

function refund_email_send_code(string $to, string $code, string $invoice_number, int $amount_cents, string $currency): void {
    $amount_str = htmlspecialchars(number_format($amount_cents / 100, 2) . ' ' . $currency);
    $invoice_safe = htmlspecialchars($invoice_number);
    $subject = "Argo Books refund code: $code";
    $body = <<<HTML
        <p>Your refund verification code is:</p>
        <p style="font-size:28px;font-weight:bold;letter-spacing:6px;">$code</p>
        <p>You're refunding <strong>$amount_str</strong> on invoice <strong>$invoice_safe</strong>.</p>
        <p>The code expires in 10 minutes, and the refund can't go through without it, so if you don't enter it the request expires on its own. This was started from your Argo Books account, so if it wasn't you, check who has access to your device.</p>
HTML;
    send_styled_email($to, $subject, $body, 'blue');
}

function refund_email_send_issued(string $to, array $req): void {
    $amount_str = htmlspecialchars(number_format($req['amount_cents'] / 100, 2) . ' ' . $req['currency']);
    $invoice_safe = htmlspecialchars($req['invoice_number']);
    $subject = "Refund issued: $invoice_safe";
    $body = <<<HTML
        <p>A refund of <strong>$amount_str</strong> was issued on invoice <strong>$invoice_safe</strong>.</p>
        <p>The customer will see the money returned to their original payment method within 5–10 business days.</p>
HTML;
    send_styled_email($to, $subject, $body, 'blue');
}

/**
 * Notification sent to the *customer* when a refund completes. Distinct from
 * refund_email_send_issued (which goes to the business owner). Wording is
 * customer-facing and tells them what to expect in their bank/card account.
 */
function refund_email_send_customer_refunded(string $to, ?string $customer_name, string $invoice_number, int $amount_cents, string $currency, ?string $business_name = null, ?string $reason = null): void {
    $amount_str    = htmlspecialchars(number_format($amount_cents / 100, 2) . ' ' . $currency);
    $invoice_safe  = htmlspecialchars($invoice_number);
    $greeting      = !empty($customer_name) ? 'Hi ' . htmlspecialchars($customer_name) . ',' : 'Hi,';
    $business_safe = !empty($business_name) ? htmlspecialchars($business_name) : 'the merchant';
    $subject       = "Your refund for invoice $invoice_safe has been issued";

    // The merchant's reason is quoted so the customer reads the explanation the owner typed, which the owner is told will happen.
    $reason_html = '';
    if (!empty($reason)) {
        $reason_safe = nl2br(htmlspecialchars(trim($reason)));
        $reason_html = <<<HTML
        <p>Reason from $business_safe:</p>
        <blockquote style="border-left:3px solid #d1d5db;padding:8px 12px;margin:0 0 16px 0;color:#374151;background:#f9fafb;">
            $reason_safe
        </blockquote>
HTML;
    }

    $body = <<<HTML
        <p>$greeting</p>
        <p>A refund of <strong>$amount_str</strong> for invoice <strong>$invoice_safe</strong> has been issued by $business_safe.</p>
        $reason_html
        <p>The money will be returned to the same payment method you originally used. Most banks and card networks post the refund within 5–10 business days; some are faster.</p>
        <p>If you don't see the refund after 10 business days, please contact $business_safe directly.</p>
HTML;
    send_styled_email($to, $subject, $body, 'blue');
}

/**
 * Single point that fires *all* notifications when a refund_request transitions
 * to 'completed'. Always emails the business owner; additionally emails the
 * customer if portal_invoices has a customer_email on file. Safe to call
 * multiple times: the underlying mailer sends per call, so callers should
 * only invoke this once per actual state transition (i.e. inside the same
 * `if (state was not yet completed)` guard that issues the UPDATE).
 */
function refund_notify_completion(PDO $pdo, array $req): void {
    $stmt = $pdo->prepare("SELECT owner_email, company_name FROM portal_companies WHERE id = ?");
    $stmt->execute([$req['company_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) return;

    if (!empty($company['owner_email'])) {
        // Wrap the owner-email send so an SMTP failure here can't block
        // the customer-email send below. Each notification is independent.
        try { refund_email_send_issued($company['owner_email'], $req); }
        catch (\Throwable $e) {
            error_log('refund_notify_completion owner email failed: ' . $e->getMessage());
        }
    }

    // Look up the customer's email + name from the matching portal_invoices row.
    $istmt = $pdo->prepare("SELECT customer_email, customer_name FROM portal_invoices WHERE company_id = ? AND invoice_id = ? LIMIT 1");
    $istmt->execute([$req['company_id'], $req['invoice_id']]);
    $invoice = $istmt->fetch(PDO::FETCH_ASSOC);
    if ($invoice && !empty($invoice['customer_email'])) {
        try {
            refund_email_send_customer_refunded(
                $invoice['customer_email'],
                $invoice['customer_name'] ?? null,
                (string)$req['invoice_number'],
                (int)$req['amount_cents'],
                (string)$req['currency'],
                $company['company_name'] ?? null,
                $req['reason'] ?? null
            );
            audit_log($pdo, (int)$req['company_id'], 'customer_notified', 'system', null, (int)$req['id'], null, [
                'to' => $invoice['customer_email'],
            ]);
        } catch (\Throwable $e) {
            // An SMTP hiccup must never roll back the refund, and the owner has already been told, so the operator can follow the customer up by hand.
            error_log('refund_notify_completion customer email failed: ' . $e->getMessage());
        }
    }
}

// --- Email senders for email verification and email change ----

function refund_email_send_registration_code(string $to, string $code): void {
    $body = <<<HTML
        <p>Welcome to the Argo Books payment portal.</p>
        <p>Confirm your email with this code:</p>
        <p style="font-size:28px;font-weight:bold;letter-spacing:6px;">$code</p>
        <p>The code expires in 10 minutes. You'll need it to issue refunds and manage your account.</p>
HTML;
    send_styled_email($to, "Confirm your Argo Books email", $body, 'blue');
}

function refund_email_send_change_old_code(string $to, string $code, string $new_email): void {
    $new_safe = htmlspecialchars($new_email);
    $body = <<<HTML
        <p>Someone (hopefully you) requested an email change on your Argo Books account from this address to <strong>$new_safe</strong>.</p>
        <p>Confirm with this code:</p>
        <p style="font-size:28px;font-weight:bold;letter-spacing:6px;">$code</p>
        <p>You'll need this code to complete the change, so if you don't enter it the request expires on its own. This was started from your Argo Books account, so if it wasn't you, check who has access to your device.</p>
HTML;
    send_styled_email($to, "Confirm email change to $new_safe", $body, 'purple');
}

function refund_email_send_change_new_code(string $to, string $code): void {
    $body = <<<HTML
        <p>You're being added as the new owner email for an Argo Books portal account.</p>
        <p>Confirm with this code:</p>
        <p style="font-size:28px;font-weight:bold;letter-spacing:6px;">$code</p>
        <p>If you didn't expect this, ignore this email. Without confirmation the change cannot complete.</p>
HTML;
    send_styled_email($to, "Confirm this is your new Argo Books email", $body, 'blue');
}

function refund_email_send_change_completed_to_old(string $old, string $new, string $revert_token): void {
    $base = rtrim($_ENV['SITE_URL'] ?? 'https://argobooks.app', '/');
    $url = $base . '/api/portal/revert-email.php?token=' . urlencode($revert_token);
    $new_safe = htmlspecialchars($new);
    $body = <<<HTML
        <p>Your Argo Books portal email was changed from this address to <strong>$new_safe</strong>.</p>
        <p>If this wasn't you, click the button below to revert. You have 30 days.</p>
        <p><a href="$url" style="background:#dc2626;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Revert email change</a></p>
HTML;
    send_styled_email($old, "Your Argo Books email was changed", $body, 'purple');
}

function refund_email_send_cooling_off(string $to, array $req, string $token): void {
    $base = rtrim($_ENV['SITE_URL'] ?? 'https://argobooks.app', '/');
    $url = $base . '/api/portal/cancel-refund.php?token=' . urlencode($token);
    $amount_str = htmlspecialchars(number_format($req['amount_cents'] / 100, 2) . ' ' . $req['currency']);
    $invoice_safe = htmlspecialchars($req['invoice_number']);
    $subject = "Refund pending review: $amount_str on invoice $invoice_safe";
    $body = <<<HTML
        <p>We're holding this refund of <strong>$amount_str</strong> on invoice <strong>$invoice_safe</strong> for a brief review window because it triggered our anomaly checks.</p>
        <p>If you didn't initiate it or want to cancel, click the button below within 15 minutes:</p>
        <p><a href="$url" style="background:#dc2626;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Cancel this refund</a></p>
        <p>Otherwise it will be sent to the customer automatically.</p>
HTML;
    send_styled_email($to, $subject, $body, 'purple');
}

/**
 * Send a calm, action-oriented heads-up to the merchant's owner_email when
 * the velocity engine hard-blocks a refund. Mirrors the in-app modal copy
 * but gives them a permanent inbox record + a clean reply-to-resolve flow.
 * Reply-To is set to contact@argorobots.com so a plain Reply gets the
 * message to the admin inbox (where the matching admin alert already lives).
 * Best-effort, caller wraps in try/catch.
 */
function refund_email_send_hard_block(string $to, array $req): void {
    $amount_str = htmlspecialchars(number_format($req['amount_cents'] / 100, 2) . ' ' . $req['currency']);
    $invoice_safe = htmlspecialchars($req['invoice_number']);
    $subject = "Refund paused: $amount_str on invoice $invoice_safe";
    $body = <<<HTML
        <p>The refund of <strong>$amount_str</strong> on invoice <strong>$invoice_safe</strong> was paused by our automated safety check, and refunds on your account are temporarily on hold while we review.</p>
        <p><strong>This is often a false positive.</strong> Our system is tuned conservatively and frequently triggers on legitimate refunds, especially for newer accounts or larger amounts.</p>
        <p>To get this sorted, just reply to this email or write to <a href="mailto:contact@argorobots.com">contact@argorobots.com</a>. We will review and resume refunds within one business day, usually sooner.</p>
        <p>The rest of your account (invoicing, accepting payments, sync) continues to work normally.</p>
HTML;
    send_styled_email($to, $subject, $body, 'purple', null, null, 'contact@argorobots.com');
}

/**
 * Send an admin alert to contact@argorobots.com when the velocity engine
 * hard-blocks a refund (and locks the merchant's account). The alert carries
 * enough context to investigate the lock without logging into the DB:
 * company info, refund details, the velocity diagnostic that tripped, and a
 * link to the audit log entry. Best-effort, caller wraps in try/catch.
 */
function refund_notify_admin_of_hard_block(array $company, array $request, array $velocity, int $request_id): void {
    $base = rtrim($_ENV['SITE_URL'] ?? 'https://argorobots.com', '/');
    $admin_url = $base . '/admin/payments/index.php#companies';

    $company_id    = (int)($company['id'] ?? 0);
    $company_name  = htmlspecialchars((string)($company['company_name'] ?? '(unknown)'));
    $owner_email   = htmlspecialchars((string)($company['owner_email'] ?? '(no email)'));
    $env_label     = htmlspecialchars((string)($company['environment'] ?? 'production'));

    $amount_cents  = (int)($request['amount_cents'] ?? 0);
    $amount_str    = htmlspecialchars(number_format($amount_cents / 100, 2) . ' ' . ($request['currency'] ?? 'USD'));
    $invoice_safe  = htmlspecialchars((string)($request['invoice_number'] ?? '(unknown)'));
    $provider_safe = htmlspecialchars((string)($request['provider'] ?? '(unknown)'));
    $reason_safe   = htmlspecialchars((string)($request['reason'] ?? ''));

    $vel_reason    = htmlspecialchars((string)($velocity['reason'] ?? '(unspecified)'));
    $today_str     = htmlspecialchars(number_format(($velocity['today_cents'] ?? 0) / 100, 2));
    $hour_count    = (int)($velocity['hour_count'] ?? 0);

    $subject = "[Argo Books] Refund hard-block: company #$company_id ($company_name)";
    $body = <<<HTML
        <p><strong>An automated safety check has paused refunds on a portal account.</strong> This often catches false positives. Review and unlock if legitimate.</p>

        <h3 style="margin-top:24px;">Company</h3>
        <ul>
            <li><strong>ID:</strong> $company_id</li>
            <li><strong>Name:</strong> $company_name</li>
            <li><strong>Owner email:</strong> $owner_email</li>
            <li><strong>Environment:</strong> $env_label</li>
        </ul>

        <h3 style="margin-top:24px;">Refund that tripped the check</h3>
        <ul>
            <li><strong>Request ID:</strong> #$request_id</li>
            <li><strong>Invoice:</strong> $invoice_safe</li>
            <li><strong>Provider:</strong> $provider_safe</li>
            <li><strong>Amount:</strong> $amount_str</li>
            <li><strong>Merchant-provided reason:</strong> $reason_safe</li>
        </ul>

        <h3 style="margin-top:24px;">Velocity diagnostic</h3>
        <ul>
            <li><strong>Trigger:</strong> <code>$vel_reason</code></li>
            <li><strong>Total refunded today (incl. this attempt):</strong> \$$today_str</li>
            <li><strong>Refund attempts in last hour:</strong> $hour_count</li>
        </ul>

        <h3 style="margin-top:24px;">Next steps</h3>
        <ol>
            <li>Open the admin panel: <a href="$admin_url">$admin_url</a></li>
            <li>Find company #$company_id in the Companies list; review their recent refund history and audit log.</li>
            <li>If legitimate, click <strong>Unlock</strong> (you'll be asked for a reason). Consider adding a per-company override if they regularly need higher limits.</li>
            <li>If suspicious, leave locked and reply to the merchant once they reach out.</li>
        </ol>

        <p>Full investigation procedure: see <code>read-me/Hard-block response procedure.md</code> in the repo.</p>
HTML;
    // Reply-To set to the merchant's owner_email so you can hit Reply and talk
    // to them directly. Skip if owner_email is empty.
    $reply_to = !empty($company['owner_email']) ? $company['owner_email'] : null;
    send_styled_email('contact@argorobots.com', $subject, $body, 'purple', null, null, $reply_to);
}

// --- Provider execution dispatch ------------------------------

/**
 * Map a provider's refund-API response status to one of:
 *   'completed':  terminal success; money is back on the customer's card
 *   'failed':     terminal failure; refund will not happen
 *   'processing': non-terminal (PENDING etc.); webhook/cron must finalize
 *
 * Critically: a non-throwing provider call is NOT proof of success. PayPal and
 * Square frequently return PENDING for bank-funded refunds that later fail or
 * settle hours later. Treating PENDING as completed notifies the customer
 * prematurely.
 */
function refund_classify_provider_status(string $provider, ?string $status): string {
    if ($status === null) {
        // No status field returned; be conservative.
        return 'processing';
    }
    switch ($provider) {
        case 'stripe':
            // Refund.status: succeeded | pending | requires_action | failed | canceled
            if ($status === 'succeeded') return 'completed';
            if ($status === 'failed' || $status === 'canceled') return 'failed';
            return 'processing';
        case 'paypal':
            // Capture refund: COMPLETED | PENDING | FAILED | CANCELLED
            $s = strtoupper($status);
            if ($s === 'COMPLETED') return 'completed';
            if ($s === 'FAILED' || $s === 'CANCELLED') return 'failed';
            return 'processing';
        case 'square':
            // PaymentRefund.status: PENDING | COMPLETED | REJECTED | FAILED
            $s = strtoupper($status);
            if ($s === 'COMPLETED') return 'completed';
            if ($s === 'REJECTED' || $s === 'FAILED') return 'failed';
            return 'processing';
        default:
            return 'processing';
    }
}

/**
 * Idempotently write the negative-amount portal_payments row + flip the
 * original payment's status + update the invoice balance. Mirrors what
 * the provider webhook does. Safe to call from both the synchronous path
 * and the webhook: record_portal_payment is keyed on provider_payment_id
 * ('refund_' . $refund_id) so the second call is a no-op.
 *
 * Returns true if this call inserted a new ledger row, false if it was
 * already there.
 */
function refund_record_ledger(PDO $pdo, array $req, string $refund_id, ?array $company = null): bool {
    require_once __DIR__ . '/portal-helper.php'; // record_portal_payment + generate_reference_number

    // The original payment gives the cumulative check a total to compare against, and a manual entry with no original simply skips the status flip.
    $stmt = $pdo->prepare(
        "SELECT * FROM portal_payments
         WHERE company_id = ? AND provider_payment_id = ? LIMIT 1"
    );
    $stmt->execute([$req['company_id'], $req['provider_payment_id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    $env = $company['environment'] ?? ($_ENV['APP_ENV'] ?? 'sandbox');
    $refundAmount = (int)$req['amount_cents'] / 100.0;

    $recordResult = record_portal_payment([
        'company_id' => $req['company_id'],
        'invoice_id' => $req['invoice_id'],
        'customer_name' => $req['customer_name'] ?? ($original['customer_name'] ?? null),
        'amount' => -$refundAmount,
        'currency' => $req['currency'],
        'payment_method' => $req['provider'],
        'provider_payment_id' => 'refund_' . $refund_id,
        'provider_transaction_id' => $req['provider_payment_id'],
        'reference_number' => generate_reference_number(),
        'status' => 'refunded',
        'payment_environment' => $env,
    ]);
    if (empty($recordResult['inserted'])) {
        return false; // webhook (or a prior call) already wrote this row
    }

    // The original payment flips to refunded only once refunds cover its full amount, so a partial refund does not read as a full one. Compared in whole cents, so rounding cannot drift past it.
    if ($original) {
        $sumStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS refunded_total
             FROM portal_payments
             WHERE amount < 0 AND payment_method = ?
               AND provider_transaction_id = ?"
        );
        $sumStmt->execute([$req['provider'], $req['provider_payment_id']]);
        $refundedCents = (int)round(abs((float)$sumStmt->fetch()['refunded_total']) * 100);
        $originalCents = (int)round((float)$original['amount'] * 100);
        if ($refundedCents >= $originalCents) {
            $pdo->prepare("UPDATE portal_payments SET status='refunded' WHERE id = ?")
                ->execute([$original['id']]);
        }
    }

    // Update invoice balance/status. SET-clause order matters: the status
    // CASE must see the pre-update balance_due.
    $pdo->prepare(
        'UPDATE portal_invoices
         SET status = CASE
                 WHEN balance_due + ? >= total_amount THEN "sent"
                 ELSE "partial"
             END,
             balance_due = LEAST(total_amount, balance_due + ?),
             updated_at = NOW()
         WHERE company_id = ? AND invoice_id = ?'
    )->execute([$refundAmount, $refundAmount, $req['company_id'], $req['invoice_id']]);

    return true;
}

/**
 * Finish a refund that the stale-processing cron found succeeded at the
 * provider. Returns true when this call moved the request to completed.
 */
function refund_complete_from_stale_cron(PDO $pdo, array $req, array $company, string $refund_id): bool {
    // Ledger first, as in refund_execute_against_provider: the webhook that would write this row never arrived, and the desktop never sees the refund without it.
    refund_record_ledger($pdo, $req, $refund_id, $company);

    // CAS guard so a webhook arriving in the same window doesn't
    // produce a second completion notification.
    $upd = $pdo->prepare("UPDATE refund_requests SET state='completed', provider_refund_id = ?, completed_at = NOW(), cancel_token = NULL, updated_at = NOW() WHERE id = ? AND state IN ('processing','cooling_off')");
    $upd->execute([$refund_id, $req['id']]);
    if ($upd->rowCount() === 0) {
        return false;
    }
    audit_log($pdo, (int)$req['company_id'], 'completed', 'system', null, (int)$req['id'], null, [
        'reconciled_via_stale_cron' => true,
        'provider_refund_id' => $refund_id,
    ]);
    $req['state'] = 'completed';
    $req['provider_refund_id'] = $refund_id;
    refund_notify_completion($pdo, $req);
    return true;
}

/**
 * Invoke the provider's refund API for a request currently in 'processing'.
 * Branches on the provider's returned status:
 *   - terminal success → write ledger row, mark 'completed', notify
 *   - terminal failure → mark 'failed', no notify
 *   - non-terminal (PENDING etc.) → stay in 'processing', let the webhook
 *     (or the stale-processing cron) finalize. provider_refund_id is stored
 *     so cron lookups can correlate the request to the provider's refund.
 *
 * A non-throwing provider call is NOT sufficient for completion: PayPal /
 * Square can return PENDING for bank-funded refunds that later fail or
 * settle hours later. We notify only on terminal success.
 */
function refund_execute_against_provider(PDO $pdo, array $company, int $request_id): void {
    $stmt = $pdo->prepare("SELECT * FROM refund_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) return;

    try {
        $refund_id = null;
        $status = null;
        switch ($req['provider']) {
            case 'stripe':
                require_once __DIR__ . '/refunds/_provider_stripe.php';
                $result = refund_stripe_issue($company, $req);
                $refund_id = $result['id'] ?? null;
                $status = $result['status'] ?? null;
                break;
            case 'paypal':
                require_once __DIR__ . '/refunds/_provider_paypal.php';
                $result = refund_paypal_issue($company, $req);
                $refund_id = $result['id'] ?? null;
                $status = $result['status'] ?? null;
                break;
            case 'square':
                require_once __DIR__ . '/refunds/_provider_square.php';
                $result = refund_square_issue($company, $req);
                $refund_id = $result['refund']['id'] ?? null;
                $status = $result['refund']['status'] ?? null;
                break;
            default:
                throw new RuntimeException("Unsupported provider: {$req['provider']}");
        }

        $outcome = refund_classify_provider_status($req['provider'], $status);

        // provider_refund_id is stored even while the request stays in processing, so the webhook and the stale-processing cron can tie this request to the provider's own record.
        if ($refund_id) {
            $pdo->prepare("UPDATE refund_requests SET provider_refund_id = ?, updated_at = NOW() WHERE id = ? AND provider_refund_id IS NULL")
                ->execute([$refund_id, $request_id]);
        }

        if ($outcome === 'failed') {
            // cancel_token = NULL because the public /cancel-refund.php link
            // must not reveal terminal state to anyone holding a leaked email.
            $upd = $pdo->prepare("UPDATE refund_requests SET state='failed', state_reason = ?, cancel_token = NULL, updated_at = NOW() WHERE id = ? AND state IN ('processing','cooling_off')");
            $upd->execute(["Provider returned terminal status: " . (string)$status, $request_id]);
            if ($upd->rowCount() > 0) {
                audit_log($pdo, (int)$company['id'], 'failed', 'system', null, $request_id, null, [
                    'provider_status' => $status,
                    'provider_refund_id' => $refund_id,
                ]);
            }
            return;
        }

        if ($outcome === 'processing') {
            // Still processing: the webhook flips it to completed when the money moves, and the cron picks it up 30 minutes later if that webhook is lost. The customer is told nothing yet.
            audit_log($pdo, (int)$company['id'], 'provider_pending', 'system', null, $request_id, null, [
                'provider_status' => $status,
                'provider_refund_id' => $refund_id,
            ]);
            return;
        }

        // The negative portal_payments row and the balance update land before the request is marked completed, and record_portal_payment is idempotent, so a webhook arriving after is a no-op.
        if ($refund_id) {
            refund_record_ledger($pdo, $req, $refund_id, $company);
        }

        // Flipped only from a pre-completed state, so if the provider's webhook gets there first its update wins, this one does nothing, and the customer hears once. cancel_token clears here.
        $upd = $pdo->prepare("UPDATE refund_requests SET state='completed', provider_refund_id = ?, completed_at = NOW(), cancel_token = NULL, updated_at = NOW() WHERE id = ? AND state IN ('processing','cooling_off')");
        $upd->execute([$refund_id, $request_id]);
        if ($upd->rowCount() === 0) {
            return;
        }
        audit_log($pdo, (int)$company['id'], 'completed', 'system', null, $request_id, null, [
            'provider_refund_id' => $refund_id,
            'provider_status' => $status,
        ]);
        $req['state'] = 'completed';
        $req['provider_refund_id'] = $refund_id;
        // Notification is best-effort: the money is already back.
        // SMTP/SQL hiccups must not propagate to the outer catch.
        try { refund_notify_completion($pdo, $req); }
        catch (\Throwable $notifyEx) {
            error_log('refund_notify_completion threw after completed refund #' . $request_id . ': ' . $notifyEx->getMessage());
        }

    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        // Marked failed only from an in-flight state, so a throw after a successful provider call cannot overwrite a completion the webhook wrote. Audited only when the state really moves.
        $upd = $pdo->prepare("UPDATE refund_requests SET state='failed', state_reason = ?, cancel_token = NULL, updated_at = NOW() WHERE id = ? AND state IN ('processing','cooling_off')");
        $upd->execute([substr($msg, 0, 1000), $request_id]);
        if ($upd->rowCount() > 0) {
            audit_log($pdo, (int)$company['id'], 'failed', 'system', null, $request_id, null, ['error' => $msg]);
        }
    }
}
