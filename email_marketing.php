<?php

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/email_sender.php';
require_once __DIR__ . '/env_helper.php';

/**
 * Marketing email contexts that users can independently subscribe to.
 * Maps context -> community_users.email_pref_<column>.
 */
function marketing_contexts(): array
{
    return [
        'product_updates'   => 'email_pref_product_updates',
        'tips_onboarding'   => 'email_pref_tips_onboarding',
        'reviews'           => 'email_pref_reviews',
        'promotions'        => 'email_pref_promotions',
        'community_digest'  => 'email_pref_community_digest',
    ];
}

/**
 * Marketing contexts driven by the no-account opt-in list (marketing_subscribers)
 * rather than community_users.email_pref_* columns. A send is allowed only when a
 * 'confirmed' row exists for that (email, context) and nothing suppresses it.
 */
function marketing_subscriber_contexts(): array
{
    return ['newsletter'];
}

/**
 * Decide whether a marketing email may be sent to $email for $context.
 *
 * Rules (see plan: Send-time gate):
 * - 'all_marketing' suppression always blocks.
 * - For 'reviews' to a license-key holder: allow unless explicitly suppressed
 *   for 'reviews' or 'all_marketing'. Community-user prefs are NOT consulted;
 *   purchase establishes an existing business relationship for one ask.
 * - For all other community-driven contexts (and reviews to non-license
 *   holders): require an opted-in community_users row.
 */
function should_send_marketing_email(string $email, string $context): bool
{
    global $pdo;

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $contexts = marketing_contexts();
    $subscriberContexts = marketing_subscriber_contexts();
    if (!isset($contexts[$context]) && !in_array($context, $subscriberContexts, true)) {
        return false;
    }

    // Hard block: blanket suppression
    $stmt = $pdo->prepare("SELECT 1 FROM email_suppressions WHERE email = ? AND context = 'all_marketing' LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        return false;
    }

    // Per-context suppression
    $stmt = $pdo->prepare('SELECT 1 FROM email_suppressions WHERE email = ? AND context = ? LIMIT 1');
    $stmt->execute([$email, $context]);
    if ($stmt->fetchColumn()) {
        return false;
    }

    // Subscriber-list path (e.g. 'newsletter'): require a confirmed opt-in row.
    if (in_array($context, $subscriberContexts, true)) {
        $stmt = $pdo->prepare("SELECT 1 FROM marketing_subscribers WHERE email = ? AND context = ? AND status = 'confirmed' LIMIT 1");
        $stmt->execute([$email, $context]);
        return (bool) $stmt->fetchColumn();
    }

    // Reviews path: license-key holder gets the ask by default
    if ($context === 'reviews') {
        $stmt = $pdo->prepare('SELECT 1 FROM license_keys WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    // Community-driven path: must have opted in via community account
    $column = $contexts[$context];
    $stmt = $pdo->prepare("SELECT $column FROM community_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $optedIn = $stmt->fetchColumn();

    return $optedIn === 1 || $optedIn === '1';
}

/**
 * Record that a marketing email was sent. Informational only, used for
 * debugging "did this email get sent?" later.
 */
function mark_marketing_sent(string $email, string $context, ?int $relatedId = null): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO email_marketing_log (email, context, related_id) VALUES (?, ?, ?)');
    $stmt->execute([strtolower(trim($email)), $context, $relatedId]);
}

/**
 * Lazily generate and persist a community-user unsubscribe token. Reused
 * across all marketing emails to that user so unsubscribe links remain stable.
 */
function ensure_unsubscribe_token(int $userId): ?string
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT email_pref_unsubscribe_token FROM community_users WHERE id = ?');
    $stmt->execute([$userId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return $existing;
    }

    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare('UPDATE community_users SET email_pref_unsubscribe_token = ? WHERE id = ?');
    $stmt->execute([$token, $userId]);
    return $token;
}

/**
 * Build the unsubscribe URL for a community-user-scoped marketing email.
 */
function community_user_unsubscribe_url(int $userId, string $context): ?string
{
    $token = ensure_unsubscribe_token($userId);
    if (!$token) {
        return null;
    }
    return site_url('/unsubscribe/marketing.php?u=' . urlencode($token) . '&c=' . urlencode($context));
}

/**
 * Build the unsubscribe URL for a license-only marketing email (review/feedback).
 * Generates and persists a per-license token if none exists yet.
 */
function license_unsubscribe_url(int $licenseId): ?string
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT review_email_token FROM license_keys WHERE id = ?');
    $stmt->execute([$licenseId]);
    $token = $stmt->fetchColumn();

    if (!$token) {
        $token = bin2hex(random_bytes(24));
        $stmt = $pdo->prepare('UPDATE license_keys SET review_email_token = ? WHERE id = ?');
        $stmt->execute([$token, $licenseId]);
    }

    return site_url('/unsubscribe/marketing.php?l=' . urlencode($token));
}

/**
 * Send a review-request email to an active license-key holder.
 * Returns true on success. Caller is responsible for marking the license row.
 */
function send_review_request_email(int $licenseId, string $email): bool
{
    if (!should_send_marketing_email($email, 'reviews')) {
        return false;
    }

    $unsubscribe_url = license_unsubscribe_url($licenseId);
    $review_url = site_url('/review/');
    $unsubscribe_safe = htmlspecialchars($unsubscribe_url ?? '', ENT_QUOTES, 'UTF-8');
    $review_safe = htmlspecialchars($review_url, ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <h2>How is Argo Books working for you?</h2>
        <p>Hey,</p>
        <p>Thanks for being an Argo Books customer. If the app has helped your bookkeeping, I'd really appreciate a short review on Capterra. It's the single biggest thing that helps other small businesses find Argo.</p>
        <p style="margin: 24px 0;">
            <a href="{$review_safe}" class="btn-primary" style="background:#2563eb;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block;">Leave a review</a>
        </p>
        <p>If something's getting in the way or you have feedback, just hit reply. I read every email.</p>
        <p>Evan, Argo Books</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">
        <p style="font-size:12px;color:#6b7280;">
            You're receiving this because you purchased an Argo Books license.
            <a href="{$unsubscribe_safe}">Unsubscribe</a>.
        </p>
        HTML;

    $sent = send_styled_email(
        $email,
        'How is Argo Books working for you?',
        $body,
        'blue',
        'noreply@argorobots.com',
        'Evan at Argo Books',
        'contact@argorobots.com'
    );

    if ($sent) {
        mark_marketing_sent($email, 'reviews', $licenseId);
    }
    return $sent;
}

/**
 * Send a feedback-request email to an inactive license-key holder.
 * Asks them to reply with what's getting in the way.
 */
function send_feedback_request_email(int $licenseId, string $email): bool
{
    if (!should_send_marketing_email($email, 'reviews')) {
        return false;
    }

    $unsubscribe_url = license_unsubscribe_url($licenseId);
    $unsubscribe_safe = htmlspecialchars($unsubscribe_url ?? '', ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <h2>Quick check-in on Argo Books</h2>
        <p>Hey,</p>
        <p>I noticed it's been a little while since you used Argo Books. I wanted to check in: was there something that didn't work for you, or that's missing?</p>
        <p>Just reply to this email and let me know. Anything you tell me goes straight to me, and it genuinely helps me decide what to build next.</p>
        <p>You can also email me directly at <a href="mailto:contact@argorobots.com">contact@argorobots.com</a>.</p>
        <p>Evan, Argo Books</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">
        <p style="font-size:12px;color:#6b7280;">
            You're receiving this because you purchased an Argo Books license.
            <a href="{$unsubscribe_safe}">Unsubscribe</a>.
        </p>
        HTML;

    $sent = send_styled_email(
        $email,
        'Quick check-in on Argo Books',
        $body,
        'blue',
        'noreply@argorobots.com',
        'Evan at Argo Books',
        'contact@argorobots.com'
    );

    if ($sent) {
        mark_marketing_sent($email, 'reviews', $licenseId);
    }
    return $sent;
}

/* ===========================================================================
 * No-account opt-in list (marketing_subscribers): double opt-in lifecycle.
 *
 * Flow: create_pending_subscriber() inserts a 'pending' row + sends a confirm
 * email -> the visitor clicks the link -> confirm_subscriber() flips it to
 * 'confirmed'. Only 'confirmed' rows receive broadcasts (enforced by
 * should_send_marketing_email('...','newsletter')). One-click unsubscribe is
 * handled by unsubscribe_subscriber_by_token(), reachable from
 * /unsubscribe/marketing.php?s=<token>.
 * ========================================================================= */

/**
 * Display names for marketing_subscribers.source. The endpoint that accepts a sign-up keeps its
 * own allowlist of what it will take; this is only what to call each one on screen and in mail.
 */
function marketing_source_labels(): array
{
    return [
        'profit_analyzer'          => 'Profit Analyzer',
        'hourly_rate_calculator'   => 'Hourly Rate Calculator',
        'break_even_calculator'    => 'Break-Even Calculator',
        'self_employed_tax'        => 'Self-Employed Tax Calculator',
        'invoice_generator'        => 'Invoice Generator',
        'estimate_generator'       => 'Estimate Generator',
        'purchase_order_generator' => 'Purchase Order Generator',
        'desktop_app'              => 'Desktop app',
    ];
}

function marketing_source_label(string $source): string
{
    return marketing_source_labels()[$source] ?? $source;
}

/** Absolute URL a subscriber clicks to confirm their opt-in. */
function subscriber_confirm_url(string $token): string
{
    return site_url('/subscribe/confirm.php?t=' . urlencode($token));
}

/** Absolute one-click unsubscribe URL for a no-account subscriber. */
function subscriber_unsubscribe_url(string $token): string
{
    return site_url('/unsubscribe/marketing.php?s=' . urlencode($token));
}

/**
 * Create (or refresh) a pending opt-in row and send the confirmation email.
 *
 * Idempotent and safe to call repeatedly: an already-confirmed subscriber is
 * left alone (no duplicate confirm email); a pending/unsubscribed/new email gets
 * a fresh confirm token and a new confirmation email.
 *
 * @return string One of: 'sent' (confirm email sent), 'already_confirmed',
 *                'invalid' (bad email), 'error' (DB/send failure).
 */
function create_pending_subscriber(string $email, string $source = 'profit_analyzer', string $context = 'newsletter', ?string $ip = null): string
{
    global $pdo;

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'invalid';
    }

    // Already on the list and confirmed: nothing to do, don't re-confirm.
    $stmt = $pdo->prepare("SELECT id, status, unsubscribe_token FROM marketing_subscribers WHERE email = ? AND context = ? LIMIT 1");
    $stmt->execute([$email, $context]);
    $existing = $stmt->fetch();
    if ($existing && $existing['status'] === 'confirmed') {
        return 'already_confirmed';
    }

    $confirmToken = bin2hex(random_bytes(24));
    $unsubToken = ($existing && $existing['unsubscribe_token']) ? $existing['unsubscribe_token'] : bin2hex(random_bytes(24));

    try {
        // Upsert to 'pending' with a fresh confirm token. The unique key on
        // (email, context) makes a repeat opt-in update the same row.
        $stmt = $pdo->prepare(
            "INSERT INTO marketing_subscribers (email, context, status, source, confirm_token, unsubscribe_token, ip, created_at)
             VALUES (?, ?, 'pending', ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE status = 'pending', confirm_token = VALUES(confirm_token),
                 unsubscribe_token = COALESCE(unsubscribe_token, VALUES(unsubscribe_token)),
                 source = VALUES(source), ip = VALUES(ip)"
        );
        $stmt->execute([$email, $context, $source, $confirmToken, $unsubToken, $ip]);
    } catch (PDOException $e) {
        error_log('create_pending_subscriber failed: ' . $e->getMessage());
        return 'error';
    }

    return send_subscription_confirm_email($email, $confirmToken) ? 'sent' : 'error';
}

/** Send the "please confirm your subscription" double opt-in email. */
function send_subscription_confirm_email(string $email, string $confirmToken): bool
{
    $confirm_url = subscriber_confirm_url($confirmToken);
    $confirm_safe = htmlspecialchars($confirm_url, ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <h2>Confirm your subscription</h2>
        <p>Thanks for your interest in Argo Books. Please confirm you'd like to receive occasional tips and product updates by clicking the button below.</p>
        <p style="margin: 24px 0;">
            <a href="{$confirm_safe}" class="btn-primary" style="background:#2563eb;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block;">Confirm subscription</a>
        </p>
        <p style="font-size:13px;color:#6b7280;">If the button doesn't work, copy and paste this link into your browser:<br>{$confirm_safe}</p>
        <p style="font-size:13px;color:#6b7280;">If you didn't request this, you can ignore this email and you won't be added to the list.</p>
        HTML;

    return send_styled_email(
        $email,
        'Confirm your Argo Books subscription',
        $body,
        'blue',
        'noreply@argorobots.com',
        'Argo Books',
        'contact@argorobots.com'
    );
}

/**
 * Confirm a pending opt-in by its confirm token. Flips the row to 'confirmed'
 * and clears any prior newsletter-context suppression (the visitor is actively
 * opting back in). Returns the subscriber's email on success, or null.
 */
function confirm_subscriber(string $token): ?string
{
    global $pdo;

    if (!preg_match('/^[a-f0-9]{24,128}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, email, context, status FROM marketing_subscribers WHERE confirm_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($row['status'] !== 'confirmed') {
        $upd = $pdo->prepare("UPDATE marketing_subscribers SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?");
        $upd->execute([$row['id']]);

        // A fresh, explicit opt-in clears a stale per-context suppression so the
        // send-time gate won't keep blocking them. all_marketing is left intact.
        $del = $pdo->prepare('DELETE FROM email_suppressions WHERE email = ? AND context = ?');
        $del->execute([strtolower(trim($row['email'])), $row['context']]);
    }

    return $row['email'];
}

/**
 * One-click unsubscribe for a no-account subscriber. Marks the row
 * 'unsubscribed' and records a per-context suppression so the gate blocks future
 * sends even if a confirmed row somehow lingers. Returns the email, or null.
 */
function unsubscribe_subscriber_by_token(string $token): ?string
{
    global $pdo;

    if (!preg_match('/^[a-f0-9]{24,128}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, email, context FROM marketing_subscribers WHERE unsubscribe_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $email = strtolower(trim($row['email']));

    $upd = $pdo->prepare("UPDATE marketing_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?");
    $upd->execute([$row['id']]);

    $check = $pdo->prepare('SELECT 1 FROM email_suppressions WHERE email = ? AND context = ? LIMIT 1');
    $check->execute([$email, $row['context']]);
    if (!$check->fetchColumn()) {
        $ins = $pdo->prepare('INSERT INTO email_suppressions (email, context, reason, source_id) VALUES (?, ?, ?, ?)');
        $ins->execute([$email, $row['context'], 'one-click unsubscribe (subscriber)', $row['id']]);
    }

    return $email;
}

/**
 * Build the correct unsubscribe URL for a broadcast recipient, branching on the
 * audience context: 'newsletter' uses the marketing_subscribers token; a
 * community context uses the community_users token. Returns null if no token
 * source is found (the cron then falls back to a generic preferences link).
 */
function marketing_unsubscribe_url_for(string $email, string $context): ?string
{
    global $pdo;

    $email = strtolower(trim($email));

    if (in_array($context, marketing_subscriber_contexts(), true)) {
        $stmt = $pdo->prepare('SELECT unsubscribe_token FROM marketing_subscribers WHERE email = ? AND context = ? LIMIT 1');
        $stmt->execute([$email, $context]);
        $token = $stmt->fetchColumn();
        return $token ? subscriber_unsubscribe_url($token) : null;
    }

    // Community-user contexts: reuse the existing per-user token flow.
    $stmt = $pdo->prepare('SELECT id FROM community_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        return community_user_unsubscribe_url((int) $userId, $context);
    }

    return null;
}
