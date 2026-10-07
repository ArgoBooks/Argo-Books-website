<?php
declare(strict_types=1);

/**
 * Square refund provider adapter.
 *
 * The Square SDK is loaded via Composer (square/square ^42.1). Refunds use
 * the same access token + location as the original payment.
 *
 * The desktop stores the Square Payment ID as Payment.ProviderPaymentId.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../db_connect.php'; // for portal_decrypt

function refund_square_get_client(array $company)
{
    if (empty($company['square_access_token'])) {
        throw new RuntimeException('Square access token not configured for company.');
    }
    $token = portal_decrypt($company['square_access_token']);
    // Keyed off the company row as Stripe and PayPal are, so an APP_ENV flip cannot send a sandbox company to live Square: the base URL must match the token.
    $isProduction = ($company['environment'] ?? 'sandbox') === 'production';
    $baseUrl = $isProduction
        ? \Square\Environments::Production->value
        : \Square\Environments::Sandbox->value;

    return new \Square\SquareClient(
        token: $token,
        version: '2026-01-22',
        options: ['baseUrl' => $baseUrl],
    );
}

/**
 * Fetch the payment, validate it's refundable. Echoes JSON + exits on failure.
 */
function refund_square_preflight(array $company, string $payment_id, int $requested_cents): int {
    try {
        $client = refund_square_get_client($company);
        $resp = $client->payments->get(
            new \Square\Payments\Requests\GetPaymentsRequest(['paymentId' => $payment_id])
        );
        $payment = $resp->getPayment();
    } catch (\Throwable $e) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'PROVIDER_PAYMENT_NOT_FOUND', 'message' => $e->getMessage()]);
        exit;
    }
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'PROVIDER_PAYMENT_NOT_FOUND']);
        exit;
    }
    $status = method_exists($payment, 'getStatus') ? $payment->getStatus() : null;
    if ($status !== 'COMPLETED' && $status !== 'APPROVED') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'NOT_REFUNDABLE', 'message' => "Payment is in state: $status"]);
        exit;
    }
    $totalMoney = method_exists($payment, 'getTotalMoney') ? $payment->getTotalMoney() : null;
    $refundedMoney = method_exists($payment, 'getRefundedMoney') ? $payment->getRefundedMoney() : null;
    $totalCents = $totalMoney ? (int)$totalMoney->getAmount() : 0;
    $refundedCents = $refundedMoney ? (int)$refundedMoney->getAmount() : 0;
    $refundable = max(0, $totalCents - $refundedCents);
    if ($requested_cents > $refundable) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'AMOUNT_EXCEEDS_REFUNDABLE', 'refundable_cents' => $refundable]);
        exit;
    }
    return $refundable;
}

/**
 * Issue the Square refund. Returns the SDK response as an associative array
 * with at least ['refund' => ['id' => ...]].
 */
function refund_square_issue(array $company, array $request): array {
    $client = refund_square_get_client($company);
    // Square caps idempotency_key at 45 chars; substr keeps us safe even if
    // refund_requests.id ever grows past 32 digits (it won't, but be explicit).
    $idempotency = substr('argo_request_' . (string)$request['id'], 0, 45);

    $body = new \Square\Refunds\Requests\RefundPaymentRequest([
        'idempotencyKey' => $idempotency,
        'amountMoney' => new \Square\Types\Money([
            'amount' => (int)$request['amount_cents'],
            'currency' => (string)$request['currency'],
        ]),
        'paymentId' => (string)$request['provider_payment_id'],
        'reason' => substr((string)($request['reason'] ?? 'Refund'), 0, 192),
    ]);

    try {
        $resp = $client->refunds->refundPayment($body);
    } catch (\Throwable $e) {
        throw new RuntimeException('square_refund_failed: ' . $e->getMessage());
    }

    $refund = method_exists($resp, 'getRefund') ? $resp->getRefund() : null;
    return [
        'refund' => $refund ? [
            'id' => method_exists($refund, 'getId') ? $refund->getId() : null,
            'status' => method_exists($refund, 'getStatus') ? $refund->getStatus() : null,
        ] : null,
    ];
}
