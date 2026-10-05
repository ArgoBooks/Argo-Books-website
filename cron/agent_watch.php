<?php
declare(strict_types=1);

/**
 * agent_watch.php
 *
 * Emails the admin when the marketing agent has not finished a run for a day and a half.
 *
 * The agent runs outside the site, on a schedule in Anthropic's cloud, and sends its own
 * update when a run finishes. A run that never starts sends nothing, so on its own a stopped
 * agent looks exactly like a quiet one. This is the check from the site's side.
 *
 * It says nothing while the agent is switched off, and warns once per stoppage: the next
 * warning needs a finished run in between.
 *
 * Schedule: daily.
 *   30 9 * * * /usr/bin/php /home/argorobots/public_html/cron/agent_watch.php
 */

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request).
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/../api/agent/actions.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

$runId = cron_run_start($pdo, 'agent_watch');

try {
    if (!agent_on($pdo, 'enabled')) {
        cron_metric_set('agent_on', 0);
        cron_run_finish($pdo, $runId, 'ok', 'The agent is switched off, so there is nothing to watch.');
        exit(0);
    }
    cron_metric_set('agent_on', 1);

    $stmt = $pdo->prepare('SELECT MAX(finished_at) FROM agent_runs WHERE environment = ?');
    $stmt->execute([current_environment()]);
    $last = $stmt->fetchColumn();
    $hours = $last ? (int) floor((time() - strtotime((string) $last)) / 3600) : null;
    cron_metric_set('hours_since_last_run', $hours ?? -1);

    $waiting = $pdo->prepare("SELECT COUNT(*) FROM agent_proposals WHERE environment = ? AND status = 'pending'");
    $waiting->execute([current_environment()]);
    cron_metric_set('waiting_for_approval', (int) $waiting->fetchColumn());

    // No run at all yet is setup still in progress, not a stoppage.
    if ($hours === null || $hours < 36) {
        cron_run_finish($pdo, $runId, 'ok');
        exit(0);
    }

    // One warning per stoppage: the last warning must be older than the last finished run.
    $warned = agent_setting($pdo, 'watch_warned_at');
    if ($warned !== '' && strtotime($warned) > strtotime((string) $last)) {
        cron_run_finish($pdo, $runId, 'ok', "Still stopped, $hours hours since the last run. Already warned.");
        exit(0);
    }

    $body = '<h2>The marketing agent has stopped running</h2>'
          . '<p>Its last finished run was ' . htmlspecialchars((string) $last) . ', ' . $hours . ' hours ago. It is meant to run once a day.</p>'
          . '<p>Things to check: the schedule at claude.ai/code/routines, whether the Claude plan has run out of usage, '
          . 'and whether the last run there shows an error.</p>'
          . '<p><a href="https://argorobots.com/admin/agent/">Open the agent page</a></p>';

    if (send_styled_email(admin_notification_email(), 'Marketing agent has stopped running', $body)) {
        agent_setting_set($pdo, 'watch_warned_at', date('Y-m-d H:i:s'));
        cron_metric_incr('warnings_sent', 1);
        cron_run_finish($pdo, $runId, 'ok', "Warned: $hours hours since the last run.");
    } else {
        cron_run_finish($pdo, $runId, 'error', "The agent has been stopped for $hours hours and the warning email could not be sent.");
    }
} catch (Throwable $e) {
    error_log('agent_watch: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
}
