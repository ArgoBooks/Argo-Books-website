<?php
declare(strict_types=1);

/**
 * Import batches, and the object lifecycle around them.
 *
 * This is the part of the API that has no Stripe equivalent, because Stripe is
 * the system of record and Argo Books is not. Objects pushed here are proposals;
 * a batch is the moment a merchant accepted some of them into their books.
 *
 * The desktop drives it:
 *   1. GET /v1/<resource>?import_status=pending   what is waiting
 *   2. merchant reviews the preview in the app
 *   3. POST /v1/import_batches                    claim the approved ids
 *   4. POST /v1/import_batches/<id>/revert        if the merchant undoes it
 *
 * Step 3 is atomic across every object in the batch. A half-claimed batch would
 * leave the merchant's books and this queue disagreeing about what was taken.
 */

/** Map an object id prefix to the table and object name it belongs to. */
function api_table_for_id(string $publicId): ?array
{
    foreach (api_resource_definitions() as $segment => $spec) {
        if (api_id_has_prefix($publicId, $spec['prefix'])) {
            return ['table' => $spec['table'], 'object' => $spec['object'], 'segment' => $segment];
        }
    }
    return null;
}

/** Serialize a batch row. */
function api_serialize_batch(array $row): array
{
    $counts = $row['object_counts'] === null ? [] : json_decode((string) $row['object_counts'], true);

    return [
        'id'            => $row['public_id'],
        'object'        => 'import_batch',
        'status'        => $row['status'],
        'object_counts' => (object) (is_array($counts) ? $counts : []),
        'created'       => api_timestamp($row['created_at'] ?? null),
        'completed_at'  => api_timestamp($row['completed_at'] ?? null),
    ];
}

/** Fetch a batch by public id, or 404. */
function api_fetch_batch(string $publicId, int $accountId): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM api_import_batches WHERE public_id = ? AND account_id = ? LIMIT 1'
    );
    $stmt->execute([$publicId, $accountId]);
    $row = $stmt->fetch();

    if (!$row) {
        api_error(404, 'invalid_request_error', 'resource_missing', "No such import_batch: '$publicId'.");
    }

    return $row;
}

/**
 * POST /v1/import_batches
 *
 * Body: { "objects": ["exp_...", "cus_..."], "local_refs": { "exp_...": "42" } }
 *
 * Claims every listed object in one transaction and returns the batch.
 */
function api_handle_create_batch(array $auth): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($input, $accountId, $pdo) {
        $objects = $input['objects'] ?? null;
        if (!is_array($objects) || $objects === []) {
            api_error(
                400,
                'invalid_request_error',
                'parameter_missing',
                "Parameter 'objects' must be a non-empty array of object ids.",
                'objects'
            );
        }
        if (count($objects) > 1000) {
            api_error(
                400,
                'invalid_request_error',
                'parameter_out_of_range',
                "A single import batch accepts at most 1000 objects. Split the import.",
                'objects'
            );
        }

        $localRefs = $input['local_refs'] ?? [];
        if (!is_array($localRefs)) {
            api_error(400, 'invalid_request_error', 'parameter_invalid_type', "Parameter 'local_refs' must be an object.", 'local_refs');
        }

        $batchPublicId = api_generate_id('imb');
        $counts = [];

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO api_import_batches (account_id, public_id, status) VALUES (?, ?, ?)'
            )->execute([$accountId, $batchPublicId, 'open']);
            $batchId = (int) $pdo->lastInsertId();

            foreach ($objects as $publicId) {
                $publicId = trim((string) $publicId);
                $target = api_table_for_id($publicId);

                if ($target === null) {
                    $pdo->rollBack();
                    api_error(
                        400,
                        'invalid_request_error',
                        'parameter_invalid_value',
                        "'$publicId' is not an id this API recognises.",
                        'objects'
                    );
                }

                $localRef = isset($localRefs[$publicId]) ? substr((string) $localRefs[$publicId], 0, 120) : null;

                // The import_status = 'pending' predicate is what makes two desktops draining one queue safe: the second matches nothing and rolls back.
                $stmt = $pdo->prepare(
                    'UPDATE ' . $target['table'] . '
                        SET import_status = ?, import_batch_id = ?, imported_at = NOW(), local_ref = ?
                      WHERE public_id = ? AND account_id = ?
                        AND deleted_at IS NULL AND import_status = ?'
                );
                $stmt->execute(['imported', $batchId, $localRef, $publicId, $accountId, 'pending']);

                if ($stmt->rowCount() !== 1) {
                    $pdo->rollBack();
                    api_error(
                        409,
                        'invalid_request_error',
                        'object_not_claimable',
                        "'$publicId' could not be imported. It does not exist, or it is no longer pending.",
                        'objects'
                    );
                }

                $counts[$target['object']] = ($counts[$target['object']] ?? 0) + 1;
            }

            $pdo->prepare(
                'UPDATE api_import_batches SET status = ?, object_counts = ?, completed_at = NOW() WHERE id = ?'
            )->execute(['completed', json_encode($counts), $batchId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $batch = api_fetch_batch($batchPublicId, $accountId);

        // Events are recorded after the commit, never inside it. A webhook for a
        // transaction that then rolled back would be a lie we could not retract.
        api_emit_import_events($accountId, $objects, 'imported');
        api_record_event($accountId, 'import_batch.completed', $batchPublicId, api_serialize_batch($batch));

        api_json(201, api_serialize_batch($batch));
    });
}

/**
 * POST /v1/import_batches/<id>/revert
 *
 * The merchant undid the import in Argo Books. Everything the batch claimed goes
 * back to pending so it shows up in the review queue again, and developers
 * watching import status see it flip back rather than being told a stale story.
 */
function api_handle_revert_batch(array $auth, string $publicId): void
{
    global $pdo;

    [, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($accountId, $publicId, $pdo) {
        $batch = api_fetch_batch($publicId, $accountId);

        if ($batch['status'] === 'reverted') {
            api_json(200, api_serialize_batch($batch));
        }

        $pdo->beginTransaction();
        try {
            foreach (api_resource_definitions() as $spec) {
                $pdo->prepare(
                    'UPDATE ' . $spec['table'] . '
                        SET import_status = ?, import_batch_id = NULL, imported_at = NULL, local_ref = NULL
                      WHERE import_batch_id = ? AND account_id = ?'
                )->execute(['pending', (int) $batch['id'], $accountId]);
            }

            $pdo->prepare('UPDATE api_import_batches SET status = ? WHERE id = ?')
                ->execute(['reverted', (int) $batch['id']]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $reverted = api_fetch_batch($publicId, $accountId);
        api_record_event($accountId, 'import_batch.reverted', $publicId, api_serialize_batch($reverted));

        api_json(200, api_serialize_batch($reverted));
    });
}

/** GET /v1/import_batches */
function api_handle_list_batches(array $auth): void
{
    global $pdo;

    $page = api_pagination_params();
    $accountId = $auth['account_id'];

    $where = ' WHERE account_id = ?';
    $params = [$accountId];

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $status = (string) $_GET['status'];
        if (!in_array($status, ['open', 'completed', 'reverted'], true)) {
            api_error(400, 'invalid_request_error', 'parameter_invalid_value', "Parameter 'status' must be open, completed or reverted.", 'status');
        }
        $where .= ' AND status = ?';
        $params[] = $status;
    }

    [$cursorSql, $cursorParams, $reverse] = api_cursor_clause($page, 'api_import_batches', $accountId);
    $where .= $cursorSql;
    $params = array_merge($params, $cursorParams);

    $order = $reverse ? 'ASC' : 'DESC';
    $stmt = $pdo->prepare(
        'SELECT * FROM api_import_batches' . $where . ' ORDER BY id ' . $order . ' LIMIT ' . ((int) $page['limit'] + 1)
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

    api_json(200, api_list_response(array_map('api_serialize_batch', $rows), $hasMore, '/v1/import_batches'));
}

/** GET /v1/import_batches/<id> */
function api_handle_retrieve_batch(array $auth, string $publicId): void
{
    api_json(200, api_serialize_batch(api_fetch_batch($publicId, $auth['account_id'])));
}

/**
 * POST /v1/<resource>/<id>/reject
 *
 * The merchant looked at the object and declined it. Distinct from delete, which
 * is the developer withdrawing their own push: rejected tells the developer their
 * data was seen and refused, which is information they can act on.
 */
function api_handle_reject(array $spec, array $auth, string $publicId): void
{
    global $pdo;

    [$input, $raw] = api_request_body();
    $accountId = $auth['account_id'];

    api_with_idempotency($accountId, $raw, static function () use ($spec, $input, $accountId, $publicId, $pdo) {
        $row = api_fetch_object($spec, $publicId, $accountId);
        api_require_pending($spec, $row, 'rejected');

        $pdo->prepare(
            'UPDATE ' . $spec['table'] . ' SET import_status = ? WHERE public_id = ? AND account_id = ?'
        )->execute(['rejected', $publicId, $accountId]);

        $rejected = api_serialize($spec, api_fetch_object($spec, $publicId, $accountId));
        api_record_event($accountId, $spec['object'] . '.rejected', $publicId, $rejected);

        api_json(200, $rejected);
    }, false);
}

/**
 * Emit one event per object in a finished batch.
 *
 * Per object rather than only per batch because a developer typically cares
 * about their own handful of rows, not about a batch that also swept up fifty
 * of someone else's. The batch event is emitted alongside for anyone who wants
 * the coarser signal.
 */
function api_emit_import_events(int $accountId, array $objectIds, string $verb): void
{
    foreach ($objectIds as $publicId) {
        $publicId = trim((string) $publicId);
        $target = api_table_for_id($publicId);
        if ($target === null) {
            continue;
        }

        try {
            $spec = api_resource_definitions()[$target['segment']];
            $row = api_fetch_object_quietly($spec, $publicId, $accountId);
            if ($row !== null) {
                api_record_event($accountId, $spec['object'] . '.' . $verb, $publicId, api_serialize($spec, $row));
            }
        } catch (Throwable $e) {
            error_log('api/v1: failed to emit ' . $verb . ' event for ' . $publicId . ': ' . $e->getMessage());
        }
    }
}

/**
 * Like api_fetch_object but returns null instead of ending the request.
 * Used on the event path, where a missing row must not turn a successful import
 * into a 404 for the caller.
 */
function api_fetch_object_quietly(array $spec, string $publicId, int $accountId): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $spec['table'] . '
          WHERE public_id = ? AND account_id = ? AND deleted_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$publicId, $accountId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}
