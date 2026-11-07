<?php
declare(strict_types=1);

/**
 * indexnow_submit.php
 *
 * Pings IndexNow (Bing, Yandex, DuckDuckGo, Seznam, Naver, ...) with the pages
 * whose source files changed since the last successful run, so freshly deployed
 * or edited pages get recrawled quickly without manual submission. The URL list
 * and each page's mtime come from sitemap_build_urls() (the same source the XML
 * sitemap uses). State (epoch of last successful submit) lives in
 * cron/logs/indexnow_last_submit; the first run with no watermark submits every
 * URL once as a bootstrap unless --baseline is passed. Google does NOT
 * participate in IndexNow; keep using Search Console there.
 *
 * Schedule: daily at 5:00 AM.
 *   0 5 * * * /usr/bin/php /home/argorobots/public_html/cron/indexnow_submit.php
 *
 * Flags:
 *   --all        Submit every URL regardless of mtime (force a full re-ping).
 *   --baseline   Record "now" as the watermark without submitting anything
 *                (use once if you do NOT want the first run to ping every URL).
 *   --dry-run    Log what would be submitted without calling IndexNow.
 */

// CLI / cron only.
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../sitemap_urls.php';
require_once __DIR__ . '/../indexnow.php';
require_once __DIR__ . '/lib/run_tracker.php';

global $pdo;

// $argv is undefined when the host runs cron PHP with register_argc_argv off,
// so default to an empty list rather than fataling before we can even log.
$args      = array_slice($argv ?? [], 1);
$forceAll  = in_array('--all', $args, true);
$dryRun    = in_array('--dry-run', $args, true);
$baseline  = in_array('--baseline', $args, true);

$logDir        = __DIR__ . '/logs';
$watermarkFile = $logDir . '/indexnow_last_submit';
$lockFile      = $logDir . '/indexnow_submit.lock';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

function indexnow_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents(__DIR__ . '/logs/indexnow_submit_' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}

// Prevent overlapping runs.
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    // A run still going is not an error and is recorded to show the cron fired, but a lock that cannot be opened stops every run, so it is one.
    $skipNote = $lock === false ? "cannot open $lockFile (check permissions)" : 'Skipped: a previous run still holds the lock.';
    cron_run_finish($pdo, cron_run_start($pdo, 'indexnow_submit'), $lock === false ? 'error' : 'ok', $skipNote);
    indexnow_log($skipNote);
    exit(0);
}

$runId = cron_run_start($pdo, 'indexnow_submit');

try {
    $startTime = time();
    $lastRun   = is_file($watermarkFile) ? (int) trim((string) file_get_contents($watermarkFile)) : 0;
    $bootstrap = ($lastRun === 0);

    // --baseline just records the watermark and exits without pinging anything.
    if ($baseline) {
        if (!$dryRun) {
            file_put_contents($watermarkFile, (string) $startTime);
        }
        indexnow_log('Baseline recorded at ' . date('Y-m-d H:i:s', $startTime) . '; no URLs submitted.');
        cron_metric_set('mode', 'baseline');
        cron_run_finish($pdo, $runId, 'ok');
        exit(0);
    }

    $all = sitemap_build_urls();

    // Pick the URLs to submit: everything on --all or first-run bootstrap,
    // otherwise only pages whose file changed since the last successful run.
    $toSubmit = [];
    foreach ($all as $u) {
        if ($u['mtime'] === null) {
            continue;
        }
        if ($forceAll || $bootstrap || $u['mtime'] > $lastRun) {
            $toSubmit[] = $u['loc'];
        }
    }
    $toSubmit = array_values(array_unique($toSubmit));

    cron_metric_set('total_urls', count($all));
    cron_metric_set('changed_urls', count($toSubmit));
    cron_metric_set('mode', $forceAll ? 'all' : ($bootstrap ? 'bootstrap' : 'incremental'));

    if (!$toSubmit) {
        indexnow_log('No changed URLs since ' . date('Y-m-d H:i:s', $lastRun) . '; nothing to submit.');
        file_put_contents($watermarkFile, (string) $startTime);
        cron_run_finish($pdo, $runId, 'ok');
        exit(0);
    }

    indexnow_log(sprintf(
        '%s%d URL(s) to submit (mode: %s)%s',
        $dryRun ? '[dry-run] ' : '',
        count($toSubmit),
        $forceAll ? 'all' : ($bootstrap ? 'bootstrap' : 'incremental'),
        ': ' . implode(', ', array_slice($toSubmit, 0, 25)) . (count($toSubmit) > 25 ? ', ...' : '')
    ));

    if ($dryRun) {
        indexnow_log('[dry-run] Not calling IndexNow and not advancing the watermark.');
        cron_run_finish($pdo, $runId, 'ok');
        exit(0);
    }

    $res = indexnow_submit($toSubmit);
    foreach ($res['batches'] as $i => $b) {
        indexnow_log(sprintf(
            'Batch %d: %d URL(s) -> HTTP %d %s%s',
            $i + 1,
            $b['count'],
            $b['http'],
            $b['ok'] ? 'OK' : 'FAILED',
            $b['error'] ? ' (' . $b['error'] . ')' : ''
        ));
    }
    cron_metric_set('submitted_ok', $res['ok'] ? count($toSubmit) : 0);

    if ($res['ok']) {
        // Only advance the watermark on full success, so a transient failure
        // means the same changed URLs are retried on the next run.
        file_put_contents($watermarkFile, (string) $startTime);
        indexnow_log('Done. Watermark advanced to ' . date('Y-m-d H:i:s', $startTime) . '.');
        cron_run_finish($pdo, $runId, 'ok');
    } else {
        indexnow_log('One or more batches failed; watermark NOT advanced (will retry next run).');
        cron_run_finish($pdo, $runId, 'error', 'IndexNow submission failed; see batch log.');
    }
} catch (Throwable $e) {
    indexnow_log('FATAL: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', $e->getMessage());
    throw $e;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
