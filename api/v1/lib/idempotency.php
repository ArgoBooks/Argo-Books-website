<?php
declare(strict_types=1);

/**
 * Idempotency-Key support for /v1 writes.
 *
 * Same claim-then-run contract as api/portal/_idempotency.php, keyed on
 * api_accounts instead of portal_companies, and returning this API's error
 * envelope instead of the portal's.
 *
 *   - A row is INSERTed with response_status = 0 BEFORE the handler runs. The
 *     UNIQUE index on (account_id, idempotency_key) means exactly one concurrent
 *     caller wins the claim.
 *   - The winner runs the handler; the buffered response is persisted on
 *     shutdown and replayed for 24 hours.
 *   - Losers replay the cached response, or get 409 if the body differs
 *     (a real bug on their side) or if the winner is still running.
 *
 * The buffering-plus-shutdown dance exists because api_json() calls exit(), so
 * the handler never returns normally. PHP runs shutdown functions before it
 * flushes output buffers, so the response is still readable at that point.
 */

/** Extract the header, validating shape. Returns '' when absent. */
function api_idempotency_key(): string
{
    $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if ($key === '') {
        return '';
    }
    if (strlen($key) > 128) {
        api_error(
            400,
            'invalid_request_error',
            'idempotency_key_too_long',
            'Idempotency-Key must be 128 characters or fewer.',
            'Idempotency-Key'
        );
    }
    return $key;
}

/**
 * Run $handler at most once per (account, key).
 *
 * $requireKey is true for anything that creates state, so a network retry
 * cannot duplicate an expense. Reads pass false and run uncached.
 */
function api_with_idempotency(int $accountId, string $rawBody, callable $handler, bool $requireKey = true): void
{
    global $pdo;

    $key = api_idempotency_key();
    if ($key === '') {
        if ($requireKey) {
            api_error(
                400,
                'invalid_request_error',
                'idempotency_key_required',
                'This endpoint requires an Idempotency-Key header so a retry cannot create the object twice.',
                'Idempotency-Key'
            );
        }
        $handler();
        return;
    }

    $bodyHash = hash('sha256', $rawBody);

    $claim = $pdo->prepare(
        // Keyed on (account_id, idempotency_key). An account is one Argo Books company, which is the only scope this API has, so nothing narrower is needed here.
        "INSERT INTO api_idempotency_cache
             (account_id, idempotency_key, body_hash, response_status, response_body)
         VALUES (?, ?, ?, 0, '')
         ON DUPLICATE KEY UPDATE id = id"
    );
    $claim->execute([$accountId, $key, $bodyHash]);

    if ($claim->rowCount() === 1) {
        api_run_claimed_handler($accountId, $key, $handler);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT body_hash, response_status, response_body
           FROM api_idempotency_cache
          WHERE account_id = ? AND idempotency_key = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $stmt->execute([$accountId, $key]);
    $row = $stmt->fetch();

    if (!$row) {
        // The key is past its 24 hours or the claim was released in between, so the stale row goes before restarting or the INSERT finds it again.
        $pdo->prepare(
            'DELETE FROM api_idempotency_cache
              WHERE account_id = ? AND idempotency_key = ?
                AND created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        )->execute([$accountId, $key]);

        api_with_idempotency($accountId, $rawBody, $handler, $requireKey);
        return;
    }

    if (!hash_equals((string) $row['body_hash'], $bodyHash)) {
        api_error(
            409,
            'idempotency_error',
            'idempotency_key_reused',
            'This Idempotency-Key was already used with a different request body. Use a new key for a new request.',
            'Idempotency-Key'
        );
    }

    if ((int) $row['response_status'] === 0) {
        header('Retry-After: 1');
        api_error(
            409,
            'idempotency_error',
            'idempotency_key_in_flight',
            'A request with this Idempotency-Key is still being processed. Retry in a moment.',
            'Idempotency-Key'
        );
    }

    api_send_cached_response((int) $row['response_status'], (string) $row['response_body']);
}

/**
 * Run the handler as the claim winner, capturing whatever it emits so the
 * response can be replayed later.
 */
function api_run_claimed_handler(int $accountId, string $key, callable $handler): void
{
    global $pdo;

    $state = new stdClass();
    $state->persist = true;
    $level = ob_get_level();

    ob_start();
    register_shutdown_function(static function () use ($accountId, $key, $state) {
        if (!$state->persist) {
            return;
        }
        // Read, do not clean: PHP still has to flush this to the client.
        $body = (string) ob_get_contents();
        $status = http_response_code() ?: 200;
        api_persist_idempotent_response($accountId, $key, $status, $body);
    });

    try {
        $handler();
    } catch (ApiResponseSent $sent) {
        // Only reachable under API_TESTING, where api_json throws instead of exiting.
        $state->persist = false;
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        api_persist_idempotent_response(
            $accountId,
            $key,
            $sent->status,
            (string) json_encode($sent->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        throw $sent;
    } catch (\Throwable $e) {
        $state->persist = false;
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        try {
            $pdo->prepare(
                'DELETE FROM api_idempotency_cache
                  WHERE account_id = ? AND idempotency_key = ? AND response_status = 0'
            )->execute([$accountId, $key]);
        } catch (PDOException $cleanupError) {
            error_log('api/v1: failed to release idempotency claim: ' . $cleanupError->getMessage());
        }
        throw $e;
    }
}

/** Store the finished response against the claim. Never throws. */
function api_persist_idempotent_response(int $accountId, string $key, int $status, string $body): void
{
    global $pdo;

    try {
        $pdo->prepare(
            'UPDATE api_idempotency_cache
                SET response_status = ?, response_body = ?, created_at = CURRENT_TIMESTAMP
              WHERE account_id = ? AND idempotency_key = ?'
        )->execute([$status, $body, $accountId, $key]);
    } catch (PDOException $e) {
        error_log('api/v1: failed to persist idempotent response: ' . $e->getMessage());
    }
}

/** Opportunistic cleanup of replay entries past their 24 hour life. */
function api_idempotency_gc(): void
{
    global $pdo;

    if (random_int(1, 200) !== 1) {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM api_idempotency_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)')
            ->execute();
    } catch (PDOException $e) {
        error_log('api/v1: idempotency GC failed: ' . $e->getMessage());
    }
}
