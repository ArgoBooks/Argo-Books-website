<?php
declare(strict_types=1);

/**
 * argo_books_sync.php
 *
 * Pushes Argo Books' own trading history into a company inside Argo Books,
 * through the public API at /v1: subscription income, the customers who paid it,
 * affiliate commissions, the affiliates they were paid to, and refunds against
 * income already sent.
 *
 * This is real bookkeeping rather than a demo. It runs daily, it is meant to keep
 * running, and every figure it sends is money that actually moved.
 *
 * Two things keep repeated runs honest:
 *
 *   - Every write carries an Idempotency-Key derived from the source row, so the
 *     API refuses a duplicate even if this script runs twice in the same minute.
 *   - argo_books_sync_map remembers the object id the API returned for each
 *     source row, together with a hash of what was sent. An unchanged row is
 *     skipped without a request; a changed one is updated in place rather than
 *     posted again as a second object.
 *
 * Nothing lands in the books unattended. The API is an ingest queue: objects
 * arrive as proposals and someone accepts them inside the app.
 *
 * Order matters. Revenue references a customer and a category, expenses
 * reference a supplier and a category, and a refund references the revenue it
 * reverses, so the phases below run in dependency order and a phase that cannot
 * resolve its reference skips the row rather than sending a dangling one.
 *
 * Schedule: daily.
 *   25 4 * * * /usr/bin/php /home/argorobots/public_html/cron/argo_books_sync.php
 *
 * Flags:
 *   --dry-run      resolve and hash everything, log what would be sent, send nothing
 *   --since=DATE   only consider source rows dated on or after this (YYYY-MM-DD)
 *   --limit=N      stop after N writes, for a cautious first run
 */

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request). Without
// this, anyone hitting the URL could drive writes into the owner's books.
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

// db_connect.php leaves $pdo null on a failed connection rather than throwing, and cron_runs cannot record a run without a database, so this one failure goes to error_log and exit 1.
if (!$pdo instanceof PDO) {
    error_log('[argo_books_sync] No database connection; nothing was synced.');
    exit(1);
}

$argv    = $argv ?? [];
$dryRun  = in_array('--dry-run', $argv, true);
$since   = null;
$limit   = 0;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $candidate = substr($arg, 8);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            $since = $candidate;
        }
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    }
}

/**
 * Overlapping runs would race on argo_books_sync_map: both would read "not yet
 * synced" for the same row and both would post it. The Idempotency-Key would
 * save the books, but the map would end up with one row's id lost, so take a
 * lock instead of relying on the server to clean up after us.
 */
$lockFile   = __DIR__ . '/logs/argo_books_sync.lock';
$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Not an error: the previous run is simply still going. Recorded so the admin
    // page shows a run happened rather than looking like a missed cron.
    $runId = cron_run_start($pdo, 'argo_books_sync');
    cron_run_finish($pdo, $runId, 'ok', 'Skipped: a previous run still holds the lock.');
    exit(0);
}

$runId = cron_run_start($pdo, 'argo_books_sync');

// --- Configuration ---------------------------------------------------------

$apiKey  = trim((string) env('ARGO_BOOKS_API_KEY', ''));
$apiBase = rtrim(site_url('/v1'), '/');
// Only these three source tables are scoped by environment, and forgetting the filter would push sandbox test payments into the real books. The API itself has no environment column.
$env     = current_environment();

if ($apiKey === '') {
    // Reported through cron_runs rather than a bare exit, because an early exit leaves no trace on the admin page and looks exactly like the cron never firing.
    $msg = 'ARGO_BOOKS_API_KEY is not set in .env; nothing was sent.';
    error_log("[argo_books_sync] $msg");
    cron_run_finish($pdo, $runId, 'error', $msg);
    exit(1);
}

/** Written to no matter what, so a failed run still leaves the counts behind. */
$logFile = __DIR__ . '/logs/argo_books_sync-' . date('Y-m-d') . '.log';

/** Replaced by the account's real ceiling during the preflight below. */
$rateLimitPerMin = 60;

/** Set once a write fails hard, so the run finishes as an error rather than ok. */
$hadFailure   = false;
$writesMade   = 0;
$limitReached = false;

/**
 * Appends a line to today's log. Cron mail is not configured, so this and
 * cron_runs are the only two places a run leaves a record.
 */
function abs_log(string $line): void
{
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] $line\n", FILE_APPEND);
}

// --- HTTP ------------------------------------------------------------------

/**
 * One API call, with retries for the failures that are worth retrying.
 *
 * A 429 is the API's own rate limiter and a 5xx is a server hiccup, so both back
 * off and try again. A 4xx is a bad request and will be exactly as bad on the
 * second attempt, so it returns immediately and the caller records it.
 *
 * @return array{status:int, body:?array, error:?string}
 */
function abs_api_request(string $method, string $path, ?array $payload, ?string $idempotencyKey): array
{
    global $apiKey, $apiBase;

    $url     = $apiBase . $path;
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $attempts = 3;
    $lastErr  = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        abs_throttle();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlEr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $lastErr = $curlEr !== '' ? $curlEr : 'curl failed with no message';
            if ($attempt < $attempts) {
                sleep($attempt * 2);
                continue;
            }
            return ['status' => 0, 'body' => null, 'error' => $lastErr];
        }

        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            $body = null;
        }

        if (($status === 429 || $status >= 500) && $attempt < $attempts) {
            sleep($attempt * 2);
            continue;
        }

        $error = null;
        if ($status < 200 || $status >= 300) {
            $error = $body['error']['message']
                ?? $body['message']
                ?? ('HTTP ' . $status . ': ' . substr((string) $raw, 0, 200));
        }

        return ['status' => $status, 'body' => $body, 'error' => $error];
    }

    return ['status' => 0, 'body' => null, 'error' => $lastErr ?? 'exhausted retries'];
}

/**
 * Keeps the run inside the account's per-minute write ceiling.
 *
 * A first run over years of history is thousands of writes, which would sail past
 * the limit in the opening seconds. The retry in abs_api_request would eventually
 * get each one through, but only by turning every request into three, so it is
 * cheaper to wait for a slot than to be refused and come back.
 */
function abs_throttle(): void
{
    global $rateLimitPerMin;

    static $recent = [];

    $now    = microtime(true);
    $recent = array_values(array_filter($recent, static fn($t) => $now - $t < 60.0));

    if (count($recent) >= $rateLimitPerMin) {
        $sleepFor = 60.0 - ($now - min($recent)) + 0.05;
        if ($sleepFor > 0) {
            usleep((int) ($sleepFor * 1_000_000));
        }
        $after  = microtime(true);
        $recent = array_values(array_filter($recent, static fn($t) => $after - $t < 60.0));
    }

    $recent[] = microtime(true);
}

// --- The map between local rows and API objects ----------------------------

/** @return array{api_object_id:string, content_hash:?string, status:string}|null */
function abs_map_get(string $sourceType, string $sourceKey): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT api_object_id, content_hash, status
           FROM argo_books_sync_map
          WHERE source_type = ? AND source_key = ?
          LIMIT 1'
    );
    $stmt->execute([$sourceType, $sourceKey]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function abs_map_put(string $sourceType, string $sourceKey, string $apiObjectId, string $hash): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO argo_books_sync_map
             (source_type, source_key, api_object_id, content_hash)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             api_object_id = VALUES(api_object_id),
             content_hash  = VALUES(content_hash)'
    );
    $stmt->execute([$sourceType, $sourceKey, $apiObjectId, $hash]);
}

/**
 * Creates the object if this source row has never been sent, updates it if what
 * we would send has changed, and does nothing at all if it has not.
 *
 * The hash covers the payload only. A row whose payload is byte-identical to the
 * one already accepted needs no request, which is what keeps a daily run over
 * years of history down to the handful of rows that actually moved.
 *
 * @return string|null The API object id, or null if the write failed.
 */
function abs_sync(string $sourceType, string $sourceKey, string $collection, array $payload): ?string
{
    global $dryRun, $hadFailure, $writesMade, $limit, $limitReached;

    $hash     = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '');
    $existing = abs_map_get($sourceType, $sourceKey);

    // A row the owner declined in the app is finished with: sending it again re-offers something already refused, and treating it as unsent would drop it from the books for good.
    if ($existing !== null && ($existing['status'] ?? 'sent') === 'rejected') {
        cron_metric_incr('skipped_rejected');
        return $existing['api_object_id'];
    }

    if ($existing !== null && $existing['content_hash'] === $hash) {
        cron_metric_incr('skipped_unchanged');
        return $existing['api_object_id'];
    }

    if ($limit > 0 && $writesMade >= $limit) {
        $limitReached = true;
        return $existing['api_object_id'] ?? null;
    }

    // Hashed rather than truncated, because two long keys sharing a prefix would collide and the second write would be rejected as a replay. Stays inside the API's 128 character ceiling.
    $idempotencyKey = 'abs-' . $sourceType . '-' . substr(hash('sha256', $sourceKey), 0, 40);

    if ($dryRun) {
        $verb = $existing === null ? 'CREATE' : 'UPDATE';
        abs_log("DRY-RUN $verb $collection $sourceKey " . json_encode($payload, JSON_UNESCAPED_SLASHES));
        cron_metric_incr($existing === null ? $sourceType . '_created' : $sourceType . '_updated');
        $writesMade++;
        return $existing['api_object_id'] ?? ('dry_' . substr($hash, 0, 12));
    }

    $isUpdate = $existing !== null;
    $path     = $isUpdate ? "/$collection/" . $existing['api_object_id'] : "/$collection";

    // An update corrects an object that exists rather than creating one again, so it needs its own key: the API rejects a repeated key whose body has changed.
    $result = abs_api_request(
        'POST',
        $path,
        $payload,
        $isUpdate ? $idempotencyKey . '-u' . substr($hash, 0, 8) : $idempotencyKey
    );

    if ($result['error'] !== null) {
        $hadFailure = true;
        cron_metric_incr('failed');
        $msg = "FAILED $collection $sourceKey: " . $result['error'];
        abs_log($msg);
        error_log("[argo_books_sync] $msg");
        return $existing['api_object_id'] ?? null;
    }

    $apiObjectId = (string) ($result['body']['id'] ?? '');
    if ($apiObjectId === '') {
        $hadFailure = true;
        cron_metric_incr('failed');
        abs_log("FAILED $collection $sourceKey: response carried no id");
        return null;
    }

    abs_map_put($sourceType, $sourceKey, $apiObjectId, $hash);
    cron_metric_incr($isUpdate ? $sourceType . '_updated' : $sourceType . '_created');
    $writesMade++;

    return $apiObjectId;
}

/**
 * Ask the API which objects the owner declined in the app, and record it.
 *
 * The server knows: declining sets import_status to 'rejected' and the list endpoint can
 * filter on it. Nothing tells this script, though, so it asks once per run before doing any
 * work. Without this step a discard in the app is invisible here, and the very next run
 * would decide the row still matched its hash and quietly leave the books short.
 *
 * @return int How many map rows were newly marked rejected.
 */
function abs_mark_rejected(): int
{
    // No $env here: the map is not scoped by deploy any more, because the API it points
    // at is not either. The source tables further down still are, and must stay that way.
    global $pdo;

    $marked = 0;

    foreach (['categories', 'customers', 'suppliers', 'expenses', 'revenue', 'refunds'] as $resource) {
        $after = null;

        // Paged, because a bad push followed by a bulk discard can be far more than one
        // page and stopping early would leave the rest looking un-actioned forever.
        do {
            $path = "/$resource?import_status=rejected&limit=100"
                . ($after !== null ? '&starting_after=' . rawurlencode($after) : '');

            $result = abs_api_request('GET', $path, null, null);
            if ($result['error'] !== null) {
                abs_log("Could not read rejected $resource: " . $result['error']);
                break;
            }

            $rows = $result['body']['data'] ?? [];
            if (!is_array($rows) || $rows === []) {
                break;
            }

            $ids = [];
            foreach ($rows as $row) {
                if (!empty($row['id'])) {
                    $ids[] = (string) $row['id'];
                }
            }

            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE argo_books_sync_map
                        SET status = 'rejected'
                      WHERE status <> 'rejected'
                        AND api_object_id IN ($placeholders)"
                );
                $stmt->execute($ids);
                $marked += $stmt->rowCount();
            }

            $after = end($ids) ?: null;
        } while (!empty($result['body']['has_more']) && $after !== null);
    }

    return $marked;
}

/** Decimal string from the database to the integer minor units the API wants. */
function abs_minor($amount): int
{
    return (int) round(((float) $amount) * 100);
}

// --- Preflight -------------------------------------------------------------
// One read before any write, so a revoked key costs one request instead of half a synced run.

$account = abs_api_request('GET', '/account', null, null);

if ($account['error'] !== null) {
    $msg = 'Preflight failed, nothing was sent: ' . $account['error'];
    error_log("[argo_books_sync] $msg");
    abs_log($msg);
    cron_run_finish($pdo, $runId, 'error', $msg);
    exit(1);
}

$accountName     = (string) ($account['body']['display_name'] ?? 'unknown');
$rateLimitPerMin = max(10, (int) (((int) ($account['body']['rate_limit_per_min'] ?? 120)) * 0.9));

abs_log(sprintf(
    'Syncing into "%s" (%s), %s, up to %d writes per minute.',
    $accountName,
    (string) ($account['body']['id'] ?? '?'),
    $dryRun ? 'DRY RUN' : 'live',
    $rateLimitPerMin
));

// --- The run ---------------------------------------------------------------

try {

    $sinceClause = $since !== null ? ' AND p.created_at >= :since' : '';
    $sinceParam  = $since !== null ? [':since' => $since] : [];

    // --- Phase 0: catch up on anything declined in the app -------------------

    $newlyRejected = abs_mark_rejected();
    if ($newlyRejected > 0) {
        cron_metric_incr('newly_rejected', $newlyRejected);
        abs_log("Marked $newlyRejected previously sent rows as rejected.");
    }

    // --- Phase 1: categories -----------------------------------------------
    // Two is all the business has: money in from subscriptions, money out to affiliates.

    $categoryIds = [];
    foreach ([
        'subscription_income'  => ['name' => 'Subscription income',  'kind' => 'revenue'],
        'affiliate_commission' => ['name' => 'Affiliate commissions', 'kind' => 'expense'],
    ] as $key => $payload) {
        $categoryIds[$key] = abs_sync('category', $key, 'categories', $payload);
    }

    // --- Phase 2: customers -------------------------------------------------
    // One per paying email rather than per subscription, so a renewal or a plan switch is one customer.

    $stmt = $pdo->prepare(
        'SELECT s.email,
                MIN(s.subscription_id) AS first_subscription_id,
                MIN(s.start_date)      AS first_seen,
                COUNT(*)               AS subscription_count
           FROM premium_subscriptions s
          WHERE s.environment = ?
            AND s.email IS NOT NULL
            AND s.email <> ""
          GROUP BY s.email
          ORDER BY MIN(s.start_date)'
    );
    $stmt->execute([$env]);

    $customerIdsByEmail = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = (string) $row['email'];

        // No display name is stored against a subscription, and inventing one from
        // the address would put a guess in the books. The address is what is known.
        $customerIdsByEmail[$email] = abs_sync('customer', 'email:' . $email, 'customers', [
            'name'     => $email,
            'email'    => $email,
            'metadata' => [
                'first_subscription_id' => (string) $row['first_subscription_id'],
                'first_seen'            => (string) $row['first_seen'],
                'subscription_count'    => (string) $row['subscription_count'],
            ],
        ]);
    }

    // --- Phase 3: suppliers -------------------------------------------------
    // Affiliates with a payout on record, since one who has never earned supplies nothing yet.

    $stmt = $pdo->prepare(
        'SELECT DISTINCT a.id, a.source_code, a.payout_email, a.payout_method, a.promo_url
           FROM affiliates a
           JOIN affiliate_payouts ap ON ap.affiliate_id = a.id
          WHERE ap.environment = ?
          ORDER BY a.id'
    );
    $stmt->execute([$env]);

    $supplierIdsByAffiliate = [];
    foreach ($stmt->fetchAll() as $row) {
        $affiliateId = (int) $row['id'];
        $supplier    = ['name' => 'Affiliate: ' . (string) $row['source_code']];

        if (!empty($row['payout_email'])) {
            $supplier['email'] = (string) $row['payout_email'];
        }
        if (!empty($row['promo_url'])) {
            $supplier['website'] = substr((string) $row['promo_url'], 0, 255);
        }
        $supplier['metadata'] = [
            'affiliate_id'  => (string) $affiliateId,
            'source_code'   => (string) $row['source_code'],
            'payout_method' => (string) $row['payout_method'],
        ];

        $supplierIdsByAffiliate[$affiliateId] =
            abs_sync('supplier', 'affiliate:' . $affiliateId, 'suppliers', $supplier);
    }

    // --- Phase 4: revenue ---------------------------------------------------
    // Refunded payments are included: the sale happened, and the refund is its own entry in phase 6.

    $sql = 'SELECT p.id, p.subscription_id, p.amount, p.currency, p.payment_method,
                   p.payment_type, p.status, p.transaction_id,
                   DATE(p.created_at) AS occurred_on,
                   s.email, s.billing_cycle
              FROM premium_subscription_payments p
              LEFT JOIN premium_subscriptions s ON s.subscription_id = p.subscription_id
             WHERE p.environment = :env
               AND p.status IN ("completed", "refunded")' . $sinceClause . '
             ORDER BY p.created_at';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([':env' => $env], $sinceParam));
    $payments = $stmt->fetchAll();

    $revenueIdsByPayment = [];
    foreach ($payments as $row) {
        $paymentId = (int) $row['id'];
        $cycle     = (string) ($row['billing_cycle'] ?? '');
        $type      = (string) $row['payment_type'];

        $description = 'Argo Books Premium';
        if ($cycle !== '') {
            $description .= ', ' . $cycle;
        }
        if (!in_array($type, ['initial', 'renewal'], true)) {
            $description .= ' (' . $type . ')';
        }

        $payload = [
            'description'    => $description,
            'amount'         => abs_minor($row['amount']),
            'currency'       => (string) $row['currency'],
            'occurred_on'    => (string) $row['occurred_on'],
            'payment_method' => substr((string) $row['payment_method'], 0, 40),
            'metadata'       => [
                'payment_id'      => (string) $paymentId,
                'subscription_id' => (string) $row['subscription_id'],
                'payment_type'    => $type,
            ],
        ];

        if ($categoryIds['subscription_income'] !== null) {
            $payload['category'] = $categoryIds['subscription_income'];
        }

        $email = (string) ($row['email'] ?? '');
        if ($email !== '' && !empty($customerIdsByEmail[$email])) {
            $payload['customer'] = $customerIdsByEmail[$email];
        }

        // The gateway's own reference, so a line in the books can be traced back to
        // the transaction that produced it without going through this script.
        if (!empty($row['transaction_id'])) {
            $payload['reference'] = substr((string) $row['transaction_id'], 0, 120);
        }

        $revenueIdsByPayment[$paymentId] =
            abs_sync('revenue', 'payment:' . $paymentId, 'revenue', $payload);
    }

    // --- Phase 5: expenses --------------------------------------------------
    // Commission actually paid out, since commission accrued but unsent is a liability.

    $sql = 'SELECT ap.id, ap.affiliate_id, ap.amount, ap.currency, ap.paid_at,
                   ap.method, ap.reference, ap.notes, a.source_code
              FROM affiliate_payouts ap
              JOIN affiliates a ON a.id = ap.affiliate_id
             WHERE ap.environment = :env'
        . ($since !== null ? ' AND ap.paid_at >= :since' : '') . '
             ORDER BY ap.paid_at';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([':env' => $env], $sinceParam));

    foreach ($stmt->fetchAll() as $row) {
        $payoutId = (int) $row['id'];

        $payload = [
            'description'    => 'Affiliate commission: ' . (string) $row['source_code'],
            'amount'         => abs_minor($row['amount']),
            'currency'       => (string) $row['currency'],
            'occurred_on'    => (string) $row['paid_at'],
            'payment_method' => substr((string) $row['method'], 0, 40),
            'metadata'       => [
                'affiliate_payout_id' => (string) $payoutId,
                'affiliate_id'        => (string) $row['affiliate_id'],
                'source_code'         => (string) $row['source_code'],
            ],
        ];

        if ($categoryIds['affiliate_commission'] !== null) {
            $payload['category'] = $categoryIds['affiliate_commission'];
        }
        $supplierId = $supplierIdsByAffiliate[(int) $row['affiliate_id']] ?? null;
        if ($supplierId !== null) {
            $payload['supplier'] = $supplierId;
        }
        if (!empty($row['reference'])) {
            $payload['reference'] = substr((string) $row['reference'], 0, 120);
        }
        if (!empty($row['notes'])) {
            $payload['notes'] = substr((string) $row['notes'], 0, 5000);
        }

        abs_sync('expense', 'affiliate_payout:' . $payoutId, 'expenses', $payload);
    }

    // --- Phase 6: refunds ---------------------------------------------------
    // A refund whose revenue has not been sent is skipped rather than posted against nothing.

    $refundsSkipped = 0;
    foreach ($payments as $row) {
        if ((string) $row['status'] !== 'refunded') {
            continue;
        }

        $paymentId = (int) $row['id'];
        $revenueId = $revenueIdsByPayment[$paymentId] ?? null;
        if ($revenueId === null) {
            $refundsSkipped++;
            abs_log("SKIP refund for payment $paymentId: its revenue is not on the books yet");
            continue;
        }

        $refundPayload = [
            'revenue'     => $revenueId,
            'amount'      => abs_minor($row['amount']),
            'currency'    => (string) $row['currency'],
            'occurred_on' => (string) $row['occurred_on'],
            'reason'      => 'Subscription refund',
            'metadata'    => ['payment_id' => (string) $paymentId],
        ];

        if (!empty($row['transaction_id'])) {
            $refundPayload['reference'] = substr((string) $row['transaction_id'], 0, 120);
        }

        abs_sync('refund', 'payment_refund:' . $paymentId, 'refunds', $refundPayload);
    }

    if ($refundsSkipped > 0) {
        cron_metric_incr('refunds_deferred', $refundsSkipped);
    }

    // --- Finish -------------------------------------------------------------

    $summary = sprintf(
        '%d write%s into "%s"%s.',
        $writesMade,
        $writesMade === 1 ? '' : 's',
        $accountName,
        $dryRun ? ' (dry run, nothing sent)' : ''
    );
    if ($limitReached) {
        $summary .= " Stopped at the --limit={$limit} ceiling; re-run to continue.";
    }

    abs_log($summary);

    if ($hadFailure) {
        cron_run_finish($pdo, $runId, 'error', 'Some objects failed to send. ' . $summary);
    } else {
        cron_run_finish($pdo, $runId, 'ok', $summary);
    }
} catch (Throwable $e) {
    $msg = 'Sync aborted: ' . $e->getMessage();
    error_log('[argo_books_sync] ' . $msg . ' @ ' . $e->getFile() . ':' . $e->getLine());
    abs_log($msg);
    cron_run_finish($pdo, $runId, 'error', $msg);
    exit(1);
}
