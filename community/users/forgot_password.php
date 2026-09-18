<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../rate_limit_helper.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Basic validation
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (rate_limit_hit('community_password_reset', get_client_ip())) {
        $error = 'Too many reset requests. Please wait ' . rate_limit_wait_phrase('community_password_reset') . ' and try again.';
    } else {
        // Attempt to send password reset link
        $result = request_password_reset($email);

        // Always show the same message regardless of whether the email exists,
        // to prevent email enumeration attacks
        $success = 'If an account with that email exists, a password reset link has been sent. Please check your inbox and spam folder.';

        // Silently log failures for debugging (hash email to avoid PII in logs)
        if (!$result) {
            $email_hash = substr(hash('sha256', strtolower($email)), 0, 12);
            error_log("Password reset failure (email_hash={$email_hash})");
        }
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
    <title>Forgot Password - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="auth-container">
        <div class="auth-card">
            <h1>Reset Password</h1>
            <p class="auth-subtitle">Enter your email to receive a password reset link</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php else: ?>
                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-blue btn-block">Send Reset Link</button>
                    </div>

                    <div class="auth-links">
                        <a href="login.php" class="link-no-underline">Back to Login</a>
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