<?php
/**
 * Portal Checkout API Endpoint
 *
 * POST /api/portal/checkout - Create a payment session for an invoice
 *
 * Handles creating payment intents/orders for Stripe, PayPal, and Square.
 * Money flows to the Argo Books user's connected account (Stripe Connect,
 * PayPal for Marketplaces, Square OAuth), NOT to ArgoRobots.
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

// Validate invoice is payable
if ($invoice['status'] === 'paid') {
    send_error_response(400, 'This invoice has already been paid.', 'ALREADY_PAID');
}
if ($invoice['status'] === 'cancelled') {
    send_error_response(400, 'This invoice has been cancelled.', 'CANCELLED');
}

// Calculate processing fee if enabled for this invoice
$passProcessingFee = !empty($invoice['pass_processing_fee']);
$processingFee = 0.00;
if ($passProcessingFee) {
    require_once __DIR__ . '/../../config/pricing.php';
    $processingFee = calculate_invoice_processing_fee(
        floatval($invoice['balance_due']),
        $invoice['currency'] ?: 'USD'
    );
}

// Validate payment amount using integer cents for precision
$requestedAmount = floatval($data['amount']);
$amountCents = (int) round($requestedAmount * 100);
$balanceDueCents = (int) round(floatval($invoice['balance_due']) * 100);
$expectedTotalCents = $balanceDueCents + (int) round($processingFee * 100);

if ($amountCents <= 0) {
    send_error_response(400, 'Payment amount must be greater than zero.', 'INVALID_AMOUNT');
}
if ($passProcessingFee) {
    // When fee is enabled, require the exact fee-inclusive total (within 1 cent tolerance)
    if (abs($amountCents - $expectedTotalCents) > 1) {
        send_error_response(400, 'Payment amount must equal the balance due plus processing fee.', 'INVALID_AMOUNT_WITH_FEE');
    }
} else {
    if ($amountCents > $balanceDueCents + 1) { // 1 cent tolerance
        send_error_response(400, 'Payment amount exceeds balance due.', 'AMOUNT_EXCEEDS_BALANCE');
    }
}

$method = strtolower($data['method']);
$currency = strtolower($invoice['currency'] ?: 'usd');
$companyId = $invoice['company_id'];

// Get environment-based keys
$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

try {
    switch ($method) {
        case 'stripe':
            handle_stripe_checkout($invoice, $amountCents, $currency, $requestedAmount, $data, $processingFee);
            break;
        case 'paypal':
            handle_paypal_checkout($invoice, $requestedAmount, $currency, $data);
            break;
        case 'square':
            handle_square_checkout($invoice, $amountCents, $currency, $requestedAmount, $data, $processingFee);
            break;
        default:
            send_error_response(400, 'Invalid payment method. Must be stripe, paypal, or square.', 'INVALID_METHOD');
    }
} catch (Exception $e) {
    error_log('Portal checkout error: ' . $e->getMessage());
    send_error_response(500, 'Payment processing failed. Please try again.', 'PAYMENT_ERROR');
}

/**
 * Create a Stripe PaymentIntent using Stripe Connect (money goes to the business's Stripe account)
 */
function handle_stripe_checkout(array $invoice, int $amountCents, string $currency, float $amount, array $data, float $processingFee = 0.00): void
{
    global $is_production;

    $stripeAccountId = $invoice['stripe_account_id'] ?? '';
    if (empty($stripeAccountId)) {
        send_error_response(400, 'Stripe is not configured for this business.', 'STRIPE_NOT_CONNECTED');
    }

    $stripe_secret_key = $is_production
        ? $_ENV['STRIPE_LIVE_SECRET_KEY']
        : $_ENV['STRIPE_SANDBOX_SECRET_KEY'];

    \Stripe\Stripe::setApiKey($stripe_secret_key);

    $paymentIntentParams = [
        'amount' => $amountCents,
        'currency' => $currency,
        'payment_method_types' => ['card'],
        'application_fee_amount' => 0, // Free feature - no platform fee
        'metadata' => [
            'portal_invoice_id' => $invoice['invoice_id'],
            'portal_company_id' => $invoice['company_id'],
            'customer_name' => $invoice['customer_name'],
            // Read back by the webhook and process-payment, which can't
            // recompute it once the payment has cleared the balance.
            'processing_fee' => number_format($processingFee, 2, '.', ''),
        ],
        'description' => 'Invoice ' . $invoice['invoice_id'] . ' - ' . ($invoice['company_name'] ?? ''),
    ];

    // Add receipt email if provided
    if (!empty($data['email'])) {
        $paymentIntentParams['receipt_email'] = $data['email'];
    }

    // Create PaymentIntent on the connected account
    $paymentIntent = \Stripe\PaymentIntent::create(
        $paymentIntentParams,
        ['stripe_account' => $stripeAccountId]
    );

    send_json_response(200, [
        'success' => true,
        'method' => 'stripe',
        'client_secret' => $paymentIntent->client_secret,
        'payment_intent_id' => $paymentIntent->id,
        'stripe_account_id' => $stripeAccountId,
        'amount' => $amount,
        'currency' => $currency,
        'timestamp' => date('c')
    ]);
}

/**
 * Handle PayPal checkout (returns order details for client-side approval)
 *
 * PayPal portal payments are intentionally disabled at this layer. The
 * customer-facing invoice page does not render the PayPal button (see
 * get_available_payment_methods in portal-helper.php), so this function
 * should never be reached through the normal flow, but if a stale client
 * or direct POST still calls it, we return a clear 503.
 */
function handle_paypal_checkout(array $invoice, float $amount, string $currency, array $data): void
{
    send_error_response(503, 'PayPal is not currently supported. Pay with Stripe or Square instead.', 'PROVIDER_UNSUPPORTED');
}

/**
 * Handle Square checkout
 */
function handle_square_checkout(array $invoice, int $amountCents, string $currency, float $amount, array $data, float $processingFee = 0.00): void
{
    global $is_production;

    $squareMerchantId = $invoice['square_merchant_id'] ?? '';
    if (empty($squareMerchantId)) {
        send_error_response(400, 'Square is not configured for this business.', 'SQUARE_NOT_CONNECTED');
    }

    // For Square with OAuth, the payment is processed using the business's access token.
    // Get the encrypted access token from the company record.
    global $pdo;
    $stmt = $pdo->prepare('SELECT square_access_token, square_location_id FROM portal_companies WHERE id = ? LIMIT 1');
    $stmt->execute([$invoice['company_id']]);
    $company = $stmt->fetch();

    if (empty($company['square_access_token'])) {
        send_error_response(400, 'Square is not properly configured for this business.', 'SQUARE_NOT_CONFIGURED');
    }

    // If a source_id is provided, process the payment directly
    if (!empty($data['source_id'])) {
        process_square_payment($invoice, $company, $data, $amountCents, $currency, $amount, $processingFee);
        return;
    }

    // Otherwise return config for client-side card form
    $squareAppId = $is_production
        ? $_ENV['SQUARE_LIVE_APP_ID']
        : $_ENV['SQUARE_SANDBOX_APP_ID'];

    send_json_response(200, [
        'success' => true,
        'method' => 'square',
        'app_id' => $squareAppId,
        'location_id' => $company['square_location_id'] ?? '',
        'invoice_id' => $invoice['invoice_id'],
        'amount' => $amount,
        'currency' => strtoupper($currency),
        'timestamp' => date('c')
    ]);
}

/**
 * Process a Square payment using the business's access token
 */
function process_square_payment(array $invoice, array $company, array $data, int $amountCents, string $currency, float $amount, float $processingFee = 0.00): void
{
    global $is_production;

    $accessToken = portal_decrypt($company['square_access_token']);
    $locationId = $company['square_location_id'] ?? '';
    $apiBaseUrl = $is_production
        ? 'https://connect.squareup.com/v2'
        : 'https://connect.squareupsandbox.com/v2';

    // Derive idempotency key from invoice+amount+source so retries reuse the same key, but clients cannot manipulate it (server-side HMAC with encryption key as secret).
    $idempotencyKey = substr(hash_hmac('sha256', $invoice['invoice_id'] . ':' . $amountCents . ':' . $data['source_id'], $_ENV['PORTAL_ENCRYPTION_KEY'] ?? ''), 0, 45);
    $referenceNumber = generate_reference_number();

    $paymentData = [
        'source_id' => $data['source_id'],
        'idempotency_key' => $idempotencyKey,
        'amount_money' => [
            'amount' => $amountCents,
            'currency' => strtoupper($currency)
        ],
        'autocomplete' => true,
        'location_id' => $locationId,
        'reference_id' => $invoice['invoice_id'],
        'note' => 'Invoice ' . $invoice['invoice_id']
    ];

    $ch = curl_init("$apiBaseUrl/payments");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => [
            "Square-Version: 2026-01-22",
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        $paymentResult = json_decode($responseData, true);

        if (isset($paymentResult['payment']) && $paymentResult['payment']['status'] === 'COMPLETED') {
            $payment = $paymentResult['payment'];

            $result = record_portal_payment([
                'company_id' => $invoice['company_id'],
                'invoice_id' => $invoice['invoice_id'],
                'customer_name' => $invoice['customer_name'],
                'amount' => $amount,
                'processing_fee' => $processingFee,
                'currency' => strtoupper($currency),
                'payment_method' => 'square',
                'provider_payment_id' => $payment['id'],
                'provider_transaction_id' => $payment['id'],
                'reference_number' => $referenceNumber,
                'status' => 'completed',
                'payment_environment' => $is_production ? 'production' : 'sandbox',
            ]);

            // Send payment confirmation email (best-effort, don't block the response on failure)
            if (!empty($invoice['customer_email'])) {
                send_payment_confirmation([
                    'customerEmail' => $invoice['customer_email'],
                    'customerName' => $invoice['customer_name'] ?? '',
                    'companyName' => $invoice['company_name'] ?? '',
                    'invoiceId' => $invoice['invoice_id'],
                    'amount' => $amount,
                    'currency' => strtoupper($currency),
                    'referenceNumber' => $referenceNumber,
                    'paymentMethod' => 'square',
                ]);
            }

            send_json_response(200, [
                'success' => true,
                'method' => 'square',
                'reference_number' => $referenceNumber,
                'provider_payment_id' => $payment['id'],
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'invoice_id' => $invoice['invoice_id'],
                'timestamp' => date('c')
            ]);
        } else {
            send_error_response(400, 'Payment was not completed.', 'PAYMENT_INCOMPLETE');
        }
    } else {
        $errors = json_decode($responseData, true);
        $errorDetail = $errors['errors'][0]['detail'] ?? 'Unknown error';
        error_log("Square portal payment error for invoice " . $invoice['invoice_id'] . ": $errorDetail");
        send_error_response(500, 'Square payment failed. Please try again.', 'SQUARE_ERROR');
    }
}
