<?php
/**
 * marketing_broadcast.php
 *
 * Drains queued admin-composed broadcasts (marketing_broadcasts) by sending a
 * capped batch of pending recipients per run. Resumable and rate-limit friendly:
 * if a broadcast has more recipients than the per-run cap, the next run picks up
 * where this one left off. Each recipient is re-checked against the send-time gate
 * (should_send_marketing_email) so anyone who unsubscribed after the broadcast was
 * queued is skipped, not emailed.
 *
 * Schedule: every 5 minutes.
 *   *\/5 * * * * /usr/bin/php /home/argorobots/public_html/cron/marketing_broadcast.php
 */

set_time_limit(300);

// CLI/cron only (a web request has REMOTE_ADDR; cron/CLI does not).
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/lib/broadcast_helpers.php';
require_once __DIR__ . '/lib/run_tracker.php';

// Max emails to send across all broadcasts in a single run. Keeps each run well
// under Resend's rate limits and the script's time budget.
const MARKETING_BROADCAST_BATCH = 100;

// ─── Lock file to prevent overlapping runs ───
$lockFile = __DIR__ . '/logs/marketing_broadcast.lock';
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0755, true);
}
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    // Not an error when the previous run is still going, and recording it shows the cron fired.
    $skipNote = $lock === false ? "cannot open $lockFile (check permissions)" : 'Skipped: a previous run still holds the lock.';
    cron_run_finish($pdo, cron_run_start($pdo, 'marketing_broadcast'), $lock === false ? 'error' : 'ok', $skipNote);
    exit(0);
}

$runId = cron_run_start($pdo, 'marketing_broadcast');
$sentThisRun = 0;

try {
    // Pick broadcasts still needing work, oldest first.
    $broadcasts = $pdo->query(
        "SELECT id, subject, html_body, audience, status
         FROM marketing_broadcasts
         WHERE status IN ('queued','sending')
         ORDER BY id ASC"
    )->fetchAll();

    foreach ($broadcasts as $b) {
        if ($sentThisRun >= MARKETING_BROADCAST_BATCH) {
            break;
        }

        $broadcastId = (int) $b['id'];

        // Mark as sending + stamp start time on first touch.
        if ($b['status'] !== 'sending') {
            $upd = $pdo->prepare("UPDATE marketing_broadcasts SET status = 'sending', started_at = COALESCE(started_at, NOW()) WHERE id = ?");
            $upd->execute([$broadcastId]);
        }

        // Pull this broadcast's remaining pending recipients, up to whatever
        // batch budget is left for the run.
        $remaining = MARKETING_BROADCAST_BATCH - $sentThisRun;
        $stmt = $pdo->prepare(
            "SELECT id, email FROM marketing_broadcast_recipients
             WHERE broadcast_id = ? AND status = 'pending'
             ORDER BY id ASC LIMIT $remaining"
        );
        $stmt->execute([$broadcastId]);
        $recipients = $stmt->fetchAll();

        foreach ($recipients as $r) {
            $recipientId = (int) $r['id'];
            $email = $r['email'];

            // Gate re-check: respects suppressions and the confirmed/opted-in state
            // as of NOW, so a since-unsubscribed recipient is skipped.
            if (!should_send_marketing_email($email, $b['audience'])) {
                $mark = $pdo->prepare("UPDATE marketing_broadcast_recipients SET status = 'skipped', error = 'gate blocked at send time' WHERE id = ?");
                $mark->execute([$recipientId]);
                $pdo->prepare("UPDATE marketing_broadcasts SET skipped_count = skipped_count + 1 WHERE id = ?")->execute([$broadcastId]);
                continue;
            }

            $unsubUrl = marketing_unsubscribe_url_for($email, $b['audience']);
            $ok = false;
            try {
                $ok = send_broadcast_email($email, $b['subject'], $b['html_body'], $unsubUrl);
            } catch (Throwable $e) {
                error_log("marketing_broadcast send failed for $email: " . $e->getMessage());
                $ok = false;
            }

            if ($ok) {
                $pdo->prepare("UPDATE marketing_broadcast_recipients SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$recipientId]);
                $pdo->prepare("UPDATE marketing_broadcasts SET sent_count = sent_count + 1 WHERE id = ?")->execute([$broadcastId]);
                mark_marketing_sent($email, $b['audience'], $broadcastId);
                cron_metric_incr('emails_sent');
                $sentThisRun++;
            } else {
                $pdo->prepare("UPDATE marketing_broadcast_recipients SET status = 'failed', error = 'send failed' WHERE id = ?")->execute([$recipientId]);
                $pdo->prepare("UPDATE marketing_broadcasts SET failed_count = failed_count + 1 WHERE id = ?")->execute([$broadcastId]);
                cron_metric_incr('emails_failed');
            }

            if ($sentThisRun >= MARKETING_BROADCAST_BATCH) {
                break;
            }
        }

        // If nothing is left pending for this broadcast, mark it complete.
        $left = $pdo->prepare("SELECT COUNT(*) FROM marketing_broadcast_recipients WHERE broadcast_id = ? AND status = 'pending'");
        $left->execute([$broadcastId]);
        if ((int) $left->fetchColumn() === 0) {
            $pdo->prepare("UPDATE marketing_broadcasts SET status = 'sent', completed_at = NOW() WHERE id = ?")->execute([$broadcastId]);
            cron_metric_incr('broadcasts_completed');
        }
    }

    cron_run_finish($pdo, $runId, 'ok');
} catch (Throwable $e) {
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    error_log('marketing_broadcast cron error: ' . $e->getMessage());
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

if (php_sapi_name() === 'cli') {
}
