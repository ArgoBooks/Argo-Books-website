<?php
/**
 * api_retention.php
 *
 * Deletes public API objects nobody will ever use again: ones a developer deleted, and ones the
 * merchant rejected in the app, 30 days after that happened. Imported objects are kept, because
 * the desktop looks them up later (a new sale naming a customer imported months ago, a refund
 * naming its sale), and an object anything else still points at is kept for the same reason.
 *
 * Schedule: daily at 4:30 AM.
 *   30 4 * * * /usr/bin/php /home/argorobots/public_html/cron/api_retention.php
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
require_once __DIR__ . '/lib/run_tracker.php';
require_once __DIR__ . '/lib/api_retention_helpers.php';

$runId = cron_run_start($pdo, 'api_retention');

try {
    [$objects, $lineItems] = api_retention_purge_all($pdo);
    cron_metric_incr('objects_deleted', $objects);
    cron_metric_incr('line_items_deleted', $lineItems);

    cron_run_finish($pdo, $runId, 'ok');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api_retention: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    exit(1);
}
