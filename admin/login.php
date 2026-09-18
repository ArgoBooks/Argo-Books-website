<?php
// Configure and start the admin session (private store, 8h lifetime, hardened
// cookie). Shared by every admin entry point so login behaves identically.
require_once __DIR__ . '/admin_session.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../rate_limit_helper.php';
require_once __DIR__ . '/settings/2fa.php';
require_once __DIR__ . '/trusted_devices.php';

/**
 * Where to send an admin after a successful login: back to the admin page they
 * were trying to reach (captured in admin_session.php), else the dashboard.
 * Validated as a local admin path so it can't become an open redirect.
 */
function admin_login_destination(): string
{
    $r = $_SESSION['admin_return_to'] ?? '';
    unset($_SESSION['admin_return_to']);
    if (is_string($r) && $r !== ''
        && preg_match('#^/[^/\\\\]#', $r)      // local absolute path, not //
        && !preg_match('#[:\s]#', $r)          // no scheme or whitespace
        && strpos($r, '/admin/') !== false     // must be an admin page
        && strpos($r, '/login.php') === false) {
        return $r;
    }
    return 'index.php';
}

// Check if user is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$show_2fa_form = false;
$clientIp = get_client_ip();

// Process 2FA verification
if (isset($_SESSION['awaiting_2fa']) && $_SESSION['awaiting_2fa'] === true) {
    $show_2fa_form = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
        // Rate limit 2FA attempts (max 5 per 15 minutes, per IP). Without this,
        // an attacker holding valid credentials could brute-force the 6-digit
        // code at full speed. Atomic check+record so concurrent requests can't
        // slip past the cap.
        if (rate_limit_hit('admin_2fa', $clientIp)) {
            $error = 'Too many verification attempts. Please wait ' . rate_limit_wait_phrase('admin_2fa') . ' before trying again.';
        } else {
            $verification_code = $_POST['verification_code'] ?? '';

            if (empty($verification_code)) {
                $error = 'Please enter the verification code.';
            } else {
                $username = $_SESSION['temp_username'];

                if (verify_2fa_login_code($username, $verification_code)) {
                    // Code is valid, complete login
                    session_regenerate_id(true);
                    $_SESSION['awaiting_2fa'] = false;
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    unset($_SESSION['temp_username']);

                    // Successful login resets the failed-attempt counters.
                    rate_limit_clear('admin_2fa', $clientIp);
                    rate_limit_clear('admin_login', $clientIp);

                    // If the user ticked "Trust this device for 30 days",
                    // issue a token so subsequent logins from this browser
                    // can skip the TOTP step (password is still required).
                    if (!empty($_POST['trust_device'])) {
                        $u = get_user_by_username($username);
                        if ($u) {
                            issue_trusted_device_token(
                                (int)$u['id'],
                                $_SERVER['HTTP_USER_AGENT'] ?? '',
                                $clientIp
                            );
                        }
                    }

                    $stmt = $pdo->prepare('UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE username = ?');
                    $stmt->execute([$username]);

                    header('Location: ' . admin_login_destination());
                    exit;
                } else {
                    $error = 'Invalid verification code. Please try again.';
                }
            }
        }
    }
}
// Process login form submission
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Atomic check+record so concurrent requests can't slip past the cap
    // (5 per 15 minutes, per IP). Successful logins clear the bucket below.
    if (rate_limit_hit('admin_login', $clientIp)) {
        $error = 'Too many login attempts. Please wait ' . rate_limit_wait_phrase('admin_login') . ' before trying again.';
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($error) && (empty($username) || empty($password))) {
        $error = 'Please enter both username and password.';
    }

    if (empty($error)) {
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $actual_username = $user['username']; // Get actual username with correct case

            if (is_2fa_enabled($actual_username)) {
                // If the browser has a valid "trust this device" cookie for
                // this admin, skip the TOTP step. The password was just
                // verified above; the cookie alone never grants access.
                $trusted_for = verify_trusted_device_cookie($clientIp);
                if ($trusted_for !== null && strcasecmp($trusted_for, $actual_username) === 0) {
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $actual_username;

                    // Mirror the normal 2FA-success path: a successful login
                    // (whether by TOTP or trust cookie) resets both buckets so
                    // prior failed 2FA attempts don't bite the user when their
                    // trust cookie eventually expires.
                    rate_limit_clear('admin_2fa', $clientIp);
                    rate_limit_clear('admin_login', $clientIp);

                    $stmt = $pdo->prepare('UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE username = ?');
                    $stmt->execute([$actual_username]);

                    header('Location: ' . admin_login_destination());
                    exit;
                }

                // 2FA is enabled, show the verification form. Don't clear the
                // password-attempt counter yet. We only count this as success
                // once 2FA also passes.
                $_SESSION['awaiting_2fa'] = true;
                $_SESSION['temp_username'] = $actual_username;
                $show_2fa_form = true;
            } else {
                // No 2FA, complete login
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $actual_username;

                rate_limit_clear('admin_login', $clientIp);

                $stmt = $pdo->prepare('UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE username = ?');
                $stmt->execute([$actual_username]);

                header('Location: ' . admin_login_destination());
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script>
        // Apply saved theme immediately to prevent flash
        (function() {
            var theme = localStorage.getItem('admin-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="../resources/images/argo-logo/argo-icon.ico">
    <title>Admin Login - Argo Books</title>

    <link rel="stylesheet" href="common-style.css">
    <link rel="stylesheet" href="../resources/styles/button.css">
    <link rel="stylesheet" href="../resources/styles/custom-colors.css">
    <link rel="stylesheet" href="../resources/styles/password-toggle.css">
    <script src="../resources/scripts/password-toggle.js" defer></script>
</head>

<body>
    <!-- Minimal header: Argo Books logo + theme toggle only (no nav) -->
    <header class="admin-header">
        <div class="header-container">
            <div class="header-left">
                <a href="../" class="logo-section">
                    <img src="../resources/images/argo-logo/argo-icon.ico" alt="Argo Logo" class="header-logo">
                    <span class="header-title">Admin Dashboard</span>
                </a>
            </div>
            <div class="header-right">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light theme">
                    <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                </button>
            </div>
        </div>
    </header>

    <div class="login-container">
        <?php if ($show_2fa_form): ?>
            <div class="login-header">
                <h1>Two-Factor Authentication</h1>
                <p>Please enter the verification code from your authenticator app</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" id="verification-form">
                <div class="form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="number" id="verification_code" name="verification_code" class="verification-code" required autofocus placeholder="000000" min="0" max="999999">
                </div>

                <div class="form-group trust-device-group">
                    <label class="trust-device-label">
                        <input type="checkbox" id="trust_device" name="trust_device" value="1">
                        Trust this device for 30 days
                    </label>
                </div>

                <input type="hidden" name="verify_code" value="1">

                <div class="center">
                    <button type="button" onclick="submitVerificationForm()" id="submit-button" class="btn btn-blue">Verify</button>
                </div>

                <div class="back-to-login">
                    <a href="logout.php">Cancel and return to login</a>
                </div>
            </form>
        <?php else: ?>
            <div class="login-header">
                <h1>Admin Login</h1>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="center">
                    <button type="submit" name="login" class="btn btn-blue">Login</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($show_2fa_form): ?>
        <script>
            // Simple function to submit the verification form
            function submitVerificationForm() {
                document.getElementById('verification-form').submit();
            }

            document.addEventListener('DOMContentLoaded', function() {
                var codeInput = document.getElementById('verification_code');

                if (codeInput) {
                    codeInput.addEventListener('input', function() {
                        // Force numeric only
                        this.value = this.value.replace(/[^0-9]/g, '');

                        // Auto-submit on 6 digits
                        if (this.value.length === 6) {
                            // Add a small delay so user sees the 6th digit
                            setTimeout(function() {
                                submitVerificationForm();
                            }, 300);
                        }
                    });

                    // Prevent arrow keys
                    codeInput.addEventListener('keydown', function(e) {
                        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                            e.preventDefault();
                        }
                        // Also submit on Enter key when 6 digits entered
                        if (e.key === 'Enter' && this.value.length === 6) {
                            e.preventDefault();
                            submitVerificationForm();
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>

    <script>
        // Theme toggle for the login header. No Chart.js here, so this is the
        // simple version (the dashboard's toggleTheme also re-renders charts).
        function toggleTheme() {
            var current = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('admin-theme', next);
        }
    </script>
</body>

</html>