<?php require_once __DIR__ . '/../../../resources/icons.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <meta name="description" content="Premium Subscription Confirmed - Argo Books">
    <meta name="author" content="Argo">
    <link rel="shortcut icon" type="image/x-icon" href="../../../resources/images/argo-logo/argo-icon.ico">
    <title>Premium Subscription Confirmed - Argo Books</title>

    <script src="../../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="../../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../../resources/styles/button.css">
    <link rel="stylesheet" href="../../../resources/styles/link.css">
    <link rel="stylesheet" href="../../../resources/header/style.css">
    <link rel="stylesheet" href="../../../resources/header/dark.css">
    <link rel="stylesheet" href="../../../resources/footer/style.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../../resources/header/header.php'; ?>
    </header>

    <?php
    $subscriptionId = isset($_GET['subscription_id']) ? htmlspecialchars($_GET['subscription_id'], ENT_QUOTES, 'UTF-8') : 'N/A';
    $email = isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : '';
    ?>

    <div class="thank-you-container">
        <div class="success-icon">
            <?= svg_icon('check-pricing') ?>
        </div>

        <h1>Premium is active</h1>
        <p class="subtitle">Your Premium subscription is active. Enter the license key below in Argo Books to turn on Premium features.</p>

        <div class="subscription-details">
            <h2>Subscription Details</h2>

            <div class="subscription-id-box">
                <label>Your License Key</label>
                <div class="subscription-id" id="subscription-id"><?php echo $subscriptionId; ?></div>
                <button class="copy-btn" onclick="copySubscriptionId()">Copy to Clipboard</button>
            </div>

            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?php echo $email; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value" style="color: var(--emerald-600);">Active</span>
            </div>
        </div>

        <div class="activation-steps">
            <h3>How to Activate Premium Features</h3>
            <ol>
                <li>Open <strong>Argo Books</strong> on your computer</li>
                <li>Click the blue <strong>Upgrade</strong> button in the top right corner</li>
                <li>Click the <strong>Enter a Key</strong> button in the bottom right</li>
                <li>Enter your <strong>License key</strong> as shown above</li>
                <li>Click <strong>Verify Key</strong> to turn on Premium</li>
            </ol>
        </div>

        <div class="cta-buttons">
            <a href="../../../downloads" class="btn btn-premium">Download Argo Books</a>
            <a href="../../../documentation/" class="btn btn-outline-premium">View Documentation</a>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../../../resources/footer/footer.php'; ?>
    </footer>

    <script>
        function copySubscriptionId() {
            const subscriptionId = document.getElementById('subscription-id').textContent;
            navigator.clipboard.writeText(subscriptionId).then(() => {
                const btn = document.querySelector('.copy-btn');
                btn.textContent = 'Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = 'Copy to Clipboard';
                    btn.classList.remove('copied');
                }, 2000);
            });
        }
    </script>
</body>

</html>
