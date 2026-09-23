<?php
/**
 * Shared helpers for admin-composed marketing broadcasts (the "email my list"
 * tool). Used by both the admin composer (admin/marketing/) and the queue-draining
 * cron (cron/marketing_broadcast.php).
 *
 * A broadcast targets one audience: 'newsletter' (no-account opt-in subscribers in
 * marketing_subscribers) or a community context that maps to a
 * community_users.email_pref_* column. Recipients are snapshotted into
 * marketing_broadcast_recipients at queue time; the cron sends them in batches,
 * re-checking the send-time gate per recipient so anyone who unsubscribed between
 * queue and send is skipped.
 */

require_once __DIR__ . '/../../email_marketing.php'; // pulls in db_connect, email_sender, env_helper

/** Audiences the composer can target: audience key => human label. */
function broadcast_audiences(): array
{
    return [
        'newsletter'      => 'Free tool and app subscribers (opt-in list)',
        'product_updates' => 'Community members — product updates',
        'tips_onboarding' => 'Community members — tips & onboarding',
        'promotions'      => 'Community members — promotions & offers',
    ];
}

/**
 * Resolve the current set of deliverable emails for an audience: the opted-in
 * population minus anyone suppressed for that context or all_marketing. This is
 * the snapshot taken at queue time; the cron re-checks the gate at send time too.
 *
 * @return string[] lowercased, de-duplicated email addresses
 */
function broadcast_audience_emails(PDO $pdo, string $audience): array
{
    if ($audience === 'newsletter') {
        $stmt = $pdo->prepare("SELECT DISTINCT email FROM marketing_subscribers WHERE context = 'newsletter' AND status = 'confirmed'");
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $cols = marketing_contexts();
        if (!isset($cols[$audience])) {
            return [];
        }
        // $col comes from the fixed marketing_contexts() whitelist, never user input.
        $col = $cols[$audience];
        $stmt = $pdo->query("SELECT DISTINCT email FROM community_users WHERE $col = 1 AND email IS NOT NULL AND email <> ''");
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $emails = array_values(array_unique(array_map(fn($e) => strtolower(trim($e)), $emails)));
    if (!$emails) {
        return [];
    }

    // Drop anyone already suppressed for this context or blanket marketing.
    $in = implode(',', array_fill(0, count($emails), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT email FROM email_suppressions WHERE context IN (?, 'all_marketing') AND email IN ($in)");
    $stmt->execute(array_merge([$audience], $emails));
    $suppressed = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $e) {
        $suppressed[strtolower(trim($e))] = true;
    }

    return array_values(array_filter($emails, fn($e) => !isset($suppressed[$e])));
}

/** Standard unsubscribe footer appended to every broadcast body. */
function broadcast_footer_html(?string $unsubscribeUrl): string
{
    $url = $unsubscribeUrl ?: site_url('/community/users/email_preferences.php');
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">'
        . '<p style="font-size:12px;color:#6b7280;">'
        . "You're receiving this because you subscribed to Argo Books emails. "
        . '<a href="' . $safe . '">Unsubscribe</a>.'
        . '</p>';
}

/**
 * Send one broadcast email. SMTP-first with mail() fallback is handled inside
 * send_styled_email(), which also wraps the admin-composed body in the site's
 * branded template. The unsubscribe footer is appended to the body.
 */
function send_broadcast_email(string $email, string $subject, string $bodyHtml, ?string $unsubscribeUrl): bool
{
    $html = $bodyHtml . broadcast_footer_html($unsubscribeUrl);
    return send_styled_email(
        $email,
        $subject,
        $html,
        'blue',
        'noreply@argorobots.com',
        'Argo Books',
        'contact@argorobots.com'
    );
}
