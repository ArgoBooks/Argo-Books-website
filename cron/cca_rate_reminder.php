<?php
declare(strict_types=1);

/**
 * Emails the admin each December and June to check the CRA asset rates the desktop app reads from resources/downloads/assets/cca.json.
 *
 * Schedule: daily.
 *   5 8 * * * /usr/bin/php /home/argorobots/public_html/cron/cca_rate_reminder.php
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

$runId = cron_run_start($pdo, 'cca_rate_reminder');

try {

$month = (int) date('n');
$day   = (int) date('j');

if (($month !== 12 && $month !== 6) || $day < 10 || $day > 20) {
    cron_metric_set('in_window', 0);
    cron_run_finish($pdo, $runId, 'ok');
    exit(0);
}

cron_metric_set('in_window', 1);

$window     = date('Y-m');
$monthStart = date('Y-m-01');

// The reminder exists to get the file checked, so a reviewedOn date inside this month means the job is done.
$rateFile    = __DIR__ . '/../resources/downloads/assets/cca.json';
$filePresent = is_file($rateFile);
$reviewedOn  = null;

if ($filePresent) {
    $data = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($data) && isset($data['reviewedOn']) && is_string($data['reviewedOn'])) {
        $reviewedOn = substr($data['reviewedOn'], 0, 10);
    }
}

cron_metric_set('file_present', $filePresent ? 1 : 0);

if ($reviewedOn !== null && $reviewedOn >= $monthStart) {
    cron_metric_set('reviewed', 1);
    cron_run_finish($pdo, $runId, 'ok', 'cca.json was reviewed on ' . $reviewedOn . ', nothing to remind about');
    exit(0);
}

cron_metric_set('reviewed', 0);

// One email per window, found by its summary line in cron_runs, so a daily schedule does not send eleven.
$already = $pdo->prepare("
    SELECT COUNT(*) FROM cron_runs
    WHERE cron_name = 'cca_rate_reminder'
      AND status = 'ok'
      AND error_message LIKE ?
      AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$already->execute(['%reminder for ' . $window . '%']);

if ((int) $already->fetchColumn() > 0) {
    cron_metric_set('already_sent', 1);
    cron_run_finish($pdo, $runId, 'ok');
    exit(0);
}

$to = $pdo->query('SELECT notification_email FROM admin_notification_prefs WHERE id = 1')->fetchColumn();
if (!$to) {
    $to = 'contact@argorobots.com';
}

$status = $filePresent
    ? '<p>The file on the server was last reviewed on <strong>' . htmlspecialchars($reviewedOn ?? 'an unknown date') . '</strong>.</p>'
    : '<p><strong>There is no cca.json on the server yet</strong>, so every install is using the copy built into it.</p>';

$subject = 'Check the CRA asset rates';
$body = '
    <p>Time for the twice-yearly check of CRA\'s Capital Cost Allowance rates. Argo Books fills
    them in for Canadian companies\' assets, so a rate or first-year rule a budget changed would
    otherwise give customers the wrong depreciation.</p>
    ' . $status . '

    <p>Full instructions are in <code>docs/AssetRateUpdates.md</code> in the desktop repository.</p>

    <p>Rough steps:</p>
    <ol>
        <li>Look through the latest federal budget and fall economic statement for changes to
            capital cost allowance, the accelerated investment incentive and immediate expensing.</li>
        <li>Check each class\'s rate against CRA\'s "Classes of depreciable property" page.</li>
        <li>Update <code>cca.json</code> if anything moved. Either way, raise <code>revision</code>
            by one and set <code>reviewedOn</code> to today: the app only takes a higher revision.</li>
        <li>Upload it to <code>resources/downloads/assets/</code> on this server, and commit the same
            file to <code>ArgoBooks.Core/Resources/Assets/</code> in the desktop repository.</li>
    </ol>

    <p>This email stops once the file on the server has a <code>reviewedOn</code> date this month.</p>
';

$sent = send_styled_email($to, $subject, $body, 'orange');

cron_metric_set('email_sent', $sent ? 1 : 0);

if (!$sent) {
    error_log('cca_rate_reminder: failed to send the reminder for ' . $window);
    cron_run_finish($pdo, $runId, 'error', 'Could not send the reminder for ' . $window);
    exit(1);
}

// This wording is the dedupe key above, so keep "reminder for <year-month>" intact.
cron_run_finish($pdo, $runId, 'ok', 'Sent reminder for ' . $window);

} catch (Throwable $e) {
    error_log('cca_rate_reminder: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
}
