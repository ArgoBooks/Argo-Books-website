<?php
/**
 * Server-side AI operation timing capture + aggregation.
 *
 * Records how long each desktop AI call actually takes ON THE SERVER (the Gemini
 * wall time, isolated from the user's network), so the Argo Books desktop app can
 * drive accurate, smooth progress bars from real pooled timings instead of a fake
 * timer. Covers the two desktop AI endpoints:
 *   - /api/ai/completions.php  (receipt scan, bank categorize, supplier suggestion,
 *                               spreadsheet analysis/processing; tagged by the client
 *                               via an "operation" field, default "completion")
 *   - /api/bank/extract.php    (bank PDF extraction; tagged "bank_pdf_extract")
 *
 * Best-effort by design: NOTHING here may ever break a scan/extract response, so
 * every DB call is wrapped and failures are logged and swallowed. Stores NO user or
 * device identity (privacy; not needed for duration priors).
 */

require_once __DIR__ . '/../../db_connect.php';

/**
 * Best-effort INSERT of one timing sample. Never throws. Required keys: operation,
 * model, elapsed_ms. All other keys are optional (see the column list).
 */
function ai_timing_record(array $row): void
{
    global $pdo;
    if ($pdo === null) {
        return;
    }
    if (empty($row['operation']) || empty($row['model']) || !isset($row['elapsed_ms'])) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO ai_call_timings
                (operation, model, size_feature, page_count, input_bytes, mime,
                 prompt_tokens, output_tokens, thought_tokens, max_output_tokens, finish_reason,
                 elapsed_ms, poll_count, success, app_platform, caller, tier, environment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            substr((string) $row['operation'], 0, 40),
            substr((string) $row['model'], 0, 50),
            ai_timing_int($row['size_feature'] ?? null),
            ai_timing_int($row['page_count'] ?? null),
            ai_timing_int($row['input_bytes'] ?? null),
            isset($row['mime']) ? substr((string) $row['mime'], 0, 30) : null,
            ai_timing_int($row['prompt_tokens'] ?? null),
            ai_timing_int($row['output_tokens'] ?? null),
            ai_timing_int($row['thought_tokens'] ?? null),
            ai_timing_int($row['max_output_tokens'] ?? null),
            isset($row['finish_reason']) ? substr((string) $row['finish_reason'], 0, 20) : null,
            (int) $row['elapsed_ms'],
            ai_timing_int($row['poll_count'] ?? null),
            !empty($row['success']) ? 1 : 0,
            isset($row['app_platform']) ? substr((string) $row['app_platform'], 0, 20) : null,
            isset($row['caller']) ? substr((string) $row['caller'], 0, 16) : null,
            in_array($row['tier'] ?? null, ['free', 'premium'], true) ? $row['tier'] : null,
            current_environment(),
        ]);
    } catch (Throwable $e) {
        error_log('[ai-timing] record failed: ' . $e->getMessage());
    }
}

/** Coerces a value to an int, or null when not set / not numeric. */
function ai_timing_int($value): ?int
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    return (int) $value;
}

/**
 * Current AI load factor: how much slower (or faster) calls are running RIGHT NOW
 * versus their own recent baseline. 1.0 = normal, 1.4 = 40% slower than usual. Lets
 * the client bias every estimate up/down when Gemini is busy. Each recent call is
 * normalized by its own operation's 14-day average, so mixing operations is fine.
 *
 * Cached in a temp file for 60s so the latency-sensitive scan/extract path pays only
 * a file read, not two DB queries, per call. Returns 1.0 (neutral) without enough
 * recent signal.
 */
function ai_timing_load_factor(): float
{
    $cacheFile = sys_get_temp_dir() . '/ai_timing_load_factor_' . current_environment() . '.json';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 60) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['load_factor'])) {
            return (float) $cached['load_factor'];
        }
    }
    $factor = ai_timing_compute_load_factor();
    @file_put_contents($cacheFile, json_encode(['load_factor' => $factor, 'at' => time()]));
    return $factor;
}

/** Computes the load factor from the DB (see ai_timing_load_factor). */
function ai_timing_compute_load_factor(): float
{
    global $pdo;
    if ($pdo === null) {
        return 1.0;
    }
    try {
        $env = current_environment();

        $stmt = $pdo->prepare(
            "SELECT operation, AVG(elapsed_ms) AS base
             FROM ai_call_timings
             WHERE success = 1 AND finish_reason = 'STOP' AND environment = ?
               AND created_at >= (NOW() - INTERVAL 14 DAY)
             GROUP BY operation"
        );
        $stmt->execute([$env]);
        $baseline = [];
        foreach ($stmt->fetchAll() as $r) {
            if ((float) $r['base'] > 0) {
                $baseline[$r['operation']] = (float) $r['base'];
            }
        }
        if (!$baseline) {
            return 1.0;
        }

        $stmt = $pdo->prepare(
            "SELECT operation, elapsed_ms
             FROM ai_call_timings
             WHERE success = 1 AND finish_reason = 'STOP' AND environment = ?
               AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$env]);
        $ratios = [];
        foreach ($stmt->fetchAll() as $r) {
            $op = $r['operation'];
            if (isset($baseline[$op]) && $baseline[$op] > 0) {
                $ratios[] = (float) $r['elapsed_ms'] / $baseline[$op];
            }
        }
        if (count($ratios) < 5) {
            return 1.0; // not enough recent signal -> neutral
        }
        $avg = array_sum($ratios) / count($ratios);
        return round(max(0.3, min(4.0, $avg)), 3);
    } catch (Throwable $e) {
        error_log('[ai-timing] load factor failed: ' . $e->getMessage());
        return 1.0;
    }
}

/**
 * Per-operation duration priors for the given model, computed in PHP from a bounded
 * recent sample (last 14 days, capped). Returns one entry per operation with p50/p90
 * (the spread that drives the smooth bar), plus averages the client uses to scale the
 * estimate by document size. Excludes failed and truncated (non-STOP) calls.
 *
 * (A precomputed summary table refreshed by a cron is a future optimization; at the
 * cache TTL and capped sample size this direct computation is cheap.)
 */
function ai_timing_compute_priors(string $model): array
{
    global $pdo;
    if ($pdo === null) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT operation, elapsed_ms, size_feature, output_tokens, page_count
             FROM ai_call_timings
             WHERE success = 1 AND finish_reason = 'STOP'
               AND environment = ? AND model = ?
               AND created_at >= (NOW() - INTERVAL 14 DAY)
             ORDER BY created_at DESC
             LIMIT 20000"
        );
        $stmt->execute([current_environment(), $model]);

        $byOp = [];
        foreach ($stmt->fetchAll() as $r) {
            $byOp[$r['operation']][] = $r;
        }

        $priors = [];
        foreach ($byOp as $op => $rows) {
            $elapsed = array_map(static fn($r) => (int) $r['elapsed_ms'], $rows);
            sort($elapsed);
            $priors[] = [
                'operation' => $op,
                'p50_ms' => ai_timing_percentile($elapsed, 0.50),
                'p90_ms' => ai_timing_percentile($elapsed, 0.90),
                'sample_count' => count($rows),
                'avg_size_feature' => ai_timing_avg($rows, 'size_feature'),
                'avg_output_tokens' => ai_timing_avg($rows, 'output_tokens'),
                'per_page_ms' => ai_timing_per_page_ms($rows),
            ];
        }
        return $priors;
    } catch (Throwable $e) {
        error_log('[ai-timing] compute priors failed: ' . $e->getMessage());
        return [];
    }
}

/** Nearest-rank percentile of an ascending-sorted int array. Null when empty. */
function ai_timing_percentile(array $sorted, float $p): ?int
{
    $n = count($sorted);
    if ($n === 0) {
        return null;
    }
    $rank = (int) ceil($p * $n) - 1;
    $rank = max(0, min($n - 1, $rank));
    return (int) $sorted[$rank];
}

/** Average of a numeric column across rows, ignoring nulls. Null when none present. */
function ai_timing_avg(array $rows, string $key): ?float
{
    $vals = [];
    foreach ($rows as $r) {
        if (isset($r[$key]) && $r[$key] !== null && $r[$key] !== '') {
            $vals[] = (float) $r[$key];
        }
    }
    if (!$vals) {
        return null;
    }
    return round(array_sum($vals) / count($vals), 2);
}

/** Milliseconds per page (total elapsed / total pages) over rows that have a page count. */
function ai_timing_per_page_ms(array $rows): ?float
{
    $pages = 0;
    $ms = 0;
    foreach ($rows as $r) {
        $pc = isset($r['page_count']) ? (int) $r['page_count'] : 0;
        if ($pc > 0) {
            $pages += $pc;
            $ms += (int) $r['elapsed_ms'];
        }
    }
    if ($pages === 0) {
        return null;
    }
    return round($ms / $pages, 2);
}
