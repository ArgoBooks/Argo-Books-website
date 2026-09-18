<?php
declare(strict_types=1);

/**
 * Authentication for /v1.
 *
 * One credential type: a merchant-issued secret key, created by the account
 * owner in Argo Books Settings and handed to a developer. There is no OAuth and
 * no test/live split. The secret is never stored, only its SHA-256, so a
 * database leak does not hand anyone a working key.
 */

/** Failed authentications allowed from one address before it is shut out. */

/** Window for that count, in seconds. */

/** Pull the presented secret from either accepted header, or '' if absent. */
function api_presented_secret(): string
{
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim((string) $_SERVER['HTTP_X_API_KEY']);
    }

    // .htaccess copies the Authorization header into HTTP_AUTHORIZATION for us,
    // because PHP-FPM strips it otherwise.
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header !== '' && preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Resolve the caller, or emit 401 and stop.
 *
 * Returns ['key_id','key_scopes','account_id','account_public_id','company_uid'].
 */
function api_authenticate(): array
{
    global $pdo;

    // Per-key rate limiting cannot help here, because a request that fails to
    // authenticate has no key to count against. Without an IP bucket, an
    // attacker can spend our database on unlimited key lookups for free.
    // Only failures count, so a busy legitimate integration never trips it.
    $ip = get_client_ip();
    if (rate_limit_exceeded('api_auth_failure', $ip, 'apiauth')) {
        header('Retry-After: ' . rate_limit_window('api_auth_failure'));
        api_error(
            429,
            'rate_limit_error',
            'rate_limit_exceeded',
            'Too many failed authentication attempts from this address. Try again later.'
        );
    }

    $secret = api_presented_secret();
    if ($secret === '') {
        api_error(
            401,
            'authentication_error',
            'missing_api_key',
            'No API key provided. Send it as "Authorization: Bearer ab_..." or in the X-Api-Key header.'
        );
    }

    // Reject anything not shaped like one of our keys before touching the
    // database, so a scanner spraying random bearer tokens costs us nothing.
    if (!preg_match('/^ab_[0-9a-f]{48}$/', $secret)) {
        rate_limit_record('api_auth_failure', $ip, 'apiauth');
        api_error(401, 'authentication_error', 'invalid_api_key', 'The API key provided is not valid.');
    }

    $stmt = $pdo->prepare(
        'SELECT k.id AS key_id, k.scopes, k.revoked_at, k.last_used_at,
                a.id AS account_id, a.public_id AS account_public_id,
                a.company_uid, a.is_active
           FROM api_keys k
           JOIN api_accounts a ON a.id = k.account_id
          WHERE k.key_hash = ?
          LIMIT 1'
    );
    $stmt->execute([hash('sha256', $secret)]);
    $row = $stmt->fetch();

    if (!$row) {
        rate_limit_record('api_auth_failure', $ip, 'apiauth');
        api_error(401, 'authentication_error', 'invalid_api_key', 'The API key provided is not valid.');
    }
    if ($row['revoked_at'] !== null) {
        // Deliberately NOT counted against the address. A revoked key is a key we
        // issued, not a guess, and the bucket is per-address with no key in it: an
        // integration still retrying a key its merchant revoked would fill the
        // bucket and lock that address out of every OTHER merchant's key as well.
        api_error(401, 'authentication_error', 'api_key_revoked', 'This API key has been revoked by the account owner.');
    }
    if ((int) $row['is_active'] !== 1) {
        api_error(403, 'invalid_request_error', 'account_inactive', 'The Argo Books account behind this key is not active.');
    }

    api_touch_key_usage((int) $row['key_id'], $row['last_used_at']);

    return [
        'key_id'            => (int) $row['key_id'],
        'key_scopes'        => array_map('trim', explode(',', (string) $row['scopes'])),
        'account_id'        => (int) $row['account_id'],
        'account_public_id' => (string) $row['account_public_id'],
        'company_uid'       => (string) $row['company_uid'],
    ];
}

/**
 * Record that a key was used, at most once a minute.
 *
 * Writing on every request would put a row update in front of every read, which
 * is a lot of write load to buy a timestamp nobody reads more precisely than
 * "roughly when". Throttling keeps the signal and drops the cost.
 */
function api_touch_key_usage(int $keyId, ?string $lastUsedAt): void
{
    global $pdo;

    if ($lastUsedAt !== null && (time() - (int) strtotime($lastUsedAt)) < 60) {
        return;
    }
    try {
        $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$keyId]);
    } catch (PDOException $e) {
        // A failed usage stamp must never fail the caller's request.
        error_log('api/v1: last_used_at update failed for key ' . $keyId . ': ' . $e->getMessage());
    }
}

/** Enforce a scope, or emit 403 and stop. */
function api_require_scope(array $auth, string $scope): void
{
    if (!in_array($scope, $auth['key_scopes'], true)) {
        api_error(
            403,
            'invalid_request_error',
            'insufficient_scope',
            "This API key does not have the '$scope' scope."
        );
    }
}
