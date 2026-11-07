<?php
/**
 * Stripe Webhook for Portal Payments
 *
 * POST /api/portal/webhooks/stripe
 *
 * Handles Stripe Connect webhook events for invoice payments.
 * This is a backup confirmation - payments are also confirmed client-side
 * via process-payment.php. The webhook ensures we don't miss payments
 * even if the customer closes their browser before the confirmation page.
 */

require_once __DIR__ . '/../portal-helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/_stripe_refund_db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';

$webhook_secret = $is_production
    ? ($_ENV['STRIPE_LIVE_PORTAL_WEBHOOK_SECRET'] ?? '')
    : ($_ENV['STRIPE_SANDBOX_PORTAL_WEBHOOK_SECRET'] ?? '');

if (empty($webhook_secret)) {
    error_log('Portal Stripe webhook: No webhook secret configured (env: ' . ($is_production ? 'production' : 'sandbox') . ')');
    http_response_code(500);
    echo json_encode(['error' => 'Webhook not configured']);
    exit;
}

$stripe_secret_key = $is_production
    ? ($_ENV['STRIPE_LIVE_SECRET_KEY'] ?? '')
    : ($_ENV['STRIPE_SANDBOX_SECRET_KEY'] ?? '');

\Stripe\Stripe::setApiKey($stripe_secret_key);

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (\UnexpectedValueException $e) {
    error_log('Portal Stripe webhook: Invalid payload');
    http_response_code(400);
    exit;
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Portal Stripe webhook: Invalid signature');
    http_response_code(400);
    exit;
}

// Handle the event
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        handle_payment_succeeded($paymentIntent, $event->account ?? null);
        break;

    case 'charge.refunded':
        try {
            apply_stripe_charge_refunds($pdo, $event->data->object, $is_production, $event->account ?? null);
        } catch (\Throwable $e) {
            // Rows are keyed by refund id, so a Stripe retry can't double-apply.
            error_log('Portal Stripe webhook: charge.refunded failed: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    default:
        // Acknowledge other events without processing
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);

/**
 * Handle a successful payment intent
 */
function handle_payment_succeeded(\Stripe\PaymentIntent $paymentIntent, ?string $account): void
{
    global $is_production, $pdo;
    $metadata = $paymentIntent->metadata;
    $invoiceId = $metadata['portal_invoice_id'] ?? '';
    $companyId = $metadata['portal_company_id'] ?? '';

    if (empty($invoiceId) || empty($companyId)) {
        // Not a portal payment (could be a license purchase)
        return;
    }

    if (!stripe_payment_belongs_to_portal_invoice($pdo, (int) $companyId, (string) $invoiceId, $account, current_environment())) {
        error_log("Portal Stripe webhook: ignored {$paymentIntent->id} from account " . ($account ?? 'none') . ", which is not the account connected for company $companyId invoice $invoiceId");
        return;
    }

    $currency = strtoupper($paymentIntent->currency);
    $zeroDecimalCurrencies = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'];
    $divisor = in_array($currency, $zeroDecimalCurrencies) ? 1 : 100;
    $amount = $paymentIntent->amount / $divisor;

    // Record the payment (idempotent - will skip if already recorded)
    record_portal_payment([
        'company_id' => (int) $companyId,
        'invoice_id' => $invoiceId,
        'customer_name' => $metadata['customer_name'] ?? '',
        'amount' => $amount,
        'processing_fee' => stripe_metadata_processing_fee($metadata, $amount),
        'currency' => $currency,
        'payment_method' => 'stripe',
        'provider_payment_id' => $paymentIntent->id,
        'provider_transaction_id' => $paymentIntent->latest_charge ?? $paymentIntent->id,
        'reference_number' => generate_reference_number(),
        'status' => 'completed',
        'payment_environment' => $is_production ? 'production' : 'sandbox',
    ]);
}

