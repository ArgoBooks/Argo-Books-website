<?php
declare(strict_types=1);

/**
 * The generic resource engine.
 *
 * Every one of the seven families is the same CRUD shape over a different set of
 * columns, so the behaviour lives here once and the differences live as data in
 * definitions.php.
 *
 * The one rule that is specific to this API rather than to CRUD in general:
 * an object can only be changed while it is still `pending`. Once the desktop
 * has imported it the merchant owns a copy in their books, and letting a
 * developer mutate the server-side original afterwards would produce two
 * versions of one fact with no way to tell which is true.
 */

require_once __DIR__ . '/definitions.php';

// --- Serialization -------------------------------------------------------------------------------

/** Turn a database row into the object a caller sees. */
function api_serialize(array $spec, array $row, array $expand = []): array
{
    $out = [
        'id'      => $row['public_id'],
        'object'  => $spec['object'],
        'created' => api_timestamp($row['created_at'] ?? null),
        'updated' => api_timestamp($row['updated_at'] ?? null),
    ];

    foreach ($spec['fields'] as $name => $field) {
        $value = $row[$name] ?? null;

        switch ($field['type']) {
            case 'amount':
                $out[$name] = $value === null ? null : (int) $value;
                break;
            case 'decimal':
                $out[$name] = $value === null ? null : (float) $value;
                break;
            case 'metadata':
                $out[$name] = api_decode_metadata($value);
                break;
            default:
                $out[$name] = $value;
        }
    }

    $out['import'] = [
        'status'      => $row['import_status'],
        'batch'       => api_batch_public_id($row['import_batch_id'] ?? null),
        'imported_at' => api_timestamp($row['imported_at'] ?? null),
        'local_ref'   => $row['local_ref'],
    ];

    if (!empty($spec['line_items'])) {
        $out['line_items'] = in_array('line_items', $expand, true)
            ? api_list_line_items((int) $row['account_id'], $spec['object'], (string) $row['public_id'])
            : null;
    }

    return api_apply_expansions($spec, $out, (int) $row['account_id'], $expand);
}

/** Resolve an import batch's internal id to its public id, or null. */
function api_batch_public_id(?int $batchId): ?string
{
    global $pdo;
    static $cache = [];

    if ($batchId === null) {
        return null;
    }
    if (isset($cache[$batchId])) {
        return $cache[$batchId];
    }

    $stmt = $pdo->prepare('SELECT public_id FROM api_import_batches WHERE id = ? LIMIT 1');
    $stmt->execute([$batchId]);
    $publicId = $stmt->fetchColumn();

    return $cache[$batchId] = ($publicId === false ? null : (string) $publicId);
}

/**
 * Replace referenced ids with full objects for anything named in expand[].
 *
 * One level only. Nested expansion (expand[]=revenue.customer) is deliberately
 * unsupported for now: it multiplies queries per row and nobody has needed it.
 */
function api_apply_expansions(array $spec, array $out, int $accountId, array $expand): array
{
    global $pdo;

    foreach ($expand as $path) {
        if ($path === 'line_items') {
            continue;
        }
        if (!isset($spec['fields'][$path]) || $spec['fields'][$path]['type'] !== 'ref') {
            api_error(
                400,
                'invalid_request_error',
                'parameter_invalid_expand',
                "'$path' cannot be expanded on a {$spec['object']}.",
                'expand'
            );
        }
        if (empty($out[$path])) {
            continue;
        }

        $field = $spec['fields'][$path];
        $target = api_definition_for_object($field['object']);

        $stmt = $pdo->prepare(
            'SELECT * FROM ' . $field['table'] . ' WHERE public_id = ? AND account_id = ? LIMIT 1'
        );
        $stmt->execute([$out[$path], $accountId]);
        $row = $stmt->fetch();

        if ($row) {
            $out[$path] = api_serialize($target, $row);
        }
    }

    return $out;
}

/** Look a definition up by its object name rather than its URL segment. */
function api_definition_for_object(string $object): array
{
    foreach (api_resource_definitions() as $spec) {
        if ($spec['object'] === $object) {
            return $spec;
        }
    }
    api_error(500, 'api_error', 'unknown_object', "No definition for object '$object'.");
    return [];
}

/** The expand[] parameter, normalised to a list. */
function api_expand_params(array $input = []): array
{
    $expand = $input['expand'] ?? $_GET['expand'] ?? [];
    if (is_string($expand)) {
        $expand = [$expand];
    }
    if (!is_array($expand)) {
        api_error(400, 'invalid_request_error', 'parameter_invalid_type', "Parameter 'expand' must be an array.", 'expand');
    }
    return array_values(array_filter(array_map('strval', $expand)));
}

// --- Reads ---------------------------------------------------------------------------------------

/** Fetch one row by public id, or 404. */
function api_fetch_object(array $spec, string $publicId, int $accountId): array
{
    global $pdo;

    if (!api_id_has_prefix($publicId, $spec['prefix'])) {
        api_error(
            404,
            'invalid_request_error',
            'resource_missing',
            "No such {$spec['object']}: '$publicId'."
        );
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $spec['table'] . '
          WHERE public_id = ? AND account_id = ? AND deleted_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$publicId, $accountId]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error(404, 'invalid_request_error', 'resource_missing', "No such {$spec['object']}: '$publicId'.");
    }

    return $row;
}

/** GET /v1/<resource> */
function api_handle_list(array $spec, array $auth, string $segment): void
{
    global $pdo;

    $accountId = $auth['account_id'];
    $page = api_pagination_params();
    $expand = api_expand_params();

    $where = ' WHERE account_id = ? AND deleted_at IS NULL';
    $params = [$accountId];

    // import_status is available on every resource: it is how the desktop finds
    // what is still waiting, and how a developer checks whether their push landed.
    if (isset($_GET['import_status']) && $_GET['import_status'] !== '') {
        $status = (string) $_GET['import_status'];
        $allowed = ['pending', 'imported', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            api_error(
                400,
                'invalid_request_error',
                'parameter_invalid_value',
                "Parameter 'import_status' must be one of: " . implode(', ', $allowed) . '.',
                'import_status'
            );
        }
        $where .= ' AND import_status = ?';
        $params[] = $status;
    }

    foreach ($spec['filters'] ?? [] as $name => $kind) {
        if (!isset($_GET[$name]) || $_GET[$name] === '') {
            continue;
        }
        if ($kind === 'exact') {
            $where .= ' AND ' . $name . ' = ?';
            $params[] = (string) $_GET[$name];
            continue;
        }
        if ($kind === 'date_range') {
            $range = $_GET[$name];
            if (is_string($range)) {
                $where .= ' AND ' . $name . ' = ?';
                $params[] = api_coerce_field($name, ['type' => 'date'], $range, $accountId);
                continue;
            }
            foreach (['gte' => '>=', 'gt' => '>', 'lte' => '<=', 'lt' => '<'] as $op => $sql) {
                if (!isset($range[$op]) || $range[$op] === '') {
                    continue;
                }
                $where .= ' AND ' . $name . ' ' . $sql . ' ?';
                $params[] = api_coerce_field($name . "[$op]", ['type' => 'date'], $range[$op], $accountId);
            }
        }
    }

    [$cursorSql, $cursorParams, $reverse] = api_cursor_clause($page, $spec['table'], $accountId);
    $where .= $cursorSql;
    $params = array_merge($params, $cursorParams);

    // Fetch one extra row: its presence is what has_more reports.
    $order = $reverse ? 'ASC' : 'DESC';
    $sql = 'SELECT * FROM ' . $spec['table'] . $where . ' ORDER BY id ' . $order . ' LIMIT ' . ((int) $page['limit'] + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $hasMore = count($rows) > $page['limit'];
    if ($hasMore) {
        array_pop($rows);
    }
    if ($reverse) {
        $rows = array_reverse($rows);
    }

    $data = array_map(static fn (array $row) => api_serialize($spec, $row, $expand), $rows);

    api_json(200, api_list_response($data, $hasMore, '/v1/' . $segment));
}

/** GET /v1/<resource>/<id> */
function api_handle_retrieve(array $spec, array $auth, string $publicId): void
{
    $row = api_fetch_object($spec, $publicId, $auth['account_id']);
    api_json(200, api_serialize($spec, $row, api_expand_params()));
}

// --- Writes --------------------------------------------------------------------------------------

/** POST /v1/<resource> */
function api_handle_create(array $spec, array $auth): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($spec, $input, $accountId, $pdo) {
        api_enforce_create_quota($accountId);
        $expand = api_expand_params($input);
        $values = api_validate_input($spec, $input, true, $accountId);

        api_check_resource_invariants($spec, $values, null);

        $publicId = api_generate_id($spec['prefix']);
        $columns = array_merge(['public_id', 'account_id'], array_keys($values));
        $bindings = array_merge([$publicId, $accountId], array_values($values));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $pdo->prepare(
            'INSERT INTO ' . $spec['table'] . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        )->execute($bindings);
        api_count_create($accountId);

        $row = api_fetch_object($spec, $publicId, $accountId);
        api_json(201, api_serialize($spec, $row, $expand));
    });
}

/** POST /v1/<resource>/<id> */
function api_handle_update(array $spec, array $auth, string $publicId): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($spec, $input, $accountId, $publicId, $pdo) {
        $existing = api_fetch_object($spec, $publicId, $accountId);
        api_require_pending($spec, $existing, 'updated');

        $expand = api_expand_params($input);
        $values = api_validate_input($spec, $input, false, $accountId);

        if ($values === []) {
            api_json(200, api_serialize($spec, $existing, $expand));
        }

        api_check_resource_invariants($spec, array_merge($existing, $values), $existing);

        $assignments = implode(', ', array_map(static fn (string $c) => $c . ' = ?', array_keys($values)));
        $bindings = array_merge(array_values($values), [$publicId, $accountId]);

        $pdo->prepare(
            'UPDATE ' . $spec['table'] . ' SET ' . $assignments . '
              WHERE public_id = ? AND account_id = ?'
        )->execute($bindings);

        $row = api_fetch_object($spec, $publicId, $accountId);
        api_json(200, api_serialize($spec, $row, $expand));
    }, false);
}

/** DELETE /v1/<resource>/<id> */
function api_handle_delete(array $spec, array $auth, string $publicId): void
{
    global $pdo;

    $accountId = $auth['account_id'];
    $existing = api_fetch_object($spec, $publicId, $accountId);
    api_require_pending($spec, $existing, 'deleted');

    // A soft delete: the row stays for referential integrity so an id is never reused and no reference dangles, and a later GET of it is a plain 404.
    $pdo->prepare(
        'UPDATE ' . $spec['table'] . ' SET deleted_at = NOW() WHERE public_id = ? AND account_id = ?'
    )->execute([$publicId, $accountId]);

    api_json(200, [
        'id'      => $publicId,
        'object'  => $spec['object'],
        'deleted' => true,
    ]);
}

/**
 * Refuse to mutate an object the merchant has already taken into their books.
 *
 * Returning 409 rather than silently succeeding matters here: a developer whose
 * correction is refused can react (issue a refund, push a correcting entry).
 * A developer whose correction is accepted server-side but never reaches the
 * merchant's books has been told a lie.
 */
function api_require_pending(array $spec, array $row, string $verb): void
{
    if ($row['import_status'] === 'pending') {
        return;
    }
    api_error(
        409,
        'invalid_request_error',
        'object_not_pending',
        "This {$spec['object']} has already been {$row['import_status']} by the account owner and can no longer be $verb. "
            . 'Push a correcting object instead.'
    );
}

/**
 * Cross-field rules that a per-field validator cannot see.
 *
 * $values is the post-write state of the object; $existing is null on create.
 */
function api_check_resource_invariants(array $spec, array $values, ?array $existing): void
{
    global $pdo;

    if (isset($values['tax_amount'], $values['amount'])
        && (int) $values['tax_amount'] > (int) $values['amount']) {
        api_error(
            400,
            'invalid_request_error',
            'parameter_invalid_value',
            "'tax_amount' cannot exceed 'amount'.",
            'tax_amount'
        );
    }

    if ($spec['object'] === 'refund') {
        $stmt = $pdo->prepare('SELECT amount, currency FROM api_revenue WHERE public_id = ? LIMIT 1');
        $stmt->execute([$values['revenue']]);
        $parent = $stmt->fetch();

        if ($parent) {
            if ($values['currency'] !== $parent['currency']) {
                api_error(
                    400,
                    'invalid_request_error',
                    'currency_mismatch',
                    "A refund must use the same currency as the revenue it refunds ({$parent['currency']}).",
                    'currency'
                );
            }

            // Total refunded can never exceed what was taken. Checked across all
            // live refunds against this revenue, not just the one being written.
            $sumStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) FROM api_refunds
                  WHERE revenue = ? AND deleted_at IS NULL AND public_id <> ?'
            );
            $sumStmt->execute([$values['revenue'], $existing['public_id'] ?? '']);
            $already = (int) $sumStmt->fetchColumn();

            if ($already + (int) $values['amount'] > (int) $parent['amount']) {
                $remaining = max(0, (int) $parent['amount'] - $already);
                api_error(
                    400,
                    'invalid_request_error',
                    'refund_exceeds_revenue',
                    "Refunding {$values['amount']} would exceed the original amount. At most $remaining remains refundable.",
                    'amount'
                );
            }
        }

        if ((int) $values['amount'] <= 0) {
            api_error(400, 'invalid_request_error', 'parameter_invalid_value', "'amount' must be greater than zero.", 'amount');
        }
    }
}

// --- Line items ----------------------------------------------------------------------------------

/** All line items for one expense or revenue, oldest first. */
function api_list_line_items(int $accountId, string $parentType, string $parentPublicId): array
{
    global $pdo;

    $spec = api_line_item_definition();
    $stmt = $pdo->prepare(
        'SELECT * FROM api_line_items
          WHERE account_id = ? AND parent_type = ? AND parent_public_id = ?
          ORDER BY id ASC'
    );
    $stmt->execute([$accountId, $parentType, $parentPublicId]);

    return array_map(
        static function (array $row) use ($spec) {
            $item = api_serialize_line_item($spec, $row);
            return $item;
        },
        $stmt->fetchAll()
    );
}

/** Line items have no import lifecycle of their own; they follow their parent. */
function api_serialize_line_item(array $spec, array $row): array
{
    $out = [
        'id'      => $row['public_id'],
        'object'  => 'line_item',
        'parent'  => $row['parent_public_id'],
        'created' => api_timestamp($row['created_at'] ?? null),
    ];
    foreach ($spec['fields'] as $name => $field) {
        $value = $row[$name] ?? null;
        $out[$name] = match ($field['type']) {
            'amount'  => $value === null ? null : (int) $value,
            'decimal' => $value === null ? null : (float) $value,
            default   => $value,
        };
    }
    return $out;
}

/** GET /v1/<expenses|revenue>/<id>/line_items */
function api_handle_list_line_items(array $spec, array $auth, string $publicId): void
{
    $parent = api_fetch_object($spec, $publicId, $auth['account_id']);
    $items = api_list_line_items($auth['account_id'], $spec['object'], $publicId);

    api_json(200, api_list_response($items, false, '/v1/' . $spec['object'] . '/' . $publicId . '/line_items'));
}

/** POST /v1/<expenses|revenue>/<id>/line_items */
function api_handle_create_line_item(array $spec, array $auth, string $publicId): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($spec, $input, $accountId, $publicId, $pdo) {
        api_enforce_create_quota($accountId);
        $parent = api_fetch_object($spec, $publicId, $accountId);
        api_require_pending($spec, $parent, 'given new line items');

        $itemSpec = api_line_item_definition();
        $values = api_validate_input($itemSpec, $input, true, $accountId);

        $itemId = api_generate_id('li');
        $columns = array_merge(
            ['public_id', 'account_id', 'parent_type', 'parent_public_id'],
            array_keys($values)
        );
        $bindings = array_merge(
            [$itemId, $accountId, $spec['object'], $publicId],
            array_values($values)
        );
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $pdo->prepare(
            'INSERT INTO api_line_items (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        )->execute($bindings);
        api_count_create($accountId);

        $stmt = $pdo->prepare('SELECT * FROM api_line_items WHERE public_id = ? LIMIT 1');
        $stmt->execute([$itemId]);

        api_json(201, api_serialize_line_item($itemSpec, $stmt->fetch()));
    });
}
