<?php
/**
 * AJAX endpoint to retry payment for failed subscriptions
 * Returns JSON response with success/error status
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../paypal-helper.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/../../track_referral_event.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token (check both POST body and X-CSRF-Token header for AJAX)
$csrfToken = '';
$input = json_decode(file_get_contents('php://input'), true);
if (!empty($input['csrf_token'])) {
    $csrfToken = $input['csrf_token'];
} elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
}

if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get subscription info
$premium_subscription = get_user_premium_subscription($user_id);

// Validate subscription state
if (!$premium_subscription) {
    echo json_encode(['success' => false, 'error' => 'No subscription found']);
    exit;
}

if ($premium_subscription['status'] !== 'payment_failed') {
    echo json_encode(['success' => false, 'error' => 'Subscription is not in a failed payment state']);
    exit;
}

$payment_method = strtolower($premium_subscription['payment_method'] ?? '');
$billing_cycle = $premium_subscription['billing_cycle'] ?? 'monthly';
$subscription_id = $premium_subscription['subscription_id'];

// Calculate amount based on billing cycle using centralized pricing
$pricing = get_pricing_config();
$base_amount = ($billing_cycle === 'yearly') ? $pricing['premium_yearly_price'] : $pricing['premium_monthly_price'];
$fee = calculate_processing_fee($base_amount);
$amount = $base_amount + $fee;

// Get environment configuration
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';

try {
    $reactivated = false;
    $message = '';
    $transaction_id = null;

    // Handle PayPal subscriptions
    if ($payment_method === 'paypal') {
        if (!empty($premium_subscription['paypal_subscription_id'])) {
            // Try to reactivate the suspended PayPal subscription
            try {
                $reactivated = activatePayPalSubscription(
                    $premium_subscription['paypal_subscription_id'],
                    'Reactivated by user - retry payment'
                );

                if ($reactivated) {
                    $message = 'Your PayPal subscription has been reactivated. Payment will be processed by PayPal.';
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Unable to reactivate your PayPal subscription. The subscription may have been cancelled. Please set up a new subscription.',
                        'action' => 'new_subscription',
                        'redirect' => "../../pricing/premium/checkout/?method=paypal&billing={$billing_cycle}&change_method=1"
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                error_log("PayPal reactivation failed: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to reactivate PayPal subscription. Please try setting up a new subscription.',
                    'action' => 'new_subscription',
                    'redirect' => "../../pricing/premium/checkout/?method=paypal&billing={$billing_cycle}&change_method=1"
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No PayPal subscription found. Please set up a new subscription.',
                'action' => 'new_subscription',
                'redirect' => "../../pricing/premium/checkout/?method=paypal&billing={$billing_cycle}&change_method=1"
            ]);
            exit;
        }
    }
    // Handle Stripe subscriptions
    else if ($payment_method === 'stripe') {
        $paymentToken = $premium_subscription['payment_token'] ?? null;
        $stripeCustomerId = $premium_subscription['stripe_customer_id'] ?? null;

        if (empty($paymentToken)) {
            echo json_encode([
                'success' => false,
                'error' => 'No saved payment method found. Please update your payment method.',
                'action' => 'update_payment',
                'redirect' => 'reactivate-subscription.php'
            ]);
            exit;
        }

        // Initialize Stripe
        $stripeSecretKey = $isProduction
            ? $_ENV['STRIPE_LIVE_SECRET_KEY']
            : $_ENV['STRIPE_SANDBOX_SECRET_KEY'];
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        try {
            // Build payment intent params
            $params = [
                'amount' => intval($amount * 100), // Stripe uses cents
                'currency' => 'cad',
                'payment_method' => $paymentToken,
                'confirm' => true,
                'off_session' => true,
                'description' => "Premium Subscription Retry Payment - $subscription_id",
                'receipt_email' => $premium_subscription['email'],
                'metadata' => [
                    'subscription_id' => $subscription_id,
                    'type' => 'retry_payment'
                ]
            ];

            // Include customer ID if available
            if ($stripeCustomerId) {
                $params['customer'] = $stripeCustomerId;
            }

            // Create and confirm payment intent
            $paymentIntent = \Stripe\PaymentIntent::create($params);

            if ($paymentIntent->status === 'succeeded') {
                $reactivated = true;
                $transaction_id = $paymentIntent->id;
                $message = 'Payment successful! Your subscription has been reactivated.';
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Payment not completed. Status: ' . $paymentIntent->status,
                    'action' => 'update_payment',
                    'redirect' => 'reactivate-subscription.php'
                ]);
                exit;
            }
        } catch (\Stripe\Exception\CardException $e) {
            // Card was declined
            echo json_encode([
                'success' => false,
                'error' => 'Card declined: ' . $e->getMessage(),
                'action' => 'update_payment',
                'redirect' => 'reactivate-subscription.php'
            ]);
            exit;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe API error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Payment processing error. Please try again or update your payment method.',
                'action' => 'update_payment',
                'redirect' => 'reactivate-subscription.php'
            ]);
            exit;
        }
    }
    // Handle Square subscriptions
    else if ($payment_method === 'square') {
        $paymentToken = $premium_subscription['payment_token'] ?? null;

        if (empty($paymentToken)) {
            echo json_encode([
                'success' => false,
                'error' => 'No saved payment method found. Please update your payment method.',
                'action' => 'update_payment',
                'redirect' => 'reactivate-subscription.php'
            ]);
            exit;
        }

        // Initialize Square
        $squareAccessToken = $isProduction
            ? $_ENV['SQUARE_LIVE_ACCESS_TOKEN']
            : $_ENV['SQUARE_SANDBOX_ACCESS_TOKEN'];
        $squareEnvironment = $isProduction ? 'production' : 'sandbox';
        $squareLocationId = $isProduction
            ? $_ENV['SQUARE_LIVE_LOCATION_ID']
            : $_ENV['SQUARE_SANDBOX_LOCATION_ID'];

        try {
            $client = new \Square\SquareClient([
                'accessToken' => $squareAccessToken,
                'environment' => $squareEnvironment
            ]);

            $paymentsApi = $client->getPaymentsApi();
            // One key per subscription and calendar day, so a retry or a double click reuses it and Square declines to charge twice.
            $idempotencyKey = substr(
                hash('sha256', 'retry_' . $subscription_id . '_' . date('Y-m-d') . '_' . $squareAccessToken),
                0,
                45
            );

            $amountMoney = new \Square\Models\Money();
            $amountMoney->setAmount(intval($amount * 100)); // Square uses cents
            $amountMoney->setCurrency('CAD');

            $createPaymentRequest = new \Square\Models\CreatePaymentRequest(
                $paymentToken,
                $idempotencyKey
            );
            $createPaymentRequest->setAmountMoney($amountMoney);
            $createPaymentRequest->setLocationId($squareLocationId);
            $createPaymentRequest->setNote("Premium Subscription Retry Payment - $subscription_id");

            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                $reactivated = true;
                $transaction_id = $payment->getId();
                $message = 'Payment successful! Your subscription has been reactivated.';
            } else {
                $errors = $response->getErrors();
                $errorMessage = $errors[0]->getDetail() ?? 'Payment failed';
                echo json_encode([
                    'success' => false,
                    'error' => 'Payment failed: ' . $errorMessage,
                    'action' => 'update_payment',
                    'redirect' => 'reactivate-subscription.php'
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Square payment error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Payment processing error. Please try again or update your payment method.',
                'action' => 'update_payment',
                'redirect' => 'reactivate-subscription.php'
            ]);
            exit;
        }
    }
    // Unknown payment method
    else {
        echo json_encode([
            'success' => false,
            'error' => 'Unknown payment method. Please update your payment method.',
            'action' => 'update_payment',
            'redirect' => 'reactivate-subscription.php'
        ]);
        exit;
    }

    // If we got here, payment/reactivation was successful
    if ($reactivated) {
        // For Stripe/Square the card has been charged synchronously, so we can safely extend end_date here. For PayPal, activatePayPalSubscription only un-suspends the subscription.
        $extendsEndDate = in_array($payment_method, ['stripe', 'square'], true);

        if ($extendsEndDate) {
            $interval = ($billing_cycle === 'yearly') ? '+1 year' : '+1 month';
            $new_end_date = date('Y-m-d H:i:s', strtotime($interval));

            // Filtered by environment because both share a database, so a retry in one cannot reactivate the other's subscription.
            $stmt = $pdo->prepare("
                UPDATE premium_subscriptions
                SET status = 'active', auto_renew = 1, end_date = ?, updated_at = NOW()
                WHERE user_id = ? AND status = 'payment_failed' AND environment = ?
            ");
            $stmt->execute([$new_end_date, $user_id, current_environment()]);
        } else {
            // PayPal: don't move end_date. The webhook will handle it.
            $new_end_date = $premium_subscription['end_date'];

            $stmt = $pdo->prepare("
                UPDATE premium_subscriptions
                SET status = 'active', auto_renew = 1, updated_at = NOW()
                WHERE user_id = ? AND status = 'payment_failed' AND environment = ?
            ");
            $stmt->execute([$user_id, current_environment()]);
        }

        if ($stmt->rowCount() > 0) {
            // Record the payment in premium_subscription_payments (for Stripe/Square)
            if ($transaction_id && in_array($payment_method, ['stripe', 'square'])) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO premium_subscription_payments (
                            subscription_id, amount, currency, payment_method,
                            transaction_id, status, payment_type, environment, created_at
                        ) VALUES (?, ?, 'CAD', ?, ?, 'completed', 'retry', ?, NOW())
                    ");
                    $stmt->execute([$subscription_id, $amount, $payment_method, $transaction_id, current_environment()]);
                } catch (Exception $e) {
                    error_log("Failed to record payment: " . $e->getMessage());
                }
            }

            // Fire premium_paid event for the retry payment.
            track_subscription_event('premium_paid', $subscription_id, [
                'amount'         => $amount,
                'currency'       => 'CAD',
                'payment_type'   => 'retry',
                'payment_method' => $payment_method,
            ], [
                'visitor_id' => $_COOKIE[ARGO_VISITOR_COOKIE] ?? null,
                'user_id'    => $_SESSION['user_id'] ?? null,
            ], ['allow_bot' => false]);

            // Send reactivation email with end date (current end date for
            // PayPal; newly-extended date for Stripe/Square).
            try {
                send_premium_subscription_reactivated_email(
                    $premium_subscription['email'],
                    $subscription_id,
                    $new_end_date,
                    $billing_cycle
                );
            } catch (Exception $e) {
                error_log("Failed to send reactivation email: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => $message ?: 'Your subscription has been reactivated! AI features are now available.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update subscription status. Please contact support.'
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error in retry-payment-ajax: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'A database error occurred. Please try again or contact support.'
    ]);
}
