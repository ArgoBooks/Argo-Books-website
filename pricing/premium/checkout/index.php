<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="Premium Subscription Checkout - Argo Books">
    <meta name="author" content="Argo">
    <link rel="shortcut icon" type="image/x-icon" href="../../../resources/images/argo-logo/argo-icon.ico">
    <title>Premium Subscription Checkout - Argo Books</title>

    <?php
    session_start();
    require_once __DIR__ . '/../../../db_connect.php';
    require_once __DIR__ . '/../../../community/users/user_functions.php';
    require_once __DIR__ . '/../../../config/pricing.php';

    $pricing = get_pricing_config();

    // Require login to checkout. No explicit target: require_login() falls back
    // to REQUEST_URI, so ?billing= / ?method= survive the login round-trip.
    require_login();

    $user_id = $_SESSION['user_id'];
    $user_email = $_SESSION['email'] ?? '';

    // Check if user already has an active subscription
    $existing_subscription = get_user_premium_subscription($user_id);
    $is_changing_method = isset($_GET['change_method']) && $_GET['change_method'] === '1';
    $is_cycle_switch = isset($_GET['cycle_switch']) && $_GET['cycle_switch'] === '1';

    if ($existing_subscription && in_array($existing_subscription['status'], ['active', 'cancelled', 'payment_failed'])) {
        // User already has a subscription
        $has_valid_subscription = $existing_subscription['status'] === 'active' ||
            (in_array($existing_subscription['status'], ['cancelled', 'payment_failed']) && strtotime($existing_subscription['end_date']) > time());

        if ($has_valid_subscription && !$is_changing_method) {
            // Redirect to subscription page unless they're changing payment method
            header('Location: ../../../community/users/subscription.php');
            exit;
        }
    }

    // Get environment-based keys
    $is_production = $_ENV['APP_ENV'] === 'production';

    $stripe_publishable_key = $is_production
        ? $_ENV['STRIPE_LIVE_PUBLISHABLE_KEY']
        : $_ENV['STRIPE_SANDBOX_PUBLISHABLE_KEY'];

    $paypal_client_id = $is_production
        ? $_ENV['PAYPAL_LIVE_CLIENT_ID']
        : $_ENV['PAYPAL_SANDBOX_CLIENT_ID'];

    // PayPal subscription plan IDs (create these in PayPal dashboard)
    $paypal_monthly_plan_id = $is_production
        ? ($_ENV['PAYPAL_LIVE_MONTHLY_PLAN_ID'] ?? '')
        : ($_ENV['PAYPAL_SANDBOX_MONTHLY_PLAN_ID'] ?? '');

    $paypal_yearly_plan_id = $is_production
        ? ($_ENV['PAYPAL_LIVE_YEARLY_PLAN_ID'] ?? '')
        : ($_ENV['PAYPAL_SANDBOX_YEARLY_PLAN_ID'] ?? '');

    $square_app_id = $is_production
        ? $_ENV['SQUARE_LIVE_APP_ID']
        : $_ENV['SQUARE_SANDBOX_APP_ID'];

    $square_location_id = $is_production
        ? $_ENV['SQUARE_LIVE_LOCATION_ID']
        : $_ENV['SQUARE_SANDBOX_LOCATION_ID'];

    // Get URL parameters - whitelist to allowed values to prevent injection
    $billing = isset($_GET['billing']) && in_array($_GET['billing'], ['monthly', 'yearly'], true)
        ? $_GET['billing']
        : 'monthly';

    // The method is chosen here, and main.js swaps providers in place so the page never reloads for a change of method.
    $method = isset($_GET['method']) && in_array($_GET['method'], ['stripe', 'paypal', 'square'], true)
        ? $_GET['method']
        : '';

    // The cycle-switch flow hides the method picker (see below), so a missing or malformed ?method= there would leave the page with nothing to act on.
    if ($is_cycle_switch && $method === '') {
        $method = 'paypal';
    }

    // Card payment is what most buyers want, so Stripe is preselected and its
    // form is ready to fill on arrival.
    if ($method === '') {
        $method = 'stripe';
    }

    // Rebuild the query string from whitelisted values only; never reflect raw $_GET.
    $checkoutParams = [
        'billing'       => $billing,
        'method'        => $method,
        'change_method' => $is_changing_method ? '1' : '',
        'cycle_switch'  => $is_cycle_switch ? '1' : '',
    ];
    $checkout_url = static function (array $overrides) use ($checkoutParams): string {
        $params = array_filter(array_merge($checkoutParams, $overrides), static fn($v) => $v !== '');
        return '?' . http_build_query($params);
    };

    $paymentMethodLabels = [
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'square' => 'Square',
    ];

    // Calculate prices from centralized config
    $monthlyPrice = $pricing['premium_monthly_price'];
    $yearlyPrice = $pricing['premium_yearly_price'];

    if ($billing === 'yearly') {
        $basePrice = $yearlyPrice;
        $finalPrice = $yearlyPrice;
        $billingPeriod = 'year';
    } else {
        $basePrice = $monthlyPrice;
        $finalPrice = $monthlyPrice;
        $billingPeriod = 'month';
    }

    // Processing fee
    $feeToday = calculate_processing_fee($finalPrice);
    $totalToday = $finalPrice + $feeToday;

    // Renewal amounts (same as initial since no discount)
    $renewalBase = ($billing === 'yearly') ? $yearlyPrice : $monthlyPrice;
    $renewalFee = calculate_processing_fee($renewalBase);
    $renewalTotal = $renewalBase + $renewalFee;

    // Both cycles are computed here and handed to the page, so the switcher updates the summary without a reload.
    $cycleFigures = [];
    foreach (['monthly', 'yearly'] as $cyc) {
        $cycBase  = ($cyc === 'yearly') ? $yearlyPrice : $monthlyPrice;
        $cycFee   = calculate_processing_fee($cycBase);
        $cycTotal = $cycBase + $cycFee;
        $cycleFigures[$cyc] = [
            'label'        => ucfirst($cyc),
            'period'       => ($cyc === 'yearly') ? 'year' : 'month',
            'base'         => $cycBase,
            'fee'          => $cycFee,
            'total'        => $cycTotal,
            'baseDisplay'  => number_format($cycBase, 2),
            'feeDisplay'   => number_format($cycFee, 2),
            'totalDisplay' => number_format($cycTotal, 2),
            'url'          => $checkout_url(['billing' => $cyc]),
        ];
    }

    // PayPal only: a Stripe or Square cycle switch goes through switch-billing-cycle-ajax.php and never reaches this page.
    $cycleSwitchRefundEstimate = 0.0;
    $cycleSwitchOldCycle = '';
    if ($is_cycle_switch && $existing_subscription
        && strtolower($existing_subscription['payment_method'] ?? '') === 'paypal') {
        require_once __DIR__ . '/../../../community/users/user_functions.php';
        $cycleSwitchOldCycle = $existing_subscription['billing_cycle'] ?? '';
        $cycleSwitchProration = calculate_cycle_switch_proration($existing_subscription, $billing, $pricing);
        if (($cycleSwitchProration['direction'] ?? '') !== 'noop') {
            $cycleSwitchRefundEstimate = round(
                (float) $cycleSwitchProration['prorated_credit']
                + (float) ($existing_subscription['credit_balance'] ?? 0),
                2
            );
        }
    }
    ?>

    <!-- Payment processor keys -->
    <?php $je = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
    <script>
        window.PAYMENT_CONFIG = {
            stripe: {
                publishableKey: <?php echo json_encode($stripe_publishable_key, $je); ?>
            },
            paypal: {
                clientId: <?php echo json_encode($paypal_client_id, $je); ?>,
                monthlyPlanId: <?php echo json_encode($paypal_monthly_plan_id, $je); ?>,
                yearlyPlanId: <?php echo json_encode($paypal_yearly_plan_id, $je); ?>
            },
            square: {
                appId: <?php echo json_encode($square_app_id, $je); ?>,
                locationId: <?php echo json_encode($square_location_id, $je); ?>
            }
        };

        // Pre-rendered figures for both cycles, so switching is a text swap.
        window.CHECKOUT_CYCLES = <?php echo json_encode($cycleFigures, $je); ?>;

        window.AI_SUBSCRIPTION = {
            billing: <?php echo json_encode($billing, $je); ?>,
            method: <?php echo json_encode($method, $je); ?>,
            basePrice: <?php echo $basePrice; ?>,
            finalPrice: <?php echo $finalPrice; ?>,
            processingFee: <?php echo $feeToday; ?>,
            totalCharge: <?php echo $totalToday; ?>,
            userId: <?php echo $user_id; ?>,
            userEmail: <?php echo json_encode($user_email, $je); ?>,
            isUpdatingPaymentMethod: <?php echo $is_changing_method ? 'true' : 'false'; ?>,
            isCycleSwitch: <?php echo $is_cycle_switch ? 'true' : 'false'; ?>
        };
    </script>

    <script src="main.js"></script>
    <script src="../../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../../resources/styles/link.css">
    <link rel="stylesheet" href="../../../resources/header/style.css">
    <link rel="stylesheet" href="../../../resources/header/dark.css">
    <link rel="stylesheet" href="../../../resources/footer/style.css">

    <style>
    /* Cycle-switch refund disclosure banner. Rendered only when the user
       arrives via switch-billing-cycle.php with cycle_switch=1. */
    .cycle-switch-banner {
        background: var(--premium-50, #eef2fe);
        border: 1px solid var(--premium-200, #c2cffb);
        border-left: 4px solid var(--premium-500, #3f63e8);
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.5;
        color: var(--gray-800, #1f2937);
    }
    </style>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../../resources/header/header.php'; ?>
    </header>

    <section class="checkout-container">
        <h1>Complete Your Premium Subscription</h1>

        <div class="checkout-form">
            <!-- main.js retitles this when the payment method changes. -->
            <h2><?php echo htmlspecialchars($paymentMethodLabels[$method]); ?> Checkout</h2>

            <?php if ($is_cycle_switch && $cycleSwitchOldCycle && $cycleSwitchOldCycle !== $billing): ?>
                <div class="cycle-switch-banner">
                    <strong>Switching from <?php echo htmlspecialchars(ucfirst($cycleSwitchOldCycle)); ?> to <?php echo htmlspecialchars(ucfirst($billing)); ?>.</strong>
                    PayPal will bill <strong>$<?php echo number_format($basePrice, 2); ?> CAD</strong> on activation (subscription only, with no processing fee on PayPal).
                    <?php if ($cycleSwitchRefundEstimate > 0): ?>
                        Your prorated refund of about <strong>$<?php echo number_format($cycleSwitchRefundEstimate, 2); ?> CAD</strong> will appear in your PayPal account within 5–10 business days.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            // Hidden for the method-change and cycle-switch flows, where the cycle is settled upstream and changing it here would desync.
            if (!$is_changing_method && !$is_cycle_switch): ?>
                <!-- Real hrefs so this works without JS; main.js intercepts and
                     swaps the figures in place, same as the method picker. -->
                <div class="cycle-switcher" role="group" aria-label="Billing cycle">
                    <a href="<?php echo htmlspecialchars($checkout_url(['billing' => 'monthly'])); ?>"
                       data-cycle="monthly"
                       class="cycle-switcher-btn<?php echo $billing === 'monthly' ? ' active' : ''; ?>"
                       <?php echo $billing === 'monthly' ? 'aria-current="true"' : ''; ?>>Monthly</a>
                    <a href="<?php echo htmlspecialchars($checkout_url(['billing' => 'yearly'])); ?>"
                       data-cycle="yearly"
                       class="cycle-switcher-btn<?php echo $billing === 'yearly' ? ' active' : ''; ?>"
                       <?php echo $billing === 'yearly' ? 'aria-current="true"' : ''; ?>>Annual</a>
                </div>
            <?php endif; ?>

            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="order-item">
                    <span>Argo Books Premium (<span data-cycle-label><?php echo ucfirst($billing); ?></span>)</span>
                    <span>$<span data-cycle-base><?php echo number_format($basePrice, 2); ?></span> CAD</span>
                </div>
                <div class="order-item" data-cycle-fee-row<?php echo $feeToday > 0 ? '' : ' style="display:none"'; ?>>
                    <span>Payment Processor Fee</span>
                    <span>$<span data-cycle-fee><?php echo number_format($feeToday, 2); ?></span> CAD</span>
                </div>
                <div class="order-total">
                    <span>Total</span>
                    <span>$<span data-cycle-total><?php echo number_format($totalToday, 2); ?></span> CAD/<span data-cycle-period><?php echo $billingPeriod; ?></span></span>
                </div>
            </div>

            <div class="subscription-notice">
                <p>You will be charged $<span data-cycle-total><?php echo number_format($totalToday, 2); ?></span> CAD today, then $<span data-cycle-renewal><?php echo number_format($renewalTotal, 2); ?></span> CAD/<span data-cycle-period><?php echo $billingPeriod; ?></span> on each renewal.</p>
                <p>Cancel anytime from your account settings.</p>
            </div>

            <?php
            // Hidden for the cycle-switch flow, which routes only PayPal subscribers here and shows the proration disclosure above.
            if (!$is_cycle_switch): ?>
            <div class="method-picker">
                <h3>Payment method</h3>
                <div class="payment-grid">
                    <!-- Real hrefs so the picker still works without JS. main.js
                         intercepts the click and swaps the form in place, so the
                         page neither reloads nor jumps back to the top. -->
                    <a class="payment-btn<?php echo $method === 'paypal' ? ' is-selected' : ''; ?>"
                       data-method="paypal" aria-label="Pay with PayPal"
                       href="<?php echo htmlspecialchars($checkout_url(['method' => 'paypal'])); ?>">
                        <img src="../../../resources/images/PayPal-logo.svg" alt="PayPal">
                    </a>
                    <a class="payment-btn<?php echo $method === 'stripe' ? ' is-selected' : ''; ?>"
                       data-method="stripe" aria-label="Pay with Stripe"
                       href="<?php echo htmlspecialchars($checkout_url(['method' => 'stripe'])); ?>">
                        <img class="Stripe" src="../../../resources/images/Stripe-logo.svg" alt="Stripe">
                    </a>
                    <a class="payment-btn<?php echo $method === 'square' ? ' is-selected' : ''; ?>"
                       data-method="square" aria-label="Pay with Square"
                       href="<?php echo htmlspecialchars($checkout_url(['method' => 'square'])); ?>">
                        <img class="Square" src="../../../resources/images/Square-logo.svg" alt="Square">
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div id="stripe-container" style="display: none;">
                <form id="stripe-payment-form">
                    <div class="form-group">
                        <label for="card-holder">Cardholder Name</label>
                        <input type="text" id="card-holder" name="card-holder" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="card-element">Card Details</label>
                        <div id="card-element" class="form-control">
                            <!-- Stripe Elements Placeholder -->
                        </div>
                        <div id="card-errors" role="alert" class="stripe-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <!-- No data-cycle spans here: main.js already rewrites this
                         label's textContent on submit and on error, so the
                         cycle switcher updates it the same way. -->
                    <button type="submit" id="stripe-submit-btn" class="checkout-btn ai-checkout-btn">
                        Subscribe - $<?php echo number_format($totalToday, 2); ?> CAD/<?php echo $billingPeriod; ?>
                    </button>
                </form>
            </div>

            <div id="square-container" style="display: none;">
                <!-- Square payment form will be inserted here by JavaScript -->
            </div>
        </div>
    </section>

    <footer class="footer">
        <?php include __DIR__ . '/../../../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>
