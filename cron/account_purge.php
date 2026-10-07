<?php
/**
 * account_purge.php
 *
 * Permanently deletes user accounts whose 30-day deletion grace period has expired.
 * Users schedule deletion from their profile; logging back in cancels the request.
 *
 * Schedule: daily at 4:00 AM.
 *   0 4 * * * /usr/bin/php /home/argorobots/public_html/cron/account_purge.php
 */

set_time_limit(120);

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request)
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/lib/purge_helpers.php';
require_once __DIR__ . '/lib/run_tracker.php';

function logPurge($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";

    $logFile = __DIR__ . '/logs/account_purge_' . date('Y-m-d') . '.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

logPurge('Starting account purge check...');

$runId = cron_run_start($pdo, 'account_purge');

try {
    global $pdo;

    $accounts = find_accounts_due_for_purge($pdo);
    logPurge("Found " . count($accounts) . " accounts due for deletion");

    $deletedCount = 0;
    $failedCount = 0;

    foreach ($accounts as $account) {
        $userId = (int) $account['id'];
        $username = $account['username'];

        $result = purge_pending_account($pdo, $userId);

        if ($result['success']) {
            if (($result['cancelled_subs'] ?? 0) > 0) {
                logPurge("Cancelled {$result['cancelled_subs']} active subscription(s) for user $username (#$userId)");
            }
            // deleted=0 means the row was already gone (concurrent removal, manual cleanup, etc).
            if (($result['deleted'] ?? 0) > 0) {
                logPurge("Deleted account: $username (#$userId) - scheduled at {$account['deletion_scheduled_at']}");
                $deletedCount++;
            } else {
                logPurge("Account $username (#$userId) was already gone before purge ran, skipping", 'WARNING');
            }
        } else {
            logPurge("Failed to delete account $username (#$userId): " . ($result['error'] ?? 'unknown error'), 'ERROR');
            $failedCount++;
        }
    }

    logPurge("Account purge complete. Deleted: $deletedCount, Failed: $failedCount");
    cron_metric_incr('accounts_deleted', $deletedCount);
    cron_run_finish($pdo, $runId, 'ok');

} catch (Throwable $e) {
    logPurge("Database error: " . $e->getMessage(), 'ERROR');
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
}
