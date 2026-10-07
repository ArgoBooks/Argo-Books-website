<?php
/**
 * Per-cron-run audit trail. Each cron job calls cron_run_start() at the top,
 * increments named counters via cron_metric_incr() / cron_metric_set() as it
 * works, and calls cron_run_finish() on exit. The admin Crons page reads the
 * cron_runs table to show what each cron has been doing over a time range.
 *
 * Usage in a cron:
 *
 *   require_once __DIR__ . '/lib/run_tracker.php';
 *   $runId = cron_run_start($pdo, 'my_cron');
 *   try {
 *       cron_metric_incr('things_processed', 5);
 *       cron_metric_set('largest_batch', 17);
 *       cron_run_finish($pdo, $runId, 'ok');
 *   } catch (Throwable $e) {
 *       cron_run_finish($pdo, $runId, 'error', $e->getMessage());
 *       throw $e;
 *   }
 *
 * Metrics live in a global accumulator so deeply-nested step functions don't
 * need to thread an array through their signatures. The cron just calls
 * cron_metric_incr() wherever it counts something.
 */

$GLOBALS['_cron_metrics'] = [];

/**
 * Insert a 'running' row and return its id. Returns 0 if the insert fails
 * (e.g. table missing on a fresh install). Callers should treat 0 as
 * "tracking unavailable" and skip the finish call's effect, not abort.
 */
function cron_run_start(PDO $pdo, string $cronName): int
{
    $GLOBALS['_cron_metrics'] = [];
    try {
        $stmt = $pdo->prepare("INSERT INTO cron_runs (cron_name, started_at, status) VALUES (?, NOW(), 'running')");
        $stmt->execute([$cronName]);
        $runId = (int) $pdo->lastInsertId();

        // A shutdown hook marks an orphaned row as errored, because a fatal, a timeout or an early return never reaches cron_run_finish().
        register_shutdown_function(static function () use ($pdo, $runId) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE cron_runs SET status = 'error', completed_at = NOW(),
                        error_message = COALESCE(error_message, 'process exited without finishing')
                     WHERE id = ? AND status = 'running'"
                );
                $stmt->execute([$runId]);
            } catch (Throwable $e) {
                // Shutdown context: nothing useful to do, swallow.
            }
        });

        return $runId;
    } catch (Throwable $e) {
        error_log("cron_run_start failed for '$cronName': " . $e->getMessage());
        return 0;
    }
}

/**
 * Stamp the row with completion data. $status is 'ok' or 'error'.
 * Always call this even on the failure path so a row never sits as 'running'
 * forever.
 */
function cron_run_finish(PDO $pdo, int $runId, string $status, ?string $error = null): void
{
    if ($runId <= 0) return;
    if (!in_array($status, ['ok', 'error'], true)) $status = 'error';
    try {
        $metrics = $GLOBALS['_cron_metrics'] ?? [];
        $stmt = $pdo->prepare("UPDATE cron_runs SET completed_at = NOW(), status = ?, metrics = ?, error_message = ? WHERE id = ?");
        $stmt->execute([
            $status,
            $metrics ? json_encode($metrics, JSON_UNESCAPED_SLASHES) : null,
            $error,
            $runId,
        ]);
    } catch (Throwable $e) {
        error_log("cron_run_finish failed for run #$runId: " . $e->getMessage());
    }

    cron_runs_prune($pdo);
}

/**
 * How long a finished run is kept. The admin Crons page looks back 30 days at
 * most, so this leaves a wide margin while stopping the table growing forever.
 */
const CRON_RUN_RETENTION_DAYS = 90;

/**
 * Delete finished runs past the retention window, occasionally.
 *
 * Nothing pruned this table before, and it only grows: the crons that run every
 * minute add well over half a million rows a year each. Retention lives here
 * rather than in any one cron because this file is what creates the rows, so
 * every cron contributes to the cleanup no matter which one happens to fire.
 *
 * The newest run of each cron is always kept, whatever its age. Without that, a
 * cron that has not run in months would vanish from the admin page entirely and
 * read as "never run", which is exactly the question that page exists to answer.
 *
 * Sampled rather than run every time: at these frequencies one in five hundred
 * still means several sweeps a day, and a DELETE on every single run would cost
 * more than the rows it removes.
 */
function cron_runs_prune(PDO $pdo): void
{
    try {
        if (random_int(1, 500) !== 1) {
            return;
        }

        // MySQL refuses a subquery reading the table an UPDATE is changing, so wrapping it in a second SELECT is what gets round that.
        $pdo->prepare(
            'DELETE FROM cron_runs
              WHERE started_at < DATE_SUB(NOW(), INTERVAL ' . CRON_RUN_RETENTION_DAYS . ' DAY)
                AND status <> ?
                AND id NOT IN (
                    SELECT keep_id FROM (
                        SELECT MAX(id) AS keep_id FROM cron_runs GROUP BY cron_name
                    ) AS latest_per_cron
                )'
        )->execute(['running']);
    } catch (Throwable $e) {
        // Housekeeping must never take a cron down with it.
        error_log('cron_runs_prune failed: ' . $e->getMessage());
    }
}

/** Increment a named counter by $by (default 1). */
function cron_metric_incr(string $key, int $by = 1): void
{
    if (!isset($GLOBALS['_cron_metrics'][$key])) $GLOBALS['_cron_metrics'][$key] = 0;
    $GLOBALS['_cron_metrics'][$key] += $by;
}

/** Set a metric to a literal value (overwriting any previous value). */
function cron_metric_set(string $key, $value): void
{
    $GLOBALS['_cron_metrics'][$key] = $value;
}
