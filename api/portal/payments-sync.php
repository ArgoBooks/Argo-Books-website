<?php
/**
 * Portal Payments Sync API Endpoint
 *
 * GET  /api/portal/payments-sync?since={timestamp} - Pull new payments (Argo Books syncing)
 * POST /api/portal/payments-sync                    - Confirm payments as synced
 *
 * Requires API key authentication (Argo Books -> Server).
 */

require_once __DIR__ . '/portal-helper.php';

set_portal_headers();
require_method(['GET', 'POST']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

$companyId = $company['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handle_pull_payments($companyId);
} else {
    handle_confirm_sync($companyId);
}

/**
 * GET: Pull new payments since last sync
 */
function handle_pull_payments(int $companyId): void
{
    global $pdo;
    $since = $_GET['since'] ?? null;
    $force = ($_GET['force'] ?? '0') === '1';

    // Joined to refund_requests on provider_refund_id so a refund row carries its request id and reason into the desktop's books.
    if ($force) {
        $stmt = $pdo->prepare(
            "SELECT pp.*, pi.invoice_token, pi.customer_token,
                    rr.id AS refund_request_id, rr.reason AS refund_reason,
                    rr.provider AS refund_provider,
                    rr.provider_payment_id AS refund_source_provider_payment_id,
                    rr.line_items_json AS refund_line_items_json
             FROM portal_payments pp
             LEFT JOIN portal_invoices pi ON pp.company_id = pi.company_id AND pp.invoice_id = pi.invoice_id
             LEFT JOIN refund_requests rr ON rr.company_id = pp.company_id
                                          AND rr.state = 'completed'
                                          AND (rr.provider_refund_id = pp.provider_payment_id
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_refund_id)
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_payment_id))
             WHERE pp.company_id = ?
             ORDER BY pp.created_at ASC"
        );
        $params = [$companyId];
    } elseif ($since) {
        $stmt = $pdo->prepare(
            "SELECT pp.*, pi.invoice_token, pi.customer_token,
                    rr.id AS refund_request_id, rr.reason AS refund_reason,
                    rr.provider AS refund_provider,
                    rr.provider_payment_id AS refund_source_provider_payment_id,
                    rr.line_items_json AS refund_line_items_json
             FROM portal_payments pp
             LEFT JOIN portal_invoices pi ON pp.company_id = pi.company_id AND pp.invoice_id = pi.invoice_id
             LEFT JOIN refund_requests rr ON rr.company_id = pp.company_id
                                          AND rr.state = 'completed'
                                          AND (rr.provider_refund_id = pp.provider_payment_id
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_refund_id)
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_payment_id))
             WHERE pp.company_id = ?
               AND (pp.synced_to_argo = 0 OR pp.created_at > ?)
             ORDER BY pp.created_at ASC"
        );
        $params = [$companyId, $since];
    } else {
        $stmt = $pdo->prepare(
            "SELECT pp.*, pi.invoice_token, pi.customer_token,
                    rr.id AS refund_request_id, rr.reason AS refund_reason,
                    rr.provider AS refund_provider,
                    rr.provider_payment_id AS refund_source_provider_payment_id,
                    rr.line_items_json AS refund_line_items_json
             FROM portal_payments pp
             LEFT JOIN portal_invoices pi ON pp.company_id = pi.company_id AND pp.invoice_id = pi.invoice_id
             LEFT JOIN refund_requests rr ON rr.company_id = pp.company_id
                                          AND rr.state = 'completed'
                                          AND (rr.provider_refund_id = pp.provider_payment_id
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_refund_id)
                                               OR pp.provider_payment_id = CONCAT('refund_', rr.provider_payment_id))
             WHERE pp.company_id = ? AND pp.synced_to_argo = 0
             ORDER BY pp.created_at ASC"
        );
        $params = [$companyId];
    }

    $stmt->execute($params);

    $payments = [];
    while ($row = $stmt->fetch()) {
        $isRefund = $row['status'] === 'refunded' && (float)$row['amount'] < 0;

        // For refund rows: figure out the originating payment's provider_payment_id so the desktop can link the local refund Payment back to its source.
        $refundedProviderPaymentId = null;
        if ($isRefund) {
            if (!empty($row['refund_source_provider_payment_id'])) {
                $refundedProviderPaymentId = $row['refund_source_provider_payment_id'];
            } elseif (!empty($row['provider_transaction_id'])) {
                $refundedProviderPaymentId = $row['provider_transaction_id'];
            } elseif (str_starts_with((string)$row['provider_payment_id'], 'refund_')) {
                $refundedProviderPaymentId = substr((string)$row['provider_payment_id'], strlen('refund_'));
            }
        }

        $payments[] = [
            'id' => (int) $row['id'],
            'invoiceId' => $row['invoice_id'],
            'customerName' => $row['customer_name'],
            'amount' => floatval($row['amount']),
            'processingFee' => floatval($row['processing_fee']),
            'currency' => $row['currency'],
            'paymentMethod' => $row['payment_method'],
            'providerPaymentId' => $row['provider_payment_id'],
            'providerTransactionId' => $row['provider_transaction_id'],
            'referenceNumber' => $row['reference_number'],
            'status' => $row['status'],
            'syncedToArgo' => (bool) $row['synced_to_argo'],
            'createdAt' => date('c', strtotime($row['created_at'])),
            // Refund-specific fields (null on regular payments)
            'isRefund' => $isRefund,
            'refundedProviderPaymentId' => $refundedProviderPaymentId,
            'refundRequestId' => $row['refund_request_id'] ? (int)$row['refund_request_id'] : null,
            'refundReason' => $row['refund_reason'] ?? null,
            'depositAmount' => $isRefund ? refund_deposit_amount($row['refund_line_items_json'] ?? null) : null,
        ];
    }

    send_json_response(200, [
        'success' => true,
        'payments' => $payments,
        'count' => count($payments),
        'syncTimestamp' => date('c')
    ]);
}

/**
 * The part of a refund that gave back the invoice's security deposit, from the lines picked in
 * the desktop's refund form. A deposit isn't revenue, so the desktop keeps that part off it.
 * Null when the refund wasn't made through the form (e.g. the Stripe Dashboard).
 */
function refund_deposit_amount(?string $lineItemsJson): ?float
{
    if ($lineItemsJson === null || $lineItemsJson === '') {
        return null;
    }
    $items = json_decode($lineItemsJson, true);
    if (!is_array($items)) {
        return null;
    }
    $sum = 0.0;
    foreach ($items as $item) {
        if (is_array($item) && ($item['kind'] ?? null) === 'deposit') {
            $sum += abs((float) ($item['amount'] ?? 0));
        }
    }
    return $sum;
}

/**
 * POST: Confirm payments as synced to Argo Books
 */
function handle_confirm_sync(int $companyId): void
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
    }

    $paymentIds = $data['paymentIds'] ?? $data['payment_ids'] ?? [];

    if (empty($paymentIds) || !is_array($paymentIds)) {
        send_error_response(400, 'Missing or invalid payment_ids array.', 'MISSING_FIELDS');
    }

    // Sanitize IDs
    $paymentIds = array_map('intval', $paymentIds);
    $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));

    global $pdo;

    // Mark payments as synced (only for this company's payments)
    $stmt = $pdo->prepare(
        "UPDATE portal_payments
         SET synced_to_argo = 1
         WHERE company_id = ? AND id IN ({$placeholders})"
    );

    $params = array_merge([$companyId], $paymentIds);
    $stmt->execute($params);
    $affectedRows = $stmt->rowCount();

    send_json_response(200, [
        'success' => true,
        'syncedCount' => $affectedRows,
        'message' => "{$affectedRows} payment(s) marked as synced.",
        'timestamp' => date('c')
    ]);
}
