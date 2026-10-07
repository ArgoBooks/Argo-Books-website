<?php
declare(strict_types=1);

/**
 * refund_stale_request_cleanup.php
 *
 * Cancels refund_requests stuck in 'pending_code' for over 1 hour.
 * Without this, abandoned codes pile up and clutter audit logs.
 *
 * Schedule: hourly.
 *   0 * * * * /usr/bin/php /home/argorobots/public_html/cron/refund_stale_request_cleanup.php
 */

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request). Without this, this endpoint over HTTP lets anyone cancel pending refund requests and purge the idempotency cache.
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/portal/_audit.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

$runId = cron_run_start($pdo, 'refund_stale_request_cleanup');

try {

$stmt = $pdo->query("
    SELECT id, company_id FROM refund_requests
    WHERE state = 'pending_code'
      AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    LIMIT 500
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cancelled = 0;
foreach ($rows as $r) {
    // Cancelled only while the row is still pending_code and still stale, so a code confirmed in between is not undone.
    $upd = $pdo->prepare("UPDATE refund_requests
        SET state='cancelled', state_reason='code_window_expired', cancel_token = NULL, updated_at = NOW()
        WHERE id = ? AND state = 'pending_code' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $upd->execute([$r['id']]);
    if ($upd->rowCount() > 0) {
        audit_log($pdo, (int)$r['company_id'], 'cancelled_by_system', 'system', null, (int)$r['id'], null, [
            'reason' => 'code_window_expired',
        ]);
        $cancelled++;
    }
}


// Also vacuum the idempotency cache (older than 24h is useless).
$pdo->exec("DELETE FROM refund_idempotency_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

cron_metric_incr('requests_cancelled', $cancelled);
cron_run_finish($pdo, $runId, 'ok');

} catch (Throwable $e) {
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    throw $e;
}
