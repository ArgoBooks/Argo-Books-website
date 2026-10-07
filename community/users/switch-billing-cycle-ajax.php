<?php
/**
 * AJAX endpoint to switch a subscription's billing cycle (monthly <-> yearly).
 * Stripe and Square only; PayPal is rejected with an explicit error.
 *
 * Returns JSON. On Stripe SCA / 3DS, returns:
 *   { success: false, action: 'sca_required', client_secret: '...', error: '...' }
 * The caller completes the 3DS challenge then re-POSTs with
 *   { ..., confirmed_payment_intent_id: 'pi_...' }
 * to finalize without creating a new PaymentIntent.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../config/pricing.php';

// 1. Auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// 2. CSRF: accept token from JSON body or X-CSRF-Token header
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || empty($csrfToken)
    || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// 3. Whitelist new_cycle
$newCycle = $input['new_cycle'] ?? '';
if (!in_array($newCycle, ['monthly', 'yearly'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid billing cycle']);
    exit;
}

// 4. Load subscription
$premium_subscription = get_user_premium_subscription($user_id);
if (!$premium_subscription) {
    echo json_encode(['success' => false, 'error' => 'No subscription found']);
    exit;
}

$payment_method = strtolower($premium_subscription['payment_method'] ?? '');
$subscription_id = $premium_subscription['subscription_id'];

// 5. Eligibility guards
if ($premium_subscription['status'] !== 'active') {
    echo json_encode(['success' => false, 'error' => 'Subscription must be active to switch billing cycles']);
    exit;
}

if (strtotime($premium_subscription['end_date']) <= time()) {
    echo json_encode(['success' => false, 'error' => 'Subscription period has ended. Please renew first.']);
    exit;
}

// PayPal explicitly rejected: Phase 2
if ($payment_method === 'paypal') {
    echo json_encode([
        'success' => false,
        'error'   => 'Cycle switching for PayPal subscriptions is not yet supported.'
    ]);
    exit;
}

if (!in_array($payment_method, ['stripe', 'square'], true)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported payment method for cycle switching']);
    exit;
}

// 5-minute cooldown on rapid switches (prevents accidental double-submit storms)
if (!empty($premium_subscription['last_cycle_change_at'])
    && (time() - strtotime($premium_subscription['last_cycle_change_at'])) < 300) {
    echo json_encode([
        'success' => false,
        'error'   => 'Please wait a few minutes between billing cycle changes.'
    ]);
    exit;
}

// 6. Server-side proration. NEVER trust client values.
$pricingConfig = get_pricing_config();
$proration = calculate_cycle_switch_proration($premium_subscription, $newCycle, $pricingConfig);

if (($proration['direction'] ?? '') === 'noop') {
    echo json_encode(['success' => false, 'error' => 'Already on the requested billing cycle']);
    exit;
}

$immediateChargeTotal = (float) $proration['immediate_charge_total'];
$creditBalanceAfter   = (float) $proration['credit_balance_after'];
$newEndDate           = $proration['new_end_date'];
$newAmountColumn      = (float) $proration['new_amount_column'];

// 7. Charge (only when there's something to charge). Stripe and Square have
// independent paths; the SCA retry path only applies to Stripe.
$is_production = ($_ENV['APP_ENV'] ?? 'development') === 'production';
$transaction_id = null;
$confirmedPaymentIntentId = $input['confirmed_payment_intent_id'] ?? null;

try {
    if ($immediateChargeTotal > 0) {
        if ($payment_method === 'stripe') {
            $paymentToken = $premium_subscription['payment_token'] ?? null;
            $stripeCustomerId = $premium_subscription['stripe_customer_id'] ?? null;

            if (empty($paymentToken)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'No saved payment method found. Please update your payment method.',
                    'redirect' => 'reactivate-subscription.php'
                ]);
                exit;
            }

            $stripeSecretKey = $is_production
                ? $_ENV['STRIPE_LIVE_SECRET_KEY']
                : $_ENV['STRIPE_SANDBOX_SECRET_KEY'];
            \Stripe\Stripe::setApiKey($stripeSecretKey);

            try {
                if ($confirmedPaymentIntentId) {
                    // SCA retry path: fetch existing PI, verify ownership and status, then read the proration snapshot back from PI metadata.
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($confirmedPaymentIntentId);
                    $piMeta = $paymentIntent->metadata ?? null;
                    $metaSubId = $piMeta['subscription_id'] ?? null;
                    $metaUserId = $piMeta['user_id'] ?? null;
                    $metaNewCycle = $piMeta['new_cycle'] ?? null;
                    if ($metaSubId !== $subscription_id || (string) $metaUserId !== (string) $user_id) {
                        echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
                        exit;
                    }
                    // Reject replay attacks where the PI was created for a different cycle direction.
                    if ($metaNewCycle !== $newCycle) {
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Payment verification failed: cycle mismatch'
                        ]);
                        exit;
                    }
                    if ($paymentIntent->status !== 'succeeded') {
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Payment did not complete. Status: ' . $paymentIntent->status
                        ]);
                        exit;
                    }
                    // Use the snapshot from metadata so the DB row matches the
                    // amounts that were actually charged.
                    $immediateChargeTotal = (float) ($piMeta['immediate_charge_total'] ?? $immediateChargeTotal);
                    $creditBalanceAfter   = (float) ($piMeta['credit_balance_after']   ?? $creditBalanceAfter);
                    $newEndDate           = $piMeta['new_end_date']                    ?? $newEndDate;
                    $newAmountColumn      = (float) ($piMeta['new_amount_column']      ?? $newAmountColumn);
                    $proration['prorated_credit']          = (float) ($piMeta['prorated_credit']          ?? $proration['prorated_credit']);
                    $proration['existing_credit_consumed'] = (float) ($piMeta['existing_credit_consumed'] ?? $proration['existing_credit_consumed']);
                    $transaction_id = $paymentIntent->id;
                } else {
                    // The proration snapshot rides in the payment intent's metadata, so the SCA retry reads it back rather than working it out again later.
                    $params = [
                        'amount'         => intval(round($immediateChargeTotal * 100)),
                        'currency'       => 'cad',
                        'payment_method' => $paymentToken,
                        'confirm'        => true,
                        'off_session'    => true,
                        'description'    => "Premium Subscription Cycle Switch ({$proration['old_cycle']}->{$proration['new_cycle']}) - $subscription_id",
                        'receipt_email'  => $premium_subscription['email'],
                        'metadata' => [
                            'subscription_id'          => $subscription_id,
                            'user_id'                  => (string) $user_id,
                            'type'                     => 'cycle_change',
                            'old_cycle'                => $proration['old_cycle'],
                            'new_cycle'                => $proration['new_cycle'],
                            'prorated_credit'          => (string) $proration['prorated_credit'],
                            'existing_credit_consumed' => (string) $proration['existing_credit_consumed'],
                            'immediate_charge_total'   => (string) $immediateChargeTotal,
                            'credit_balance_after'     => (string) $creditBalanceAfter,
                            'new_end_date'             => $newEndDate,
                            'new_amount_column'        => (string) $newAmountColumn,
                        ]
                    ];
                    if ($stripeCustomerId) {
                        $params['customer'] = $stripeCustomerId;
                    }

                    // One key per subscription, cycle and day, so a double click or an abandoned SCA retry within 24 hours returns the original payment.
                    $stripeIdempotencyKey = hash(
                        'sha256',
                        'cycleswitch_' . $subscription_id . '_' . $newCycle . '_'
                            . date('Y-m-d') . '_' . $stripeSecretKey
                    );

                    $paymentIntent = \Stripe\PaymentIntent::create(
                        $params,
                        ['idempotency_key' => $stripeIdempotencyKey]
                    );

                    if ($paymentIntent->status === 'succeeded') {
                        $transaction_id = $paymentIntent->id;
                    } elseif ($paymentIntent->status === 'requires_action') {
                        // SCA / 3DS: bounce to client to complete
                        echo json_encode([
                            'success'           => false,
                            'action'            => 'sca_required',
                            'client_secret'     => $paymentIntent->client_secret,
                            'payment_intent_id' => $paymentIntent->id,
                            'error'             => 'Additional authentication required'
                        ]);
                        exit;
                    } else {
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Payment did not complete. Status: ' . $paymentIntent->status
                        ]);
                        exit;
                    }
                }
            } catch (\Stripe\Exception\CardException $e) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Card declined: ' . $e->getMessage()
                ]);
                exit;
            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log('Stripe API error in switch-billing-cycle-ajax: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error'   => 'Payment processing error. Please try again.'
                ]);
                exit;
            }
        } elseif ($payment_method === 'square') {
            $paymentToken = $premium_subscription['payment_token'] ?? null;
            if (empty($paymentToken)) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'No saved payment method found. Please update your payment method.',
                    'redirect' => 'reactivate-subscription.php'
                ]);
                exit;
            }

            $squareAccessToken = $is_production
                ? $_ENV['SQUARE_LIVE_ACCESS_TOKEN']
                : $_ENV['SQUARE_SANDBOX_ACCESS_TOKEN'];
            $squareEnvironment = $is_production ? 'production' : 'sandbox';
            $squareLocationId = $is_production
                ? $_ENV['SQUARE_LIVE_LOCATION_ID']
                : $_ENV['SQUARE_SANDBOX_LOCATION_ID'];

            try {
                $client = new \Square\SquareClient([
                    'accessToken' => $squareAccessToken,
                    'environment' => $squareEnvironment
                ]);
                $paymentsApi = $client->getPaymentsApi();

                // Deterministic idempotency key per (subscription, calendar day, cycle).
                // Salted with access token so dev/prod keys never collide.
                $idempotencyKey = substr(
                    hash(
                        'sha256',
                        'cycleswitch_' . $subscription_id . '_' . $newCycle . '_'
                            . date('Y-m-d') . '_' . $squareAccessToken
                    ),
                    0,
                    45
                );

                $amountMoney = new \Square\Models\Money();
                $amountMoney->setAmount(intval(round($immediateChargeTotal * 100)));
                $amountMoney->setCurrency('CAD');

                $createPaymentRequest = new \Square\Models\CreatePaymentRequest($paymentToken, $idempotencyKey);
                $createPaymentRequest->setAmountMoney($amountMoney);
                $createPaymentRequest->setLocationId($squareLocationId);
                $createPaymentRequest->setNote("Premium Subscription Cycle Switch ({$proration['old_cycle']}->{$proration['new_cycle']}) - $subscription_id");

                $response = $paymentsApi->createPayment($createPaymentRequest);

                if ($response->isSuccess()) {
                    $transaction_id = $response->getResult()->getPayment()->getId();
                } else {
                    $errors = $response->getErrors();
                    $errorMessage = (count($errors) > 0 && $errors[0]->getDetail())
                        ? $errors[0]->getDetail()
                        : 'Payment failed';
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Payment failed: ' . $errorMessage
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                error_log('Square error in switch-billing-cycle-ajax: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error'   => 'Payment processing error. Please try again.'
                ]);
                exit;
            }
        }
    } else {
        // No charge needed (credit fully covered). Synthetic transaction id for the audit row.
        $transaction_id = 'CYCLE_NOCHARGE_' . bin2hex(random_bytes(8));
    }

    // 8. Persist subscription update + audit row in a transaction.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE premium_subscriptions
        SET billing_cycle        = ?,
            amount               = ?,
            end_date             = ?,
            credit_balance       = ?,
            transaction_id       = ?,
            last_cycle_change_at = NOW(),
            updated_at           = NOW()
        WHERE subscription_id = ? AND user_id = ?
    ");
    $stmt->execute([
        $newCycle,
        $newAmountColumn,
        $newEndDate,
        $creditBalanceAfter,
        $transaction_id,
        $subscription_id,
        $user_id
    ]);

    if ($stmt->rowCount() < 1) {
        $pdo->rollBack();
        // Gateway charge succeeded but row wasn't updated. Log critical.
        if ($immediateChargeTotal > 0) {
            error_log("CRITICAL: cycle switch charge succeeded but DB UPDATE matched 0 rows. user_id=$user_id, subscription_id=$subscription_id, transaction_id=$transaction_id, amount=$immediateChargeTotal");
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to update subscription record. Please contact support.'
        ]);
        exit;
    }

    // Idempotency: if a cycle_change row already exists for this transaction id, skip the insert.
    $auditCurrency = $premium_subscription['currency'] ?? 'CAD';
    // Env-scope the dedupe so a cycle_change row from the other environment
    // doesn't suppress the audit row in this one (shared DB).
    $checkStmt = $pdo->prepare("
        SELECT id FROM premium_subscription_payments
        WHERE transaction_id = ? AND payment_type = 'cycle_change' AND environment = ?
        LIMIT 1
    ");
    $checkStmt->execute([$transaction_id, current_environment()]);
    if (!$checkStmt->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscription_payments (
                subscription_id, amount, currency, payment_method,
                transaction_id, status, payment_type, environment, created_at
            ) VALUES (?, ?, ?, ?, ?, 'completed', 'cycle_change', ?, NOW())
        ");
        $stmt->execute([
            $subscription_id,
            $immediateChargeTotal,
            $auditCurrency,
            $payment_method,
            $transaction_id,
            current_environment()
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Database error in switch-billing-cycle-ajax: ' . $e->getMessage());
    if ($immediateChargeTotal > 0) {
        error_log("CRITICAL: cycle switch charge succeeded but DB write failed. user_id=$user_id, subscription_id=$subscription_id, transaction_id=$transaction_id, amount=$immediateChargeTotal");
    }
    echo json_encode([
        'success' => false,
        'error'   => 'A database error occurred. Please try again or contact support.'
    ]);
    exit;
}

// 9. Notification email: wrap in try/catch, never block success on email failure.
try {
    send_premium_subscription_cycle_changed_email(
        $premium_subscription['email'],
        $subscription_id,
        $proration['old_cycle'],
        $proration['new_cycle'],
        $proration['prorated_credit'],
        $proration['existing_credit_consumed'],
        $immediateChargeTotal,
        $newEndDate,
        $creditBalanceAfter,
        (float) $pricingConfig['premium_monthly_price']
    );
} catch (Exception $e) {
    error_log('Failed to send cycle change email: ' . $e->getMessage());
}

// 10. Build success message
$verb = ($newCycle === 'yearly') ? 'upgraded to yearly' : 'changed to monthly';
if ($immediateChargeTotal > 0) {
    $message = "Your subscription has been $verb. Charged \$" . number_format($immediateChargeTotal, 2) . ' CAD.';
} else {
    $message = "Your subscription has been $verb. No charge today, covered by credit.";
}

echo json_encode([
    'success'              => true,
    'message'              => $message,
    'new_end_date'         => $newEndDate,
    'charged'              => $immediateChargeTotal,
    'credit_balance_after' => $creditBalanceAfter
]);
