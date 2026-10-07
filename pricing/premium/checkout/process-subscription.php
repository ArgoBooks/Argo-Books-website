<?php
/**
 * Premium Subscription Payment Processor
 * Handles subscription creation with recurring billing support
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../email_sender.php';
require_once __DIR__ . '/../../../license_functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/pricing.php';
require_once __DIR__ . '/../../../track_referral_event.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Verify user is logged in - user_id must come from session, not POST body
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

// Extract common fields
$email = $input['email'] ?? $input['payer_email'] ?? '';
// Only CAD is supported, and a client-supplied currency is never trusted: it is stored on the subscription and decides the currency a PayPal refund goes out in.
$currency = 'CAD';
$billing = $input['billing'] ?? 'monthly';
$paymentMethod = $input['payment_method'] ?? 'unknown';
// Use session user_id as authoritative source (POST user_id is untrusted)
$userId = intval($_SESSION['user_id']);

// Compute server-side total (base price + processing fee). Never trust client amount
$pricingConfig = get_pricing_config();
$monthlyPrice = $pricingConfig['premium_monthly_price'];
$yearlyPrice = $pricingConfig['premium_yearly_price'];

if ($billing === 'yearly') {
    $baseCharge = $yearlyPrice;
} else {
    $baseCharge = $monthlyPrice;
}
$processingFee = calculate_processing_fee($baseCharge);
$amount = $baseCharge + $processingFee;

// Get environment configuration
$isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';

if ($userId <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields or invalid user']);
    exit();
}

// Verify the user exists and use their ACCOUNT email (never trust a
// client-supplied email) for the subscription record and receipt.
try {
    $stmt = $pdo->prepare("SELECT id, email FROM community_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch();
    if (!$userRow) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user']);
        exit();
    }
    $email = $userRow['email'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

// Serialised per user so a double submit cannot create two subscriptions, because the check for an existing one and the INSERT below are not atomic together.
try {
    $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 10)");
    $lockStmt->execute(["premium_checkout_$userId"]);
    $lockResult = $lockStmt->fetchColumn();
    if ($lockResult === '0' || $lockResult === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Another checkout is already in progress. Please wait a moment and try again.']);
        exit();
    }
} catch (PDOException $e) {
    // Best-effort: if advisory locking is unavailable, proceed rather than block checkout.
    error_log("Checkout GET_LOCK failed for user $userId: " . $e->getMessage());
}

// Check if user is updating payment method for existing subscription
$isUpdatingPaymentMethod = $input['update_payment_method'] ?? false;
$cycleSwitch = !empty($input['cycle_switch']) && (
    $input['cycle_switch'] === true
    || $input['cycle_switch'] === 'true'
    || $input['cycle_switch'] === 1
    || $input['cycle_switch'] === '1'
);
$existingSubscription = null;
$subscriptionStillValid = false;

try {
    // Filtered by environment because dev and production share one database, so a production checkout could otherwise read a sandbox row as an active subscription.
    $stmt = $pdo->prepare("
        SELECT * FROM premium_subscriptions
        WHERE user_id = ? AND status IN ('active', 'cancelled', 'payment_failed')
        AND environment = ?
        AND end_date > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId, current_environment()]);
    $existingSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
    $subscriptionStillValid = ($existingSubscription !== false);
} catch (PDOException $e) {
    // Continue with new subscription if check fails
}

// Generate subscription ID using the shared license key generator
function generateSubscriptionId() {
    return generate_license_key('premium');
}

try {
    $pdo->beginTransaction();

    $subscriptionId = generateSubscriptionId();

    // Calculate subscription dates
    $startDate = date('Y-m-d H:i:s');
    if ($billing === 'yearly') {
        $endDate = date('Y-m-d H:i:s', strtotime('+1 year'));
    } else {
        $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));
    }

    // Process payment and get transaction details based on payment method
    $transactionId = '';
    $paymentToken = null; // Token for recurring billing
    $stripeCustomerId = null; // Stripe customer ID for recurring billing

    // Check if this is a PayPal subscription
    $paypalSubscriptionId = null;

    // Skip payment processing when updating payment method and subscription is still valid
    $skipPaymentProcessing = ($isUpdatingPaymentMethod && $subscriptionStillValid);

    switch ($paymentMethod) {
        case 'paypal':
            $isPayPalSubscription = $input['is_paypal_subscription'] ?? false;

            if ($isPayPalSubscription) {
                // PayPal Subscriptions API - recurring billing handled by PayPal
                $paypalSubscriptionId = $input['paypal_subscription_id'] ?? $input['subscriptionID'] ?? '';
                // Validated at entry because the id is stored and then put into PayPal URL paths for cancel and refund.
                require_once __DIR__ . '/../../../paypal-helper.php';
                if (!isValidPayPalResourceId($paypalSubscriptionId)) {
                    $pdo->rollBack();
                    error_log("Rejected PayPal subscription with invalid id format. user_id=$userId, raw_id=" . substr((string) $paypalSubscriptionId, 0, 64));
                    echo json_encode(['success' => false, 'error' => 'Invalid PayPal subscription identifier']);
                    exit();
                }
                $transactionId = $paypalSubscriptionId;
                $paymentToken = $paypalSubscriptionId; // Store subscription ID as token
            } else {
                // One-time payments are rejected: nothing here verifies a capture for an order id, so a client-supplied one would buy a subscription without paying.
                $pdo->rollBack();
                error_log("Rejected unsupported one-time PayPal payment. user_id=$userId");
                echo json_encode(['success' => false, 'error' => 'Unsupported PayPal payment type']);
                exit();
            }
            break;

        case 'stripe':
            $paymentMethodId = $input['payment_method_id'] ?? '';
            $transactionId = $paymentMethodId;

            // Initialize Stripe
            $stripeSecretKey = $isProduction
                ? $_ENV['STRIPE_LIVE_SECRET_KEY']
                : $_ENV['STRIPE_SANDBOX_SECRET_KEY'];
            \Stripe\Stripe::setApiKey($stripeSecretKey);

            try {
                // Create or get customer
                $customers = \Stripe\Customer::all(['email' => $email, 'limit' => 1]);
                if (count($customers->data) > 0) {
                    $customer = $customers->data[0];
                } else {
                    $customer = \Stripe\Customer::create([
                        'email' => $email,
                        'metadata' => ['user_id' => $userId]
                    ]);
                }

                // Attach payment method to customer for recurring billing
                $paymentMethodObj = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                $paymentMethodObj->attach(['customer' => $customer->id]);

                // Set as default payment method
                \Stripe\Customer::update($customer->id, [
                    'invoice_settings' => ['default_payment_method' => $paymentMethodId]
                ]);

                // Store customer ID for recurring billing
                $stripeCustomerId = $customer->id;

                if ($skipPaymentProcessing) {
                    $transactionId = 'UPDATE_' . strtoupper(bin2hex(random_bytes(8)));
                    $paymentToken = $paymentMethodId; // Store for future renewals
                } else {
                    // Create payment intent for the initial charge
                    $paymentIntent = \Stripe\PaymentIntent::create([
                        'amount' => intval($amount * 100),
                        'currency' => 'cad',
                        'customer' => $customer->id,
                        'payment_method' => $paymentMethodId,
                        'off_session' => true,
                        'confirm' => true,
                        'description' => "Premium Subscription - Initial Payment ($billing)",
                        'receipt_email' => $email,
                        'metadata' => [
                            'subscription_id' => $subscriptionId,
                            'user_id' => $userId,
                            'billing_cycle' => $billing
                        ]
                    ]);

                    if ($paymentIntent->status !== 'succeeded') {
                        throw new Exception('Payment not completed');
                    }

                    $transactionId = $paymentIntent->id;
                    $paymentToken = $paymentMethodId; // Store for recurring billing
                }

            } catch (\Stripe\Exception\CardException $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Card declined: ' . $e->getMessage()]);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Stripe error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Payment processing failed']);
                exit();
            }
            break;

        case 'square':
            $sourceId = $input['source_id'] ?? '';

            // Initialize Square API credentials
            $squareAccessToken = $isProduction
                ? $_ENV['SQUARE_LIVE_ACCESS_TOKEN']
                : $_ENV['SQUARE_SANDBOX_ACCESS_TOKEN'];
            $squareLocationId = $isProduction
                ? $_ENV['SQUARE_LIVE_LOCATION_ID']
                : $_ENV['SQUARE_SANDBOX_LOCATION_ID'];
            $squareApiBaseUrl = $isProduction
                ? 'https://connect.squareup.com/v2'
                : 'https://connect.squareupsandbox.com/v2';

            // Helper function for Square API calls
            $squareApiCall = function($endpoint, $method, $data = null) use ($squareApiBaseUrl, $squareAccessToken) {
                $ch = curl_init("$squareApiBaseUrl/$endpoint");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => [
                        "Square-Version: 2026-01-22",
                        "Authorization: Bearer $squareAccessToken",
                        "Content-Type: application/json"
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                return ['response' => json_decode($response, true), 'http_code' => $httpCode];
            };

            try {
                // Search for existing customer by email
                $searchData = [
                    'query' => [
                        'filter' => [
                            'email_address' => [
                                'exact' => $email
                            ]
                        ]
                    ]
                ];
                $searchResult = $squareApiCall('customers/search', 'POST', $searchData);
                $customerId = null;

                if ($searchResult['http_code'] >= 200 && $searchResult['http_code'] < 300
                    && !empty($searchResult['response']['customers'])) {
                    $customerId = $searchResult['response']['customers'][0]['id'];
                } else {
                    // Create new customer
                    $customerData = [
                        'idempotency_key' => bin2hex(random_bytes(16)),
                        'email_address' => $email,
                        'reference_id' => "user_$userId"
                    ];
                    $createResult = $squareApiCall('customers', 'POST', $customerData);

                    if ($createResult['http_code'] >= 200 && $createResult['http_code'] < 300
                        && isset($createResult['response']['customer'])) {
                        $customerId = $createResult['response']['customer']['id'];
                    }
                }

                // Create card on file for recurring billing
                $cardData = [
                    'idempotency_key' => bin2hex(random_bytes(16)),
                    'source_id' => $sourceId,
                    'card' => [
                        'customer_id' => $customerId
                    ]
                ];
                $cardResult = $squareApiCall('cards', 'POST', $cardData);
                $cardId = null;

                if ($cardResult['http_code'] >= 200 && $cardResult['http_code'] < 300
                    && isset($cardResult['response']['card'])) {
                    $cardId = $cardResult['response']['card']['id'];
                } else {
                    $errorDetail = $cardResult['response']['errors'][0]['detail'] ?? 'Failed to save card';
                    throw new Exception($errorDetail);
                }

                if ($skipPaymentProcessing) {
                    $transactionId = 'UPDATE_' . strtoupper(bin2hex(random_bytes(8)));
                    $paymentToken = $cardId; // Store card ID for future renewals
                } else {
                    // Process initial payment
                    $paymentData = [
                        'idempotency_key' => bin2hex(random_bytes(16)),
                        'source_id' => $cardId ?? $sourceId,
                        'amount_money' => [
                            'amount' => intval($amount * 100),
                            'currency' => 'CAD'
                        ],
                        'location_id' => $squareLocationId,
                        'customer_id' => $customerId,
                        'note' => "Premium Subscription - Initial Payment ($billing)",
                        'autocomplete' => true
                    ];
                    $paymentResult = $squareApiCall('payments', 'POST', $paymentData);

                    if ($paymentResult['http_code'] >= 200 && $paymentResult['http_code'] < 300
                        && isset($paymentResult['response']['payment'])) {
                        $transactionId = $paymentResult['response']['payment']['id'];
                        $paymentToken = $cardId; // Store card ID for recurring billing
                    } else {
                        $errorDetail = $paymentResult['response']['errors'][0]['detail'] ?? 'Payment failed';
                        throw new Exception($errorDetail);
                    }
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Square error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Payment processing failed: ' . $e->getMessage()]);
                exit();
            }
            break;
    }

    // Check if updating existing subscription's payment method
    if ($existingSubscription && $isUpdatingPaymentMethod) {
        // Update existing subscription with new payment method and billing cycle
        $subscriptionId = $existingSubscription['subscription_id'];

        // $newAmount is the base price grandfathering locks in; $newAmountCharged adds the processing fee and is what the amount column holds, as on a new signup.
        $newAmount = ($billing === 'yearly') ? $pricingConfig['premium_yearly_price'] : $pricingConfig['premium_monthly_price'];
        $newAmountCharged = $newAmount + calculate_processing_fee($newAmount);

        // A subscription still inside its paid period keeps its end date, unless this charges for a new period.
        $existingEndDate = $existingSubscription['end_date'];
        $newEndDate = $existingEndDate; // Default: keep existing

        if (!$skipPaymentProcessing) {
            // User was charged, so start a new billing period
            $newEndDate = ($billing === 'yearly')
                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                : date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        // PayPal bills its own first cycle on activation, so the end date becomes today plus the new cycle, and a failed prereq must not roll back a subscription that already exists there.
        $isPayPalCycleSwitch = (
            $cycleSwitch
            && $paymentMethod === 'paypal'
            && !empty($paypalSubscriptionId)
        );
        $paypalCycleSwitchData = null;
        if ($isPayPalCycleSwitch) {
            require_once __DIR__ . '/../../../paypal-helper.php';
            require_once __DIR__ . '/../../../community/users/user_functions.php';

            // Re-read inside the transaction with FOR UPDATE, so a renewal webhook or an admin edit between the earlier SELECT and this write is not overwritten.
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM premium_subscriptions
                    WHERE subscription_id = ? AND user_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$subscriptionId, $userId]);
                $lockedSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Cycle switch FOR UPDATE failed: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Could not lock subscription for update']);
                exit();
            }
            if (!$lockedSubscription) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Subscription not found']);
                exit();
            }
            // From here on, use $lockedSubscription for all reads. It's the
            // authoritative row state under the row lock.
            $existingSubscription = $lockedSubscription;

            // Checked again here because this endpoint is reachable by direct POST, and every rejection happens before any write and before anything changes at PayPal.

            // A cycle switch needs an active subscription, which is stricter than the query above, since that one also accepts cancelled and payment_failed for payment-method updates.
            if (($existingSubscription['status'] ?? '') !== 'active') {
                $pdo->rollBack();
                error_log("Cycle switch rejected: subscription not active. user_id=$userId, status=" . ($existingSubscription['status'] ?? 'unknown'));
                echo json_encode(['success' => false, 'error' => 'Cycle switching requires an active subscription']);
                exit();
            }

            // Five minutes between cycle changes, which catches two approvals at once that the anti-replay check below cannot, because PayPal issues a different id for each approval.
            if (!empty($existingSubscription['last_cycle_change_at'])
                && (time() - strtotime($existingSubscription['last_cycle_change_at'])) < 300) {
                $pdo->rollBack();
                error_log("Cycle switch rejected: cooldown active. user_id=$userId, last_cycle_change_at={$existingSubscription['last_cycle_change_at']}");
                echo json_encode(['success' => false, 'error' => 'Please wait a few minutes between billing cycle changes.']);
                exit();
            }

            $oldPaypalSubId = $existingSubscription['paypal_subscription_id'] ?? '';

            // PayPal issues a new id on each approval, so the same id arriving twice is a replay.
            if ($oldPaypalSubId === $paypalSubscriptionId) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Replay rejected: same PayPal subscription id']);
                exit();
            }

            $proration = calculate_cycle_switch_proration($existingSubscription, $billing, $pricingConfig);
            if (($proration['direction'] ?? '') === 'noop') {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Already on the requested billing cycle']);
                exit();
            }

            // Without a completed sale there is nothing to refund against, so the switch would charge for the new cycle and forfeit the unused part of the old one.
            $sale = getMostRecentPayPalSale($subscriptionId);
            if (!$sale) {
                $pdo->rollBack();
                error_log("Cycle switch rejected: no completed PayPal sale to refund against. user_id=$userId, subscription_id=$subscriptionId");
                echo json_encode(['success' => false, 'error' => 'Your subscription is still being activated by PayPal. Please wait a few minutes after subscribing before changing cycles.']);
                exit();
            }

            // Override end_date: PayPal bills the new sub on activation
            $newEndDate = ($billing === 'yearly')
                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                : date('Y-m-d H:i:s', strtotime('+1 month'));

            // Use the FRESH credit_balance from the locked row so a
            // concurrent credit grant doesn't get blown away.
            $existingCreditBalance = (float) ($existingSubscription['credit_balance'] ?? 0);
            $refundAmount = min(
                (float) $proration['prorated_credit'] + $existingCreditBalance,
                (float) $sale['amount']
            );

            $paypalCycleSwitchData = [
                'old_paypal_sub_id' => $oldPaypalSubId,
                'sale'              => $sale,
                'refund_amount'     => round($refundAmount, 2),
                'proration'         => $proration,
                'existing_credit'   => $existingCreditBalance,
            ];
        }

        if ($isPayPalCycleSwitch) {
            // previous_paypal_subscription_id is what lets the cancel webhook ignore itself. credit_balance is set after the commit instead, so a failed refund cannot wipe the credit already held.
            $stmt = $pdo->prepare("
                UPDATE premium_subscriptions
                SET payment_method = ?,
                    payment_token = ?,
                    stripe_customer_id = ?,
                    transaction_id = ?,
                    billing_cycle = ?,
                    amount = ?,
                    signup_base_price = ?,
                    end_date = ?,
                    paypal_subscription_id = ?,
                    previous_paypal_subscription_id = ?,
                    last_cycle_change_at = NOW(),
                    status = 'active',
                    auto_renew = 1,
                    cancelled_at = NULL,
                    updated_at = NOW()
                WHERE subscription_id = ?
            ");
            $stmt->execute([
                $paymentMethod,
                $paymentToken,
                $stripeCustomerId,
                $transactionId,
                $billing,
                $newAmountCharged,
                // Cycle switch is a deliberate plan change, so re-lock to the
                // current price for the new cycle (not the old grandfathered one).
                $newAmount,
                $newEndDate,
                $paypalSubscriptionId,
                $paypalCycleSwitchData['old_paypal_sub_id'],
                $subscriptionId
            ]);

            // Only the audit row: the real $0 sale row arrives with the PAYMENT.SALE.COMPLETED webhook. Skipped when a double submit has already written one.
            try {
                $auditCurrency = $existingSubscription['currency'] ?? 'CAD';
                // Env-scope the dedupe so a cycle_change row recorded by the
                // other environment doesn't suppress the audit row in this one.
                $checkStmt = $pdo->prepare("SELECT id FROM premium_subscription_payments WHERE transaction_id = ? AND payment_type = 'cycle_change' AND environment = ? LIMIT 1");
                $checkStmt->execute([$paypalSubscriptionId, current_environment()]);
                if (!$checkStmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO premium_subscription_payments (
                            subscription_id, amount, currency, payment_method,
                            transaction_id, status, payment_type, environment, created_at
                        ) VALUES (?, 0, ?, 'paypal', ?, 'completed', 'cycle_change', ?, NOW())
                    ");
                    $stmt->execute([$subscriptionId, $auditCurrency, $paypalSubscriptionId, current_environment()]);
                }
            } catch (PDOException $e) {
                error_log("Could not write cycle_change audit row: " . $e->getMessage());
            }
        } else {
            // Original UPDATE for non-cycle-switch payment-method updates
            $stmt = $pdo->prepare("
                UPDATE premium_subscriptions
                SET payment_method = ?,
                    payment_token = ?,
                    stripe_customer_id = ?,
                    transaction_id = ?,
                    billing_cycle = ?,
                    amount = ?,
                    signup_base_price = ?,
                    end_date = ?,
                    status = 'active',
                    auto_renew = 1,
                    cancelled_at = NULL,
                    updated_at = NOW()
                WHERE subscription_id = ?
            ");
            $stmt->execute([
                $paymentMethod,
                $paymentToken,
                $stripeCustomerId,
                $transactionId,
                $billing,
                $newAmountCharged,
                // Re-lock to the current price for the new cycle.
                $newAmount,
                $newEndDate,
                $subscriptionId
            ]);

            // Update with PayPal subscription ID if applicable
            if ($paypalSubscriptionId) {
                try {
                    $stmt = $pdo->prepare("UPDATE premium_subscriptions SET paypal_subscription_id = ? WHERE subscription_id = ?");
                    $stmt->execute([$paypalSubscriptionId, $subscriptionId]);
                } catch (PDOException $e) {
                    error_log("Could not set paypal_subscription_id: " . $e->getMessage());
                }
            }
        }

        $pdo->commit();

        // Cancel after the commit, so the cancel webhook finds previous_paypal_subscription_id set and ignores itself, and refund last, so a failed refund cannot undo a switch that went through.
        $switchWarnings = [];
        if ($isPayPalCycleSwitch && $paypalCycleSwitchData) {
            $sw = $paypalCycleSwitchData;

            // 1. Cancel the old PayPal subscription
            if (!empty($sw['old_paypal_sub_id'])) {
                $cancelOk = cancelPayPalSubscription(
                    $sw['old_paypal_sub_id'],
                    "Cycle switch to {$billing}"
                );
                if (!$cancelOk) {
                    error_log("CRITICAL: cycle switch cancel-old failed. user_id=$userId, subscription_id=$subscriptionId, old_paypal_sub_id={$sw['old_paypal_sub_id']}, new_paypal_sub_id=$paypalSubscriptionId");
                    $switchWarnings[] = "We couldn't automatically cancel your old PayPal subscription. Please cancel it manually from your PayPal account or contact support.";
                }
            }

            // 2. Issue the prorated refund (skip if no sale to refund against).
            //    Pass the original sale's currency so PayPal validates it.
            $refundSucceeded = false;
            if ($sw['refund_amount'] > 0 && !empty($sw['sale']['transaction_id'])) {
                $refundResult = refundPayPalSale(
                    $sw['sale']['transaction_id'],
                    $sw['refund_amount'],
                    $sw['sale']['currency'] ?? ($existingSubscription['currency'] ?? 'CAD'),
                    'Cycle switch proration'
                );
                if ($refundResult['success']) {
                    $refundSucceeded = true;
                } else {
                    error_log("CRITICAL: cycle switch refund failed. user_id=$userId, subscription_id=$subscriptionId, sale_id={$sw['sale']['transaction_id']}, refund_amount={$sw['refund_amount']}, error=" . ($refundResult['error'] ?? 'unknown'));
                    $switchWarnings[] = "Your prorated refund of $" . number_format($sw['refund_amount'], 2) . " is processing. If you don't see it within 10 business days, please contact support.";
                }
            } elseif ($sw['refund_amount'] > 0) {
                error_log("WARN: cycle switch refund skipped: no completed PayPal sale found. user_id=$userId, subscription_id=$subscriptionId, refund_amount={$sw['refund_amount']}");
                $switchWarnings[] = "We couldn't issue your prorated refund automatically because no recent PayPal payment was found. Please contact support if you expected a refund.";
            }

            // Credit is resolved after the commit: a refund that went through carries any excess forward, and one that did not restores the original credit so entitlement is not lost as well.
            $totalRefundableValue = (float) $sw['proration']['prorated_credit'] + $sw['existing_credit'];
            if ($refundSucceeded) {
                $creditBalanceAfter = max(0, round($totalRefundableValue - (float) $sw['refund_amount'], 2));
            } else {
                $creditBalanceAfter = (float) $sw['existing_credit'];
            }
            try {
                $stmt = $pdo->prepare("UPDATE premium_subscriptions SET credit_balance = ?, updated_at = NOW() WHERE subscription_id = ?");
                $stmt->execute([$creditBalanceAfter, $subscriptionId]);
            } catch (PDOException $e) {
                error_log("CRITICAL: failed to set credit_balance post-refund. user_id=$userId, subscription_id=$subscriptionId, intended_value=$creditBalanceAfter, error=" . $e->getMessage());
            }

            // 4. Send the cycle-changed email with refund details. Pass the
            //    actual post-refund credit_balance so the user sees it accurately.
            try {
                send_premium_subscription_cycle_changed_email(
                    $existingSubscription['email'] ?? $email,
                    $subscriptionId,
                    $sw['proration']['old_cycle'],
                    $sw['proration']['new_cycle'],
                    (float) $sw['proration']['prorated_credit'],
                    (float) $sw['existing_credit'],
                    (float) $newAmount,
                    $newEndDate,
                    (float) $creditBalanceAfter,
                    (float) $pricingConfig['premium_monthly_price'],
                    $refundSucceeded ? (float) $sw['refund_amount'] : 0.0,
                    $refundSucceeded ? 'PayPal' : null
                );
            } catch (Exception $e) {
                error_log("Failed to send cycle change email: " . $e->getMessage());
            }
        }

        // Says so plainly when the switch raises the rate, comparing monthly equivalents, so a bigger charge is no surprise to somebody on an older locked-in price.
        $priceIncreaseNote = '';
        $oldLockedBase = $existingSubscription['signup_base_price'] ?? null;
        if ($oldLockedBase !== null && (float) $oldLockedBase > 0) {
            $oldMonthly = ($existingSubscription['billing_cycle'] === 'yearly')
                ? (float) $oldLockedBase / 12
                : (float) $oldLockedBase;
            $newMonthly = ($billing === 'yearly') ? (float) $newAmount / 12 : (float) $newAmount;
            if ($newMonthly > $oldMonthly + 0.01) {
                $newRateStr = '$' . number_format((float) $newAmount, 2) . '/' . ($billing === 'yearly' ? 'year' : 'month');
                $priceIncreaseNote = " Note: changing your plan moves you to our current rate ($newRateStr). Your previous price was locked in when you first subscribed, and switching plans applies today's pricing.";
            }
        }

        // Build appropriate success message
        $formattedEndDate = date('F j, Y', strtotime($newEndDate));
        if ($isPayPalCycleSwitch) {
            $chargeMessage = "Subscription switched to $billing. PayPal will bill the new amount today; your prorated refund (if any) will appear in 5–10 business days.";
        } elseif ($skipPaymentProcessing) {
            $chargeMessage = "Your subscription has been updated. You will not be charged until $formattedEndDate.";
        } else {
            $chargeMessage = "Payment successful! Your subscription is now active until $formattedEndDate.";
        }
        $chargeMessage .= $priceIncreaseNote;

        $responseData = [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'message' => $chargeMessage,
            'next_billing_date' => $newEndDate,
        ];
        if (!empty($switchWarnings)) {
            $responseData['warnings'] = $switchWarnings;
        }
        echo json_encode($responseData);

    } else {
        // Create new subscription
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscriptions (
                subscription_id, user_id, email, billing_cycle, amount, signup_base_price, currency,
                start_date, end_date, status, payment_method, transaction_id,
                payment_token, stripe_customer_id, auto_renew, environment, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, 'active', ?, ?,
                ?, ?, 1, ?, NOW()
            )
        ");

        $stmt->execute([
            $subscriptionId,
            $userId,
            $email,
            $billing,
            $amount,
            // Lock the base price (pre-fee) at signup so future renewals charge
            // this rate even if the env price changes later. Grandfathering.
            $baseCharge,
            $currency,
            $startDate,
            $endDate,
            $paymentMethod,
            $transactionId,
            $paymentToken,
            $stripeCustomerId,
            current_environment()
        ]);

        // Update with PayPal subscription ID if applicable (column may not exist in older schema)
        if ($paypalSubscriptionId) {
            try {
                $stmt = $pdo->prepare("UPDATE premium_subscriptions SET paypal_subscription_id = ? WHERE subscription_id = ?");
                $stmt->execute([$paypalSubscriptionId, $subscriptionId]);
            } catch (PDOException $e) {
                // Column may not exist yet - log but don't fail
                error_log("Could not set paypal_subscription_id (column may not exist): " . $e->getMessage());
            }
        }

        // Log the payment transaction
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscription_payments (
                subscription_id, amount, currency, payment_method,
                transaction_id, status, payment_type, environment, created_at
            ) VALUES (?, ?, ?, ?, ?, 'completed', 'initial', ?, NOW())
        ");

        $stmt->execute([
            $subscriptionId,
            $amount,
            $currency,
            $paymentMethod,
            $transactionId,
            current_environment()
        ]);

        // The desktop app redeems against premium_subscription_keys, so a paid key is rejected without this row. device_id stays NULL so the first redemption binds to this subscription.
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscription_keys (
                subscription_key, email, duration_months,
                redeemed_at, device_id, subscription_id, notes
            ) VALUES (?, ?, ?, NOW(), NULL, ?, 'Auto-created for paid subscription')
        ");
        $stmt->execute([
            $subscriptionId,
            $email,
            ($billing === 'yearly') ? 12 : 1,
            $subscriptionId
        ]);

        $pdo->commit();

        // Mark referral visit as converted if this purchase came from a referral source.
        // The existing referral_visits write is load-bearing for the legacy admin charts.
        $referralSource = $_SESSION['referral_source'] ?? null;
        $visitorId      = $_COOKIE[ARGO_VISITOR_COOKIE] ?? null;

        // Creating an account or logging in resets the session, so the durable visitor cookie's first-touch source is the fallback.
        if (empty($referralSource) && !empty($visitorId)) {
            $referralSource = get_referral_source_for_visitor($visitorId);
        }

        // An affiliate is credited only when their click on this visitor is recent enough, so a stale click earns no commission on a sale it did not drive.
        if (!empty($referralSource)) {
            require_once __DIR__ . '/../../../community/affiliate/affiliate_functions.php';
            if (!affiliate_source_within_window($visitorId, $referralSource, current_environment())) {
                $referralSource = null;
            }
        }

        if (!empty($referralSource)) {
            try {
                $refStmt = $pdo->prepare("UPDATE referral_visits SET converted = 1, license_key = ? WHERE source_code = ? AND converted = 0 ORDER BY visited_at DESC LIMIT 1");
                $refStmt->execute([$subscriptionId, $referralSource]);
            } catch (Exception $e) {
                error_log("Failed to mark referral conversion: " . $e->getMessage());
            }
        }

        // Backfills this visitor's earlier funnel events too, so attribution stays attached across sessions they browsed logged out.
        try {
            track_referral_event('premium_signup', [
                'visitor_id'      => $visitorId,
                'source_code'     => $referralSource,
                'subscription_id' => $subscriptionId,
                'user_id'         => $userId,
                'event_data'      => [
                    'amount'        => $amount,
                    'currency'      => $currency,
                    'billing_cycle' => $billing ?? null,
                ],
            ]);

            track_referral_event('premium_paid', [
                'visitor_id'      => $visitorId,
                'source_code'     => $referralSource,
                'subscription_id' => $subscriptionId,
                'user_id'         => $userId,
                'event_data'      => [
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'payment_type'   => 'initial',
                    'payment_method' => $paymentMethod,
                ],
            ]);

            if (!empty($visitorId)) {
                backfill_visitor_events($visitorId, $subscriptionId, $userId);
            }
        } catch (Exception $e) {
            error_log("Failed to track premium signup events: " . $e->getMessage());
        }

        unset($_SESSION['referral_source']);

        // Send receipt email
        try {
            send_premium_subscription_receipt($email, $subscriptionId, $billing, $amount, $endDate, $transactionId, $paymentMethod);
        } catch (Exception $e) {
            // Log email error but don't fail the transaction
            error_log("Failed to send Premium subscription email: " . $e->getMessage());
        }

        // Best effort and gated by the admin's notification preferences, so a failed notification never affects the customer's checkout.
        try {
          if (admin_notification_enabled('notify_new_customer')) {
            $providerNames = ['paypal' => 'PayPal', 'stripe' => 'Stripe', 'square' => 'Square'];
            $custEmail  = htmlspecialchars($email);
            $planSafe   = htmlspecialchars(ucfirst($billing));
            $amtSafe    = htmlspecialchars(number_format((float) $amount, 2));
            $curSafe    = htmlspecialchars($currency);
            $methodSafe = htmlspecialchars($providerNames[strtolower($paymentMethod)] ?? ucfirst($paymentMethod));
            $subSafe    = htmlspecialchars($subscriptionId);
            $envLabel   = htmlspecialchars(current_environment());
            $newCustomerBody = <<<HTML
                <h2>You have a new paying customer 🎉</h2>
                <p>A new Premium subscription was just created.</p>
                <ul>
                    <li><strong>Customer:</strong> $custEmail</li>
                    <li><strong>Plan:</strong> $planSafe</li>
                    <li><strong>Amount:</strong> \$$amtSafe $curSafe</li>
                    <li><strong>Payment method:</strong> $methodSafe</li>
                    <li><strong>Subscription ID:</strong> $subSafe</li>
                    <li><strong>Environment:</strong> $envLabel</li>
                </ul>
                HTML;
            send_styled_email(
                admin_notification_email(),
                "[Argo Books] New paying customer: $custEmail",
                $newCustomerBody,
                'premium',
                null,
                null,
                $email
            );
          }
        } catch (Exception $e) {
            error_log("Failed to send new-customer admin notification: " . $e->getMessage());
        }

        // PayPal bills the plan's baked-in price rather than the amount computed here, so a stale plan charges the wrong amount while the receipt shows ours. Best effort, never blocking checkout.
        if ($paypalSubscriptionId) {
            try {
                require_once __DIR__ . '/../../../paypal-helper.php';
                $paypalBilled = getPayPalSubscriptionBilledAmount($paypalSubscriptionId);
                if ($paypalBilled !== null && abs($paypalBilled - (float) $amount) > 0.01) {
                    $expectedStr = number_format((float) $amount, 2);
                    $billedStr   = number_format($paypalBilled, 2);
                    error_log(
                        "CRITICAL: PayPal price mismatch. subscription_id=$subscriptionId, "
                        . "paypal_subscription_id=$paypalSubscriptionId, expected=$expectedStr $currency, "
                        . "paypal_will_bill=$billedStr $currency. Regenerate the PayPal plans "
                        . "(setup-paypal-plans.php) and update the plan IDs in .env."
                    );

                    $envLabel = htmlspecialchars(current_environment());
                    $subSafe  = htmlspecialchars($subscriptionId);
                    $paypalSafe = htmlspecialchars($paypalSubscriptionId);
                    $emailSafe = htmlspecialchars($email);
                    $curSafe   = htmlspecialchars($currency);
                    $expSafe   = htmlspecialchars($expectedStr);
                    $billSafe  = htmlspecialchars($billedStr);
                    $alertBody = <<<HTML
                        <p><strong>A PayPal subscription was charged a different amount than the receipt shows.</strong> This means the live PayPal plan price has drifted from the configured price. Regenerate the plans and update the plan IDs in <code>.env</code>.</p>

                        <h3 style="margin-top:24px;">Subscription</h3>
                        <ul>
                            <li><strong>Subscription ID:</strong> $subSafe</li>
                            <li><strong>PayPal subscription ID:</strong> $paypalSafe</li>
                            <li><strong>Customer email:</strong> $emailSafe</li>
                            <li><strong>Environment:</strong> $envLabel</li>
                        </ul>

                        <h3 style="margin-top:24px;">Amounts</h3>
                        <ul>
                            <li><strong>Receipt / expected:</strong> \$$expSafe $curSafe</li>
                            <li><strong>PayPal will actually bill:</strong> \$$billSafe $curSafe</li>
                        </ul>

                        <h3 style="margin-top:24px;">Fix</h3>
                        <ol>
                            <li>Run <code>php setup-paypal-plans.php</code> in the affected environment.</li>
                            <li>Paste the new plan IDs into <code>.env</code> (<code>PAYPAL_*_MONTHLY_PLAN_ID</code> / <code>PAYPAL_*_YEARLY_PLAN_ID</code>).</li>
                            <li>Existing subscribers keep their current price; only new checkouts use the new plans.</li>
                        </ol>
                        HTML;
                    send_styled_email(
                        'contact@argorobots.com',
                        "[Argo Books] PayPal price mismatch: $subscriptionId",
                        $alertBody,
                        'premium',
                        null,
                        null,
                        $email
                    );
                }
            } catch (Exception $e) {
                error_log("PayPal price-drift guard failed: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'subscription_id' => $subscriptionId,
            'message' => 'Subscription created successfully'
        ]);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Premium Subscription Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create subscription. Please contact support.'
    ]);
}
