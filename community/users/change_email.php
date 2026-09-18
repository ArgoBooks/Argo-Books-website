<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../community_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../resources/icons.php';

require_login();

$user_id = $_SESSION['user_id'];
$user = get_user($user_id);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

if (isset($_SESSION['change_email_success'])) {
    $success_message = $_SESSION['change_email_success'];
    unset($_SESSION['change_email_success']);
}

if (isset($_SESSION['change_email_error'])) {
    $error_message = $_SESSION['change_email_error'];
    unset($_SESSION['change_email_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['change_email_error'] = 'Invalid request. Please try again.';
        header('Location: change_email.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'change_email') {
        $new_email = trim($_POST['new_email'] ?? '');
        $password = $_POST['email_password'] ?? '';

        if (empty($new_email) || empty($password)) {
            $_SESSION['change_email_error'] = 'Email and password are required';
            header('Location: change_email.php');
            exit;
        }

        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['change_email_error'] = 'Please enter a valid email address';
            header('Location: change_email.php');
            exit;
        }

        if ($new_email === $user['email']) {
            $_SESSION['change_email_error'] = 'This is already your current email address';
            header('Location: change_email.php');
            exit;
        }

        // Each request resets the code's guess count, so requests are capped too
        if (rate_limit_hit('community_email_change', (string) $user_id)) {
            $_SESSION['change_email_error'] = 'Too many email change requests. Please try again in ' . rate_limit_wait_phrase('community_email_change') . '.';
            header('Location: change_email.php');
            exit;
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM community_users WHERE id = ?');
        $stmt->execute([$user_id]);
        $password_data = $stmt->fetch();

        if (!$password_data || !password_verify($password, $password_data['password_hash'])) {
            $_SESSION['change_email_error'] = 'Current password is incorrect';
            header('Location: change_email.php');
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM community_users WHERE email = ? AND id != ?');
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['change_email_error'] = 'This email address is already registered';
            header('Location: change_email.php');
            exit;
        }

        $verification_code = start_email_change($user_id, $new_email);
        $email_sent = send_verification_email($new_email, $verification_code, $user['username']);

        if ($email_sent) {
            $_SESSION['pending_email'] = $new_email;
            $_SESSION['email_change_pending'] = true;
            $_SESSION['change_email_success'] = 'Verification email sent to ' . htmlspecialchars($new_email) . '. Please enter the verification code below.';
        } else {
            $_SESSION['change_email_error'] = 'Failed to send verification email. Please try again.';
        }

        header('Location: change_email.php');
        exit;
    } elseif ($action === 'verify_email') {
        if (!isset($_SESSION['email_change_pending']) || !isset($_SESSION['pending_email'])) {
            $_SESSION['change_email_error'] = 'No email change pending';
            header('Location: change_email.php');
            exit;
        }

        $verification_code = trim($_POST['email_verification_code'] ?? '');

        if (empty($verification_code)) {
            $_SESSION['change_email_error'] = 'Verification code is required';
            header('Location: change_email.php');
            exit;
        }

        // $new_email is the address stored with the code, not the session's copy
        $result = verify_email_change_code($user_id, $verification_code, $new_email);

        if ($result !== 'ok') {
            if ($result === 'invalid') {
                $_SESSION['change_email_error'] = 'Invalid verification code';
            } else {
                unset($_SESSION['pending_email'], $_SESSION['email_change_pending']);
                $_SESSION['change_email_error'] = $result === 'expired'
                    ? 'That verification code has expired. Please request a new one.'
                    : 'That verification code can no longer be used. Please request a new one.';
            }
            header('Location: change_email.php');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE community_users SET email = ?, email_verified = 1 WHERE id = ?');

        if ($stmt->execute([$new_email, $user_id])) {
            $stmt = $pdo->prepare('UPDATE community_posts SET user_email = ? WHERE user_id = ?');
            $stmt->execute([$new_email, $user_id]);

            $stmt = $pdo->prepare('UPDATE community_comments SET user_email = ? WHERE user_id = ?');
            $stmt->execute([$new_email, $user_id]);

            $_SESSION['email'] = $new_email;
            unset($_SESSION['pending_email']);
            unset($_SESSION['email_change_pending']);

            $_SESSION['change_email_success'] = 'Email address updated successfully!';
        } else {
            $_SESSION['change_email_error'] = 'Failed to update email address.';
        }

        header('Location: change_email.php');
        exit;
    }
}

// Refresh user data after any updates
$user = get_user($user_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>Change Email - Argo Community</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="edit-profile.css">
    <link rel="stylesheet" href="account-subpage.css">
    <link rel="stylesheet" href="../../resources/styles/button.css">
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/link.css">
    <link rel="stylesheet" href="../../resources/header/style.css">
    <link rel="stylesheet" href="../../resources/header/dark.css">
    <link rel="stylesheet" href="../../resources/footer/style.css">
    <link rel="stylesheet" href="../../resources/styles/password-toggle.css">
    <script src="../../resources/scripts/password-toggle.js" defer></script>
</head>

<body>
    <header>
        <?php include __DIR__ . '/../../resources/header/header.php'; ?>
    </header>

    <div class="account-subpage">
        <a href="edit_profile.php" class="link-no-underline back-link">
            <?= svg_icon('arrow-back', 16) ?>
            Back to Edit Account
        </a>

        <div class="subpage-card">
            <div class="subpage-header">
                <div class="subpage-icon">
                    <?= svg_icon('mail', 28, '', null, 'stroke-linecap="round" stroke-linejoin="round"') ?>
                </div>
                <div>
                    <h1>Change Email</h1>
                    <p class="subpage-subtitle">Update the email address associated with your account.</p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="banner banner-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="banner banner-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <p class="current-email"><strong>Current email:</strong> <?= htmlspecialchars($user['email']) ?></p>

            <?php if (isset($_SESSION['email_change_pending']) && $_SESSION['email_change_pending']): ?>
                <div class="verification-pending">
                    <h4>Verification pending</h4>
                    <p>We've sent a verification code to <strong><?= htmlspecialchars($_SESSION['pending_email']) ?></strong>. Enter it below to complete the change.</p>
                    <form method="post">
                        <input type="hidden" name="action" value="verify_email">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-group">
                            <label for="email_verification_code">Verification code</label>
                            <input type="text" id="email_verification_code" name="email_verification_code" class="verification-code-input" placeholder="6-digit code" maxlength="6" required autofocus>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-blue">Verify Email</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="change_email">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                        <label for="new_email">New email address</label>
                        <input type="email" id="new_email" name="new_email" required autofocus>
                        <p class="info-text">You'll need to verify your new email address before the change takes effect.</p>
                    </div>

                    <div class="form-group">
                        <label for="email_password">Current password</label>
                        <input type="password" id="email_password" name="email_password" required>
                        <p class="info-text">Enter your current password to confirm this change.</p>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-blue">Send verification code</button>
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
