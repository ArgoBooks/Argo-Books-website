<?php

require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/config/pricing.php';

/**
 * Strip CR/LF and the rest of the ASCII control range from any value that
 * ends up in an email header. PHPMailer sanitizes its own header inputs,
 * but the mail() fallback path in send_styled_email() concatenates these
 * into the headers string verbatim. A stray newline would let an attacker
 * inject Bcc/Cc/etc. via user-controlled fields (subject lines from
 * community posts, contact-form reply-to, etc.).
 *
 * Returns null for null input so callers can pass through optional fields.
 */
function sanitize_header_value(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    return preg_replace('/[\r\n\x00-\x1f]+/', ' ', $value);
}

/**
 * Admin notification preferences (single global row).
 *
 * Every team-facing alert that a founder can toggle on/off consults this row:
 * one destination email plus a set of boolean toggles. Managed from
 * admin/settings/ (Notifications tab). The row is auto-seeded on first read so
 * callers never have to worry about a missing config.
 *
 * @return array Associative row with notification_email + notify_* flags.
 */
function get_admin_notification_prefs()
{
    global $pdo;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Seed the default row once. Default destination mirrors the address the
    // customer/cancellation alerts historically hardcoded, so behaviour is
    // unchanged until an admin edits it.
    $pdo->prepare(
        "INSERT IGNORE INTO admin_notification_prefs (id, notification_email)
         VALUES (1, 'contact@argorobots.com')"
    )->execute();

    $stmt = $pdo->query('SELECT * FROM admin_notification_prefs WHERE id = 1');
    $cache = $stmt->fetch() ?: [
        'notification_email'            => 'contact@argorobots.com',
        'notify_new_posts'              => 1,
        'notify_new_comments'           => 1,
        'notify_new_reports'            => 1,
        'notify_new_customer'           => 1,
        'notify_subscription_cancelled' => 1,
    ];
    return $cache;
}

/**
 * The address admin notifications should be sent to.
 *
 * @return string
 */
function admin_notification_email()
{
    $prefs = get_admin_notification_prefs();
    $email = trim((string)($prefs['notification_email'] ?? ''));
    return $email !== '' ? $email : 'contact@argorobots.com';
}

/**
 * Whether a given admin notification toggle is enabled.
 *
 * @param string $key One of the notify_* column names.
 * @return bool Defaults to true for unknown keys (fail-open: better to send a
 *              stray alert than to silently swallow one).
 */
function admin_notification_enabled($key)
{
    $prefs = get_admin_notification_prefs();
    if (!array_key_exists($key, $prefs)) {
        return true;
    }
    return (int)$prefs[$key] === 1;
}

// Note: send_post_reply_email() and send_mention_email() defined below
// call into helpers from email_marketing.php (should_send_marketing_email,
// community_user_unsubscribe_url, mark_marketing_sent). Callers of those
// senders must require_once email_marketing.php themselves. We don't
// require it here to avoid a circular include. email_marketing.php
// already requires this file.

/**
 * Build an HTML <li> list of Premium plan features from plans.json.
 *
 * @param string $prefix Optional string (e.g. "✓ ") prepended to each item
 * @return string Concatenated <li>...</li> markup
 */
function _premium_feature_list_items($prefix = '')
{
    $items = '';
    foreach (get_plan_features()['premium']['features'] as $feature) {
        $items .= '<li>' . $prefix . render_feature_label($feature) . '</li>';
    }
    return $items;
}

/**
 * Base function to send an email with standard Argo styling.
 *
 * @param string      $to_email      Recipient email address
 * @param string      $subject       Email subject
 * @param string      $body_content  HTML content for the email body (will be wrapped in template). When $format === 'plain', this is used as-is as plain text and no template is applied.
 * @param string      $header_style  Optional header style ('blue', 'premium', 'purple', '' for default, or a raw inline style string for backwards compatibility)
 * @param string|null $from_email    Sender email address; falls back to noreply@argorobots.com when null
 * @param string|null $from_name     Sender display name; defaults to 'Argo Books' when null
 * @param string|null $reply_to      Reply-To address; defaults to support@argorobots.com when null
 * @param array       $extra_headers Optional associative array of additional headers (added to the SMTP message via addCustomHeader, and to the mail() fallback's header block)
 * @param string|null $preheader     Optional inbox-preview snippet; rendered as a hidden element so it appears next to the subject in most mail clients without being visible in the body. Ignored when $format === 'plain'.
 * @param string      $format        'html' (default, full styled template) or 'plain' (no wrapper, sent as text/plain)
 * @param string|null &$message_id   Out-parameter: receives the RFC 822 Message-ID of the sent email (with angle brackets), or null if the send failed. Pass an existing variable to capture; ignore otherwise. Used by the outreach follow-up sender so a follow-up can thread to the original via In-Reply-To / References.
 * @return bool                      True if successful, false otherwise
 */
function send_styled_email($to_email, $subject, $body_content, $header_style = '', $from_email = null, $from_name = null, $reply_to = null, $extra_headers = [], $preheader = null, $format = 'html', &$message_id = null)
{
    // sanitize_header_value() strips CR/LF and ASCII control chars so
    // user-controlled fields can't inject Bcc/Cc/etc into the mail()
    // fallback's concatenated header string.
    $to_email = sanitize_header_value($to_email);
    $subject = (string) sanitize_header_value($subject);
    $from_email = sanitize_header_value($from_email);
    $from_name = sanitize_header_value($from_name);
    $reply_to = sanitize_header_value($reply_to);

    $isPlain = ($format === 'plain');

    if ($isPlain) {
        $email_body = (string) $body_content;
    } else {
        $css = file_get_contents(__DIR__ . '/email.css');
        $site_url = site_url();

        $preheaderHtml = '';
        if ($preheader !== null && trim((string) $preheader) !== '') {
            $safePreheader = htmlspecialchars((string) $preheader, ENT_QUOTES, 'UTF-8');
            $preheaderHtml = '<div style="display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:transparent;mso-hide:all;">' . $safePreheader . '</div>';
        }

        // Map style keywords to CSS classes. 'premium' is the blue Argo Books Premium
        // header used by subscription mail; 'purple' remains the generic second
        // accent for affiliate, portal and refund mail; 'blue' is the default.
        $header_class_map = [
            'premium' => 'header-premium',
            'purple'  => 'header-purple',
        ];
        $header_class = $header_class_map[$header_style] ?? 'header-blue';

        // Subjects can be admin-authored or AI-generated; escape for the
        // HTML <title> context. The raw subject still goes to SMTP/mail()
        // as the email Subject header below.
        $titleEscaped = htmlspecialchars((string) $subject, ENT_QUOTES, 'UTF-8');

        $email_body = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <title>{$titleEscaped}</title>
                <style>
                    {$css}
                </style>
            </head>
            <body>
                {$preheaderHtml}
                <div class="container">
                    <div class="header {$header_class}">
                        <img src="{$site_url}/resources/images/argo-logo/argo-logo-white.png" alt="Argo Logo" width="140">
                    </div>
                    <div class="content">
                        {$body_content}
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    // Synthesize the Message-ID up-front so both transport paths use the same
    // value. Callers that want to thread a future reply (e.g. the outreach
    // follow-up sender) capture this via the &$message_id out-param.
    $generated_message_id = '<' . bin2hex(random_bytes(16)) . '@argorobots.com>';

    // Use SMTP relay if configured, otherwise fall back to mail()
    $mailer = create_smtp_mailer();
    if ($mailer) {
        try {
            if ($from_email) {
                $mailer->setFrom($from_email, $from_name ?? 'Argo Books');
            }
            $mailer->addAddress($to_email);
            $mailer->addReplyTo($reply_to ?? 'support@argorobots.com');
            $mailer->Subject = $subject;
            if ($isPlain) {
                $mailer->isHTML(false);
            }
            $mailer->Body = $email_body;
            $mailer->MessageID = $generated_message_id;
            if (!empty($extra_headers) && is_array($extra_headers)) {
                foreach ($extra_headers as $name => $value) {
                    $mailer->addCustomHeader($name, $value);
                }
            }
            $mailer->send();
            $message_id = $generated_message_id;
            return true;
        } catch (\Exception $e) {
            $message_id = null;
            error_log("SMTP email failed for {$to_email}: " . $e->getMessage());
            return false;
        }
    }

    $actualFrom = $from_email ? ($from_name ?? 'Argo Books') . " <{$from_email}>" : 'Argo Books <noreply@argorobots.com>';
    $contentType = $isPlain ? 'text/plain; charset=UTF-8' : 'text/html; charset=UTF-8';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType,
        'From: ' . $actualFrom,
        'Reply-To: ' . ($reply_to ?? 'support@argorobots.com'),
        'Message-ID: ' . $generated_message_id,
        'X-Mailer: PHP/' . phpversion()
    ];
    // Append extra headers to the fallback path too. Without this, threading
    // headers (In-Reply-To, References) only work when SMTP is configured.
    if (!empty($extra_headers) && is_array($extra_headers)) {
        foreach ($extra_headers as $name => $value) {
            $headers[] = $name . ': ' . sanitize_header_value((string) $value);
        }
    }

    $sent = mail($to_email, $subject, $email_body, implode("\r\n", $headers));
    $message_id = $sent ? $generated_message_id : null;
    return $sent;
}

/**
 * Resend Premium subscription ID email
 *
 * @param string $to_email User's email address
 * @param string $subscription_id Premium subscription ID
 * @param string $billing_cycle Billing cycle (monthly/yearly)
 * @param string $end_date Subscription end date
 * @return bool Success status
 */
function resend_subscription_id_email($to_email, $subscription_id, $billing_cycle = 'monthly', $end_date = '')
{
    $billing_text = $billing_cycle === 'yearly' ? 'yearly' : 'monthly';
    $end_date_text = !empty($end_date) ? date('F j, Y', strtotime($end_date)) : 'N/A';
    $site_url = site_url();

    $body = <<<HTML
        <h1>Your Premium license key</h1>
        <p>As requested, here is your Argo Books Premium license key:</p>

        <div class="license-key">{$subscription_id}</div>

        <h2>Subscription details</h2>
        <table class="details-table">
            <tr>
                <td><strong>Plan</strong></td>
                <td>{$billing_text}</td>
            </tr>
            <tr>
                <td><strong>Next Billing</strong></td>
                <td>{$end_date_text}</td>
            </tr>
        </table>

        <div class="button-container">
            <a href="{$site_url}/community/users/subscription.php" class="button button-premium">Manage Subscription</a>
        </div>

        <p>Keep this key safe. You may need it when contacting support about your subscription.</p>
        <p>If you have any questions or need assistance, please don't hesitate to <a href="{$site_url}/contact-us/">contact support</a>.</p>
        HTML;

    return send_styled_email($to_email, 'Your Requested Argo Books Premium License Key', $body, 'premium');
}

/**
 * Send verification email with code
 *
 * @param string $email User's email address
 * @param string $code Verification code
 * @param string $username Username
 * @return bool Success status
 */
function send_verification_email($email, $code, $username)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $body = <<<HTML
        <h1>Welcome to the Argo Community</h1>
        <p>Hello {$username},</p>
        <p>Thank you for registering. <strong>Email verification is required</strong> to activate your account and access your license key.</p>

        <p>Please use the following verification code to complete your registration:</p>
        <div class="verification-code">{$code}</div>

        <p>This code will expire in 24 hours.</p>

        <p>If you did not sign up for an account, you can ignore this email.</p>
        <p>Regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Verify Your Account - Argo Books', $body);
}

/**
 * Community admin notification sender
 *
 * @param string $type Notification type ('new_post', 'new_comment')
 * @param array $data Notification data
 * @return bool Success status
 */
function send_notification_email($type, $data)
{
    // Respect the admin's notification preferences (Settings > Notifications).
    $notification_column = ($type === 'new_post') ? 'notify_new_posts' : 'notify_new_comments';
    if (!admin_notification_enabled($notification_column)) {
        return true;
    }
    $recipient_email = admin_notification_email();

    // Prepare email content
    $subject = '';
    $site_url = site_url();
    $body_template = '';

    if ($type === 'new_post') {
        $post_type_text = $data['post_type'] === 'bug' ? 'Bug Report' : 'Feature Request';
        $subject = "[Argo Community] New $post_type_text: " . $data['title'];
        $post_url = "$site_url/community/view_post.php?id=" . $data['id'];
        $escaped_title = htmlspecialchars($data['title']);
        $escaped_content = nl2br(htmlspecialchars($data['content'] ?? ''));

        $body_template = <<<HTML
                    <h2>New {$post_type_text} posted</h2>
                    <p>A new {$post_type_text} has been posted on the Argo Community:</p>

                    <p><strong>Title:</strong> {$escaped_title}</p>
                    <p><strong>Posted by:</strong> {$data['user_name']} ({$data['user_email']})</p>

                    <div style="background-color: #f5f5f5; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.6;">{$escaped_content}</p>
                    </div>

                    <div class="button-container">
                        <a href="{$post_url}" class="button">View Post</a>
                    </div>

                    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">This is an automated notification. You received this because you're an administrator of the Argo Community.
                    You can adjust your notification settings <a href="$site_url/admin/settings/?tab=notifications">here</a>.</p>
            HTML;
    } elseif ($type === 'new_comment') {
        $subject = "[Argo Community] New Comment on: " . $data['post_title'];
        $post_url = "$site_url/community/view_post.php?id=" . $data['post_id'];
        $escaped_post_title = htmlspecialchars($data['post_title']);
        $escaped_content = nl2br(htmlspecialchars($data['content'] ?? ''));

        $body_template = <<<HTML
                    <h2>New comment posted</h2>
                    <p>A new comment has been posted on "{$escaped_post_title}":</p>

                    <p><strong>Posted by:</strong> {$data['user_name']} ({$data['user_email']})</p>

                    <div style="background-color: #f5f5f5; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.6;">{$escaped_content}</p>
                    </div>

                    <div class="button-container">
                        <a href="{$post_url}" class="button">View Comment</a>
                    </div>

                    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">This is an automated notification. You received this because you're an administrator of the Argo Community.
                    You can adjust your notification settings <a href="$site_url/admin/settings/?tab=notifications">here</a>.</p>
            HTML;
    } else {
        return false; // Unknown notification type
    }

    // Send a single email to the configured admin notification address
    // (uses SMTP when configured).
    return send_styled_email($recipient_email, $subject, $body_template);
}

/**
 * Send password reset email
 *
 * @param string $email User's email address
 * @param string $token Reset token
 * @param string $username Username
 * @return bool Success status
 */
function send_password_reset_email($email, $token, $username)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    // Build the reset link from the configured SITE_URL, never from the request
    // Host header. A forged Host would otherwise place a valid reset token in a
    // link pointing at an attacker's domain (account-takeover vector).
    $reset_link = site_url('/community/users/reset_password.php?token=' . $token);

    $body = <<<HTML
        <h1>Password reset request</h1>
        <p>Hello {$username},</p>
        <p>We received a request to reset your password for your Argo Community account. To complete the password reset process, please click the button below:</p>

        <div class="button-container">
            <a href="{$reset_link}" class="button">Reset Password</a>
        </div>

        <p>If the button above doesn't work, you can also copy and paste the following link into your browser:</p>
        <div class="reset-link">{$reset_link}</div>

        <p>This password reset link will expire in 24 hours.</p>

        <p>If you did not request a password reset, you can safely ignore this email - your account is secure.</p>

        <p>Regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Password Reset - Argo Community', $body);
}

/**
 * Confirm a Premium activation and ask the buyer to verify the address they gave.
 *
 * Sent after Premium is already switched on, never before. Verification here is a data quality
 * flag: it tells us the address is real, it does not decide whether anyone gets what they paid
 * for. Anything else generates support tickets from customers whose activation appears stuck
 * behind an email that has not arrived yet.
 *
 * Goes through send_styled_email(), which tries the Resend SMTP relay first and only falls back
 * to mail() when no relay is configured.
 *
 * @param string $email The address the buyer entered in the app
 * @param string $token One-click verification token
 * @return bool Success status
 */
function send_license_email_verification($email, $token)
{
    $verify_link = site_url('/verify-email/?token=' . urlencode($token));

    $body = <<<HTML
        <h1>Your Premium licence is active</h1>
        <p>Thanks for activating Argo Books Premium. Everything is already unlocked, so there is nothing you need to do to start using it.</p>

        <p>Confirming your email lets us send your receipt, reach you about your licence, and help if you ever need to move it to another computer.</p>

        <div class="button-container">
            <a href="{$verify_link}" class="button">Verify email</a>
        </div>

        <p>If the button above doesn't work, you can also copy and paste the following link into your browser:</p>
        <div class="reset-link">{$verify_link}</div>

        <p>If you did not activate Argo Books Premium, you can safely ignore this email.</p>

        <p>Regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email(
        $email,
        'Confirm your email - Argo Books Premium',
        $body,
        'premium',
        null,
        null,
        null,
        [],
        'Your Premium licence is already active. Confirm your email so we can reach you.'
    );
}

/**
 * Send account deletion scheduled email
 *
 * @param string $email User's email address
 * @param string $username Username
 * @param string $scheduled_date Scheduled deletion date
 * @return bool Success status
 */
function send_account_deletion_scheduled_email($email, $username, $scheduled_date)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $formatted_date = date('F j, Y \a\t g:i A', strtotime($scheduled_date));
    $site_url = site_url();

    $body = <<<HTML
        <h1>Account deletion scheduled</h1>
        <p>Hello {$username},</p>

        <p>Your Argo Community account has been scheduled for deletion on <strong>{$formatted_date}</strong>.</p>

        <p><strong>Important Information:</strong></p>
        <ul>
            <li>Your account will be permanently deleted in 30 days</li>
            <li>All your posts, comments, and profile data will be removed</li>
            <li>This action can be cancelled by logging into your account before the deletion date</li>
        </ul>

        <div class="button-container">
            <a href="{$site_url}/community/users/login.php" class="button">Cancel Deletion - Login Now</a>
        </div>

        <p>If you did not request this deletion, please log into your account immediately to cancel it.</p>

        <p>If you have any questions, please contact support.</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Account Deletion Scheduled - Argo Community', $body);
}

/**
 * Send account deletion cancelled email (when user logs in after scheduling)
 *
 * @param string $email User's email address
 * @param string $username Username
 * @return bool Success status
 */
function send_account_deletion_cancelled_email($email, $username)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    $body = <<<HTML
        <h1>Account deletion cancelled</h1>
        <p>Hello {$username},</p>

        <p>Your account deletion has been <strong>cancelled</strong> because you logged into your account.</p>

        <p>Your Argo Community account is now <strong>active</strong> and will not be deleted. All your:</p>
        <ul>
            <li>Profile information</li>
            <li>Posts and comments</li>
            <li>Community contributions</li>
        </ul>
        <p>remain intact and accessible.</p>

        <div class="button-container">
            <a href="{$site_url}/community/users/profile.php" class="button">View Your Profile</a>
        </div>

        <p>If you decide to delete your account in the future, you can do so from your profile settings.</p>

        <p>Welcome back!</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Account Deletion Cancelled - Argo Community', $body);
}

/**
 * Send ban notification email to banned user
 *
 * @param string $email User's email address
 * @param string $username Username
 * @param string $ban_reason Reason for ban
 * @param string $ban_duration Duration of ban (5_days, 10_days, 30_days, 100_days, 1_year, permanent)
 * @param string|null $expires_at Expiration date for temporary bans
 * @return bool Success status
 */
function send_ban_notification_email($email, $username, $ban_reason, $ban_duration, $expires_at = null)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $ban_reason = htmlspecialchars($ban_reason, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    // Format duration text
    $duration_text = '';

    switch ($ban_duration) {
        case '5_days':
            $duration_text = '5 days';
            break;
        case '10_days':
            $duration_text = '10 days';
            break;
        case '30_days':
            $duration_text = '30 days';
            break;
        case '100_days':
            $duration_text = '100 days';
            break;
        case '1_year':
            $duration_text = '1 year';
            break;
        case 'permanent':
            $duration_text = 'permanently';
            break;
        default:
            $duration_text = 'an unspecified period';
            break;
    }

    // Format expiration date if available
    $expiration_info = '';
    if ($expires_at) {
        $formatted_date = date('F j, Y \a\t g:i A', strtotime($expires_at));
        $expiration_info = "<p>Your ban will expire on <strong>{$formatted_date}</strong>.</p>";
    }

    $appeal_text = '<p>If you believe this ban was issued in error, you can <a href="' . $site_url . '/contact-us/">contact support</a> to request a review. Please include your username and explain why you believe the ban should be reconsidered.</p>';

    $body = <<<HTML
        <h1>Community ban notification</h1>
        <p>Hello {$username},</p>

        <p>Your account has been banned from posting content on the Argo Community for <strong>{$duration_text}</strong>.</p>

        {$expiration_info}

        <p><strong>Reason:</strong> {$ban_reason}</p>

        <p>During this ban period:</p>
        <ul>
            <li>You can still use the Argo Books application</li>
            <li>You can still view posts and comments on the community page</li>
            <li>You cannot create new posts or comments</li>
            <li>Repeated violations may result in a permanent ban</li>
        </ul>

        {$appeal_text}

        <p>Please review our <a href="{$site_url}/community/guidelines.php">community guidelines</a> to ensure future compliance.</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Community Ban Notification - Argo Books', $body);
}

/**
 * Send unban notification email to unbanned user
 *
 * @param string $email User's email address
 * @param string $username Username
 * @return bool Success status
 */
function send_unban_notification_email($email, $username)
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    $body = <<<HTML
        <h1>Account unbanned</h1>
        <p>Hello {$username},</p>

        <p>Your community ban has been lifted. You can now post and comment again on the Argo Community.</p>

        <p>Please remember to:</p>
        <ul>
            <li>Review and follow our <a href="{$site_url}/community/guidelines.php">community guidelines</a></li>
            <li>Be respectful and helpful to other community members</li>
            <li>Future violations may result in another ban</li>
        </ul>

        <div class="button-container">
            <a href="{$site_url}/community/" class="button">Visit Community</a>
        </div>

        <p>Thank you for being part of the Argo community. We're glad to have you back!</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Account Unbanned - Argo Community', $body);
}
/**
 * Send username reset notification email
 *
 * @param string $email User's email address
 * @param string $old_username Original username
 * @param string $new_username New random username
 * @param string $violation_type Type of violation reported
 * @param string $additional_info Additional information from report
 * @return bool Success status
 */
function send_username_reset_email($email, $old_username, $new_username, $violation_type, $additional_info = '')
{
    $old_username = htmlspecialchars($old_username, ENT_QUOTES, 'UTF-8');
    $new_username = htmlspecialchars($new_username, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    // Format violation type
    $violation_text = ucfirst(str_replace('_', ' ', htmlspecialchars($violation_type, ENT_QUOTES, 'UTF-8')));

    // Additional info section
    $additional_section = '';
    if (!empty($additional_info)) {
        $additional_section = "
        <p><strong>Additional details:</strong> " . htmlspecialchars($additional_info) . "</p>";
    }

    $body = <<<HTML
        <h1>Username reset notification</h1>
        <p>Hello,</p>

        <p>Your username has been changed by our moderation team due to a policy violation.</p>

        <p><strong>Previous username:</strong> <del>{$old_username}</del></p>
        <p><strong>New username:</strong> {$new_username}</p>

        <p><strong>Reason for action:</strong> {$violation_text}</p>

        {$additional_section}

        <p><strong>What you can do:</strong></p>
        <ul>
            <li>You can change your username to something appropriate by visiting your <a href="{$site_url}/community/users/edit_profile.php">profile settings</a></li>
            <li>Your new username must comply with our community guidelines</li>
            <li>All your posts and comments have been updated with the new username</li>
        </ul>

        <p>If you believe this action was taken in error, please <a href="{$site_url}/contact-us/">contact support</a> with your account details.</p>

        <p>Please review our <a href="{$site_url}/community/guidelines.php">community guidelines</a> to ensure future compliance.</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Username Reset - Argo Community', $body);
}

/**
 * Send bio cleared notification email
 *
 * @param string $email User's email address
 * @param string $username Username
 * @param string $violation_type Type of violation reported
 * @param string $additional_info Additional information from report
 * @return bool Success status
 */
function send_bio_cleared_email($email, $username, $violation_type, $additional_info = '')
{
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    // Format violation type
    $violation_text = ucfirst(str_replace('_', ' ', htmlspecialchars($violation_type, ENT_QUOTES, 'UTF-8')));

    // Additional info section
    $additional_section = '';
    if (!empty($additional_info)) {
        $additional_section = "
        <p><strong>Additional details:</strong> " . htmlspecialchars($additional_info) . "</p>";
    }

    $body = <<<HTML
        <h1>Bio cleared notification</h1>
        <p>Hello {$username},</p>

        <p>Your bio has been removed by our moderation team due to a policy violation.</p>

        <p><strong>Reason for action:</strong> {$violation_text}</p>

        {$additional_section}

        <p><strong>What you can do:</strong></p>
        <ul>
            <li>You can add a new bio by visiting your <a href="{$site_url}/community/users/edit_profile.php">profile settings</a></li>
            <li>Your new bio must comply with our community guidelines</li>
            <li>Ensure your bio content is appropriate and respectful</li>
        </ul>

        <p>If you believe this action was taken in error, please <a href="{$site_url}/contact-us/">contact support</a> with your account details.</p>

        <p>Please review our <a href="{$site_url}/community/guidelines.php">community guidelines</a> to ensure future compliance.</p>

        <p>Best regards,<br>The Argo Team</p>
        HTML;

    return send_styled_email($email, 'Bio Cleared - Argo Community', $body);
}

/**
 * Send new report notification email to admins
 *
 * @param string $email Admin email address
 * @param int $report_id Report ID
 * @param string $content_type Type of content reported
 * @param string $violation_type Type of violation
 * @param string $reporter_username Username of reporter
 * @param string $reported_username Username of reported user (or N/A)
 * @return bool Success status
 */
function send_new_report_notification($email, $report_id, $content_type, $violation_type, $reporter_username, $reported_username = 'N/A')
{
    $reporter_username = htmlspecialchars($reporter_username, ENT_QUOTES, 'UTF-8');
    $reported_username = htmlspecialchars($reported_username, ENT_QUOTES, 'UTF-8');
    $site_url = site_url();
    // Format content type and violation type
    $content_type_text = ucfirst(htmlspecialchars($content_type, ENT_QUOTES, 'UTF-8'));
    $violation_text = ucfirst(str_replace('_', ' ', htmlspecialchars($violation_type, ENT_QUOTES, 'UTF-8')));

    $body = <<<HTML
        <h1>New content report</h1>
        <p>A new content report has been submitted and requires your attention.</p>

        <p><strong>Report Details</strong></p>
        <ul>
            <li><strong>Report ID:</strong> #{$report_id}</li>
            <li><strong>Content Type:</strong> {$content_type_text}</li>
            <li><strong>Violation Type:</strong> {$violation_text}</li>
            <li><strong>Reported by:</strong> {$reporter_username}</li>
            <li><strong>Reported user:</strong> {$reported_username}</li>
        </ul>

        <p><strong>Action Required</strong></p>
        <p>Please review this report in the admin panel and take appropriate action.</p>

        <div class="button-container">
            <a href="{$site_url}/admin/reports/" class="button">View Reports</a>
        </div>

        <p>This report is currently in <strong>pending</strong> status and awaits your review.</p>
        HTML;

    return send_styled_email($email, 'New Content Report - Argo Community', $body);
}

/**
 * Send Premium subscription confirmation/receipt email
 *
 * @param string $email User's email address
 * @param string $subscriptionId Subscription ID
 * @param string $billing Billing cycle (monthly/yearly)
 * @param float $amount Payment amount
 * @param string $endDate Next renewal date
 * @param string $transactionId Transaction ID
 * @param string $paymentMethod Payment method used
 * @return bool Success status
 */
function send_premium_subscription_receipt($email, $subscriptionId, $billing, $amount, $endDate, $transactionId, $paymentMethod)
{
    $billingText = $billing === 'yearly' ? 'yearly' : 'monthly';
    $renewalDate = date('F j, Y', strtotime($endDate));
    $paymentDate = date('F j, Y');
    $paymentMethodText = ucfirst($paymentMethod);
    $site_url = site_url();
    $featureList = _premium_feature_list_items('✓ ');

    $body = <<<HTML
        <h1>Payment receipt</h1>
        <p>Thank you for subscribing to Argo Books Premium!</p>

        <div class="payment-box">
            <h3>Payment details</h3>
            <table class="details-table">
                <tr>
                    <td><strong>Date</strong></td>
                    <td>{$paymentDate}</td>
                </tr>
                <tr>
                    <td><strong>Description</strong></td>
                    <td>Premium Subscription ({$billingText})</td>
                </tr>
                <tr>
                    <td><strong>Amount</strong></td>
                    <td>\${$amount} CAD</td>
                </tr>
                <tr>
                    <td><strong>Payment Method</strong></td>
                    <td>{$paymentMethodText}</td>
                </tr>
                <tr>
                    <td><strong>Transaction ID</strong></td>
                    <td class="monospace">{$transactionId}</td>
                </tr>
                <tr>
                    <td><strong>Next Renewal</strong></td>
                    <td>{$renewalDate}</td>
                </tr>
            </table>
        </div>

        <h3>What's included</h3>
        <ul class="feature-list">
            {$featureList}
        </ul>

        <p>You can manage your subscription anytime from your <a href="{$site_url}/community/users/subscription.php">account settings</a>.</p>

        <p>Have a question or some feedback? Just reply to this email or reach me at <a href="mailto:contact@argorobots.com">contact@argorobots.com</a>. I read every message, and I'm always looking for ways to make Argo Books better.</p>

        <div class="receipt-footer">
            <p>License Key: {$subscriptionId}</p>
            <p>Thank you for using Argo Books!</p>
            <p><a href="{$site_url}">argorobots.com</a></p>
        </div>
        HTML;

    return send_styled_email($email, 'Payment Receipt - Argo Books Premium Subscription', $body, 'premium');
}

/**
 * Send Premium subscription cancellation confirmation email
 *
 * @param string $email User's email address
 * @param string $subscriptionId Subscription ID
 * @param string $endDate Date when access ends
 * @return bool Success status
 */
function send_premium_subscription_cancelled_email($email, $subscriptionId, $endDate)
{
    $accessUntil = date('F j, Y', strtotime($endDate));
    $site_url = site_url();

    // Rendered as a list, not a comma-joined sentence: the feature labels are
    // written as short benefit phrases and several contain commas of their own,
    // which turned the old run-on sentence into an unreadable string. This also
    // matches the other Premium emails, which already use this helper.
    $featureList = _premium_feature_list_items();

    $body = <<<HTML
        <h1>Subscription cancelled</h1>
        <p>Your Argo Books Premium subscription has been cancelled as requested.</p>

        <div class="info-box info-box-warning">
            <p><strong>Important:</strong> You will continue to have access to Premium features until <strong>{$accessUntil}</strong>.</p>
        </div>

        <p>After this date, these Premium features will no longer be available:</p>
        <ul>{$featureList}</ul>

        <p>Changed your mind? You can resubscribe anytime from your account settings.</p>

        <div class="button-container">
            <a href="{$site_url}/pricing/premium/" class="button button-premium">Resubscribe</a>
        </div>

        <p>If you have any questions, please <a href="{$site_url}/contact-us/">contact support</a>.</p>

        <div class="receipt-footer">
            <p>License Key: {$subscriptionId}</p>
            <p>Thank you for trying Argo Books Premium!</p>
            <p><a href="{$site_url}">argorobots.com</a></p>
        </div>
        HTML;

    return send_styled_email($email, 'Subscription Cancelled - Argo Books Premium', $body, 'premium');
}

/**
 * Notify the team when a Premium subscription is cancelled. Mirrors the
 * new-customer alert sent from process-subscription.php. Best-effort: callers
 * must wrap this in try/catch so a failed notification never affects the
 * customer-facing cancellation flow.
 *
 * @param string $email          Customer's email address
 * @param string $subscriptionId Subscription (license) ID
 * @param string $endDate        Date Premium access ends
 * @param string $source         Where the cancellation came from (e.g. 'account settings', 'PayPal')
 * @return bool Success status
 */
function send_premium_subscription_cancelled_admin_notification($email, $subscriptionId, $endDate, $source = '')
{
    // Respect the admin's notification preferences (Settings > Notifications).
    if (!admin_notification_enabled('notify_subscription_cancelled')) {
        return true;
    }

    $custEmail   = htmlspecialchars($email);
    $subSafe     = htmlspecialchars($subscriptionId);
    $accessUntil = htmlspecialchars(date('F j, Y', strtotime($endDate)));
    $sourceSafe  = htmlspecialchars($source !== '' ? $source : 'unknown');
    $envLabel    = htmlspecialchars(current_environment());

    $body = <<<HTML
        <h2>A Premium subscription was cancelled</h2>
        <p>A customer just cancelled their Argo Books Premium subscription.</p>
        <ul>
            <li><strong>Customer:</strong> $custEmail</li>
            <li><strong>Subscription ID:</strong> $subSafe</li>
            <li><strong>Premium access ends:</strong> $accessUntil</li>
            <li><strong>Cancelled via:</strong> $sourceSafe</li>
            <li><strong>Environment:</strong> $envLabel</li>
        </ul>
        HTML;

    return send_styled_email(
        admin_notification_email(),
        "[Argo Books] Premium subscription cancelled: $custEmail",
        $body,
        'premium',
        null,
        null,
        $email
    );
}

/**
 * Send Premium subscription reactivated email
 * @param string $email User's email address
 * @param string $subscriptionId Subscription ID
 * @param string $endDate Next billing date
 * @param string $billingCycle Monthly or yearly
 * @return bool Success status
 */
function send_premium_subscription_reactivated_email($email, $subscriptionId, $endDate, $billingCycle = 'monthly')
{
    $nextBillingDate = date('F j, Y', strtotime($endDate));
    $billingLabel = ucfirst($billingCycle);
    $site_url = site_url();
    $featureList = _premium_feature_list_items();

    $body = <<<HTML
        <h1>Your Premium subscription is active again</h1>
        <p>Your Argo Books Premium subscription has been reactivated.</p>

        <div class="info-box info-box-success">
            <p><strong>Your Premium features are now active!</strong> You have full access to all Premium features.</p>
        </div>

        <p>Here's a summary of your subscription:</p>

        <table class="details-table">
            <tr>
                <td class="label">License Key</td>
                <td class="value">{$subscriptionId}</td>
            </tr>
            <tr>
                <td class="label">Billing Cycle</td>
                <td class="value">{$billingLabel}</td>
            </tr>
            <tr>
                <td class="label">Next Billing Date</td>
                <td class="value">{$nextBillingDate}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td><span class="status-badge status-active">Active</span></td>
            </tr>
        </table>

        <p>Features now available:</p>
        <ul class="styled-list">
            {$featureList}
        </ul>

        <div class="button-container">
            <a href="{$site_url}/community/users/subscription.php" class="button button-premium">View Subscription</a>
        </div>

        <p>If you have any questions, please <a href="{$site_url}/contact-us/">contact support</a>.</p>

        <div class="receipt-footer">
            <p>Thank you for continuing with Argo Books Premium!</p>
            <p><a href="{$site_url}">argorobots.com</a></p>
        </div>
        HTML;

    return send_styled_email($email, 'Subscription Reactivated - Argo Books Premium', $body, 'premium');
}

/**
 * Send notification email when a user changes their subscription billing cycle.
 *
 * @param string $email                User's email address
 * @param string $subscriptionId       Subscription ID (license key)
 * @param string $oldCycle             Previous billing cycle ('monthly' or 'yearly')
 * @param string $newCycle             New billing cycle
 * @param float  $proratedCredit       Credit value of the unused old period
 * @param float  $existingCreditUsed   Pre-existing credit_balance consumed today (0 if none)
 * @param float  $immediateCharge      Amount charged to the gateway today (0 if covered by credit)
 * @param string $newEndDate           New end_date (Y-m-d H:i:s)
 * @param float  $creditBalanceAfter   Remaining credit_balance after the switch
 * @param float  $monthlyBase          Monthly base price (for "covers N months" hint)
 * @param float  $refundAmount         PayPal-only: prorated refund issued to the user's PayPal account (0 for Stripe/Square)
 * @param string|null $refundProvider  PayPal-only: name of the refund provider rendered in the breakdown row (e.g. 'PayPal')
 * @return bool  Success status
 */
function send_premium_subscription_cycle_changed_email(
    $email,
    $subscriptionId,
    $oldCycle,
    $newCycle,
    $proratedCredit,
    $existingCreditUsed,
    $immediateCharge,
    $newEndDate,
    $creditBalanceAfter,
    $monthlyBase,
    $refundAmount = 0.0,
    $refundProvider = null
) {
    $site_url        = site_url();
    $oldCycleLabel   = ucfirst($oldCycle);
    $newCycleLabel   = ucfirst($newCycle);
    $nextBillingDate = date('F j, Y', strtotime($newEndDate));
    $isUpgrade       = ($newCycle === 'yearly');

    $subject = $isUpgrade
        ? "You've upgraded to Argo Books Premium Yearly"
        : "Your Argo Books Premium plan changed to Monthly";

    $headline = $isUpgrade
        ? 'Subscription upgraded to Yearly'
        : 'Subscription changed to Monthly';

    $intro = "Your Argo Books Premium subscription has been switched from <strong>{$oldCycleLabel}</strong> to <strong>{$newCycleLabel}</strong>. The full breakdown is below.";

    // Build breakdown table rows conditionally
    $rows = '';
    $rows .= '<tr><td class="label">Prorated credit (unused ' . htmlspecialchars($oldCycleLabel) . ' period)</td>'
          . '<td class="value">$' . number_format($proratedCredit, 2) . ' CAD</td></tr>';

    if ($existingCreditUsed > 0) {
        $rows .= '<tr><td class="label">Existing account credit applied</td>'
              . '<td class="value">$' . number_format($existingCreditUsed, 2) . ' CAD</td></tr>';
    }

    $rows .= '<tr><td class="label">Charged today</td>'
          . '<td class="value">$' . number_format($immediateCharge, 2) . ' CAD</td></tr>';

    if ($creditBalanceAfter > 0) {
        $monthsCovered = ($monthlyBase > 0) ? (int) floor($creditBalanceAfter / $monthlyBase) : 0;
        $monthsHint = ($monthsCovered > 0)
            ? ' (covers about ' . $monthsCovered . ' future renewal' . ($monthsCovered === 1 ? '' : 's') . ')'
            : '';
        $rows .= '<tr><td class="label">Remaining credit balance</td>'
              . '<td class="value">$' . number_format($creditBalanceAfter, 2) . ' CAD' . $monthsHint . '</td></tr>';
    }

    // PayPal-only: prorated refund line. Refund processes asynchronously
    // and shows up in the user's PayPal account within 5–10 business days.
    if ($refundAmount > 0) {
        $providerLabel = $refundProvider ? ' via ' . htmlspecialchars($refundProvider) : '';
        $rows .= '<tr><td class="label">Prorated refund (5–10 business days)</td>'
              . '<td class="value">$' . number_format($refundAmount, 2) . ' CAD' . $providerLabel . '</td></tr>';
    }

    $rows .= '<tr><td class="label">Next billing date</td>'
          . '<td class="value">' . htmlspecialchars($nextBillingDate) . '</td></tr>';
    $rows .= '<tr><td class="label">License Key</td>'
          . '<td class="value">' . htmlspecialchars($subscriptionId) . '</td></tr>';

    $body = <<<HTML
        <h1>{$headline}</h1>
        <p>{$intro}</p>

        <table class="details-table">
            {$rows}
        </table>

        <div class="button-container">
            <a href="{$site_url}/community/users/subscription.php" class="button button-premium">View Subscription</a>
        </div>

        <p>If you have any questions, please <a href="{$site_url}/contact-us/">contact support</a>.</p>

        <div class="receipt-footer">
            <p>Thank you for being part of Argo Books Premium!</p>
            <p><a href="{$site_url}">argorobots.com</a></p>
        </div>
        HTML;

    return send_styled_email($email, $subject, $body, 'premium');
}

/**
 * Send free subscription key notification email
 *
 * @param string $email User's email address
 * @param string $subscriptionKey The subscription key
 * @param int $durationMonths Duration in months (0 = permanent)
 * @param string $note Optional note from admin
 * @return bool Success status
 */
function send_free_subscription_key_email($email, $subscriptionKey, $durationMonths = 1, $note = '')
{
    $durationText = $durationMonths == 0 ? 'permanent' : $durationMonths . ' month' . ($durationMonths > 1 ? 's' : '');
    $site_url = site_url();
    $featureList = _premium_feature_list_items();

    $noteSection = '';
    if (!empty($note)) {
        $noteSection = "
            <div class=\"info-box info-box-gray\">
                <p><strong>Note from Argo:</strong> " . htmlspecialchars($note) . "</p>
            </div>";
    }

    $body = <<<HTML
        <h1>Your free Premium subscription key</h1>
        <p>You've been given a free Argo Books Premium subscription key.</p>

        <div class="license-key">{$subscriptionKey}</div>

        <p class="text-center text-muted">Duration: <strong>{$durationText}</strong></p>

        {$noteSection}

        <h2>How to activate your license</h2>
        <ol>
            <li>Open Argo Books on your computer</li>
            <li>Click the blue upgrade button on the top right</li>
            <li>Enter your license key</li>
            <li>Enjoy unlimited access to all premium features!</li>
        </ol>

        <h2>What's included</h2>
        <ul>
            {$featureList}
        </ul>

        <p>If you have any questions or need assistance, please don't hesitate to <a href="{$site_url}/contact-us/">contact support</a>.</p>
        <p>Thank you for being part of the Argo community!</p>
        HTML;

    return send_styled_email($email, 'Your Free Argo Books Premium Subscription Key', $body, 'premium');
}

/**
 * Send payment failed notification email
 *
 * @param string $email User's email address
 * @param string $subscriptionId Subscription ID
 * @param string $errorMessage Description of the failure
 * @return bool Success status
 */
function send_payment_failed_email($email, $subscriptionId, $errorMessage = '')
{
    $site_url = site_url();
    $errorDetail = '';
    if (!empty($errorMessage)) {
        $safeMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        $errorDetail = "<p><strong>Details:</strong> {$safeMessage}</p>";
    }

    $body = <<<HTML
        <h1>Payment failed</h1>
        <p>We were unable to process your subscription renewal payment.</p>

        <div class="info-box info-box-warning">
            <p><strong>License Key:</strong> {$subscriptionId}</p>
            {$errorDetail}
        </div>

        <p><strong>What to do next:</strong></p>
        <ul>
            <li>Check that your payment method is up to date</li>
            <li>Ensure there are sufficient funds available</li>
            <li>Update your payment information in your account settings</li>
        </ul>

        <p>If the payment continues to fail, your subscription may be suspended. Please update your payment method to avoid interruption of service.</p>

        <div class="button-container">
            <a href="{$site_url}/community/users/subscription.php" class="button button-premium">Update Payment Method</a>
        </div>

        <p>If you need assistance, please <a href="{$site_url}/contact-us/">contact support</a>.</p>
        HTML;

    return send_styled_email($email, 'Payment Failed - Argo Books Premium Subscription', $body, 'premium');
}

/**
 * Send free credit notification email
 *
 * @param string $email User's email address
 * @param float $creditAmount Amount of credit given
 * @param string $note Optional note from admin
 * @param string $subscriptionId Subscription ID
 * @return bool Success status
 */
function send_free_credit_email($email, $creditAmount, $note = '', $subscriptionId = '')
{
    $formattedAmount = number_format($creditAmount, 2);
    $site_url = site_url();
    $noteSection = '';
    if (!empty($note)) {
        $noteSection = "
            <div class=\"info-box info-box-note\">
                <p><strong>Note from Argo:</strong> " . htmlspecialchars($note) . "</p>
            </div>";
    }

    $body = <<<HTML
        <h1>Credit added to your subscription</h1>
        <p>Free credit has been added to your Argo Books Premium subscription.</p>

        <div class="credit-display">
            <p class="label">Credit Added</p>
            <p class="amount">\${$formattedAmount} CAD</p>
        </div>

        {$noteSection}

        <p>This credit will be automatically applied to your future subscription renewals, saving you money on upcoming payments.</p>

        <h3>How credit works</h3>
        <ul class="styled-list">
            <li>Credit is applied automatically at renewal time</li>
            <li>If your credit covers the full renewal amount, you won't be charged</li>
            <li>Any remaining credit carries over to future renewals</li>
            <li>You can view your credit balance in your subscription settings</li>
        </ul>

        <div class="button-container">
            <a href="{$site_url}/community/users/subscription.php" class="button button-premium">View Your Subscription</a>
        </div>

        <p>If you have any questions about your credit or subscription, please <a href="{$site_url}/contact-us/">contact support</a>.</p>

        <div class="receipt-footer">
            <p>Thank you for being an Argo Books Premium subscriber!</p>
            <p><a href="{$site_url}">argorobots.com</a></p>
        </div>
        HTML;

    return send_styled_email($email, "You've Received Free Credit - Argo Books Premium", $body, 'premium');
}

/**
 * Build a short plain-text excerpt from HTML/markdown content for use in
 * notification emails. Strips tags, decodes entities, collapses whitespace,
 * and truncates with an ellipsis.
 */
function _community_excerpt(string $content, int $max = 240): string
{
    $text = trim(html_entity_decode(strip_tags($content), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/u', ' ', $text);
    if (mb_strlen($text) > $max) {
        $text = mb_substr($text, 0, $max - 1) . '…';
    }
    return $text;
}

/**
 * Send "someone replied to your post" email. Returns true on success.
 * Caller is responsible for de-duplicating against mention emails so the
 * post author doesn't receive both for the same comment.
 *
 * Gated by community_digest preference via should_send_marketing_email().
 */
function send_post_reply_email(int $postAuthorId, int $postId, int $commentId, string $commenterName, string $postTitle, string $commentContent): bool
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT email FROM community_users WHERE id = ?');
    $stmt->execute([$postAuthorId]);
    $email = $stmt->fetchColumn();
    if (!$email) {
        return false;
    }

    if (!should_send_marketing_email($email, 'community_digest')) {
        return false;
    }

    $unsubscribe_url = community_user_unsubscribe_url($postAuthorId, 'community_digest');
    $post_url = site_url('/community/view_post.php?id=' . $postId . '#comment-' . $commentId);

    $unsub_safe   = htmlspecialchars($unsubscribe_url ?? '', ENT_QUOTES, 'UTF-8');
    $post_safe    = htmlspecialchars($post_url, ENT_QUOTES, 'UTF-8');
    $title_safe   = htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8');
    $name_safe    = htmlspecialchars($commenterName, ENT_QUOTES, 'UTF-8');
    $excerpt_safe = htmlspecialchars(_community_excerpt($commentContent), ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <h2>{$name_safe} replied to your post</h2>
        <p>Hi,</p>
        <p><strong>{$name_safe}</strong> replied to your post &ldquo;<strong>{$title_safe}</strong>&rdquo; on Argo Community:</p>
        <blockquote style="border-left:3px solid #2563eb;background:#f9fafb;padding:12px 16px;margin:16px 0;color:#374151;">{$excerpt_safe}</blockquote>
        <p style="margin: 24px 0;">
            <a href="{$post_safe}" style="background:#2563eb;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block;">View the discussion</a>
        </p>
        <p>Argo Community</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">
        <p style="font-size:12px;color:#6b7280;">
            You're receiving this because you opted in to community notifications.
            <a href="{$unsub_safe}">Unsubscribe</a>.
        </p>
        HTML;

    $sent = send_styled_email(
        $email,
        $commenterName . ' replied to your post on Argo Community',
        $body,
        'blue',
        'noreply@argorobots.com',
        'Argo Community',
        'support@argorobots.com'
    );

    if ($sent) {
        mark_marketing_sent($email, 'community_digest', $commentId);
    }
    return $sent;
}

/**
 * Send "you were mentioned" email. $commentId may be 0 if the mention is in
 * the post body itself.
 *
 * Gated by community_digest preference via should_send_marketing_email().
 */
function send_mention_email(int $mentionedUserId, int $postId, int $commentId, string $mentionerName, string $postTitle, string $content): bool
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT email FROM community_users WHERE id = ?');
    $stmt->execute([$mentionedUserId]);
    $email = $stmt->fetchColumn();
    if (!$email) {
        return false;
    }

    if (!should_send_marketing_email($email, 'community_digest')) {
        return false;
    }

    $unsubscribe_url = community_user_unsubscribe_url($mentionedUserId, 'community_digest');
    $anchor = $commentId > 0 ? '#comment-' . $commentId : '';
    $post_url = site_url('/community/view_post.php?id=' . $postId . $anchor);

    $unsub_safe   = htmlspecialchars($unsubscribe_url ?? '', ENT_QUOTES, 'UTF-8');
    $post_safe    = htmlspecialchars($post_url, ENT_QUOTES, 'UTF-8');
    $title_safe   = htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8');
    $name_safe    = htmlspecialchars($mentionerName, ENT_QUOTES, 'UTF-8');
    $excerpt_safe = htmlspecialchars(_community_excerpt($content), ENT_QUOTES, 'UTF-8');
    // Build the location prefix so the post-body case ($commentId === 0)
    // doesn't render with an awkward double-space ("in  Title").
    $location_prefix = $commentId > 0 ? 'in a comment on ' : 'in ';

    $body = <<<HTML
        <h2>{$name_safe} mentioned you on Argo Community</h2>
        <p>Hi,</p>
        <p><strong>{$name_safe}</strong> mentioned you {$location_prefix}<a href="{$post_safe}">{$title_safe}</a>:</p>
        <blockquote style="border-left:3px solid #2563eb;background:#f9fafb;padding:12px 16px;margin:16px 0;color:#374151;">{$excerpt_safe}</blockquote>
        <p style="margin: 24px 0;">
            <a href="{$post_safe}" style="background:#2563eb;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;display:inline-block;">Open in Argo Community</a>
        </p>
        <p>Argo Community</p>
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 16px;">
        <p style="font-size:12px;color:#6b7280;">
            You're receiving this because you opted in to community notifications.
            <a href="{$unsub_safe}">Unsubscribe</a>.
        </p>
        HTML;

    $sent = send_styled_email(
        $email,
        $mentionerName . ' mentioned you on Argo Community',
        $body,
        'blue',
        'noreply@argorobots.com',
        'Argo Community',
        'support@argorobots.com'
    );

    if ($sent) {
        mark_marketing_sent($email, 'community_digest', $commentId ?: $postId);
    }
    return $sent;
}
