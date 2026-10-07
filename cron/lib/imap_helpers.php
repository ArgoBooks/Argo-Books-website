<?php
/**
 * IMAP helpers for automated reply detection.
 * Used by /cron/reply_checker.php to poll the contact mailbox and detect
 * replies from outreach leads.
 */

if (defined('IMAP_HELPERS_LOADED')) return;
define('IMAP_HELPERS_LOADED', true);

/**
 * Open an IMAP connection using env vars.
 * Throws on failure. Caller must imap_close() the returned resource.
 */
function imap_connect_mailbox()
{
    if (!function_exists('imap_open')) {
        throw new Exception('PHP imap extension is not enabled on this server');
    }

    $host = $_ENV['IMAP_HOST'] ?? '';
    $port = (int) ($_ENV['IMAP_PORT'] ?? 993);
    $username = $_ENV['IMAP_USERNAME'] ?? '';
    $password = $_ENV['IMAP_PASSWORD'] ?? '';
    $mailbox = $_ENV['IMAP_MAILBOX'] ?? 'INBOX';

    if (empty($host) || empty($username) || empty($password)) {
        throw new Exception('IMAP credentials not configured in .env');
    }

    $mailboxString = '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}' . $mailbox;

    $imap = @imap_open($mailboxString, $username, $password, 0, 1);
    if (!$imap) {
        throw new Exception('IMAP connection failed: ' . imap_last_error());
    }

    return $imap;
}

/**
 * Fetch messages received in the last $daysBack days.
 * Returns array of ['uid', 'from', 'sender_email', 'subject', 'date', 'headers_raw'].
 */
function imap_fetch_recent_messages($imap, $daysBack = 7)
{
    $since = date('d-M-Y', strtotime("-$daysBack days"));
    $uids = @imap_search($imap, 'SINCE "' . $since . '"', SE_UID);

    if ($uids === false || empty($uids)) {
        return [];
    }

    $messages = [];
    foreach ($uids as $uid) {
        $headerInfo = @imap_headerinfo($imap, imap_msgno($imap, $uid));
        $rawHeaders = @imap_fetchheader($imap, $uid, FT_UID);

        if (!$headerInfo) continue;

        $from = '';
        $senderEmail = '';
        if (!empty($headerInfo->from) && is_array($headerInfo->from)) {
            $fromObj = $headerInfo->from[0];
            $from = (isset($fromObj->personal) ? $fromObj->personal . ' ' : '') .
                    '<' . ($fromObj->mailbox ?? '') . '@' . ($fromObj->host ?? '') . '>';
            $senderEmail = strtolower(($fromObj->mailbox ?? '') . '@' . ($fromObj->host ?? ''));
        }

        $subject = '';
        if (!empty($headerInfo->subject)) {
            $decoded = imap_mime_header_decode($headerInfo->subject);
            foreach ($decoded as $part) {
                $subject .= $part->text;
            }
        }

        $date = !empty($headerInfo->date) ? date('Y-m-d H:i:s', strtotime($headerInfo->date)) : null;

        $messages[] = [
            'uid' => $uid,
            'from' => $from,
            'sender_email' => $senderEmail,
            'subject' => $subject,
            'date' => $date,
            'headers_raw' => $rawHeaders ?: '',
        ];
    }

    return $messages;
}

/**
 * Check if an email's raw headers and subject indicate an auto-responder.
 * Returns true if the message should be skipped.
 */
function imap_is_autoresponder($rawHeaders, $subject = '')
{
    if (preg_match('/^Auto-Submitted:\s*(auto-replied|auto-generated|auto-notified)/mi', $rawHeaders)) {
        return true;
    }
    if (preg_match('/^X-Autoreply:\s*yes/mi', $rawHeaders)) {
        return true;
    }
    if (preg_match('/^X-Autorespond:/mi', $rawHeaders)) {
        return true;
    }
    if (preg_match('/^Precedence:\s*(bulk|junk|list|auto_reply)/mi', $rawHeaders)) {
        return true;
    }
    if (preg_match('/^(auto(matic)?\s*(reply|response)|out of office|auto:|automatic reply|vacation)/i', trim($subject))) {
        return true;
    }
    return false;
}

/**
 * Check if a message looks like a delivery bounce or complaint notification.
 * Detects Resend/SES bounces and generic MAILER-DAEMON DSNs.
 */
function imap_is_bounce($senderEmail, $subject = '')
{
    $senderEmail = strtolower(trim($senderEmail));

    // Resend uses SES infrastructure; bounces can come from either provider. Exact-domain match (not substring) to avoid false positives from addresses like user@resend.com.example.com.
    $atPos = strrpos($senderEmail, '@');
    $domain = $atPos !== false ? substr($senderEmail, $atPos + 1) : '';
    $bounceDomains = ['resend.com', 'amazonses.com', 'email-smtp.amazonaws.com'];
    foreach ($bounceDomains as $d) {
        if ($domain === $d || str_ends_with($domain, '.' . $d)) {
            return true;
        }
    }
    // Generic postmaster/mailer-daemon bounces
    if (preg_match('/^(mailer-daemon|postmaster|complaints|bounce|bounces)@/i', $senderEmail)) {
        return true;
    }
    // Subject-based detection for bounces that come from other return paths
    if (preg_match('/(delivery status notification|undelivered mail|mail delivery failed|returned mail|failure notice|delivery failure)/i', $subject)) {
        return true;
    }
    return false;
}

/**
 * Fetch the plain-text body of a message by UID.
 * Returns empty string if unavailable.
 */
function imap_fetch_body_text($imap, $uid)
{
    $body = @imap_fetchbody($imap, $uid, 1, FT_UID | FT_PEEK);
    if (empty($body)) {
        $body = @imap_body($imap, imap_msgno($imap, $uid), FT_PEEK);
    }
    return $body ?: '';
}

/**
 * Parse the bounced recipient address from a DSN/bounce body.
 * Looks for Final-Recipient or Original-Recipient headers, then falls back
 * to the first email address mentioned after a failure indicator.
 */
function imap_parse_bounced_address($body)
{
    if (empty($body)) return null;

    // Standard DSN: "Final-Recipient: rfc822; user@example.com"
    if (preg_match('/^(Final-Recipient|Original-Recipient):\s*(?:rfc822;\s*)?([^\s<>]+@[^\s<>]+)/mi', $body, $m)) {
        $addr = trim($m[2], " \t\r\n<>.,;");
        if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return strtolower($addr);
        }
    }

    // Fallback: "failed recipient: user@example.com" or "<user@example.com>"
    if (preg_match('/(?:failed|undeliverable|rejected|could not be delivered)[^@\n]{0,60}<?([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})>?/i', $body, $m)) {
        $addr = strtolower(trim($m[1]));
        if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return $addr;
        }
    }

    return null;
}
