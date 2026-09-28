<?php
declare(strict_types=1);

/**
 * Webhook endpoint management and the event log.
 *
 *   POST   /v1/webhook_endpoints          create (secret returned once)
 *   GET    /v1/webhook_endpoints          list
 *   GET    /v1/webhook_endpoints/<id>     retrieve
 *   POST   /v1/webhook_endpoints/<id>     update url / events / status
 *   DELETE /v1/webhook_endpoints/<id>     remove
 *   GET    /v1/events                     the event log
 *   GET    /v1/events/<id>                one event
 */

require_once __DIR__ . '/events.php';

function api_serialize_endpoint(array $row, ?string $secret = null): array
{
    $events = $row['enabled_events'] === null ? null : json_decode((string) $row['enabled_events'], true);

    $out = [
        'id'              => $row['public_id'],
        'object'          => 'webhook_endpoint',
        'url'             => $row['url'],
        'description'     => $row['description'],
        'status'          => $row['status'],
        'disabled_reason' => $row['disabled_reason'],
        'enabled_events'  => is_array($events) && $events !== [] ? $events : ['*'],
        'created'         => api_timestamp($row['created_at'] ?? null),
    ];

    // Only ever present on the create response. After that we still hold the
    // secret (we have to, to sign with it), but there is no reason to hand it
    // back on every list call and every reason not to.
    if ($secret !== null) {
        $out['signing_secret'] = $secret;
    }

    return $out;
}

function api_fetch_endpoint(string $publicId, int $accountId): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM api_webhook_endpoints
          WHERE public_id = ? AND account_id = ? AND deleted_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$publicId, $accountId]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error(404, 'invalid_request_error', 'resource_missing', "No such webhook_endpoint: '$publicId'.");
    }

    return $row;
}

/**
 * Validate a subscription list against the types we actually emit.
 *
 * A silently-accepted typo here would mean an endpoint that never fires and a
 * developer with nothing to debug, so an unknown type is a 400.
 */
function api_validate_event_types($value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        $value = array_map('trim', explode(',', $value));
    }
    if (!is_array($value)) {
        api_error(400, 'invalid_request_error', 'parameter_invalid_type', "Parameter 'enabled_events' must be an array.", 'enabled_events');
    }
    if ($value === [] || in_array('*', $value, true)) {
        return null; // NULL means every type
    }

    foreach ($value as $type) {
        if (!in_array($type, API_EVENT_TYPES, true)) {
            api_error(
                400,
                'invalid_request_error',
                'parameter_invalid_value',
                "Unknown event type '$type'. Valid types: " . implode(', ', API_EVENT_TYPES) . '.',
                'enabled_events'
            );
        }
    }

    return json_encode(array_values(array_unique($value)));
}

/**
 * Endpoints must be public HTTPS.
 *
 * This is not politeness. Without it, anyone holding a write-scoped key can
 * point a signed POST from our server at our own network, which is a
 * server-side request forgery primitive.
 *
 * The host rules live in net.php because the delivery cron applies the same
 * ones again before every send. Checking only here would leave DNS rebinding
 * open: a name can resolve publicly now and privately at delivery time.
 */
function api_validate_webhook_url(string $url): string
{
    $url = trim($url);
    $parts = parse_url($url);

    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        api_error(400, 'invalid_request_error', 'parameter_invalid_value', "Parameter 'url' must be an https:// URL.", 'url');
    }
    if (mb_strlen($url) > 500) {
        api_error(400, 'invalid_request_error', 'parameter_too_long', "Parameter 'url' must be 500 characters or fewer.", 'url');
    }

    $host = api_normalise_host((string) $parts['host']);

    if (api_host_is_reserved_name($host)) {
        api_error(400, 'invalid_request_error', 'parameter_invalid_value', "Parameter 'url' must be publicly reachable.", 'url');
    }

    if (!api_host_is_public($host)) {
        // One message for "private" and for "did not resolve" on purpose. The
        // distinction is only useful to someone probing what our network can see.
        api_error(
            400,
            'invalid_request_error',
            'parameter_invalid_value',
            "Parameter 'url' must resolve to a public address.",
            'url'
        );
    }

    return $url;
}

function api_handle_create_endpoint(array $auth): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($input, $accountId, $pdo) {
        api_enforce_create_quota($accountId);
        if (empty($input['url'])) {
            api_error(400, 'invalid_request_error', 'parameter_missing', "Missing required parameter 'url'.", 'url');
        }

        $url = api_validate_webhook_url((string) $input['url']);
        $events = api_validate_event_types($input['enabled_events'] ?? null);
        $secret = api_generate_signing_secret();
        $publicId = api_generate_id('whe');

        $pdo->prepare(
            'INSERT INTO api_webhook_endpoints
                 (account_id, public_id, url, signing_secret, enabled_events, description)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $accountId,
            $publicId,
            $url,
            $secret,
            $events,
            substr(trim((string) ($input['description'] ?? '')), 0, 255),
        ]);
        api_count_create($accountId);

        api_json(201, api_serialize_endpoint(api_fetch_endpoint($publicId, $accountId), $secret));
    });
}

function api_handle_update_endpoint(array $auth, string $publicId): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($input, $accountId, $publicId, $pdo) {
        api_fetch_endpoint($publicId, $accountId);

        $sets = [];
        $params = [];

        if (isset($input['url'])) {
            $sets[] = 'url = ?';
            $params[] = api_validate_webhook_url((string) $input['url']);
        }
        if (array_key_exists('enabled_events', $input)) {
            $sets[] = 'enabled_events = ?';
            $params[] = api_validate_event_types($input['enabled_events']);
        }
        if (isset($input['description'])) {
            $sets[] = 'description = ?';
            $params[] = substr(trim((string) $input['description']), 0, 255);
        }
        if (isset($input['status'])) {
            if (!in_array($input['status'], ['enabled', 'disabled'], true)) {
                api_error(400, 'invalid_request_error', 'parameter_invalid_value', "Parameter 'status' must be enabled or disabled.", 'status');
            }
            $sets[] = 'status = ?';
            $params[] = $input['status'];
            // Re-enabling clears the auto-disable note, so a stale reason does
            // not sit on a working endpoint forever.
            $sets[] = 'disabled_reason = ?';
            $params[] = null;
        }

        if ($sets !== []) {
            $params[] = $publicId;
            $params[] = $accountId;
            $pdo->prepare(
                'UPDATE api_webhook_endpoints SET ' . implode(', ', $sets) . ' WHERE public_id = ? AND account_id = ?'
            )->execute($params);
        }

        api_json(200, api_serialize_endpoint(api_fetch_endpoint($publicId, $accountId)));
    }, false);
}

function api_handle_list_endpoints(array $auth): void
{
    global $pdo;

    $page = api_pagination_params();
    $accountId = $auth['account_id'];

    $where = ' WHERE account_id = ? AND deleted_at IS NULL';
    $params = [$accountId];

    [$cursorSql, $cursorParams, $reverse] = api_cursor_clause($page, 'api_webhook_endpoints', $accountId);
    $where .= $cursorSql;
    $params = array_merge($params, $cursorParams);

    $stmt = $pdo->prepare(
        'SELECT * FROM api_webhook_endpoints' . $where
        . ' ORDER BY id ' . ($reverse ? 'ASC' : 'DESC') . ' LIMIT ' . ((int) $page['limit'] + 1)
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $hasMore = count($rows) > $page['limit'];
    if ($hasMore) {
        array_pop($rows);
    }
    if ($reverse) {
        $rows = array_reverse($rows);
    }

    api_json(200, api_list_response(
        array_map(static fn (array $r) => api_serialize_endpoint($r), $rows),
        $hasMore,
        '/v1/webhook_endpoints'
    ));
}

function api_handle_retrieve_endpoint(array $auth, string $publicId): void
{
    api_json(200, api_serialize_endpoint(api_fetch_endpoint($publicId, $auth['account_id'])));
}

function api_handle_delete_endpoint(array $auth, string $publicId): void
{
    global $pdo;

    api_fetch_endpoint($publicId, $auth['account_id']);
    $pdo->prepare(
        'UPDATE api_webhook_endpoints SET deleted_at = NOW() WHERE public_id = ? AND account_id = ?'
    )->execute([$publicId, $auth['account_id']]);

    api_json(200, ['id' => $publicId, 'object' => 'webhook_endpoint', 'deleted' => true]);
}

/**
 * GET /v1/events
 *
 * The log exists so an endpoint that was down does not need us to replay
 * anything: a developer can walk back through what they missed themselves.
 */
function api_handle_list_events(array $auth): void
{
    global $pdo;

    $page = api_pagination_params();
    $accountId = $auth['account_id'];

    $where = ' WHERE account_id = ?';
    $params = [$accountId];

    if (isset($_GET['type']) && $_GET['type'] !== '') {
        $where .= ' AND type = ?';
        $params[] = (string) $_GET['type'];
    }

    [$cursorSql, $cursorParams, $reverse] = api_cursor_clause($page, 'api_events', $accountId);
    $where .= $cursorSql;
    $params = array_merge($params, $cursorParams);

    $stmt = $pdo->prepare(
        'SELECT * FROM api_events' . $where
        . ' ORDER BY id ' . ($reverse ? 'ASC' : 'DESC') . ' LIMIT ' . ((int) $page['limit'] + 1)
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $hasMore = count($rows) > $page['limit'];
    if ($hasMore) {
        array_pop($rows);
    }
    if ($reverse) {
        $rows = array_reverse($rows);
    }

    api_json(200, api_list_response(array_map('api_serialize_event', $rows), $hasMore, '/v1/events'));
}

function api_handle_retrieve_event(array $auth, string $publicId): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM api_events WHERE public_id = ? AND account_id = ? LIMIT 1'
    );
    $stmt->execute([$publicId, $auth['account_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error(404, 'invalid_request_error', 'resource_missing', "No such event: '$publicId'.");
    }

    api_json(200, api_serialize_event($row));
}
