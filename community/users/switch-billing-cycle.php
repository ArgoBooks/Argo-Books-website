<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/../../resources/icons.php';

require_login();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$premium_subscription = get_user_premium_subscription($user_id);

// Eligibility guards. On any failure, redirect back to subscription.php
// with an error flash; UI handles display.
$redirect_with_error = function ($message) {
    $_SESSION['subscription_error'] = $message;
    header('Location: subscription.php');
    exit;
};

if (!$premium_subscription) {
    $redirect_with_error('No subscription found.');
}

if ($premium_subscription['status'] !== 'active') {
    $redirect_with_error('Switching billing cycle is only available for active subscriptions.');
}

if (strtotime($premium_subscription['end_date']) <= time()) {
    $redirect_with_error('Your subscription period has ended. Please renew first.');
}

if (!empty($premium_subscription['last_cycle_change_at'])
    && (time() - strtotime($premium_subscription['last_cycle_change_at'])) < 300) {
    $redirect_with_error('Please wait a few minutes between billing cycle changes.');
}

$payment_method = strtolower($premium_subscription['payment_method'] ?? '');
$is_paypal = ($payment_method === 'paypal');

// Only Stripe, Square, and PayPal reach this page. free_key, manual, or unknown payment methods can't switch cycles, so redirect rather than render a confirm UI that would inevitably fail.
if (!in_array($payment_method, ['stripe', 'square', 'paypal'], true)) {
    $redirect_with_error('Cycle switching is not available for this subscription type. Please contact support.');
}

// PayPal needs a completed sale to refund against, which it has none of seconds after subscribing.
if ($is_paypal) {
    require_once __DIR__ . '/../../paypal-helper.php';
    $paypal_recent_sale = getMostRecentPayPalSale($premium_subscription['subscription_id']);
    if (!$paypal_recent_sale) {
        $redirect_with_error('Your subscription is still being activated by PayPal. Please wait a few minutes after subscribing before changing cycles.');
    }
}

$pricing_config = get_pricing_config();
$monthly_base = $pricing_config['premium_monthly_price'];
$yearly_base = $pricing_config['premium_yearly_price'];
$old_cycle = $premium_subscription['billing_cycle'];
$new_cycle = ($old_cycle === 'monthly') ? 'yearly' : 'monthly';

// Worked out on the server, and worked out again by the endpoint that charges, so a tampered figure on the page cannot be billed.
$proration = calculate_cycle_switch_proration($premium_subscription, $new_cycle, $pricing_config);

// PayPal-only refund estimate (same math used by checkout disclosure banner)
$paypal_refund_estimate = 0.0;
if ($is_paypal && ($proration['direction'] ?? '') !== 'noop') {
    $paypal_refund_estimate = round(
        (float) $proration['prorated_credit']
        + (float) ($premium_subscription['credit_balance'] ?? 0),
        2
    );
    // Cap at the most recent sale's amount (PayPal won't refund more
    // than the original sale).
    if ($paypal_recent_sale && $paypal_refund_estimate > (float) $paypal_recent_sale['amount']) {
        $paypal_refund_estimate = (float) $paypal_recent_sale['amount'];
    }
}

// Stripe publishable key for 3DS handling (only loaded when needed)
$is_production = ($_ENV['APP_ENV'] ?? 'development') === 'production';
$stripe_publishable_key = '';
if ($payment_method === 'stripe') {
    $stripe_publishable_key = $is_production
        ? ($_ENV['STRIPE_LIVE_PUBLISHABLE_KEY'] ?? '')
        : ($_ENV['STRIPE_SANDBOX_PUBLISHABLE_KEY'] ?? '');
}

$old_cycle_label = ucfirst($old_cycle);
$new_cycle_label = ucfirst($new_cycle);
$is_upgrade = ($new_cycle === 'yearly');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="Switch your Argo Books Premium billing cycle">
    <meta name="author" content="Argo">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Switch Billing Cycle - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>
    <?php if ($payment_method === 'stripe' && !empty($stripe_publishable_key)): ?>
        <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>

    <link rel="stylesheet" href="subscription.css">
    <link rel="stylesheet" href="subscription-confirm.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">

    <style>
    .proration-breakdown {
        background: var(--gray-50);
        border: 1px solid var(--gray-border);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .proration-breakdown h3 {
        font-size: 14px;
        font-weight: 600;
        color: var(--gray-700);
        margin: 0 0 12px 0;
    }

    .proration-line {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        color: var(--black);
    }

    .proration-line.discount .value {
        color: var(--green-700);
    }

    .proration-line .label {
        text-align: left;
    }

    .proration-line .value {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .proration-divider {
        border-top: 1px solid var(--gray-border);
        margin: 8px 0;
    }

    .proration-total {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--gray-900);
    }

    .proration-footer-line {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
        color: var(--gray-700);
    }

    .credit-coverage-hint {
        font-size: 13px;
        color: var(--gray-600);
        margin-top: 8px;
        font-style: italic;
        text-align: left;
    }
    </style>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="confirm-page-container">
        <div class="confirm-card reactivate-card">
            <div class="confirm-icon reactivate-icon">
                <?= svg_icon('refresh', 48) ?>
            </div>

            <?php if ($is_paypal):
                // PayPal flow: show the breakdown with refund-style copy, then send the user to checkout where they'll approve a new PayPal subscription with the new plan_id.
                $new_billed_base = ($new_cycle === 'yearly') ? $yearly_base : $monthly_base;
                $new_billed_fee = 0.0;
                $new_billed_total = round($new_billed_base, 2);
                $existing_credit_used = (float) ($premium_subscription['credit_balance'] ?? 0);
                $next_billing_date = date('F j, Y', strtotime(($new_cycle === 'yearly') ? '+1 year' : '+1 month'));
            ?>

                <h1>Switch to <?= htmlspecialchars(ucfirst($new_cycle)) ?> Billing?</h1>

                <p class="confirm-description">
                    You'll be redirected to PayPal to approve a new <?= htmlspecialchars($new_cycle) ?>
                    subscription. PayPal will bill the new amount on activation, and the prorated
                    value of your unused <?= htmlspecialchars($old_cycle) ?> period
                    <?php if ($existing_credit_used > 0): ?>(plus your existing account credit) <?php endif; ?>will
                    be refunded to your PayPal account within 5–10 business days.
                </p>

                <div class="proration-breakdown">
                    <h3>Charge & Refund Breakdown</h3>

                    <div class="proration-line">
                        <span class="label">New <?= htmlspecialchars($new_cycle) ?> subscription</span>
                        <span class="value">$<?= number_format($new_billed_base, 2) ?> CAD</span>
                    </div>

                    <?php if ($new_billed_fee > 0): ?>
                    <div class="proration-line">
                        <span class="label">Processing fee</span>
                        <span class="value">$<?= number_format($new_billed_fee, 2) ?> CAD</span>
                    </div>
                    <?php endif; ?>

                    <div class="proration-divider"></div>

                    <div class="proration-total">
                        <span>Charged today by PayPal</span>
                        <span>$<?= number_format($new_billed_total, 2) ?> CAD</span>
                    </div>

                    <?php if ($paypal_refund_estimate > 0): ?>
                        <div class="proration-divider"></div>

                        <div class="proration-line discount">
                            <span class="label">Prorated refund (5–10 business days)</span>
                            <span class="value">-$<?= number_format($paypal_refund_estimate, 2) ?> CAD</span>
                        </div>

                        <?php if ($proration['prorated_credit'] > 0): ?>
                        <div class="proration-line" style="font-size:13px; color:var(--gray-600); padding-left:12px;">
                            <span class="label">Unused <?= htmlspecialchars($old_cycle) ?> period</span>
                            <span class="value">$<?= number_format($proration['prorated_credit'], 2) ?> CAD</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($existing_credit_used > 0): ?>
                        <div class="proration-line" style="font-size:13px; color:var(--gray-600); padding-left:12px;">
                            <span class="label">Existing account credit</span>
                            <span class="value">$<?= number_format($existing_credit_used, 2) ?> CAD</span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="proration-divider"></div>

                    <div class="proration-footer-line">
                        <span>Next billing date</span>
                        <span><?= htmlspecialchars($next_billing_date) ?></span>
                    </div>
                </div>

                <div class="confirm-actions">
                    <a href="../../pricing/premium/checkout/?method=paypal&billing=<?= urlencode($new_cycle) ?>&change_method=1&cycle_switch=1"
                       class="btn btn-premium">Continue with PayPal →</a>
                    <a href="subscription.php" class="btn btn-outline">Cancel</a>
                </div>

            <?php else:
                // Stripe / Square: render proration breakdown
                $charge_today = $proration['immediate_charge_total'];
                $proc_fee = $proration['processing_fee'];
                $prorated_credit = $proration['prorated_credit'];
                $existing_credit_used = $proration['existing_credit_consumed'];
                $credit_after = $proration['credit_balance_after'];
                $next_billing_date = date('F j, Y', strtotime($proration['new_end_date']));
                $months_covered = ($credit_after > 0 && $monthly_base > 0)
                    ? (int) floor($credit_after / $monthly_base)
                    : 0;
                $payment_method_display = ucfirst($payment_method);
            ?>

                <h1>Switch to <?= htmlspecialchars($new_cycle_label) ?> Billing?</h1>

                <p class="confirm-description">
                    You're about to change your Argo Books Premium subscription from
                    <strong><?= htmlspecialchars($old_cycle_label) ?></strong>
                    to <strong><?= htmlspecialchars($new_cycle_label) ?></strong>.
                </p>

                <div class="proration-breakdown">
                    <h3>Charge Breakdown</h3>

                    <div class="proration-line">
                        <span class="label"><?= htmlspecialchars($new_cycle_label) ?> subscription</span>
                        <span class="value">$<?= number_format($is_upgrade ? $yearly_base : $monthly_base, 2) ?> CAD</span>
                    </div>

                    <?php if ($prorated_credit > 0): ?>
                    <div class="proration-line discount">
                        <span class="label">Unused <?= htmlspecialchars(strtolower($old_cycle_label)) ?> period (credit)</span>
                        <span class="value">-$<?= number_format($prorated_credit, 2) ?> CAD</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($existing_credit_used > 0): ?>
                    <div class="proration-line discount">
                        <span class="label">Existing account credit applied</span>
                        <span class="value">-$<?= number_format($existing_credit_used, 2) ?> CAD</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($proc_fee > 0): ?>
                    <div class="proration-line">
                        <span class="label">Processing fee</span>
                        <span class="value">$<?= number_format($proc_fee, 2) ?> CAD</span>
                    </div>
                    <?php endif; ?>

                    <div class="proration-divider"></div>

                    <div class="proration-total">
                        <span>Charged today</span>
                        <span>$<?= number_format($charge_today, 2) ?> CAD</span>
                    </div>

                    <?php if ($credit_after > 0): ?>
                    <div class="proration-divider"></div>
                    <div class="proration-footer-line">
                        <span>Remaining credit balance</span>
                        <span>$<?= number_format($credit_after, 2) ?> CAD</span>
                    </div>
                    <?php if ($months_covered > 0): ?>
                    <p class="credit-coverage-hint">
                        Your credit covers approximately <?= $months_covered ?>
                        future <?= $months_covered === 1 ? 'renewal' : 'renewals' ?>
                        before you'll be charged again.
                    </p>
                    <?php endif; ?>
                    <?php endif; ?>

                    <div class="proration-divider"></div>

                    <div class="proration-footer-line">
                        <span>Next billing date</span>
                        <span><?= htmlspecialchars($next_billing_date) ?></span>
                    </div>

                    <?php if ($charge_today > 0): ?>
                    <div class="proration-footer-line">
                        <span>Payment method</span>
                        <span><?= htmlspecialchars($payment_method_display) ?> (saved card)</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="confirm-actions">
                    <button type="button" id="confirm-switch-btn" class="btn btn-premium">
                        <?php if ($charge_today > 0): ?>
                            Confirm Switch: Charge $<?= number_format($charge_today, 2) ?> CAD
                        <?php else: ?>
                            Confirm Switch (no charge today)
                        <?php endif; ?>
                    </button>
                    <a href="subscription.php" class="btn btn-outline">Cancel</a>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <?php if (!$is_paypal): ?>
    <!-- Switch confirmation modal: pattern matches retry-payment modal in subscription.php -->
    <div class="modal-overlay" id="switch-modal">
        <div class="modal-container">
            <button class="modal-close" id="modal-close-btn" aria-label="Close modal">
                <?= svg_icon('x', 24) ?>
            </button>

            <div class="modal-state" id="modal-state-loading">
                <div class="modal-spinner"><div class="spinner"></div></div>
                <h2>Processing...</h2>
                <p class="modal-description" id="loading-message">Switching your billing cycle.</p>
            </div>

            <div class="modal-state hidden" id="modal-state-success">
                <div class="modal-icon success">
                    <?= svg_icon('circle-check', 48) ?>
                </div>
                <h2>Switch Complete!</h2>
                <p class="modal-description" id="success-message"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-premium" id="success-close-btn">Done</button>
                </div>
            </div>

            <div class="modal-state hidden" id="modal-state-error">
                <div class="modal-icon error">
                    <?= svg_icon('x-circle', 48) ?>
                </div>
                <h2>Unable to Switch</h2>
                <p class="modal-description" id="error-message"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" id="error-close-btn">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>

    <?php if (!$is_paypal): ?>
    <script>
    (function() {
        const NEW_CYCLE = <?= json_encode($new_cycle) ?>;
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
        const PAYMENT_METHOD = <?= json_encode($payment_method) ?>;
        const STRIPE_PK = <?= json_encode($stripe_publishable_key) ?>;

        const modal = document.getElementById('switch-modal');
        const closeBtn = document.getElementById('modal-close-btn');
        const stateLoading = document.getElementById('modal-state-loading');
        const stateSuccess = document.getElementById('modal-state-success');
        const stateError = document.getElementById('modal-state-error');
        const loadingMessage = document.getElementById('loading-message');
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        const successCloseBtn = document.getElementById('success-close-btn');
        const errorCloseBtn = document.getElementById('error-close-btn');
        const confirmSwitchBtn = document.getElementById('confirm-switch-btn');

        let stripe = null;
        if (PAYMENT_METHOD === 'stripe' && STRIPE_PK && typeof Stripe !== 'undefined') {
            stripe = Stripe(STRIPE_PK);
        }

        function showState(state) {
            [stateLoading, stateSuccess, stateError].forEach(s => s.classList.add('hidden'));
            state.classList.remove('hidden');
            closeBtn.style.display = (state === stateLoading) ? 'none' : 'block';
        }

        function openModal(initialState) {
            showState(initialState || stateLoading);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function postSwitch(extraBody) {
            const body = Object.assign({
                csrf_token: CSRF_TOKEN,
                new_cycle: NEW_CYCLE
            }, extraBody || {});

            return fetch('switch-billing-cycle-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(r => r.json());
        }

        function handleSuccess(data) {
            successMessage.textContent = data.message || 'Your billing cycle has been switched.';
            showState(stateSuccess);
        }

        function handleError(message) {
            errorMessage.textContent = message || 'An unexpected error occurred. Please try again.';
            showState(stateError);
        }

        function handleResponse(data) {
            if (data.success) {
                handleSuccess(data);
                return;
            }

            // Stripe 3DS / SCA challenge
            if (data.action === 'sca_required' && data.client_secret && stripe) {
                loadingMessage.textContent = 'Your bank requires additional authentication.';
                showState(stateLoading);
                stripe.confirmCardPayment(data.client_secret).then(function(result) {
                    if (result.error) {
                        handleError(result.error.message || 'Authentication failed.');
                        return;
                    }
                    if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                        loadingMessage.textContent = 'Authentication complete. Finalizing...';
                        postSwitch({ confirmed_payment_intent_id: result.paymentIntent.id })
                            .then(handleResponse)
                            .catch(function() {
                                handleError('Network error after authentication. Please contact support.');
                            });
                    } else {
                        handleError('Authentication did not complete. Please try again.');
                    }
                });
                return;
            }

            handleError(data.error);
        }

        confirmSwitchBtn.addEventListener('click', function() {
            confirmSwitchBtn.disabled = true;
            loadingMessage.textContent = 'Switching your billing cycle.';
            openModal(stateLoading);

            postSwitch().then(handleResponse).catch(function() {
                handleError('Network error. Please try again.');
            }).finally(function() {
                confirmSwitchBtn.disabled = false;
            });
        });

        closeBtn.addEventListener('click', closeModal);
        errorCloseBtn.addEventListener('click', closeModal);
        successCloseBtn.addEventListener('click', function() {
            window.location.href = 'subscription.php';
        });

        // Close on overlay click (only outside loading state, mousedown must start on backdrop)
        let modalMouseDownTarget = null;
        modal.addEventListener('mousedown', function(e) {
            modalMouseDownTarget = e.target;
        });
        modal.addEventListener('click', function(e) {
            if (e.target === modal && modalMouseDownTarget === modal && stateLoading.classList.contains('hidden')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active') && stateLoading.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>

</html>
