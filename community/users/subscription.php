<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/../../resources/icons.php';

$pricing = get_pricing_config();
$monthlyPrice = $pricing['premium_monthly_price'];
$yearlyPrice = $pricing['premium_yearly_price'];
$premiumDiscount = $pricing['premium_discount'];
$yearlySavings = ($monthlyPrice * 12) - $yearlyPrice;

// Ensure user is logged in
require_login();

// Generate CSRF token for retry payment AJAX
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$user = get_user($user_id);

$success_message = '';
$error_message = '';

if (isset($_SESSION['subscription_success'])) {
    $success_message = $_SESSION['subscription_success'];
    unset($_SESSION['subscription_success']);
}

if (isset($_SESSION['subscription_error'])) {
    $error_message = $_SESSION['subscription_error'];
    unset($_SESSION['subscription_error']);
}

// Get subscription info
$premium_subscription = get_user_premium_subscription($user_id);

// Get payment history
$payment_history = [];
if ($premium_subscription) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM premium_subscription_payments
            WHERE subscription_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$premium_subscription['subscription_id']]);
        $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail - payment history not critical
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="Manage your Premium Subscription - Argo Community">
    <meta name="author" content="Argo">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Premium Subscription - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="subscription.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="subscription-page-container">
        <div class="page-header">
            <div class="title-container">
                <h1>Premium Subscription</h1>
            </div>

            <div class="button-container">
                <a href="profile.php" class="link-no-underline back-link">
                    <?= svg_icon('arrow-back', 16) ?>
                    Back to Profile
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Subscription Status Section -->
        <div class="subscription-section">
            <h2>Subscription Status</h2>

            <?php if ($premium_subscription): ?>
                <div class="subscription-card">
                    <div class="subscription-header">
                        <div class="subscription-plan">
                            <span class="plan-name">Argo Books Premium</span>
                            <span class="billing-cycle"><?php echo ucfirst($premium_subscription['billing_cycle']); ?> Plan</span>
                        </div>
                        <?php if ($premium_subscription['status'] != 'payment_failed'): ?>
                            <div class="subscription-status <?php echo $premium_subscription['status']; ?>">
                                <span class="status-badge"><?php echo ucfirst($premium_subscription['status']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="subscription-details">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">License Key</span>
                                <span class="detail-value">
                                    <a href="resend_subscription_id.php" class="send-id-link" title="Send license key to your email">
                                        <?= svg_icon('mail-alt', 16) ?>
                                        Send to Email
                                    </a>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Price</span>
                                <span class="detail-value">$<?php echo number_format($premium_subscription['amount'], 2); ?> <?php echo $premium_subscription['currency']; ?>/<?php echo $premium_subscription['billing_cycle'] === 'yearly' ? 'year' : 'month'; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Started</span>
                                <span class="detail-value"><?php echo date('F j, Y', strtotime($premium_subscription['start_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo $premium_subscription['status'] === 'active' ? 'Next Billing Date' : 'Access Until'; ?></span>
                                <span class="detail-value"><?php echo date('F j, Y', strtotime($premium_subscription['end_date'])); ?></span>
                            </div>
                            <?php
                            $creditBalance = floatval($premium_subscription['credit_balance'] ?? 0);
                            $originalCredit = floatval($premium_subscription['original_credit'] ?? 0);
                            // Only show discount if there's still credit remaining
                            if ($premium_subscription['discount_applied'] && $creditBalance > 0): ?>
                            <div class="detail-item">
                                <span class="detail-label">Discount</span>
                                <span class="detail-value discount">$<?php echo number_format($premiumDiscount, 0); ?> Standard Discount Applied</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($creditBalance > 0):
                                $monthsRemaining = floor($creditBalance / $monthlyPrice);
                            ?>
                            <div class="detail-item">
                                <span class="detail-label">Credit Balance</span>
                                <span class="detail-value credit-balance">$<?php echo number_format($creditBalance, 2); ?> CAD</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Months Covered</span>
                                <span class="detail-value"><?php echo $monthsRemaining; ?> month<?php echo $monthsRemaining !== 1 ? 's' : ''; ?> remaining</span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <span class="detail-label">Payment Method</span>
                                <span class="detail-value"><?php echo ucfirst($premium_subscription['payment_method']); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($creditBalance > 0 && $premium_subscription['status'] === 'active'): ?>
                        <div class="subscription-notice credit-notice">
                            <?= svg_icon('dollar', 20) ?>
                            <div>
                                <p><strong>Credit Balance Active</strong></p>
                                <p class="notice-detail">You have $<?php echo number_format($creditBalance, 2); ?> in credit covering your next <?php echo $monthsRemaining; ?> month<?php echo $monthsRemaining !== 1 ? 's' : ''; ?>. You won't be charged until your credit is depleted.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($premium_subscription['status'] === 'active'): ?>
                        <?php
                            $switchTargetCycle = $premium_subscription['billing_cycle'] === 'monthly' ? 'Yearly' : 'Monthly';
                            // Stripe and Square switch through switch-billing-cycle-ajax.php, where PayPal goes through the checkout redirect.
                            $canSwitchCycle = in_array(
                                strtolower($premium_subscription['payment_method'] ?? ''),
                                ['stripe', 'square', 'paypal'],
                                true
                            );
                        ?>
                        <div class="subscription-actions">
                            <?php if ($canSwitchCycle): ?>
                                <a href="switch-billing-cycle.php" class="btn btn-premium">Switch to <?= htmlspecialchars($switchTargetCycle) ?></a>
                            <?php endif; ?>
                            <a href="cancel-subscription.php" class="btn btn-outline-red btn-cancel">Cancel Subscription</a>
                        </div>
                    <?php elseif ($premium_subscription['status'] === 'cancelled'): ?>
                        <div class="subscription-notice cancelled">
                            <?= svg_icon('alert-circle', 20) ?>
                            <div>
                                <p>Your subscription has been cancelled.</p>
                                <p class="notice-detail">Premium features will remain active until <strong><?php echo date('F j, Y', strtotime($premium_subscription['end_date'])); ?></strong>.</p>
                            </div>
                        </div>
                        <div class="subscription-actions">
                            <?php if (strtotime($premium_subscription['end_date']) > time()): ?>
                                <a href="reactivate-subscription.php" class="btn btn-premium btn-reactivate">Reactivate Subscription</a>
                            <?php else: ?>
                                <a href="reactivate-subscription.php" class="btn btn-premium">Resubscribe</a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($premium_subscription['status'] === 'payment_failed'): ?>
                        <div class="subscription-notice payment-failed">
                            <?= svg_icon('alert-triangle', 20) ?>
                            <div>
                                <p><strong>Payment Failed</strong></p>
                                <p class="notice-detail">We were unable to process your renewal payment. Please update your payment method or retry with your existing method.</p>
                            </div>
                        </div>
                        <div class="subscription-actions payment-failed-actions">
                            <a href="reactivate-subscription.php" class="btn btn-premium">Update Payment Method</a>
                            <button type="button" class="btn btn-outline" id="retry-payment-btn">Retry with Existing Method</button>
                        </div>
                    <?php elseif ($premium_subscription['status'] === 'expired'): ?>
                        <div class="subscription-notice expired">
                            <?= svg_icon('x-circle', 20) ?>
                            <div>
                                <p>Your subscription has expired.</p>
                                <p class="notice-detail">Renew to continue using Premium features.</p>
                            </div>
                        </div>
                        <div class="subscription-actions">
                            <a href="../../pricing/premium/" class="btn btn-blue">Renew Subscription</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Features Included -->
                <div class="features-section">
                    <h3>Features Included</h3>
                    <div class="features-grid">
                        <?php foreach (get_plan_features()['premium']['features'] as $feature): ?>
                            <div class="feature-item">
                                <?= svg_icon('check-rounded') ?>
                                <span><?= render_feature_label($feature) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-subscription-card">
                    <div class="no-subscription-icon">
                        <?= svg_icon('subscription', 48, '', 1.5) ?>
                    </div>
                    <h3>No Active Subscription</h3>
                    <p>Get unlimited invoices & payments and AI-powered features like receipt scanning, and predictive analytics.</p>
                    <div class="pricing-preview">
                        <span class="price">$<?php echo number_format($monthlyPrice, 0); ?></span>
                        <span class="period">CAD/month</span>
                        <span class="divider">or</span>
                        <span class="price">$<?php echo number_format($yearlyPrice, 0); ?></span>
                        <span class="period">CAD/year (save $<?php echo number_format($yearlySavings, 0); ?>)</span>
                    </div>
                    <a href="../../pricing/premium/" class="btn btn-premium btn-subscribe">Subscribe to Premium</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History Section -->
        <div class="subscription-section">
            <h2>Payment History</h2>

            <?php if (!empty($payment_history)): ?>
                <div class="payment-history-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $paymentType = $payment['payment_type'] ?? 'initial';
                                    $paymentTypeLabels = [
                                        'initial'      => 'Initial',
                                        'renewal'      => 'Renewal',
                                        'manual'       => 'Manual',
                                        'credit'       => 'Credit Applied',
                                        'retry'        => 'Retry',
                                        'cycle_change' => 'Plan Change',
                                    ];
                                    $paymentTypeLabel = $paymentTypeLabels[$paymentType] ?? ucfirst($paymentType);
                                    ?>
                                    <span class="payment-type <?php echo htmlspecialchars($paymentType); ?>">
                                        <?php echo htmlspecialchars($paymentTypeLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (floatval($payment['amount']) == 0 && ($payment['payment_type'] ?? '') === 'credit'): ?>
                                        <span class="credit-payment">$0.00</span>
                                    <?php else: ?>
                                        $<?php echo number_format($payment['amount'], 2); ?> <?php echo $payment['currency']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <span class="payment-status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-payment-history">
                    <p>No payment history available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Help Section -->
        <div class="subscription-section help-section">
            <h2>Need Help?</h2>
            <p>If you have questions about your subscription or need assistance, please contact our support team.</p>
            <a href="../../contact-us/" class="btn btn-outline">Contact Support</a>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>

    <!-- Retry Payment Modal -->
    <?php if ($premium_subscription && $premium_subscription['status'] === 'payment_failed'): ?>
    <div class="modal-overlay" id="retry-payment-modal">
        <div class="modal-container">
            <button class="modal-close" id="modal-close-btn" aria-label="Close modal">
                <?= svg_icon('x', 24) ?>
            </button>

            <!-- Initial State -->
            <div class="modal-state" id="modal-state-confirm">
                <div class="modal-icon">
                    <?= svg_icon('refresh', 48) ?>
                </div>
                <h2>Retry Payment</h2>
                <p class="modal-description">
                    This will attempt to charge your existing payment method:
                </p>
                <div class="modal-payment-info">
                    <div class="payment-method-badge">
                        <?php
                        $paymentMethodLower = strtolower($premium_subscription['payment_method'] ?? 'unknown');
                        $paymentMethodDisplay = ucfirst($premium_subscription['payment_method'] ?? 'Unknown');
                        ?>
                        <?php if ($paymentMethodLower === 'stripe'): ?>
                            <img src="../../resources/images/Stripe-logo.svg" alt="Stripe" class="payment-logo" loading="lazy">
                        <?php elseif ($paymentMethodLower === 'paypal'): ?>
                            <img src="../../resources/images/PayPal-logo.svg" alt="PayPal" class="payment-logo" loading="lazy">
                        <?php elseif ($paymentMethodLower === 'square'): ?>
                            <img src="../../resources/images/Square-logo.svg" alt="Square" class="payment-logo" loading="lazy">
                        <?php else: ?>
                            <span class="payment-text"><?php echo $paymentMethodDisplay; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="billing-info"><?php echo ucfirst($premium_subscription['billing_cycle'] ?? 'Monthly'); ?> billing</span>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-premium" id="confirm-retry-btn">
                        <span class="btn-text">Retry Payment</span>
                    </button>
                    <button type="button" class="btn btn-outline" id="cancel-retry-btn">Cancel</button>
                </div>
            </div>

            <!-- Loading State -->
            <div class="modal-state hidden" id="modal-state-loading">
                <div class="modal-spinner">
                    <div class="spinner"></div>
                </div>
                <h2>Processing...</h2>
                <p class="modal-description">Please wait while we retry your payment.</p>
            </div>

            <!-- Success State -->
            <div class="modal-state hidden" id="modal-state-success">
                <div class="modal-icon success">
                    <?= svg_icon('circle-check', 48) ?>
                </div>
                <h2>Payment Successful!</h2>
                <p class="modal-description" id="success-message">Your subscription has been reactivated.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-premium" id="success-close-btn">Done</button>
                </div>
            </div>

            <!-- Error State -->
            <div class="modal-state hidden" id="modal-state-error">
                <div class="modal-icon error">
                    <?= svg_icon('x-circle', 48) ?>
                </div>
                <h2>Payment Failed</h2>
                <p class="modal-description" id="error-message">Unable to process your payment.</p>
                <div class="modal-actions">
                    <a href="reactivate-subscription.php" class="btn btn-premium" id="error-update-btn">Update Payment Method</a>
                    <button type="button" class="btn btn-outline" id="error-close-btn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const modal = document.getElementById('retry-payment-modal');
        const retryBtn = document.getElementById('retry-payment-btn');
        const closeBtn = document.getElementById('modal-close-btn');
        const cancelBtn = document.getElementById('cancel-retry-btn');
        const confirmBtn = document.getElementById('confirm-retry-btn');
        const successCloseBtn = document.getElementById('success-close-btn');
        const errorCloseBtn = document.getElementById('error-close-btn');
        const errorUpdateBtn = document.getElementById('error-update-btn');

        const stateConfirm = document.getElementById('modal-state-confirm');
        const stateLoading = document.getElementById('modal-state-loading');
        const stateSuccess = document.getElementById('modal-state-success');
        const stateError = document.getElementById('modal-state-error');
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');

        function showState(state) {
            [stateConfirm, stateLoading, stateSuccess, stateError].forEach(s => s.classList.add('hidden'));
            state.classList.remove('hidden');
            // Show/hide close button based on state
            closeBtn.style.display = (state === stateLoading) ? 'none' : 'block';
        }

        function openModal() {
            showState(stateConfirm);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            // Reset to confirm state after animation
            setTimeout(() => showState(stateConfirm), 300);
        }

        function handleRetryPayment() {
            showState(stateLoading);

            fetch('retry-payment-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successMessage.textContent = data.message || 'Your subscription has been reactivated!';
                    showState(stateSuccess);
                } else {
                    errorMessage.textContent = data.error || 'Unable to process your payment.';
                    if (data.redirect) {
                        errorUpdateBtn.href = data.redirect;
                    }
                    showState(stateError);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMessage.textContent = 'An unexpected error occurred. Please try again.';
                showState(stateError);
            });
        }

        // Event listeners
        if (retryBtn) retryBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (confirmBtn) confirmBtn.addEventListener('click', handleRetryPayment);
        if (errorCloseBtn) errorCloseBtn.addEventListener('click', closeModal);
        if (successCloseBtn) {
            successCloseBtn.addEventListener('click', function() {
                window.location.reload();
            });
        }

        // Close on overlay click (only if mousedown also started on backdrop and not in loading state)
        let modalMouseDownTarget = null;
        modal.addEventListener('mousedown', function(e) {
            modalMouseDownTarget = e.target;
        });
        modal.addEventListener('click', function(e) {
            if (e.target === modal && modalMouseDownTarget === modal && stateLoading.classList.contains('hidden')) {
                closeModal();
            }
        });

        // Close on ESC key
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
