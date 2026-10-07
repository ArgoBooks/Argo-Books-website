<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

/**
 * Initialize the Stripe SDK with the right key for this company's environment.
 */
function refund_stripe_init(array $company): void {
    $env = $company['environment'] ?? 'production';
    $key = ($env === 'production')
        ? ($_ENV['STRIPE_LIVE_SECRET_KEY'] ?? '')
        : ($_ENV['STRIPE_SANDBOX_SECRET_KEY'] ?? '');
    if (empty($key)) {
        throw new RuntimeException("Stripe key not configured for environment: $env");
    }
    \Stripe\Stripe::setApiKey($key);
}

/**
 * Pre-flight: confirm the payment_intent exists, isn't disputed/refunded/too-old,
 * and has at least $requested_cents refundable. Echoes JSON + exits on failure.
 *
 * Returns the still-refundable amount in cents on success.
 */
function refund_stripe_preflight(array $company, string $payment_intent_id, int $requested_cents): int {
    refund_stripe_init($company);
    $stripe_account = $company['stripe_account_id'] ?? null;

    try {
        $pi = \Stripe\PaymentIntent::retrieve(
            ['id' => $payment_intent_id, 'expand' => ['latest_charge']],
            $stripe_account ? ['stripe_account' => $stripe_account] : []
        );
    } catch (\Stripe\Exception\ApiErrorException $e) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'PROVIDER_PAYMENT_NOT_FOUND',
            'message' => $e->getMessage(),
        ]);
        exit;
    }

    $charge = $pi->latest_charge;
    if (!$charge) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'NO_CHARGEABLE', 'message' => 'No charge attached to this payment intent.']);
        exit;
    }
    if ($charge->refunded) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'ALREADY_FULLY_REFUNDED']);
        exit;
    }
    if ((time() - (int)$charge->created) > 86400 * 180) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'CHARGE_TOO_OLD',
            'message' => 'This payment is older than 180 days and cannot be refunded automatically.',
        ]);
        exit;
    }
    if (!empty($charge->disputed)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'CHARGE_DISPUTED',
            'message' => "Refunds aren't allowed during a dispute. Resolve in your Stripe dashboard.",
        ]);
        exit;
    }

    $refundable = (int)$charge->amount - (int)$charge->amount_refunded;
    if ($requested_cents > $refundable) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'AMOUNT_EXCEEDS_REFUNDABLE',
            'refundable_cents' => $refundable,
        ]);
        exit;
    }
    return $refundable;
}

/**
 * Issue the refund. Returns the Stripe Refund object as an associative array.
 * Throws on failure.
 */
function refund_stripe_issue(array $company, array $request): array {
    refund_stripe_init($company);
    $stripe_account = $company['stripe_account_id'] ?? null;
    $opts = $stripe_account ? ['stripe_account' => $stripe_account] : [];
    // Held to 45 characters because PayPal and Square cap there while Stripe allows 255, so one retry policy works across all three.
    $opts['idempotency_key'] = substr('argo_request_' . (string)$request['id'], 0, 45);

    $refund = \Stripe\Refund::create([
        'payment_intent' => $request['provider_payment_id'],
        'amount' => (int)$request['amount_cents'],
        'reason' => 'requested_by_customer',
        'metadata' => [
            'argo_request_id' => (string)$request['id'],
            'argo_invoice_id' => $request['invoice_id'],
            'argo_invoice_number' => $request['invoice_number'],
        ],
    ], $opts);

    return $refund->toArray();
}
