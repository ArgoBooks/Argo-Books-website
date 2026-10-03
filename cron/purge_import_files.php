<?php
declare(strict_types=1);

/**
 * Deletes expired import diagnostic files.
 *
 * The admin page promises every file deletes itself after its retention window
 * whether or not anyone looked at it. This is the thing that keeps that promise,
 * so a run that does not happen is a privacy problem rather than a tidiness one.
 * That is why the failure paths below report through cron_runs: a purge that
 * cannot reach the database must not look the same as a purge with nothing to do.
 *
 * Also sweeps orphans: a file on disk whose row is gone, which can only happen if
 * an upload died between writing the file and inserting its row.
 *
 * Schedule: hourly.
 *   0 * * * * /usr/bin/php /home/argorobots/public_html/cron/purge_import_files.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/lib/run_tracker.php';

$storageDir = __DIR__ . '/../storage/import-diagnostics';

$runId = cron_run_start($pdo, 'purge_import_files');

try {
    // Rows past their window, in every environment: an expired sandbox file is as
    // real on disk as a production one.
    $stmt = $pdo->query(
        "SELECT id, storage_name FROM import_diagnostic_files
          WHERE state = 'held' AND expires_at <= NOW()"
    );
    $expired = $stmt->fetchAll();

    $filesRemoved = 0;
    $bytesFreed = 0;
    $knownNames = [];

    foreach ($expired as $row) {
        $path = $storageDir . '/' . $row['storage_name'] . '.enc';
        if (is_file($path)) {
            $bytesFreed += (int) filesize($path);
            if (@unlink($path)) {
                $filesRemoved++;
            } else {
                error_log('purge_import_files: could not unlink ' . $row['storage_name']);
            }
        }
        $mark = $pdo->prepare(
            "UPDATE import_diagnostic_files SET state = 'downloaded' WHERE id = ?"
        );
        $mark->execute([$row['id']]);
    }

    // Orphan sweep. Every name the table still expects to exist.
    $live = $pdo->query(
        "SELECT storage_name FROM import_diagnostic_files WHERE state = 'held'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $knownNames = array_flip($live);

    $orphans = 0;
    foreach (glob($storageDir . '/*.enc') ?: [] as $path) {
        $name = basename($path, '.enc');
        if (!isset($knownNames[$name])) {
            if (@unlink($path)) {
                $orphans++;
            }
        }
    }

    cron_metric_set('expired_rows', count($expired));
    cron_metric_set('files_removed', $filesRemoved);
    cron_metric_set('orphans_removed', $orphans);
    cron_metric_set('kb_freed', (int) round($bytesFreed / 1024));

    $remaining = (int) $pdo->query(
        "SELECT COUNT(*) FROM import_diagnostic_files WHERE state = 'held'"
    )->fetchColumn();
    cron_metric_set('still_held', $remaining);

    cron_run_finish($pdo, $runId, 'ok', sprintf(
        '%d expired, %d files removed, %d orphans, %d still held',
        count($expired), $filesRemoved, $orphans, $remaining
    ));
} catch (Throwable $e) {
    error_log('purge_import_files failed: ' . $e->getMessage());
    cron_run_finish($pdo, $runId, 'error', substr($e->getMessage(), 0, 400));
    exit(1);
}
