<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../paypal-helper.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/../../track_referral_event.php';

require_once __DIR__ . '/../../resources/icons.php';

// Ensure user is logged in
require_login();

$user_id = $_SESSION['user_id'];

// Get subscription info
$premium_subscription = get_user_premium_subscription($user_id);

// Redirect if no active subscription
if (!$premium_subscription || $premium_subscription['status'] !== 'active') {
    header('Location: subscription.php');
    exit;
}

$error_message = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
        // Skip cancellation processing
    } else {
    try {
        // Get subscription details before cancelling
        $stmt = $pdo->prepare("
            SELECT subscription_id, email, end_date, credit_balance, original_credit, payment_method, paypal_subscription_id
            FROM premium_subscriptions
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$user_id]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        // If this is a PayPal subscription, cancel it on PayPal's side first
        $paypalCancelFailed = false;
        if ($subscription && $subscription['payment_method'] === 'paypal' && !empty($subscription['paypal_subscription_id'])) {
            try {
                $cancelled = cancelPayPalSubscription($subscription['paypal_subscription_id'], 'Cancelled by user from account settings');
                if (!$cancelled) {
                    $paypalCancelFailed = true;
                    error_log("Failed to cancel PayPal subscription: " . $subscription['paypal_subscription_id']);
                }
            } catch (Exception $e) {
                $paypalCancelFailed = true;
                error_log("Error cancelling PayPal subscription: " . $e->getMessage());
            }
        }

        // Cancel the subscription and invalidate any remaining credit
        // Credit is forfeited upon cancellation
        $stmt = $pdo->prepare("
            UPDATE premium_subscriptions
            SET status = 'cancelled', auto_renew = 0, credit_balance = 0, cancelled_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$user_id]);

        // Send cancellation email
        if ($subscription) {
            try {
                send_premium_subscription_cancelled_email(
                    $subscription['email'],
                    $subscription['subscription_id'],
                    $subscription['end_date']
                );
            } catch (Exception $e) {
                error_log("Failed to send cancellation email: " . $e->getMessage());
            }

            // Notify the team. Best-effort: never let it affect the cancel flow.
            try {
                send_premium_subscription_cancelled_admin_notification(
                    $subscription['email'],
                    $subscription['subscription_id'],
                    $subscription['end_date'],
                    'account settings'
                );
            } catch (Exception $e) {
                error_log("Failed to send cancellation admin notification: " . $e->getMessage());
            }

            // Runs in a real browser session, so keep the bot filter (allow_bot
            // false) and fall back to the live cookie/session user when signup
            // attribution is missing.
            track_subscription_event('premium_churned', $subscription['subscription_id'],
                ['reason' => 'user_cancelled', 'source' => 'portal'],
                [
                    'visitor_id' => $_COOKIE[ARGO_VISITOR_COOKIE] ?? null,
                    'user_id'    => $user_id,
                ],
                ['allow_bot' => false]);
        }

        $successMsg = 'Your Premium subscription has been cancelled. You will retain access until the end of your billing period.';

        // Add PayPal warning if cancellation on their side failed
        if ($paypalCancelFailed) {
            $successMsg .= ' Note: We could not automatically cancel your PayPal subscription. Please also cancel it directly from your PayPal account to prevent future charges.';
        }

        $_SESSION['subscription_success'] = $successMsg;
        header('Location: subscription.php');
        exit;
    } catch (PDOException $e) {
        $error_message = 'Failed to cancel subscription. Please contact support.';
    }
    } // end CSRF else
}

$end_date = date('F j, Y', strtotime($premium_subscription['end_date']));
$premium_features = get_plan_features()['premium']['features'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="Cancel Premium Subscription - Argo Community">
    <meta name="author" content="Argo">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Cancel Subscription - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">

    <style>
.cancel-page {
    max-width: 580px;
    margin: 0 auto;
    padding: 60px 20px;
    min-height: 80vh;
}

.cancel-card {
    background: var(--white);
    border-radius: 20px;
    border: 1px solid var(--gray-border);
    box-shadow: 0 20px 50px -20px var(--shadow-default);
    padding: 48px 40px;
}

.cancel-hero {
    text-align: center;
    margin-bottom: 32px;
}

.cancel-hero h1 {
    font-size: 30px;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 12px;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.cancel-hero p {
    color: var(--gray-700);
    font-size: 15px;
    line-height: 1.6;
    margin: 0 auto;
    max-width: 420px;
}

.cancel-alert {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 24px;
    background: var(--red-100);
    color: var(--red-800);
    border: 1px solid var(--red-300);
    font-size: 14px;
}

.access-end-card {
    background: var(--gray-50);
    border: 1px solid var(--gray-border);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 28px;
}

.access-end-label {
    font-size: 14px;
    color: var(--gray-700);
    font-weight: 600;
    margin-bottom: 4px;
}

.access-end-date {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-900);
    letter-spacing: -0.02em;
    margin-bottom: 8px;
}

.access-end-detail {
    font-size: 13.5px;
    color: var(--gray-700);
    line-height: 1.5;
    margin: 0;
}

.cancel-section-label {
    font-size: 16px;
    font-weight: 600;
    color: var(--gray-900);
    margin: 0 0 14px 0;
}

.cancel-feature-grid {
    list-style: none;
    padding: 0;
    margin: 0 0 32px 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.cancel-feature-grid li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: var(--gray-bg-light);
    border-radius: 8px;
    font-size: 14px;
    color: var(--gray-800);
    line-height: 1.3;
}

.cancel-feature-grid svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    stroke: var(--gray-500);
}

.cancel-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 8px;
}

.cancel-actions > form,
.cancel-actions > .btn {
    width: 100%;
}

.cancel-actions form .btn {
    width: 100%;
}

.cancel-actions .btn {
    padding: 13px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 10px;
    text-align: center;
}

.cancel-footnote {
    text-align: center;
    font-size: 13px;
    color: var(--gray-600);
    margin: 24px 0 0;
    line-height: 1.5;
}

@media (max-width: 576px) {
    .cancel-page {
        padding: 24px 16px;
    }
    .cancel-card {
        padding: 32px 24px;
        border-radius: 16px;
    }
    .cancel-hero h1 {
        font-size: 24px;
    }
    .access-end-date {
        font-size: 22px;
    }
    .cancel-feature-grid {
        grid-template-columns: 1fr;
    }
    .cancel-actions {
        grid-template-columns: 1fr;
    }
    .cancel-actions > form {
        grid-row: 2;
    }
}
    </style>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <main class="cancel-page">
        <div class="cancel-card">
            <div class="cancel-hero">
                <h1>Are you sure you want to cancel?</h1>
                <p>Your subscription will stay active until the end of your current billing period. You can resubscribe at any time.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="cancel-alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="access-end-card">
                <div class="access-end-label">Premium access ends</div>
                <div class="access-end-date"><?php echo $end_date; ?></div>
                <p class="access-end-detail">After this date your subscription will not auto-renew and Premium features will be disabled.</p>
            </div>

            <h2 class="cancel-section-label">Features you'll lose access to</h2>
            <ul class="cancel-feature-grid">
                <?php foreach ($premium_features as $feature): ?>
                    <li>
                        <?= svg_icon('check-rounded') ?>
                        <span><?= render_feature_label($feature) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="cancel-actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="confirm_cancel" value="1">
                    <button type="submit" class="btn btn-outline-red">Yes, Cancel</button>
                </form>
                <a href="subscription.php" class="btn btn-premium">Keep My Subscription</a>
            </div>

            <p class="cancel-footnote">Need help instead? <a class="link" href="../../contact-us/">Contact support</a>. We'd love to hear what's not working.</p>
        </div>
    </main>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>
