<?php
/**
 * outreach_pipeline.php
 *
 * Runs the outreach pipeline for the leads already in the list: generates AI
 * email drafts for leads without one, auto-approves drafts, sends approved
 * emails up to the daily limit, and drafts and sends the follow-ups. It does not
 * find leads. They are added by hand or by CSV on the admin Outreach page. Full
 * detail in read-me/Cron-jobs.md.
 *
 * Schedule: daily at 8:00 AM.
 *   0 8 * * * /usr/bin/php /home/argorobots/public_html/cron/outreach_pipeline.php
 *
 * Flags:
 *   --draft-only      Only run draft generation
 *   --send-only       Only run send (same as outreach_email.php)
 *   --dry-run         Log what would happen without doing it
 */

set_time_limit(600); // 10 minutes max for full pipeline

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request)
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/lib/outreach_helpers.php';
require_once __DIR__ . '/lib/run_tracker.php';

// ─── Lock file to prevent overlapping runs ───

$lockFile = __DIR__ . '/logs/outreach_pipeline.lock';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

// ─── Configuration ───

define('DAILY_SEND_LIMIT', (int) ($_ENV['OUTREACH_DAILY_SEND_LIMIT'] ?? 10));
// Follow-ups have their own daily cap, separate from first-touch sends.
// With the multi-touch sequence (touches 2 through N), this cap applies
// across ALL touch positions combined. Default 75; raise via env var
// (OUTREACH_DAILY_FOLLOWUP_LIMIT) once domain reputation supports more.
define('DAILY_FOLLOWUP_LIMIT', (int) ($_ENV['OUTREACH_DAILY_FOLLOWUP_LIMIT'] ?? 75));
define('DAILY_DRAFT_LIMIT', (int) ($_ENV['OUTREACH_DAILY_DRAFT_LIMIT'] ?? 100));

// Parse CLI flags ($argv is null under CGI, fall back to empty array)
$args = array_slice($argv ?? [], 1);
$draftOnly = in_array('--draft-only', $args);
$sendOnly = in_array('--send-only', $args);
$dryRun = in_array('--dry-run', $args);
$runAll = !$draftOnly && !$sendOnly;

// ─── Logging ───

function logPipeline($message, $type = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";

    $logFile = __DIR__ . '/logs/outreach_pipeline_' . date('Y-m-d') . '.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// log_activity() is provided by cron/lib/outreach_helpers.php

function getState($pdo, $key, $default = null)
{
    $stmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['state_value'] : $default;
}

function setState($pdo, $key, $value)
{
    $stmt = $pdo->prepare("INSERT INTO outreach_pipeline_state (state_key, state_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)");
    $stmt->execute([$key, $value]);
}

// ══════════════════════════════════════════════════════════════
//  MAIN PIPELINE
// ══════════════════════════════════════════════════════════════

logPipeline('=== Outreach Pipeline Starting ===');
if ($dryRun) logPipeline('DRY RUN MODE: no changes will be made');

global $pdo;
$cronRunId = $dryRun ? 0 : cron_run_start($pdo, 'outreach_pipeline');

try {
    // ─── Master kill-switch: admin can disable the entire outreach system
    // from the Settings tab. When off, the server cron still fires but does
    // nothing until re-enabled.
    $outreachEnabled = getState($pdo, 'outreach_enabled', '1');
    if ($outreachEnabled !== '1') {
        logPipeline('Outreach is DISABLED via admin Settings (outreach_enabled != "1"). Pipeline exiting without running any steps.');
        // Finish cleanly: this is a normal no-op, not a crash. Without this the
        // run row would stay 'running' and the admin Crons pill would read
        // "Running" forever while outreach is toggled off.
        cron_run_finish($pdo, $cronRunId, 'ok');
        return;
    }

    // ─── STEP 3: Generate AI Drafts ───
    if ($runAll || $draftOnly) {
        stepGenerateDrafts($pdo, $dryRun);
    }

    // ─── STEP 4: Auto-Approve ───
    // Runtime-toggled via outreach_pipeline_state.auto_send_mode
    // ('auto' | 'review'). Defaults to 'auto' on a DB that hasn't had the
    // toggle set yet. Admin can flip to review-mode in the Settings tab.
    $autoSendMode = getState($pdo, 'auto_send_mode', 'auto');
    if (($runAll || $draftOnly) && $autoSendMode === 'auto') {
        stepAutoApprove($pdo, $dryRun);
    } elseif (($runAll || $draftOnly) && $autoSendMode === 'review') {
        logPipeline('Send mode: review. Drafts generated but auto-approve skipped.');
    }

    // ─── STEP 5: Send Emails ───
    if ($runAll || $sendOnly) {
        stepSendEmails($pdo, $dryRun);
    }

    // ─── STEP 5.5: Halt Follow-ups (replies / unsubscribes / bounces) ───
    // Also runs in --draft-only so we don't waste Gemini drafts on leads who
    // have already replied/unsubscribed/bounced since the last run.
    if ($runAll || $sendOnly || $draftOnly) {
        stepHaltFollowups($pdo, $dryRun);
    }

    // ─── STEP 5.6: Draft Follow-ups (Gemini, lazy ~1 day before send) ───
    // Always runs regardless of send mode. Drafting itself is harmless.
    // The review-vs-auto gating happens INSIDE stepDraftFollowups (which
    // advances drafted → approved only when auto_send_mode = 'auto').
    if ($runAll || $sendOnly || $draftOnly) {
        stepDraftFollowups($pdo, $dryRun);
    }

    // ─── STEP 6: Send Follow-ups ───
    // Step 6 always runs; review-vs-auto gating is implicit in row statuses.
    // (Review mode: rows stay 'drafted' awaiting admin approval, not picked
    // up by the WHERE status='approved' query.)
    if ($runAll || $sendOnly) {
        stepSendFollowups($pdo, $dryRun);
    }

    logPipeline('=== Outreach Pipeline Complete ===');
    cron_run_finish($pdo, $cronRunId, 'ok');

} catch (Throwable $e) {
    // Throwable, not Exception: also catch PHP Errors (TypeError, OOM-adjacent
    // fatals) so a non-Exception failure still records 'error' instead of
    // leaving the run row orphaned as 'running'.
    logPipeline("Pipeline fatal error: " . $e->getMessage(), 'ERROR');
    cron_run_finish($pdo, $cronRunId, 'error', $e->getMessage());
    exit(1);
} finally {
    // Release lock file
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

// ══════════════════════════════════════════════════════════════
//  STEP IMPLEMENTATIONS
// ══════════════════════════════════════════════════════════════

function stepGenerateDrafts($pdo, $dryRun)
{
    logPipeline('--- Step 3: Generate AI Drafts ---');

    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($geminiKey)) {
        logPipeline('Gemini API key not configured. Skipping draft generation.', 'WARN');
        return;
    }

    // Find leads that have an email but no draft yet
    $stmt = $pdo->prepare("
        SELECT *
        FROM outreach_leads
        WHERE email IS NOT NULL AND email != ''
          AND (draft_subject IS NULL OR draft_subject = '')
          AND sent_at IS NULL
          AND status NOT IN ('contacted', 'replied', 'interested', 'not_interested', 'onboarded', 'email_bounced', 'disqualified')
        ORDER BY date_added ASC
        LIMIT ?
    ");
    $stmt->execute([DAILY_SEND_LIMIT]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No leads need drafts. Skipping.');
        return;
    }

    logPipeline("Found " . count($leads) . " leads needing AI drafts");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would generate draft for: {$lead['business_name']} ({$lead['email']})");
        }
        return;
    }

    $success = 0;
    $failed = 0;
    $disqualified = 0;

    foreach ($leads as $lead) {
        try {
            $result = generate_draft_for_lead($pdo, $lead);

            if (isset($result['error'])) {
                logPipeline("Draft generation failed for {$lead['business_name']}: {$result['error']}", 'ERROR');
                $failed++;
            } elseif (!empty($result['disqualified'])) {
                // AI size gate rejected this lead. log_activity + status update
                // were already done inside disqualify_lead(); just surface it
                // in the pipeline log for the daily summary.
                logPipeline("Disqualified by AI size gate: {$lead['business_name']} ({$result['reason']}: {$result['detail']})");
                $disqualified++;
            } else {
                log_activity($pdo, $lead['id'], 'draft_generated', 'AI draft auto-generated by pipeline');
                logPipeline("Draft generated for {$lead['business_name']}");
                $success++;
            }

            // Rate limit: pause between Gemini calls
            sleep(1);

        } catch (Exception $e) {
            logPipeline("Draft error for {$lead['business_name']}: " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }

    logPipeline("Drafts generated: $success, disqualified: $disqualified, failed: $failed");
    cron_metric_incr('drafts_generated', $success);
    if ($disqualified > 0) {
        cron_metric_incr('leads_disqualified', $disqualified);
    }
}

function stepAutoApprove($pdo, $dryRun)
{
    logPipeline('--- Step 4: Auto-Approve Drafts ---');

    // Approve all leads that have a draft but haven't been approved yet.
    // Excludes disqualified leads as a belt-and-suspenders guard: disqualify_lead()
    // already clears approval_status, but if a draft was created before the
    // disqualify (e.g. via the AI gate post-draft retroactive backfill) we
    // don't want to re-approve it here.
    $stmt = $pdo->prepare("
        SELECT id, business_name
        FROM outreach_leads
        WHERE draft_subject IS NOT NULL AND draft_subject != ''
          AND draft_body IS NOT NULL AND draft_body != ''
          AND email IS NOT NULL AND email != ''
          AND approval_status != 'approved'
          AND sent_at IS NULL
          AND status != 'disqualified'
    ");
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No drafts to approve.');
        return;
    }

    logPipeline("Found " . count($leads) . " drafts to auto-approve");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would auto-approve: {$lead['business_name']}");
        }
        return;
    }

    $count = 0;
    foreach ($leads as $lead) {
        $stmt = $pdo->prepare("UPDATE outreach_leads SET approval_status = 'approved' WHERE id = ?");
        $stmt->execute([$lead['id']]);
        log_activity($pdo, $lead['id'], 'auto_approved', 'Draft auto-approved by pipeline');
        $count++;
    }

    logPipeline("Auto-approved $count drafts");
}

function stepSendEmails($pdo, $dryRun)
{
    logPipeline('--- Step 5: Send Emails ---');

    // Check how many already sent today
    $stmt = $pdo->prepare("SELECT COUNT(*) as sent_today FROM outreach_leads WHERE DATE(sent_at) = CURDATE()");
    $stmt->execute();
    $sentToday = (int) $stmt->fetch(PDO::FETCH_ASSOC)['sent_today'];

    $remaining = DAILY_SEND_LIMIT - $sentToday;

    if ($remaining <= 0) {
        logPipeline("Daily limit of " . DAILY_SEND_LIMIT . " emails already reached ($sentToday sent today). Skipping.");
        return;
    }

    logPipeline("Already sent $sentToday today. Will send up to $remaining more.");

    // Find approved leads with drafts that haven't been sent.
    // status != 'disqualified' is a safety net: disqualify_lead() resets
    // approval_status, but explicitly excluding here means a disqualified row
    // can never appear in the send queue regardless of approval_status state.
    $stmt = $pdo->prepare("
        SELECT id, business_name, email, draft_subject, draft_body, unsubscribe_token
        FROM outreach_leads
        WHERE approval_status = 'approved'
          AND draft_subject IS NOT NULL AND draft_subject != ''
          AND draft_body IS NOT NULL AND draft_body != ''
          AND email IS NOT NULL AND email != ''
          AND sent_at IS NULL
          AND status != 'disqualified'
        ORDER BY date_added ASC
        LIMIT ?
    ");
    $stmt->execute([$remaining]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No approved leads ready to send.');
        return;
    }

    logPipeline("Found " . count($leads) . " approved leads to send");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would send email to: {$lead['business_name']} <{$lead['email']}>");
        }
        return;
    }

    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;

    foreach ($leads as $lead) {
        $id = $lead['id'];
        $businessName = $lead['business_name'];
        $email = $lead['email'];

        try {
            $reason = null;
            if (send_outreach_lead($pdo, $lead, $reason)) {
                log_activity($pdo, $id, 'email_sent', 'Outreach email sent automatically via pipeline to: ' . $email);
                logPipeline("Sent email to $businessName <$email> (lead #$id)");

                // Schedule the multi-touch follow-up sequence (if any configured).
                $scheduled = schedule_followups_for_lead($pdo, $id);
                if ($scheduled > 0) {
                    logPipeline("Scheduled $scheduled follow-up(s) for lead #$id");
                }

                $successCount++;
            } elseif ($reason === 'already_sent' || $reason === 'suppressed' || $reason === 'customer') {
                // Skip outcomes are already logged inside send_outreach_lead;
                // don't double-log as failures and don't count toward fail tally.
                logPipeline("Skipped $businessName <$email> (lead #$id): $reason");
                $skipCount++;
            } else {
                log_activity($pdo, $id, 'email_failed', 'Pipeline email send failed for: ' . $email . ' (' . ($reason ?? 'unknown') . ')');
                logPipeline("Failed to send email to $businessName <$email> (lead #$id): " . ($reason ?? 'unknown'), 'ERROR');
                $failCount++;
            }

            // Brief pause between sends
            if ($successCount + $failCount + $skipCount < count($leads)) {
                sleep(2);
            }

        } catch (Exception $e) {
            log_activity($pdo, $id, 'email_failed', 'Pipeline email error: ' . $e->getMessage());
            logPipeline("Error sending to $businessName <$email> (lead #$id): " . $e->getMessage(), 'ERROR');
            $failCount++;
        }
    }

    logPipeline("Send complete. Sent: $successCount, Failed: $failCount, Skipped: $skipCount");
    cron_metric_incr('first_emails_sent', $successCount);
}

function stepSendFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 6: Send Follow-ups ---');

    // Count how many follow-up sends have happened today (across all touch positions)
    $sentToday = (int) $pdo->query(
        "SELECT COUNT(*) FROM outreach_followups WHERE DATE(sent_at) = CURDATE()"
    )->fetchColumn();
    $remaining = DAILY_FOLLOWUP_LIMIT - $sentToday;

    if ($remaining <= 0) {
        logPipeline("Follow-up daily limit of " . DAILY_FOLLOWUP_LIMIT . " reached ($sentToday sent today). Skipping.");
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM outreach_followups
         WHERE status = 'approved'
           AND scheduled_for <= NOW()
         ORDER BY scheduled_for ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $remaining, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        logPipeline('No follow-ups ready to send.');
        return;
    }

    logPipeline('Found ' . count($rows) . ' follow-up(s) ready to send (cap remaining: ' . $remaining . ').');

    if ($dryRun) {
        foreach ($rows as $r) {
            logPipeline("[DRY RUN] Would send followup #{$r['id']} (lead #{$r['lead_id']}, touch {$r['touch_number']})");
        }
        return;
    }

    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;

    foreach ($rows as $row) {
        try {
            $reason = null;
            if (send_followup_row($pdo, $row, $reason)) {
                logPipeline("Sent followup #{$row['id']} (lead #{$row['lead_id']}, touch {$row['touch_number']})");
                $successCount++;
            } elseif ($reason === 'not_eligible') {
                $skipCount++;
            } else {
                logPipeline("Failed to send followup #{$row['id']}: " . ($reason ?? 'unknown'), 'WARN');
                $failCount++;
            }

            if ($successCount + $failCount + $skipCount < count($rows)) {
                sleep(2);
            }
        } catch (Throwable $e) {
            logPipeline("Error sending followup #{$row['id']}: " . $e->getMessage(), 'ERROR');
            $failCount++;
        }
    }

    logPipeline("Follow-ups complete. Sent: $successCount, Failed: $failCount, Skipped: $skipCount");
    cron_metric_incr('followups_sent', $successCount);
}

function stepHaltFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 5.5: Halt Follow-ups ---');

    if ($dryRun) {
        // Count how many WOULD be halted, but don't write
        $countStmt = $pdo->query(
            "SELECT COUNT(*) FROM outreach_followups f
             JOIN outreach_leads l ON l.id = f.lead_id
             WHERE f.status IN ('scheduled','drafted','approved')
               AND (
                   l.status IN ('replied','interested','not_interested','onboarded','email_bounced')
                   OR EXISTS (SELECT 1 FROM email_suppressions s WHERE LOWER(s.email) = LOWER(l.email) AND s.context = 'outreach')
               )"
        );
        $count = (int) $countStmt->fetchColumn();
        logPipeline("[DRY RUN] Would halt $count follow-up row(s).");
        return;
    }

    $counts = halt_followups_bulk($pdo);
    $total = array_sum($counts);
    if ($total === 0) {
        logPipeline('No follow-ups halted.');
    } else {
        logPipeline("Halted $total follow-up(s): " . json_encode($counts));
    }
}

function stepDraftFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 5.6: Draft Follow-ups ---');

    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($geminiKey)) {
        logPipeline('Gemini API key not configured. Skipping follow-up draft generation.', 'WARN');
        return;
    }

    // Find rows whose draft window has opened (scheduled_for within next 24h). The marketing
    // agent writes the follow-ups for the leads it added, so those are left for it.
    $stmt = $pdo->prepare(
        "SELECT f.* FROM outreach_followups f
           JOIN outreach_leads l ON l.id = f.lead_id
         WHERE f.status = 'scheduled'
           AND f.scheduled_for <= DATE_ADD(NOW(), INTERVAL 1 DAY)
           AND l.source != 'agent'
         ORDER BY f.scheduled_for ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, DAILY_DRAFT_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        logPipeline('No follow-ups need drafts. Skipping.');
        return;
    }

    logPipeline('Found ' . count($rows) . ' follow-up(s) needing AI drafts (cap: ' . DAILY_DRAFT_LIMIT . ').');

    if ($dryRun) {
        foreach ($rows as $r) {
            logPipeline("[DRY RUN] Would draft followup #{$r['id']} (lead #{$r['lead_id']}, touch {$r['touch_number']})");
        }
        return;
    }

    $autoSendMode = getState($pdo, 'auto_send_mode', 'auto');

    $success = 0;
    $failed = 0;
    foreach ($rows as $row) {
        try {
            $ok = draft_followup_via_gemini($pdo, $row);
            if ($ok) {
                $success++;
                // In auto-send mode, advance drafted → approved immediately
                if ($autoSendMode === 'auto') {
                    $pdo->prepare("UPDATE outreach_followups SET status = 'approved' WHERE id = ? AND status = 'drafted'")
                        ->execute([(int) $row['id']]);
                }
            } else {
                $failed++;
            }
            sleep(1); // Rate-limit Gemini calls
        } catch (Throwable $e) {
            logPipeline("Draft followup error (followup #{$row['id']}): " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }

    logPipeline("Follow-up drafts: $success generated, $failed failed.");
    cron_metric_incr('followup_drafts_generated', $success);
}
