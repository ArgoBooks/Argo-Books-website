<?php
/**
 * Deleting public API objects nobody will use again. Used by cron/api_retention.php, and kept
 * apart from it so the tests can run the purge without running the cron.
 */

require_once __DIR__ . '/../../api/v1/lib/definitions.php';

const API_RETENTION_DAYS = 30;
const API_RETENTION_CHUNK = 1000;

/**
 * Every column that points at an object, keyed by the object it points at, so a row still
 * referenced from somewhere is never deleted out from under the reference.
 *
 * @return array<string, list<array{0: string, 1: string}>> object => [[table, column], ...]
 */
function api_retention_referrers(): array
{
    $referrers = [];
    $specs = array_values(api_resource_definitions());
    $specs[] = api_line_item_definition();

    foreach ($specs as $spec) {
        foreach ($spec['fields'] as $column => $field) {
            if (($field['type'] ?? null) === 'ref') {
                $referrers[$field['object']][] = [$spec['table'], $column];
            }
        }
    }

    return $referrers;
}

/** Deletes one table's dead rows, and their line items, in chunks. Returns [rows, line items]. */
function api_retention_purge(PDO $pdo, array $spec, array $referrers): array
{
    $stillReferenced = '';
    foreach ($referrers[$spec['object']] ?? [] as [$table, $column]) {
        $stillReferenced .= " AND NOT EXISTS (SELECT 1 FROM $table r WHERE r.account_id = t.account_id AND r.$column = t.public_id)";
    }

    $select = $pdo->prepare(
        "SELECT t.id, t.public_id FROM {$spec['table']} t
          WHERE ((t.deleted_at IS NOT NULL AND t.deleted_at < NOW() - INTERVAL ? DAY)
             OR (t.import_status = 'rejected' AND t.updated_at < NOW() - INTERVAL ? DAY))
          $stillReferenced
          LIMIT " . API_RETENTION_CHUNK
    );

    $rows = 0;
    $lineItems = 0;
    do {
        $select->execute([API_RETENTION_DAYS, API_RETENTION_DAYS]);
        $chunk = $select->fetchAll();
        if ($chunk === []) {
            break;
        }

        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $pdo->beginTransaction();
        if (!empty($spec['line_items'])) {
            $stmt = $pdo->prepare(
                "DELETE FROM api_line_items WHERE parent_type = ? AND parent_public_id IN ($placeholders)"
            );
            $stmt->execute(array_merge([$spec['object']], array_column($chunk, 'public_id')));
            $lineItems += $stmt->rowCount();
        }
        $stmt = $pdo->prepare("DELETE FROM {$spec['table']} WHERE id IN ($placeholders)");
        $stmt->execute(array_column($chunk, 'id'));
        $rows += $stmt->rowCount();
        $pdo->commit();
    } while (count($chunk) === API_RETENTION_CHUNK);

    return [$rows, $lineItems];
}

/**
 * Runs the purge over every resource table. Returns [objects deleted, line items deleted].
 *
 * Objects that point at others go first (refunds at revenue, revenue at customers), so a dead
 * customer whose only reference was a dead sale goes in the same run.
 */
function api_retention_purge_all(PDO $pdo): array
{
    $referrers = api_retention_referrers();
    $objects = 0;
    $lineItems = 0;

    foreach (array_reverse(api_resource_definitions()) as $spec) {
        [$rows, $items] = api_retention_purge($pdo, $spec, $referrers);
        $objects += $rows;
        $lineItems += $items;
    }

    return [$objects, $lineItems];
}
