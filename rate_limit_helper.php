<?php

/**
 * Shared rate limiting, backed by the `rate_limit_counters` table.
 *
 * One row per bucket, keyed by environment + a caller-supplied prefix +
 * sha256(identifier). Callers pass their own prefix so buckets don't collide
 * between admin login, portal token lookups, payment endpoints, etc. The
 * identifier is usually an IP but can be any opaque string (a license key, a
 * browser fingerprint, the literal 'GLOBAL' for a site-wide cap).
 *
 * Windows are fixed and anchored at the bucket's first attempt: a bucket
 * stays tripped until $windowSeconds have passed since that first attempt,
 * then resets on the next call. That matches the behaviour of the flat-file
 * implementation this replaced.
 *
 * Every mutating operation is a single INSERT ... ON DUPLICATE KEY UPDATE, so
 * concurrent requests serialise on that bucket's row lock and nothing else.
 * There is no table-wide or process-wide lock, and no transaction is opened,
 * so these functions are safe to call inside a caller's existing transaction.
 *
 * The limiter fails OPEN: if the database is unreachable or the statement
 * errors, requests are allowed through and the failure is logged. A limiter
 * outage must not lock real users out.
 */

require_once __DIR__ . '/env_helper.php';
require_once __DIR__ . '/config/rate_limits.php';

/**
 * Drop counter rows older than a day. Called opportunistically on a small
 * fraction of requests so the table can't grow without bound, and without
 * needing a cron entry for something this trivial.
 *
 * Two days is twice the longest window any caller uses (24 hours), and stale rows
 * are harmless in the meantime: every read filters on the window and every write
 * resets an expired bucket. Keep the margin if a longer window is ever added, or
 * the cleanup will reset a live bucket early.
 */
const RATE_LIMIT_GC_INTERVAL = '2 DAY';

/**
 * Resolve the client IP, trusting X-Forwarded-For only when the request
 * arrives from an IP listed in the TRUSTED_PROXY_IPS env var.
 */
function get_client_ip(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $trustedProxyConfig = env('TRUSTED_PROXY_IPS', '');
    if (!empty($trustedProxyConfig) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $trustedProxies = array_map('trim', explode(',', $trustedProxyConfig));
        if (in_array($remoteAddr, $trustedProxies, true)) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
    }

    return $remoteAddr;
}

/**
 * The PDO handle to run counter queries on, or null if there isn't one.
 *
 * Most callers have already loaded db_connect.php, so this is normally just a
 * read of $GLOBALS['pdo']. The lazy require covers the handful of endpoints
 * that rate limit without otherwise touching the database, and promotes $pdo
 * to the global scope because db_connect.php assigns it wherever it happens
 * to be included from, which here is this function's local scope.
 */
function rate_limit_pdo(): ?PDO
{
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    require_once __DIR__ . '/db_connect.php';

    if (isset($pdo) && $pdo instanceof PDO) {
        $GLOBALS['pdo'] = $pdo;
        return $pdo;
    }

    return null;
}

/**
 * Build the storage key for a bucket.
 *
 * Call rate_limit_pdo() first: current_environment() is defined in
 * db_connect.php, which rate_limit_pdo() is responsible for loading.
 *
 * The environment is part of the key because sandbox (dev.argorobots.com) and
 * production share one database. Without it, testing an endpoint on dev would
 * burn the production allowance for the same IP, which for a 5-attempt admin
 * login limit means locking yourself out of the live site from your own desk.
 */
function rate_limit_bucket_key(string $identifier, string $prefix): string
{
    return current_environment() . ':' . $prefix . '_' . hash('sha256', $identifier);
}

/**
 * Delete expired counter rows, on roughly 1 request in 200.
 */
function rate_limit_gc(PDO $pdo): void
{
    if (random_int(1, 200) !== 1) {
        return;
    }

    try {
        $pdo->prepare(
            'DELETE FROM rate_limit_counters
             WHERE first_attempt_at < (UTC_TIMESTAMP() - INTERVAL ' . RATE_LIMIT_GC_INTERVAL . ')'
        )->execute();
    } catch (PDOException $e) {
        error_log('rate_limit_helper: GC failed: ' . $e->getMessage());
    }
}

/**
 * Record one attempt against a bucket, incrementing only while the bucket is
 * below $maxAttempts, and return the count that was already on the bucket
 * BEFORE this call (0 for a new or expired bucket).
 *
 * The whole thing is one statement so the read and the increment can't be
 * split by a concurrent request. LAST_INSERT_ID(expr) is the MySQL idiom for
 * smuggling a value out of an upsert: it sets the connection's insert id to
 * expr and returns expr, so the prior count comes back via lastInsertId()
 * without a second query that another request could interleave with.
 *
 * Passing PHP_INT_MAX for $maxAttempts makes it an unconditional increment.
 *
 * @return int|null Prior count, or null if the statement could not run.
 */
function rate_limit_touch(PDO $pdo, string $key, int $maxAttempts, int $windowSeconds): ?int
{
    // Read left to right: the IF() condition runs first, setting the insert id
    // to the bucket's count within the current window (0 once it has expired).
    // If that prior count is already at the cap the row is left completely
    // untouched, so a blocked attempt costs nothing and doesn't extend the
    // window. Otherwise the count becomes prior + 1, and first_attempt_at is
    // re-anchored only when the old window had run out.
    $sql = 'INSERT INTO rate_limit_counters (bucket_key, attempt_count, first_attempt_at)
            VALUES (?, LAST_INSERT_ID(0) + 1, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                attempt_count = IF(
                    LAST_INSERT_ID(
                        IF(first_attempt_at < (UTC_TIMESTAMP() - INTERVAL ? SECOND), 0, attempt_count)
                    ) >= ?,
                    attempt_count,
                    LAST_INSERT_ID() + 1
                ),
                first_attempt_at = IF(
                    first_attempt_at < (UTC_TIMESTAMP() - INTERVAL ? SECOND),
                    UTC_TIMESTAMP(),
                    first_attempt_at
                )';

    try {
        $pdo->prepare($sql)->execute([$key, $windowSeconds, $maxAttempts, $windowSeconds]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('rate_limit_helper: touch failed for ' . $key . ': ' . $e->getMessage());
        return null;
    }
}

/**
 * Check whether a bucket is at or over its limit, WITHOUT recording anything.
 *
 * Pair with record_rate_limit_attempt(). Where a concurrent request slipping
 * between the check and the record would matter, use
 * check_and_record_rate_limit() instead, which is a single atomic operation.
 *
 * @param string $ip Client IP, or any opaque identifier for the bucket
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @param string $prefix Key prefix for different rate limit buckets
 * @return bool True if rate limit exceeded
 */
function is_rate_limited(string $ip, int $maxAttempts = 10, int $windowSeconds = 900, string $prefix = 'portal'): bool
{
    $pdo = rate_limit_pdo();
    if ($pdo === null) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT attempt_count FROM rate_limit_counters
             WHERE bucket_key = ?
               AND first_attempt_at >= (UTC_TIMESTAMP() - INTERVAL ? SECOND)'
        );
        $stmt->execute([rate_limit_bucket_key($ip, $prefix), $windowSeconds]);
        $count = (int) ($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        error_log('rate_limit_helper: check failed for prefix ' . $prefix . ': ' . $e->getMessage());
        return false;
    }

    return $count >= $maxAttempts;
}

/**
 * Record a rate-limited action attempt.
 *
 * $windowSeconds must match the window passed to is_rate_limited() by the same
 * caller: it decides when the bucket is considered expired and reset. Passing
 * a smaller window here than is_rate_limited uses will silently shorten the
 * effective rate-limit window.
 *
 * @param string $ip Client IP, or any opaque identifier for the bucket
 * @param string $prefix Key prefix for different rate limit buckets
 * @param int $windowSeconds Time window in seconds (must match is_rate_limited window)
 */
function record_rate_limit_attempt(string $ip, string $prefix = 'portal', int $windowSeconds = 900): void
{
    $pdo = rate_limit_pdo();
    if ($pdo === null) {
        return;
    }

    // PHP_INT_MAX as the cap: this function always counts the attempt, so that
    // is_rate_limited() can be called with any threshold afterwards.
    rate_limit_touch($pdo, rate_limit_bucket_key($ip, $prefix), PHP_INT_MAX, $windowSeconds);
    rate_limit_gc($pdo);
}

/**
 * Atomically check the rate limit AND record the attempt.
 * Use for endpoints where a gap between the check and the record could let
 * concurrent requests slip past the cap (e.g. admin login). Every call counts
 * toward the limit; pair with clear_rate_limit_attempts() on success if you
 * want successful actions to reset the counter.
 *
 * @return bool True if the limit was already exceeded BEFORE this call (the
 *              attempt is NOT counted in that case), false if the attempt was
 *              recorded and processing should continue.
 */
function check_and_record_rate_limit(string $ip, int $maxAttempts, int $windowSeconds, string $prefix): bool
{
    $pdo = rate_limit_pdo();
    if ($pdo === null) {
        // Fail open so a database problem doesn't lock real users out.
        return false;
    }

    $priorCount = rate_limit_touch($pdo, rate_limit_bucket_key($ip, $prefix), $maxAttempts, $windowSeconds);
    rate_limit_gc($pdo);

    if ($priorCount === null) {
        return false;
    }

    return $priorCount >= $maxAttempts;
}

/**
 * Clear all recorded attempts for this bucket.
 * Call on a successful action so legitimate users don't accumulate counts.
 */
function clear_rate_limit_attempts(string $ip, string $prefix, int $windowSeconds = 900): void
{
    $pdo = rate_limit_pdo();
    if ($pdo === null) {
        return;
    }

    // $windowSeconds is accepted for call-site symmetry with the other
    // functions; deleting the row clears the bucket regardless of its window.
    unset($windowSeconds);

    try {
        $pdo->prepare('DELETE FROM rate_limit_counters WHERE bucket_key = ?')
            ->execute([rate_limit_bucket_key($ip, $prefix)]);
    } catch (PDOException $e) {
        error_log('rate_limit_helper: clear failed for prefix ' . $prefix . ': ' . $e->getMessage());
    }
}

/**
 * The named-limit API. Prefer these four over the functions above: the ceiling and the window
 * both come from one entry in config/rate_limits.php, so a check and its record cannot disagree
 * about the window, and changing a limit is an .env edit rather than a hunt through call sites.
 *
 * $name is the entry in config/rate_limits.php and doubles as the bucket prefix. Pass $prefix only
 * where an existing bucket name has to be kept.
 */
function rate_limit_exceeded(string $name, string $identifier, ?string $prefix = null): bool
{
    $limit = rate_limit($name);
    return is_rate_limited($identifier, $limit['max'], $limit['window'], $prefix ?? $name);
}

function rate_limit_record(string $name, string $identifier, ?string $prefix = null): void
{
    record_rate_limit_attempt($identifier, $prefix ?? $name, rate_limit_window($name));
}

/**
 * Check and count in one atomic step. Returns true when the caller is over the limit.
 */
function rate_limit_hit(string $name, string $identifier, ?string $prefix = null): bool
{
    $limit = rate_limit($name);
    return check_and_record_rate_limit($identifier, $limit['max'], $limit['window'], $prefix ?? $name);
}

function rate_limit_clear(string $name, string $identifier, ?string $prefix = null): void
{
    clear_rate_limit_attempts($identifier, $prefix ?? $name, rate_limit_window($name));
}

/**
 * How long the caller has to wait, in words, for a message like "try again in 15 minutes".
 * The window is the honest answer only for a caller who just filled the bucket, which is who
 * sees this: a bucket that filled earlier expires sooner.
 */
function rate_limit_wait_phrase(string $name): string
{
    $seconds = rate_limit_window($name);

    return match (true) {
        $seconds >= 86400 => intdiv($seconds, 86400) === 1 ? 'a day' : intdiv($seconds, 86400) . ' days',
        $seconds >= 3600 => intdiv($seconds, 3600) === 1 ? 'an hour' : intdiv($seconds, 3600) . ' hours',
        $seconds >= 60 => intdiv($seconds, 60) === 1 ? 'a minute' : intdiv($seconds, 60) . ' minutes',
        default => $seconds . ' seconds',
    };
}

/**
 * When this bucket's window opened, as a unix timestamp, or null if it is not currently
 * counting. Lets a caller show a countdown instead of a flat "try again later".
 */
function rate_limit_started_at(string $name, string $identifier, ?string $prefix = null): ?int
{
    $pdo = rate_limit_pdo();
    if ($pdo === null) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT UNIX_TIMESTAMP(first_attempt_at) FROM rate_limit_counters
             WHERE bucket_key = ?
               AND first_attempt_at >= (UTC_TIMESTAMP() - INTERVAL ? SECOND)'
        );
        $stmt->execute([rate_limit_bucket_key($identifier, $prefix ?? $name), rate_limit_window($name)]);
        $startedAt = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('rate_limit_helper: window lookup failed for ' . $name . ': ' . $e->getMessage());
        return null;
    }

    return $startedAt === false ? null : (int) $startedAt;
}
