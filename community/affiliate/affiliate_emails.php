<?php
/**
 * Transactional emails for the affiliate program. All go through
 * send_styled_email() (Resend via SMTP, with the mail() fallback handled
 * inside email_sender.php).
 */

require_once __DIR__ . '/../../email_sender.php';

if (!function_exists('send_affiliate_application_received_email')) {
    /** Confirmation that an application was received and is under review. */
    function send_affiliate_application_received_email(string $to_email, string $username): bool
    {
        $name = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
            <h1>We received your affiliate application</h1>
            <p>Hi {$name},</p>
            <p>Thanks for applying to the Argo Books affiliate program. Your application is now under review.</p>
            <p>We review applications manually, so this usually takes a day or two. We'll email you the moment a decision is made. If approved, you'll get your unique referral link and can start earning right away.</p>
            <p>Talk soon,<br>The Argo Books team</p>
HTML;

        return send_styled_email($to_email, 'Your Argo Books affiliate application', $body, 'purple');
    }
}

if (!function_exists('send_affiliate_admin_notification_email')) {
    /**
     * Notify the admin (founder) that a new affiliate application came in, so
     * they know to review it. Sends to contact@argorobots.com, matching the
     * refund-alert convention in api/portal/_refund_helpers.php. Reply-to is the
     * applicant so the founder can reply to them directly. Best-effort.
     */
    function send_affiliate_admin_notification_email(string $applicant_username, string $applicant_email, string $promo_url, string $reason): bool
    {
        $base = rtrim($_ENV['SITE_URL'] ?? 'https://argorobots.com', '/');
        $admin_url = $base . '/admin/affiliates/';

        $name  = htmlspecialchars($applicant_username, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($applicant_email, ENT_QUOTES, 'UTF-8');
        $promo = trim($promo_url) !== '' ? htmlspecialchars($promo_url, ENT_QUOTES, 'UTF-8') : '(not provided)';
        $why   = nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));

        $subject = '[Argo Books] New affiliate application: ' . $applicant_username;
        $body = <<<HTML
            <p><strong>A new affiliate application is waiting for review.</strong></p>
            <ul>
                <li><strong>User:</strong> {$name}</li>
                <li><strong>Email:</strong> {$email}</li>
                <li><strong>Promotes at:</strong> {$promo}</li>
            </ul>
            <p><strong>How they'll promote Argo Books:</strong></p>
            <p>{$why}</p>
            <div class="button-container">
                <a href="{$admin_url}" class="button">Review in the admin panel</a>
            </div>
HTML;

        // Reply-to the applicant so a reply reaches them, not the noreply box.
        return send_styled_email('contact@argorobots.com', $subject, $body, 'purple', null, null, $applicant_email);
    }
}

if (!function_exists('send_affiliate_approved_email')) {
    /** Approval email containing the affiliate's live referral link + dashboard. */
    function send_affiliate_approved_email(string $to_email, string $username, string $referral_url, string $dashboard_url): bool
    {
        $name = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($referral_url, ENT_QUOTES, 'UTF-8');
        $dash = htmlspecialchars($dashboard_url, ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
            <h1>Your affiliate application is approved</h1>
            <p>Hi {$name},</p>
            <p>Your application is approved. Here's your unique referral link, share it anywhere you like:</p>
            <p style="font-size:16px;"><strong><a href="{$link}">{$link}</a></strong></p>
            <p>You earn <strong>50% of every payment</strong> from customers you refer, for the first 12 months of their subscription. Track your clicks, signups, and commission any time from your dashboard:</p>
            <div class="button-container">
                <a href="{$dash}" class="button">Open my affiliate dashboard</a>
            </div>
            <p>Happy promoting,<br>The Argo Books team</p>
HTML;

        return send_styled_email($to_email, 'Your Argo Books affiliate link is ready', $body, 'purple');
    }
}

if (!function_exists('send_affiliate_rejected_email')) {
    /** Polite decline. $reason is optional admin-supplied context. */
    function send_affiliate_rejected_email(string $to_email, string $username, string $reason = ''): bool
    {
        $name = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $reasonHtml = '';
        if (trim($reason) !== '') {
            $safe = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $reasonHtml = "<p>{$safe}</p>";
        }
        $body = <<<HTML
            <h1>Update on your affiliate application</h1>
            <p>Hi {$name},</p>
            <p>Thanks for your interest in the Argo Books affiliate program. We're not able to approve your application at this time.</p>
            {$reasonHtml}
            <p>You're welcome to apply again in the future. We appreciate your support of Argo Books.</p>
            <p>The Argo Books team</p>
HTML;

        return send_styled_email($to_email, 'Update on your Argo Books affiliate application', $body, 'purple');
    }
}
