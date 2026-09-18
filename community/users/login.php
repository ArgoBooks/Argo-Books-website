<?php
session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../rate_limit_helper.php';
require_once __DIR__ . '/user_functions.php';

// A link can specify where to return after login, e.g.
// login.php?redirect=/pricing/premium/. Stored in the session so it survives
// the login POST and any register/verify detour. Validated as a local path
// (same rule the post-login redirect uses) to prevent open redirects.
if (isset($_GET['redirect']) && is_string($_GET['redirect'])
    && preg_match('#^/[^/\\\\]#', $_GET['redirect'])
    && !preg_match('#[:\s]#', $_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Redirect if already logged in
if (is_user_logged_in()) {
    $dest = 'profile.php';
    if (!empty($_SESSION['redirect_after_login'])
        && preg_match('#^/[^/\\\\]#', $_SESSION['redirect_after_login'])
        && !preg_match('#[:\s]#', $_SESSION['redirect_after_login'])) {
        $dest = $_SESSION['redirect_after_login'];
    }
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $dest);
    exit;
}

// Check for remember me cookie and auto-login user if valid
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    check_remember_me();
}

$error = '';
$verification_notice = '';
$clientIp = get_client_ip();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atomic IP-based rate limit (5 per 15 minutes). The previous session-keyed
    // counter was trivially bypassed by dropping the session cookie between
    // attempts. Successful logins clear the bucket below.
    if (rate_limit_hit('community_login', $clientIp)) {
        $error = 'Too many login attempts. Please wait ' . rate_limit_wait_phrase('community_login') . ' before trying again.';
    }

    // Get form data
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember_me = isset($_POST['remember_me']) ? true : false;

    // Basic validation
    if (empty($error) && (empty($login) || empty($password))) {
        $error = 'Please enter both username/email and password';
    }

    if (empty($error)) {
        // Attempt to log in
        $user = login_user($login, $password);

        if ($user) {
            // Check if email is not verified
            if (isset($user['email_not_verified']) && $user['email_not_verified']) {
                // Set temp_user_id for verification page
                $_SESSION['temp_user_id'] = $user['user_id'];
                // Redirect to verification page
                header('Location: verify_code.php');
                exit;
            }

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Clear rate limit counter on successful login
            rate_limit_clear('community_login', $clientIp);

            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];

            // Handle "Stay logged in" option
            if ($remember_me) {
                // Determine if the current request is over HTTPS
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

                // Set session cookie to last 30 days instead of browser close
                setcookie(
                    session_name(),
                    session_id(),
                    [
                        'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                        'path' => '/',
                        'secure' => $isSecure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );

                // Store remember me token in database and set cookie
                $token = generate_remember_token($user['id']);
                if ($token) {
                    setcookie(
                        'remember_me',
                        $token,
                        [
                            'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                            'path' => '/',
                            'secure' => $isSecure,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );
                }
            }

            // Redirect after login (validate redirect is a local path to prevent open redirect)
            if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                // Only allow relative paths starting with / and no protocol-relative URLs
                if (preg_match('#^/[^/\\\\]#', $redirect) && !preg_match('#[:\s]#', $redirect)) {
                    header("Location: $redirect");
                } else {
                    header('Location: profile.php');
                }
            } else {
                header('Location: profile.php');
            }
            exit;
        } else {
            $error = 'Invalid username/email or password';
        }
    } // end rate limit check
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <link rel="shortcut icon" type="image/x-icon" href="../../resources/images/argo-logo/argo-icon.ico">
    <title>Log In - Argo Community</title>

    <script src="../../resources/scripts/main.js"></script>

    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="../../resources/styles/password-toggle.css">
    <script src="../../resources/scripts/password-toggle.js" defer></script>
    <link rel="stylesheet" href="../../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../../resources/styles/checkbox.css">
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
            <h1>Log In</h1>
            <p class="auth-subtitle">Welcome back! Log in to your account</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($verification_notice): ?>
                <div class="verification-notice">
                    <?php echo $verification_notice; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input type="text" id="login" name="login" value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="checkbox">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Stay logged in for 30 days</label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-blue btn-block">Log In</button>
                </div>

                <div class="auth-links">
                    <a href="forgot_password.php" class="link-no-underline">Forgot password?</a>
                    <p>Don't have an account? <a href="register.php" class="link-no-underline">Register</a></p>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        <?php include __DIR__ . '/../../resources/footer/footer.php'; ?>
    </footer>

</body>

</html>