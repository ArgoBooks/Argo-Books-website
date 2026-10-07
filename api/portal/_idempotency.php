<?php
declare(strict_types=1);

/**
 * Idempotency-Key support for POST endpoints. The desktop generates a fresh
 * GUID per logical action and replays it on retry. We cache (status, body) by
 * (company_id, key, body_hash); same key + same body returns the cached
 * response within 24h; same key + different body returns 409.
 *
 * Concurrency: claim-then-run.
 *   - A row is INSERTed BEFORE the handler runs, keyed on
 *     (company_id, idempotency_key) with response_status = 0 sentinel.
 *   - The UNIQUE index on that pair ensures exactly one concurrent caller
 *     wins the insert; the others hit ON DUPLICATE KEY (rowCount = 0).
 *   - The winner runs the handler and UPDATEs the row with the real
 *     response. If the handler throws, the placeholder row is deleted so
 *     a retry can run.
 *   - Losers SELECT the existing row and either replay the cached
 *     response, return 409 IDEMPOTENCY_KEY_MISMATCH on body_hash mismatch,
 *     or return 409 IDEMPOTENCY_KEY_IN_FLIGHT if the winner is still
 *     processing (the client retries; the second replay hits the cache).
 *
 * Backed by refund_idempotency_cache (see mysql_schema.sql).
 */

/**
 * Wrap a handler so its response is cached by Idempotency-Key and the key
 * is claimed atomically. If the header is missing, the handler runs
 * uncached. On replay, the cached response is served with the original
 * status code. The handler MUST echo its body and call http_response_code()
 * before returning.
 */
function with_idempotency(PDO $pdo, int $company_id, string $raw_body, callable $handler, bool $require_key = false): void {
    $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
    if (!$key) {
        // A financial endpoint passes $require_key=true, so a retry from the desktop without a key cannot create a second refund request.
        if ($require_key) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'IDEMPOTENCY_KEY_REQUIRED',
                'message' => 'This endpoint requires the Idempotency-Key header.',
            ]);
            return;
        }
        $handler();
        return;
    }
    if (strlen($key) > 128) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IDEMPOTENCY_KEY_TOO_LONG']);
        return;
    }
    $body_hash = hash('sha256', $raw_body);

    // An atomic claim: exactly one INSERT wins when two requests race on the same key, and rowCount says which caller owns the slot.
    $claim = $pdo->prepare("
        INSERT INTO refund_idempotency_cache
            (company_id, idempotency_key, body_hash, response_status, response_body)
        VALUES (?, ?, ?, 0, '')
        ON DUPLICATE KEY UPDATE id = id
    ");
    $claim->execute([$company_id, $key, $body_hash]);

    if ($claim->rowCount() === 1) {
        try {
            ob_start();
            $handler();
            $body = ob_get_clean();
        } catch (\Throwable $e) {
            // Release the claim so a legitimate retry isn't permanently
            // blocked by a handler that crashed mid-flight.
            if (ob_get_level() > 0) ob_end_clean();
            $pdo->prepare("
                DELETE FROM refund_idempotency_cache
                WHERE company_id = ? AND idempotency_key = ? AND response_status = 0
            ")->execute([$company_id, $key]);
            throw $e;
        }
        $status = http_response_code() ?: 200;
        $upd = $pdo->prepare("
            UPDATE refund_idempotency_cache
            SET response_status = ?, response_body = ?, created_at = CURRENT_TIMESTAMP
            WHERE company_id = ? AND idempotency_key = ?
        ");
        $upd->execute([$status, $body, $company_id, $key]);
        http_response_code($status);
        header('Content-Type: application/json');
        echo $body;
        return;
    }

    // Another caller already claimed the slot. SELECT the existing row
    // to decide between cache-hit, body-mismatch, and in-flight.
    $stmt = $pdo->prepare("
        SELECT body_hash, response_status, response_body
        FROM refund_idempotency_cache
        WHERE company_id = ? AND idempotency_key = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$company_id, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        // The row was released or expired between the INSERT and this SELECT, so the flow restarts once and then either claims or finds the cache.
        with_idempotency($pdo, $company_id, $raw_body, $handler);
        return;
    }
    if (!hash_equals($row['body_hash'], $body_hash)) {
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'IDEMPOTENCY_KEY_MISMATCH',
            'message' => 'Same Idempotency-Key reused with different request body.',
        ]);
        return;
    }
    if ((int)$row['response_status'] === 0) {
        // Original handler hasn't finished. Tell the client to retry;
        // the second replay hits the cached completed response.
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'IDEMPOTENCY_KEY_IN_FLIGHT',
            'message' => 'Another request with this Idempotency-Key is being processed. Retry shortly.',
        ]);
        return;
    }
    // Cache hit: replay the stored response.
    http_response_code((int)$row['response_status']);
    header('Content-Type: application/json');
    echo $row['response_body'];
}
