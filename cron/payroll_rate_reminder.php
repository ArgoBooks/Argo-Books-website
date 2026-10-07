<?php
declare(strict_types=1);

/**
 * payroll_rate_reminder.php
 *
 * Emails the admin that CRA's payroll deduction tables are about to change, so a new rate
 * file can be prepared and uploaded before it takes effect.
 *
 * CRA publishes twice a year, effective January 1 and July 1. The app refuses to calculate a
 * pay run when it holds no edition covering that pay date, and never falls back to the
 * previous one, so a missed deadline does not produce wrong deductions: it stops payroll
 * working at all until a new file ships. That is a loud failure rather than a silent one, but it still lands on every
 * customer at once, on a date fixed by CRA, which is what this reminder exists to prevent.
 *
 * Sent from the 10th to the 20th of December and June, roughly two weeks of lead time. Sends
 * once per window, tracked in cron_runs, so a daily schedule does not produce eleven emails.
 *
 * Always sent. There is no preference to disable it: the whole point is that it arrives in a
 * month when payroll is not on anyone's mind.
 *
 * Schedule: daily.
 *   0 8 * * * /usr/bin/php /home/argorobots/public_html/cron/payroll_rate_reminder.php
 */

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request).
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

$runId = cron_run_start($pdo, 'payroll_rate_reminder');

try {

$month = (int) date('n');
$day   = (int) date('j');
$year  = (int) date('Y');

// December warns about the January edition, June about July's.
if ($month === 12) {
    $edition       = ($year + 1) . '-01';
    $effectiveDate = 'January 1, ' . ($year + 1);
} elseif ($month === 6) {
    $edition       = $year . '-07';
    $effectiveDate = 'July 1, ' . $year;
} else {
    cron_metric_set('in_window', 0);
    cron_run_finish($pdo, $runId, 'ok');
    exit(0);
}

// Two windows for two messages: CRA publishes about a month before an edition takes effect, so by the 10th the numbers exist.
if ($day >= 10 && $day <= 20) {
    $stage = 'prepare';
} elseif ($day >= ($month === 12 ? 29 : 28)) {
    $stage = 'final';
} else {
    cron_metric_set('in_window', 0);
    cron_run_finish($pdo, $runId, 'ok');
    exit(0);
}

cron_metric_set('in_window', 1);

// The whole point of the reminder is to get this file onto the server, so if it is already there the job is done and there is nothing worth saying.
$rateFile   = __DIR__ . '/../resources/downloads/payroll/' . $edition . '.json';
$filePresent = is_file($rateFile);

cron_metric_set('file_present', $filePresent ? 1 : 0);

if ($filePresent) {
    cron_run_finish($pdo, $runId, 'ok', $edition . ' is already uploaded, nothing to remind about');
    exit(0);
}

// One email per stage per window. Checking cron_runs rather than adding a table keeps the state where every other cron already keeps it, and a re-run on the same day cannot send twice.
$already = $pdo->prepare("
    SELECT COUNT(*) FROM cron_runs
    WHERE cron_name = 'payroll_rate_reminder'
      AND status = 'ok'
      AND error_message LIKE ?
      AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$already->execute(['%' . $stage . ' reminder for ' . $edition . '%']);

if ((int) $already->fetchColumn() > 0) {
    cron_metric_set('already_sent', 1);
    cron_run_finish($pdo, $runId, 'ok');
    exit(0);
}

$to = $pdo->query('SELECT notification_email FROM admin_notification_prefs WHERE id = 1')->fetchColumn();
if (!$to) {
    $to = 'contact@argorobots.com';
}

$safeEdition  = htmlspecialchars($edition);
$safeEffective = htmlspecialchars($effectiveDate);

$steps = '
    <p>Full instructions, including where to get the numbers and how to verify them, are in
    <code>docs/PayrollRateUpdates.md</code> in the desktop repository.</p>

    <p>Rough steps:</p>
    <ol>
        <li>Get the new figures from CRA\'s T4127 guide and the CPP, CPP2 and EI rate pages.
            CRA publishes an edition about a month before it takes effect, so they are already
            out.</li>
        <li>Check every derived maximum reproduces from its own rate.</li>
        <li>Build <code>' . $safeEdition . '.json</code> and check it against CRA\'s calculator.</li>
        <li>Upload it to <code>resources/downloads/payroll/</code> on this server. Existing
            installs fetch it from there, so this is the step that reaches customers without a
            release. The app validates it and ignores anything that fails, so check it first:
            a rejected file is silent.</li>
        <li>Also commit it to <code>ArgoBooks.Core/Resources/Payroll/</code>, so a fresh install
            has it offline rather than needing to reach the server before it can run payroll.</li>
    </ol>

    <p>This email stops once <code>' . $safeEdition . '.json</code> is on the server. If you get
    the three day warning, the upload has not happened.</p>
';

if ($stage === 'final') {
    $daysLeft = (int) ceil((strtotime($effectiveDate) - time()) / 86400);

    $subject = 'Payroll rates change in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's')
             . ' and ' . $edition . ' is not uploaded';

    $body = '
        <p><strong>' . $safeEdition . '.json is still not on the server</strong>, and CRA\'s new
        payroll deduction tables take effect on <strong>' . $safeEffective . '</strong>.</p>

        <p>On that date every customer\'s payroll stops. The app refuses to calculate a pay run
        when it holds no edition covering that pay date, rather than falling back to the old
        rates, so nothing wrong gets calculated, but nobody can run payroll at all until the
        file is up.</p>
    ' . $steps;
} else {
    $subject = 'Payroll rates change on ' . $effectiveDate;

    $body = '
        <p>CRA\'s payroll deduction tables change on <strong>' . $safeEffective . '</strong>.
        A new rate file needs to be prepared and uploaded before then.</p>

        <p>Without the new file, any pay run on or after that date will refuse to calculate. Argo
        Books never falls back to the previous edition, so nothing incorrect is produced, but
        payroll stops working for every customer until the new file ships.</p>

        <p><strong>Edition to prepare:</strong> ' . $safeEdition . '</p>
    ' . $steps;
}

$sent = send_styled_email($to, $subject, $body, $stage === 'final' ? 'red' : 'orange');

cron_metric_set('email_sent', $sent ? 1 : 0);

if (!$sent) {
    error_log('payroll_rate_reminder: failed to send ' . $stage . ' reminder for edition ' . $edition);
    cron_run_finish($pdo, $runId, 'error', 'Could not send ' . $stage . ' reminder for ' . $edition);
    exit(1);
}

// This wording IS the dedupe key above, matched case for case. Keep the phrase
// "<stage> reminder for <edition>" intact or the same email sends every day of the window.
cron_run_finish($pdo, $runId, 'ok',
    'Sent ' . $stage . ' reminder for ' . $edition . ', effective ' . $effectiveDate);

} catch (Throwable $e) {
    error_log('payroll_rate_reminder: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
}
