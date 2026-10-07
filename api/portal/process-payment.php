<?php
/**
 * Portal Process Payment Endpoint
 *
 * POST /api/portal/process-payment - Finalize a payment after client-side confirmation
 *
 * Called after Stripe confirmCardPayment() or PayPal order capture to record
 * the payment in our database and update the invoice balance.
 */

require_once __DIR__ . '/portal-helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

set_portal_headers();
require_method(['POST']);

// Rate limit payment attempts (20 per IP per 15 minutes)
enforce_payment_rate_limit();

// Parse request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error_response(400, 'Invalid JSON: ' . json_last_error_msg(), 'INVALID_JSON');
}

// Validate required fields
$required = ['invoice_token', 'method', 'amount'];
$missing = [];
foreach ($required as $field) {
    if (!isset($data[$field]) || ($data[$field] === '' && $data[$field] !== 0)) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    send_error_response(400, 'Missing required fields: ' . implode(', ', $missing), 'MISSING_FIELDS');
}

$invoice = get_invoice_by_token($data['invoice_token']);
if (!$invoice) {
    send_error_response(404, 'Invoice not found.', 'NOT_FOUND');
}

$method = strtolower($data['method']);
$amount = floatval($data['amount']);
$referenceNumber = generate_reference_number();
$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

// payment_intent.succeeded can be recorded before the browser gets here, and by then it has already cleared the balance.
$alreadyRecorded = $method === 'stripe'
    && is_string($data['payment_intent_id'] ?? null)
    && find_recorded_portal_payment($data['payment_intent_id'], (int) $invoice['company_id'], $invoice['invoice_id']) !== null;

// Calculate processing fee if enabled for this invoice
$passProcessingFee = !empty($invoice['pass_processing_fee']);
$processingFee = 0.00;
if ($passProcessingFee) {
    require_once __DIR__ . '/../../config/pricing.php';
    $processingFee = calculate_invoice_processing_fee(
        floatval($invoice['balance_due']),
        strtoupper($invoice['currency'] ?: 'USD')
    );
}

// Use integer cents for precise monetary comparisons
$amountCents = (int) round($amount * 100);
$balanceDueCents = (int) round(floatval($invoice['balance_due']) * 100);
$expectedTotalCents = $balanceDueCents + (int) round($processingFee * 100);
$invoiceStatus = $invoice['status'] ?? '';

if ($amountCents <= 0) {
    send_error_response(400, 'Payment amount must be greater than zero.', 'INVALID_AMOUNT');
}
if (!$alreadyRecorded) {
    if ($passProcessingFee) {
        if (abs($amountCents - $expectedTotalCents) > 1) {
            send_error_response(400, 'Payment amount must equal the balance due plus processing fee.', 'INVALID_AMOUNT_WITH_FEE');
        }
    } else {
        if ($amountCents > $balanceDueCents + 1) {
            send_error_response(400, 'Payment amount exceeds balance due.', 'EXCEEDS_BALANCE');
        }
    }
    if (in_array($invoiceStatus, ['paid', 'cancelled'])) {
        send_error_response(400, 'This invoice is not payable.', 'NOT_PAYABLE');
    }
    if ($balanceDueCents <= 0) {
        send_error_response(400, 'This invoice has no balance due.', 'NO_BALANCE');
    }
}

try {
    switch ($method) {
        case 'stripe':
            process_stripe_payment($invoice, $data, $amount, $referenceNumber, $processingFee, $alreadyRecorded);
            break;
        case 'paypal':
            process_paypal_payment($invoice, $data, $amount, $referenceNumber, $processingFee);
            break;
        default:
            // Square payments are processed directly in checkout.php
            send_error_response(400, 'Invalid payment method for this endpoint.', 'INVALID_METHOD');
    }
} catch (Exception $e) {
    error_log('Portal process-payment error: ' . $e->getMessage());
    send_error_response(500, 'Payment processing failed. Please try again.', 'PAYMENT_ERROR');
}

/**
 * Process a confirmed Stripe payment
 */
function process_stripe_payment(array $invoice, array $data, float $amount, string $referenceNumber, float $processingFee = 0.00, bool $alreadyRecorded = false): void
{
    global $is_production;

    $paymentIntentId = $data['payment_intent_id'] ?? '';
    if (empty($paymentIntentId)) {
        send_error_response(400, 'Missing payment_intent_id.', 'MISSING_FIELD');
    }

    // Validate payment intent ID format (Stripe IDs are alphanumeric with underscores)
    if (!preg_match('/^pi_[A-Za-z0-9]+$/', $paymentIntentId)) {
        send_error_response(400, 'Invalid payment intent ID format.', 'INVALID_PAYMENT_INTENT');
    }

    // Verify the payment intent status with Stripe
    $stripeAccountId = $invoice['stripe_account_id'] ?? '';
    if (empty($stripeAccountId)) {
        send_error_response(400, 'Stripe is not configured for this business.', 'STRIPE_NOT_CONNECTED');
    }
    $stripe_secret_key = $is_production
        ? $_ENV['STRIPE_LIVE_SECRET_KEY']
        : $_ENV['STRIPE_SANDBOX_SECRET_KEY'];

    \Stripe\Stripe::setApiKey($stripe_secret_key);

    $paymentIntent = \Stripe\PaymentIntent::retrieve(
        $paymentIntentId,
        ['stripe_account' => $stripeAccountId]
    );

    if ($paymentIntent->status !== 'succeeded') {
        send_error_response(400, 'Payment has not been completed. Status: ' . $paymentIntent->status, 'PAYMENT_NOT_COMPLETED');
    }

    // Verify amount matches
    $paidAmountCents = $paymentIntent->amount;
    $expectedCents = (int) round($amount * 100);
    if (abs($paidAmountCents - $expectedCents) > 1) { // 1 cent tolerance
        error_log("Portal Stripe amount mismatch for invoice " . $invoice['invoice_id'] . " (PI: $paymentIntentId)");
        send_error_response(400, 'Payment amount mismatch.', 'AMOUNT_MISMATCH');
    }

    if ($alreadyRecorded) {
        // The balance the fee was computed from is gone, so take the fee checkout charged. record_portal_payment fills in a missing fee on a duplicate and never lowers one.
        $processingFee = stripe_metadata_processing_fee($paymentIntent->metadata, $amount);
    }

    $result = record_portal_payment([
        'company_id' => $invoice['company_id'],
        'invoice_id' => $invoice['invoice_id'],
        'customer_name' => $invoice['customer_name'],
        'amount' => $amount,
        'processing_fee' => $processingFee,
        'currency' => strtoupper($invoice['currency'] ?: 'usd'),
        'payment_method' => 'stripe',
        'provider_payment_id' => $paymentIntentId,
        'provider_transaction_id' => $paymentIntent->latest_charge ?? $paymentIntentId,
        'reference_number' => $referenceNumber,
        'status' => 'completed',
        'payment_environment' => $is_production ? 'production' : 'sandbox',
    ]);

    if (!$result['success']) {
        send_error_response(500, $result['message'], 'RECORD_FAILED');
    }

    // Send payment confirmation email (best-effort, don't block the response on failure)
    if (!empty($invoice['customer_email'])) {
        send_payment_confirmation([
            'customerEmail' => $invoice['customer_email'],
            'customerName' => $invoice['customer_name'] ?? '',
            'companyName' => $invoice['company_name'] ?? '',
            'invoiceId' => $invoice['invoice_id'],
            'amount' => $amount,
            'currency' => strtoupper($invoice['currency'] ?: 'usd'),
            'referenceNumber' => $result['reference_number'],
            'paymentMethod' => 'stripe',
        ]);
    }

    send_json_response(200, [
        'success' => true,
        'method' => 'stripe',
        'reference_number' => $result['reference_number'],
        'amount' => $amount,
        'currency' => strtoupper($invoice['currency'] ?: 'usd'),
        'invoice_id' => $invoice['invoice_id'],
        'timestamp' => date('c')
    ]);
}

/**
 * Process a confirmed PayPal payment
 *
 * Defensive guard: PayPal portal payments are intentionally disabled. The
 * customer-facing UI does not render the PayPal button (see
 * get_available_payment_methods in portal-helper.php) and checkout.php
 * rejects PayPal at the order-create step, so this function should never
 * be reached through the normal flow. If a stale client still POSTs here,
 * fail closed with a clear 503.
 */
function process_paypal_payment(array $invoice, array $data, float $amount, string $referenceNumber, float $processingFee = 0.00): void
{
    send_error_response(503, 'PayPal is not currently supported. Pay with Stripe or Square instead.', 'PROVIDER_UNSUPPORTED');
}
