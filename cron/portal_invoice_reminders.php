<?php
declare(strict_types=1);

/**
 * portal_invoice_reminders.php
 *
 * Sends automatic overdue-invoice reminders to a merchant's customers on a
 * fixed 3/7/14-day cadence, then stops. Opt-in per company via
 * portal_companies.reminders_enabled (set from Argo Books through
 * PUT /api/portal/preferences).
 *
 * Runs server-side so reminders keep going out while Argo Books is closed,
 * which is the whole point of the feature.
 *
 * Schedule: daily at 9:00 AM.
 *   0 9 * * * /usr/bin/php /home/argorobots/public_html/cron/portal_invoice_reminders.php
 *
 * Flags:
 *   --dry-run   Log what would be sent, send nothing, write no reminder rows.
 */

set_time_limit(300);

// CLI/cron only (a web request has REMOTE_ADDR; cron/CLI does not). Without
// this, anyone could trigger a mass customer email run over HTTP.
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/portal/portal-helper.php';
require_once __DIR__ . '/lib/run_tracker.php';
require_once __DIR__ . '/lib/invoice_reminder_helpers.php';

global $pdo;

// Max reminders across all companies in one run, to stay inside Resend's
// rate limits and the script's time budget.
const PORTAL_REMINDER_RUN_CAP = 200;

// No single merchant may consume the whole run budget.
const PORTAL_REMINDER_COMPANY_CAP = 50;

$dryRun = in_array('--dry-run', $argv ?? [], true);

/**
 * Records a failure that happens before the run proper can start, then stops.
 *
 * Writes a failed run to cron_runs so it appears on the admin Cron Activity page.
 * That page is the only place these are actually read: cron mail is not configured,
 * and the daily log cannot be written because it lives in the directory that failed.
 */
function reminders_abort(PDO $pdo, string $message): never
{
    error_log('portal_invoice_reminders: ' . $message);
    try {
        $runId = cron_run_start($pdo, 'portal_invoice_reminders');
        cron_run_finish($pdo, $runId, 'error', $message);
    } catch (Throwable $e) {
        error_log('portal_invoice_reminders: could not record the failure: ' . $e->getMessage());
    }
    exit(1);
}

// --- Lock file to prevent overlapping runs ---
// A lock that cannot be created is recorded, since exiting quietly looks like no run.
$lockDir = __DIR__ . '/logs';
if (!is_dir($lockDir) && !@mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    reminders_abort($pdo, "cannot create $lockDir (check permissions)");
}

$lockFile = $lockDir . '/portal_invoice_reminders.lock';
$lock = fopen($lockFile, 'c');
if ($lock === false) {
    reminders_abort($pdo, "cannot open $lockFile (check permissions)");
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Another run holds the lock. Quiet is correct here.
    exit(0);
}

$logFile = __DIR__ . '/logs/portal_invoice_reminders_' . date('Y-m-d') . '.log';
$logLine = static function (string $msg) use ($logFile): void {
    @file_put_contents($logFile, '[' . date('H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
};

$runId = cron_run_start($pdo, 'portal_invoice_reminders');
$sentThisRun = 0;
$skipped = 0;
$failed = 0;
$scanned = 0;
$perCompany = [];

try {
    // Which invoices qualify, and why each condition is there, is in
    // portal_reminder_candidates().
    $candidates = portal_reminder_candidates($pdo, current_environment());
    $scanned = count($candidates);

    $portalBaseUrl = env('SITE_URL', 'https://argorobots.com');

    foreach ($candidates as $inv) {
        if ($sentThisRun >= PORTAL_REMINDER_RUN_CAP) {
            $logLine("Run cap reached (" . PORTAL_REMINDER_RUN_CAP . "); remaining candidates deferred to tomorrow.");
            break;
        }

        $companyId = (int)$inv['company_id'];
        if (($perCompany[$companyId] ?? 0) >= PORTAL_REMINDER_COMPANY_CAP) {
            continue;
        }

        $invoiceRowId = (int)$inv['id'];
        $daysOverdue = (int)$inv['days_overdue'];

        // The highest stage reached, unless it or a later one has already gone
        // out, or the last reminder was too recent.
        $stage = portal_reminder_stage_due($pdo, $invoiceRowId, $daysOverdue);
        if ($stage === null) {
            continue;
        }

        if ($dryRun) {
            $logLine("DRY RUN would send stage {$stage} for invoice {$inv['invoice_id']} (company {$companyId}, {$daysOverdue}d overdue) to {$inv['customer_email']}");
            $sentThisRun++;
            $perCompany[$companyId] = ($perCompany[$companyId] ?? 0) + 1;
            continue;
        }

        // Claim the stage BEFORE sending. Null means another run, or an earlier
        // day, already owns this touch.
        $reminderId = portal_reminder_claim($pdo, $inv, $stage);
        if ($reminderId === null) {
            continue;
        }

        // Re-read state immediately before sending, in case the customer has
        // paid or the merchant has cancelled since the list was read.
        [$haltReason, $now] = portal_reminder_halt_reason($pdo, $invoiceRowId);

        if ($haltReason !== null) {
            $pdo->prepare('UPDATE portal_invoice_reminders SET status = "skipped", halt_reason = ? WHERE id = ?')
                ->execute([$haltReason, $reminderId]);
            $skipped++;
            cron_metric_incr('reminders_skipped');
            $logLine("Skipped invoice {$inv['invoice_id']} stage {$stage}: {$haltReason}");
            continue;
        }

        // Only give the customer a reply-to when the merchant's address is
        // verified, matching the bar every other owner-directed portal mail uses.
        $replyTo = !empty($inv['email_verified_at']) ? (string)($inv['owner_email'] ?? '') : '';

        $result = ['success' => false, 'message' => 'not attempted'];
        try {
            $result = send_invoice_reminder([
                'customerEmail' => $now['customer_email'],
                'customerName' => $inv['customer_name'],
                'companyName' => $inv['company_name'],
                'invoiceId' => $inv['invoice_id'],
                'balanceDue' => $now['balance_due'],
                'currency' => $inv['currency'],
                'dueDate' => $inv['due_date'],
                'invoiceUrl' => rtrim($portalBaseUrl, '/') . '/invoice/' . $inv['invoice_token'],
                'passProcessingFee' => !empty($inv['pass_processing_fee']),
                'stage' => $stage,
                'daysOverdue' => $daysOverdue,
                'replyToEmail' => $replyTo,
            ]);
        } catch (Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if (!empty($result['success'])) {
            $pdo->prepare('UPDATE portal_invoice_reminders SET status = "sent", sent_at = NOW() WHERE id = ?')
                ->execute([$reminderId]);
            $sentThisRun++;
            $perCompany[$companyId] = ($perCompany[$companyId] ?? 0) + 1;
            cron_metric_incr('reminders_sent');
            cron_metric_incr('stage' . $stage . '_sent');
            $logLine("Sent stage {$stage} for invoice {$inv['invoice_id']} to {$now['customer_email']} ({$daysOverdue}d overdue)");
        } else {
            // Deliberately not retried. The next stage still fires on schedule, so a transient SMTP failure costs one touch instead of risking a duplicate.
            $pdo->prepare('UPDATE portal_invoice_reminders SET status = "failed", error_message = ? WHERE id = ?')
                ->execute([substr((string)($result['message'] ?? 'unknown'), 0, 255), $reminderId]);
            $failed++;
            cron_metric_incr('reminders_failed');
            $logLine("FAILED stage {$stage} for invoice {$inv['invoice_id']}: " . ($result['message'] ?? 'unknown'));
        }
    }

    cron_metric_incr('invoices_scanned', $scanned);

    $summary = ($dryRun ? '[DRY RUN] ' : '')
        . "Scanned: $scanned, Sent: $sentThisRun, Skipped: $skipped, Failed: $failed";
    $logLine($summary);

    cron_run_finish($pdo, $runId, 'ok', $summary);
} catch (Throwable $e) {
    error_log('portal_invoice_reminders cron error: ' . $e->getMessage());
    $logLine('ERROR: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    throw $e;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
