<?php
/**
 * Portal Quotes Sync API Endpoint
 *
 * GET  /api/portal/quotes/sync         - Pull customer answers Argo Books has not applied yet
 * POST /api/portal/quotes/sync/confirm - Confirm those answers as applied
 *
 * Requires API key authentication (Argo Books -> Server). Same pull-then-confirm shape as
 * payment sync: the flag, not a timestamp, is what stops an answer being applied twice.
 */

require_once __DIR__ . '/quote-helper.php';

set_portal_headers();
require_method(['GET', 'POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handle_pull_quote_responses((int) $company['id']);
} else {
    handle_confirm_quote_sync((int) $company['id']);
}

/**
 * GET: answers the desktop app has not confirmed yet
 */
function handle_pull_quote_responses(int $companyId): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT quote_id, status, responded_at, response_note
         FROM portal_quotes
         WHERE company_id = ? AND synced_to_argo = 0 AND status IN ('accepted', 'declined')
         ORDER BY responded_at ASC"
    );
    $stmt->execute([$companyId]);

    $quotes = [];
    foreach ($stmt->fetchAll() as $row) {
        $quotes[] = [
            'quoteId' => $row['quote_id'],
            'status' => $row['status'],
            'respondedAt' => portal_iso_datetime($row['responded_at']),
            'responseNote' => $row['response_note'],
        ];
    }

    send_json_response(200, [
        'success' => true,
        'quotes' => $quotes,
        'count' => count($quotes),
        'syncTimestamp' => date('c')
    ]);
}

/**
 * POST: mark answers as applied
 */
function handle_confirm_quote_sync(int $companyId): void
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
    }

    $quoteIds = $data['quoteIds'] ?? $data['quote_ids'] ?? [];
    if (empty($quoteIds) || !is_array($quoteIds)) {
        send_error_response(400, 'Missing or invalid quoteIds array.', 'MISSING_FIELDS');
    }

    $quoteIds = array_slice(array_map(static fn($id) => quote_clean($id, 100), $quoteIds), 0, 500);
    $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));

    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE portal_quotes SET synced_to_argo = 1
         WHERE company_id = ? AND quote_id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$companyId], $quoteIds));
    $affectedRows = $stmt->rowCount();

    send_json_response(200, [
        'success' => true,
        'syncedCount' => $affectedRows,
        'message' => "{$affectedRows} quote answer(s) marked as synced.",
        'timestamp' => date('c')
    ]);
}
