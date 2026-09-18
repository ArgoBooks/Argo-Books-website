<?php

/**
 * This script handles retrieving and resending Premium subscription IDs to users
 */
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../email_sender.php';

// Initialize response variables
$success_message = '';
$error_message = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'] ?? '';

// Get subscription info
$premium_subscription = get_user_premium_subscription($user_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $premium_subscription
    && rate_limit_hit('community_resend_key', (string) $user_id)) {
    $error_message = 'Your license key was sent recently. Please check your inbox and spam folder, or try again in '
        . rate_limit_wait_phrase('community_resend_key') . '.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $premium_subscription) {
    $subscription_id = $premium_subscription['subscription_id'];
    $billing_cycle = $premium_subscription['billing_cycle'];
    $end_date = $premium_subscription['end_date'];
    $subscription_email = $premium_subscription['email'] ?? $email;

    // Send the subscription ID via email
    $send_to = !empty($subscription_email) ? $subscription_email : $email;
    $email_sent = resend_subscription_id_email($send_to, $subscription_id, $billing_cycle, $end_date);

    if ($email_sent) {
        $success_message = 'Your license key has been sent to your email address.';
    } else {
        $error_message = 'Failed to send email. Please try again later or <a href="../../contact-us/">contact support</a>.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Resend License Key - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <style>
        .subscription-info {
            background: var(--premium-50);
            border: 1px solid var(--premium-200);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .subscription-info h3 {
            color: var(--premium-600);
            margin-top: 0;
        }
    </style>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h1>Resend License Key</h1>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <div class="centered">
                    <a href="subscription.php" class="btn btn-premium">Back to Subscription</a>
                </div>
            <?php elseif ($error_message): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
                <div class="centered">
                    <a href="subscription.php" class="btn btn-premium">Back to Subscription</a>
                </div>
            <?php elseif (!$premium_subscription): ?>
                <div class="error-message">
                    You don't have an active Premium subscription.
                </div>
                <div class="centered">
                    <a href="../../pricing/premium/" class="btn btn-premium">Subscribe to Premium Features</a>
                </div>
            <?php else: ?>
                <p class="auth-subtitle">We'll send your Premium license key to your registered email address: <strong><?php echo htmlspecialchars($email); ?></strong></p>

                <div class="subscription-info">
                    <h3>License Key Information</h3>
                    <p>Your license key is a unique identifier for your Premium subscription. Keep it safe for your records.</p>
                    <p>You may need this key when contacting support about billing or subscription issues.</p>
                </div>

                <form method="post" class="auth-form">
                    <div class="form-actions centered">
                        <button type="submit" class="btn btn-premium">Send License Key</button>
                        <a href="subscription.php" class="btn btn-black">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>
</body>

</html>
