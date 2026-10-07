<?php
declare(strict_types=1);

/**
 * api_webhook_delivery.php
 *
 * Delivers queued Argo Books API webhook events to developer endpoints, with
 * signed bodies and exponential backoff.
 *
 * Delivery is a cron rather than an inline POST during the request that created
 * the event, because a developer's slow or hanging server must never become a
 * merchant's slow import. The trade is up to a minute of latency, which is
 * nothing next to a queue whose next step is a human opening an app.
 *
 * Schedule: every minute.
 *   * * * * * /usr/bin/php /home/argorobots/public_html/cron/api_webhook_delivery.php
 *
 * Flags:
 *   --dry-run   resolve everything and log what would be sent, send nothing
 */

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request).
// Without this, anyone could drive outbound signed POSTs by hitting the URL.
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../api/v1/lib/bootstrap.php';
require_once __DIR__ . '/../api/v1/lib/events.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** Overlapping runs would double-POST the same delivery, so take a lock. */
$lockFile = __DIR__ . '/logs/api_webhook_delivery.lock';
$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Not an error: the previous run is simply still going. Recorded so the
    // admin page shows the run happened rather than looking like a missed cron.
    $runId = cron_run_start($pdo, 'api_webhook_delivery');
    cron_run_finish($pdo, $runId, 'ok', 'Skipped: a previous run still holds the lock.');
    exit(0);
}

$runId = cron_run_start($pdo, 'api_webhook_delivery');

try {

/**
 * Claim due deliveries. Limited per run so one badly-behaved endpoint with a
 * huge backlog cannot starve everyone else's notifications.
 *
 * There is no environment filter here, nor anywhere under /v1:
 * an account is one Argo Books company, and that is the whole of the scoping.
 */
$stmt = $pdo->prepare("
    SELECT d.id, d.attempts, d.event_id,
           e.public_id AS event_public_id, e.type, e.data, e.created_at AS event_created_at,
           w.id AS endpoint_id, w.public_id AS endpoint_public_id, w.url, w.signing_secret
      FROM api_webhook_deliveries d
      INNER JOIN api_events e ON e.id = d.event_id
      INNER JOIN api_webhook_endpoints w ON w.id = d.endpoint_id
     WHERE d.status = 'pending'
       AND d.next_attempt_at <= NOW()
       AND w.status = 'enabled'
       AND w.deleted_at IS NULL
     ORDER BY d.next_attempt_at ASC
     LIMIT 200
");
$stmt->execute();
$due = $stmt->fetchAll(PDO::FETCH_ASSOC);

cron_metric_set('due', count($due));

$delivered = 0;
$retried = 0;
$exhausted = 0;
$blocked = 0;

foreach ($due as $row) {
    $body = json_encode([
        'id'      => $row['event_public_id'],
        'object'  => 'event',
        'type'    => $row['type'],
        'created' => strtotime((string) $row['event_created_at']),
        'data'    => ['object' => json_decode((string) $row['data'], true)],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $timestamp = time();
    $signature = api_webhook_signature((string) $row['signing_secret'], $timestamp, (string) $body);
    $attempt = ((int) $row['attempts']) + 1;

    if ($dryRun) {
        error_log("api_webhook_delivery [dry-run] would POST {$row['type']} to {$row['url']} (attempt $attempt)");
        continue;
    }

    // Re-check the destination immediately before sending.
    $host = (string) parse_url((string) $row['url'], PHP_URL_HOST);
    if (api_host_is_reserved_name($host) || !api_host_is_public($host)) {
        $pdo->prepare("
            UPDATE api_webhook_deliveries
               SET status = 'failed', attempts = ?, last_error = ?
             WHERE id = ?
        ")->execute([$attempt, 'Destination no longer resolves to a public address.', (int) $row['id']]);
        $blocked++;
        error_log("api_webhook_delivery: refused delivery {$row['id']} to {$row['url']}: host is not publicly routable");
        api_maybe_disable_endpoint($pdo, (int) $row['endpoint_id'], (string) $row['endpoint_public_id']);
        continue;
    }

    [$statusCode, $error] = api_webhook_post((string) $row['url'], (string) $body, $signature);

    // Anything other than 2xx retries, a 410 included, because the attempt limit catches an endpoint that has really gone.
    if ($statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
        $pdo->prepare("
            UPDATE api_webhook_deliveries
               SET status = 'succeeded', attempts = ?, last_status_code = ?, last_error = NULL, delivered_at = NOW()
             WHERE id = ?
        ")->execute([$attempt, $statusCode, (int) $row['id']]);
        $delivered++;
        continue;
    }

    if ($attempt >= API_WEBHOOK_MAX_ATTEMPTS) {
        $pdo->prepare("
            UPDATE api_webhook_deliveries
               SET status = 'failed', attempts = ?, last_status_code = ?, last_error = ?
             WHERE id = ?
        ")->execute([$attempt, $statusCode, substr((string) $error, 0, 500), (int) $row['id']]);
        $exhausted++;
        error_log("api_webhook_delivery: giving up on delivery {$row['id']} to {$row['url']} after $attempt attempts: $error");
        api_maybe_disable_endpoint($pdo, (int) $row['endpoint_id'], (string) $row['endpoint_public_id']);
        continue;
    }

    $delaySeconds = API_WEBHOOK_BACKOFF[min($attempt, count(API_WEBHOOK_BACKOFF) - 1)];
    $pdo->prepare("
        UPDATE api_webhook_deliveries
           SET attempts = ?, last_status_code = ?, last_error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
         WHERE id = ?
    ")->execute([$attempt, $statusCode, substr((string) $error, 0, 500), $delaySeconds, (int) $row['id']]);
    $retried++;
}

cron_metric_set('delivered', $delivered);
cron_metric_set('retried', $retried);
cron_metric_set('exhausted', $exhausted);
cron_metric_set('blocked', $blocked);

// Events are the developer's catch-up log, but they do not need to be kept
// forever. Ninety days is well past any reasonable outage.
$pruned = $pdo->exec("DELETE FROM api_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
cron_metric_set('events_pruned', (int) $pruned);

cron_run_finish(
    $pdo,
    $runId,
    'ok',
    sprintf('%d delivered, %d retrying, %d exhausted, %d blocked', $delivered, $retried, $exhausted, $blocked)
);

} catch (Throwable $e) {
    error_log('api_webhook_delivery failed: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', substr($e->getMessage(), 0, 500));
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

/**
 * POST one delivery. Returns [statusCode|null, errorMessage|null].
 *
 * Short timeouts on purpose: a webhook receiver that takes more than ten seconds
 * is misusing the channel, and waiting on it holds up everyone else in the run.
 */
function api_webhook_post(string $url, string $body, string $signature): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        // Never follow a redirect: the signature is for the URL the merchant
        // approved, and following one would replay it somewhere they did not.
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Argo-Signature: ' . $signature,
            'User-Agent: ArgoBooks-Webhooks/1.0',
        ],
    ]);

    curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($statusCode === 0) {
        return [null, $error !== '' ? $error : 'No response'];
    }

    return [$statusCode, $error !== '' ? $error : "HTTP $statusCode"];
}

/**
 * Disable an endpoint whose last 20 deliveries all failed.
 *
 * Without this, one abandoned URL keeps generating outbound requests forever.
 * The merchant re-enables it from the API once the receiver is fixed.
 */
function api_maybe_disable_endpoint(PDO $pdo, int $endpointId, string $endpointPublicId): void
{
    $stmt = $pdo->prepare("
        SELECT status FROM api_webhook_deliveries
         WHERE endpoint_id = ?
         ORDER BY id DESC
         LIMIT 20
    ");
    $stmt->execute([$endpointId]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($statuses) < 20 || in_array('succeeded', $statuses, true)) {
        return;
    }

    $pdo->prepare("
        UPDATE api_webhook_endpoints
           SET status = 'disabled', disabled_reason = ?
         WHERE id = ? AND status = 'enabled'
    ")->execute(['Automatically disabled after 20 consecutive delivery failures.', $endpointId]);

    cron_metric_incr('endpoints_disabled');
    error_log("api_webhook_delivery: auto-disabled endpoint $endpointPublicId after 20 consecutive failures");
}
