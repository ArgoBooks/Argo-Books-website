<?php
declare(strict_types=1);

/**
 * Fixed-window rate limiting, per key. The window is RL_API_V1_PER_MINUTE_WINDOW,
 * one minute by default.
 *
 * A fixed window rather than a sliding one: it is a single upsert instead of a
 * per-request log, and the failure mode (a caller getting up to 2x the limit
 * across a window boundary) is harmless at these volumes. If that ever matters,
 * the counter table can become a sliding log without changing this interface.
 */

/**
 * Count one request against the key's budget. Emits the X-RateLimit-* headers
 * either way, and 429s when the budget is gone.
 */
function api_enforce_rate_limit(int $keyId): void
{
    global $pdo;

    $window = rate_limit_window('api_v1_per_minute');
    $windowStartTs = intdiv(time(), $window) * $window;
    $windowStart = gmdate('Y-m-d H:i:s', $windowStartTs);
    $resetAt = $windowStartTs + $window;

    try {
        $pdo->prepare(
            'INSERT INTO api_rate_limits (api_key_id, window_started_at, request_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        )->execute([$keyId, $windowStart]);

        $stmt = $pdo->prepare(
            'SELECT request_count FROM api_rate_limits WHERE api_key_id = ? AND window_started_at = ?'
        );
        $stmt->execute([$keyId, $windowStart]);
        $count = (int) ($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        // Never let the limiter's own failure take the API down. Log and allow.
        error_log('api/v1: rate limit check failed for key ' . $keyId . ': ' . $e->getMessage());
        return;
    }

    $remaining = max(0, API_RATE_LIMIT_PER_MINUTE - $count);
    header('X-RateLimit-Limit: ' . API_RATE_LIMIT_PER_MINUTE);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Reset: ' . $resetAt);

    if ($count > API_RATE_LIMIT_PER_MINUTE) {
        header('Retry-After: ' . max(1, $resetAt - time()));
        api_error(
            429,
            'rate_limit_error',
            'rate_limit_exceeded',
            'Too many requests. The limit is ' . API_RATE_LIMIT_PER_MINUTE . ' requests per '
                . api_rate_limit_window_phrase() . ' per API key.'
        );
    }
}

/**
 * Drop counter rows well past their window. Called opportunistically on a small
 * fraction of requests so the table cannot grow without bound, and without
 * needing a cron entry for something this trivial. The floor of an hour keeps the
 * cleanup from chasing a shortened window, and the doubling keeps it clear of a
 * lengthened one, whose current row must survive.
 */
function api_rate_limit_gc(): void
{
    global $pdo;

    if (random_int(1, 200) !== 1) {
        return;
    }
    $keepSeconds = max(3600, rate_limit_window('api_v1_per_minute') * 2);
    try {
        $pdo->prepare('DELETE FROM api_rate_limits WHERE window_started_at < (UTC_TIMESTAMP() - INTERVAL ? SECOND)')
            ->execute([$keepSeconds]);
    } catch (PDOException $e) {
        error_log('api/v1: rate limit GC failed: ' . $e->getMessage());
    }
}

/**
 * A limit's window as a phrase to follow "per": 'minute' and 'day' for the defaults.
 */
function api_rate_limit_window_phrase(string $name = 'api_v1_per_minute'): string
{
    $window = rate_limit_window($name);

    return match (true) {
        $window === 60 => 'minute',
        $window === 86400 => 'day',
        $window % 86400 === 0 => ($window / 86400) . ' days',
        $window % 3600 === 0 => ($window / 3600) . ' hours',
        $window % 60 === 0 => ($window / 60) . ' minutes',
        default => $window . ' seconds',
    };
}

/**
 * Refuse a create once the company has used its daily allowance. Checked before the write and
 * counted after it by api_count_create(), so a request refused for bad input costs nothing.
 */
function api_enforce_create_quota(int $accountId): void
{
    $name = 'api_v1_creates_per_day';
    if (!rate_limit_exceeded($name, (string) $accountId)) {
        return;
    }

    $startedAt = rate_limit_started_at($name, (string) $accountId) ?? time();
    header('Retry-After: ' . max(1, $startedAt + rate_limit_window($name) - time()));
    api_error(
        429,
        'rate_limit_error',
        'daily_create_limit_exceeded',
        'This company has reached its limit of ' . rate_limit_max($name) . ' new objects per '
            . api_rate_limit_window_phrase($name) . '. Retry-After says when it can create more.'
    );
}

function api_count_create(int $accountId): void
{
    rate_limit_record('api_v1_creates_per_day', (string) $accountId);
}
