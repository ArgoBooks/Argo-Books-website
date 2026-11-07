<?php
/**
 * reply_checker.php
 *
 * Polls the contact mailbox via IMAP, matches incoming emails against
 * outreach leads in 'contacted' status, and auto-promotes matched leads
 * to 'replied'. Admins can then manually classify as interested/not_interested.
 *
 * Schedule: hourly.
 *   0 * * * * /usr/bin/php /home/argorobots/public_html/cron/reply_checker.php
 *
 * Requires:
 *   - PHP imap extension enabled (cPanel → Select PHP Version → Extensions)
 *   - IMAP_* env vars configured in .env
 */

set_time_limit(300);

if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/lib/outreach_helpers.php';
require_once __DIR__ . '/lib/imap_helpers.php';
require_once __DIR__ . '/lib/run_tracker.php';

// ─── Lock file ───

$lockFile = __DIR__ . '/logs/reply_checker.lock';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Not an error: the previous run is still going, and recording it shows the cron fired.
    cron_run_finish($pdo, cron_run_start($pdo, 'reply_checker'), 'ok', 'Skipped: a previous run still holds the lock.');
    exit(0);
}

// ─── Logging ───

function logReply($message, $type = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";

    $logFile = __DIR__ . '/logs/reply_checker_' . date('Y-m-d') . '.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// ─── Main ───

logReply('=== Reply Checker Starting ===');

$runId = cron_run_start($pdo, 'reply_checker');

try {
    global $pdo;

    $imap = imap_connect_mailbox();
    logReply('Connected to IMAP mailbox');

    $messages = imap_fetch_recent_messages($imap, 7);
    logReply('Fetched ' . count($messages) . ' messages from last 7 days');

    $matched = 0;
    $skipped_auto = 0;
    $bounces = 0;
    $no_match = 0;

    $selectStmt = $pdo->prepare("SELECT id, business_name, sent_at FROM outreach_leads WHERE LOWER(email) = ? AND status = 'contacted' AND sent_at IS NOT NULL LIMIT 1");
    $updateStmt = $pdo->prepare("UPDATE outreach_leads SET status = 'replied' WHERE id = ? AND status = 'contacted'");
    $suppressStmt = $pdo->prepare("INSERT IGNORE INTO email_suppressions (email, context, reason, source_id) VALUES (?, 'outreach', ?, ?)");
    $leadByEmailStmt = $pdo->prepare("SELECT id FROM outreach_leads WHERE LOWER(email) = ? ORDER BY sent_at DESC, id DESC LIMIT 1");
    $markNotInterestedStmt = $pdo->prepare("UPDATE outreach_leads SET status = 'not_interested' WHERE id = ? AND status NOT IN ('replied','interested','not_interested','onboarded','email_bounced')");

    foreach ($messages as $msg) {
        if (empty($msg['sender_email'])) continue;

        // Bounce / complaint handling, add bounced address to suppression list
        if (imap_is_bounce($msg['sender_email'], $msg['subject'])) {
            $body = imap_fetch_body_text($imap, $msg['uid']);
            $bouncedAddr = imap_parse_bounced_address($body);

            if ($bouncedAddr) {
                $leadByEmailStmt->execute([$bouncedAddr]);
                $leadRow = $leadByEmailStmt->fetch();
                $leadId = $leadRow['id'] ?? null;

                $suppressStmt->execute([$bouncedAddr, 'Auto-suppressed from bounce: ' . substr($msg['subject'], 0, 100), $leadId]);

                if ($leadId) {
                    $markNotInterestedStmt->execute([$leadId]);
                    log_activity($pdo, $leadId, 'bounce_received',
                        'Auto-suppressed after bounce from ' . $msg['sender_email'] . ' for address ' . $bouncedAddr);
                }
                $bounces++;
                logReply('Bounce: ' . $bouncedAddr . ' (from ' . $msg['sender_email'] . ') added to suppression list');
            } else {
                logReply('Bounce-looking message from ' . $msg['sender_email'] . ' but could not parse bounced address; skipping');
            }
            continue;
        }

        if (imap_is_autoresponder($msg['headers_raw'], $msg['subject'])) {
            $skipped_auto++;
            logReply('Skipped auto-responder from: ' . $msg['sender_email'] . ' | Subject: ' . $msg['subject']);
            continue;
        }

        $selectStmt->execute([$msg['sender_email']]);
        $lead = $selectStmt->fetch();

        if (!$lead) {
            $no_match++;
            continue;
        }

        // Only count as reply if email arrived after we sent the outreach
        if ($msg['date'] && $lead['sent_at'] && strtotime($msg['date']) <= strtotime($lead['sent_at'])) {
            logReply('Email from ' . $msg['sender_email'] . ' predates outreach sent_at, skipping');
            continue;
        }

        $updateStmt->execute([$lead['id']]);
        if ($updateStmt->rowCount() > 0) {
            log_activity($pdo, $lead['id'], 'reply_received',
                'Auto-detected reply from: ' . $msg['sender_email'] . ' | Subject: ' . $msg['subject']);
            $matched++;
            logReply('Matched reply: lead #' . $lead['id'] . ' (' . $lead['business_name'] . ') from ' . $msg['sender_email']);
        }
    }

    imap_close($imap);

    logReply("Run complete. Matched: $matched | Bounces: $bounces | Auto-responders skipped: $skipped_auto | No match: $no_match");
    logReply('=== Reply Checker Complete ===');
    cron_metric_incr('replies_matched', $matched);
    cron_metric_incr('emails_scanned', count($messages));
    cron_run_finish($pdo, $runId, 'ok');

} catch (Exception $e) {
    logReply('Fatal error: ' . $e->getMessage(), 'ERROR');
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
} finally {
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}
