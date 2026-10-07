<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/2fa.php';
require_once __DIR__ . '/../trusted_devices.php';
require_once __DIR__ . '/../../email_sender.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// CSRF token shared by every form on this page.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Settings';
$page_description = 'Security and notification preferences for the admin dashboard.';

$username = $_SESSION['admin_username'];
$error = '';
$success = '';
$notif_error = '';
$notif_success = '';
$is_enabled = is_2fa_enabled($username);
$new_secret = '';
$qr_code_data = '';

// Which tab renders active. Query param wins for deep links; a form submission
// re-activates the tab it came from so messages land next to their form.
$valid_tabs = ['security', 'notifications'];
$active_tab = 'security';
if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs, true)) {
    $active_tab = $_GET['tab'];
}

// Seed the notification prefs row (idempotent) so the UPDATE below always hits.
get_admin_notification_prefs();

// ---- 2FA setup flow (Security tab) ----
if (!$is_enabled && isset($_GET['setup'])) {
    if (!isset($_SESSION['temp_2fa_secret'])) {
        $new_secret = generate_2fa_secret();
        $_SESSION['temp_2fa_secret'] = $new_secret;
    } else {
        $new_secret = $_SESSION['temp_2fa_secret'];
    }
    $qr_code_data = get_qr_code_url($username, $new_secret, 'Argo Books Admin');
}

// ---- POST handling ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = (string)($_POST['csrf_token'] ?? '');
    $csrf_ok = hash_equals($_SESSION['csrf_token'] ?? '', $posted_csrf);

    if (isset($_POST['save_notifications'])) {
        // ---- Notifications tab ----
        $active_tab = 'notifications';
        $email = trim((string)($_POST['notification_email'] ?? ''));
        $toggles = [
            'notify_new_posts',
            'notify_new_comments',
            'notify_new_reports',
            'notify_new_customer',
            'notify_subscription_cancelled',
        ];
        $values = [];
        foreach ($toggles as $t) {
            $values[$t] = isset($_POST[$t]) ? 1 : 0;
        }

        if (!$csrf_ok) {
            $notif_error = 'Security check failed. Please refresh the page and try again.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $notif_error = 'Please enter a valid notification email address.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE admin_notification_prefs
                        SET notification_email = ?,
                            notify_new_posts = ?,
                            notify_new_comments = ?,
                            notify_new_reports = ?,
                            notify_new_customer = ?,
                            notify_subscription_cancelled = ?
                      WHERE id = 1'
                );
                $stmt->execute([
                    $email,
                    $values['notify_new_posts'],
                    $values['notify_new_comments'],
                    $values['notify_new_reports'],
                    $values['notify_new_customer'],
                    $values['notify_subscription_cancelled'],
                ]);
                $notif_success = 'Notification settings saved.';
            } catch (PDOException $e) {
                error_log('admin_notification_prefs update failed: ' . $e->getMessage());
                $notif_error = 'Failed to save notification settings. Please try again.';
            }
        }
    } elseif (isset($_POST['disable_2fa'])) {
        // ---- 2FA disable (Security tab) ----
        $active_tab = 'security';
        if (!$csrf_ok) {
            $error = 'Security check failed. Please refresh the page and try again.';
        } elseif (disable_2fa($username)) {
            $success = 'Two-factor authentication has been disabled.';
            $is_enabled = false;
        } else {
            $error = 'Failed to disable two-factor authentication.';
        }
    } elseif (isset($_POST['enable_2fa'])) {
        // ---- 2FA enable (Security tab) ----
        $active_tab = 'security';
        $verification_code = $_POST['verification_code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        if (!$csrf_ok) {
            $error = 'Security check failed. Please refresh the page and try again.';
        } elseif (empty($secret)) {
            $error = 'Session expired or invalid. Please try again.';
        } elseif (verify_2fa_code($secret, $verification_code)) {
            if (save_2fa_secret($username, $secret)) {
                $success = 'Two-factor authentication successfully enabled!';
                $is_enabled = true;
                unset($_SESSION['temp_2fa_secret']);
            } else {
                $error = 'Failed to save authentication settings.';
            }
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
}

// Current notification prefs for rendering. Read straight from the DB so a save in this same request is reflected.
$prefs = $pdo->query('SELECT * FROM admin_notification_prefs WHERE id = 1')->fetch();
if (!$prefs) {
    $prefs = [
        'notification_email'            => 'contact@argorobots.com',
        'notify_new_posts'              => 1,
        'notify_new_comments'           => 1,
        'notify_new_reports'            => 1,
        'notify_new_customer'           => 1,
        'notify_subscription_cancelled' => 1,
    ];
}
if ($notif_error !== '') {
    $prefs['notification_email'] = $_POST['notification_email'] ?? $prefs['notification_email'];
    foreach (['notify_new_posts', 'notify_new_comments', 'notify_new_reports', 'notify_new_customer', 'notify_subscription_cancelled'] as $t) {
        $prefs[$t] = isset($_POST[$t]) ? 1 : 0;
    }
}

// Trusted-device count for the Security tab card.
$user = get_user_by_username($username);
$trusted_count = ($is_enabled && $user) ? count(list_trusted_devices((int)$user['id'])) : 0;

// Notification toggle definitions, grouped for display.
$notif_groups = [
    'Community' => [
        'notify_new_posts'    => ['New posts', 'When someone posts a bug report or feature request.'],
        'notify_new_comments' => ['New comments', 'When someone comments on any community post.'],
        'notify_new_reports'  => ['Content reports', 'When a user reports content for moderation.'],
    ],
    'Sales' => [
        'notify_new_customer'           => ['New paying customer', 'When someone subscribes to Argo Books Premium.'],
        'notify_subscription_cancelled' => ['Subscription cancelled', 'When someone cancels their Premium subscription.'],
    ],
];

// Listed with no toggle, because turning one off would mean hearing about a problem from a customer instead.
$notif_always = [
    'Safety' => [
        ['Refund blocked', 'When the refund safety check stops a refund from going through.'],
        ['Payment price mismatch', 'When a payment amount does not match the price it should have been.'],
    ],
    'Maintenance' => [
        ['Payroll rates need updating', 'Twice a year, before CRA\'s new payroll deduction tables take effect on January 1 and July 1. Sent in mid-December and mid-June. Miss it and payroll stops: the app refuses to calculate a pay run when it has no table for that pay date, rather than guessing, so nothing wrong is produced, but nobody can run payroll until the new file ships.'],
    ],
];

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="settings.css">

<div class="container settings-page">

    <div class="section-tabs">
        <button type="button" class="section-tab <?= $active_tab === 'security' ? 'active' : '' ?>" data-tab="tab-security">Security</button>
        <button type="button" class="section-tab <?= $active_tab === 'notifications' ? 'active' : '' ?>" data-tab="tab-notifications">Notifications</button>
    </div>

    <!-- ===================== SECURITY ===================== -->
    <div id="tab-security" class="tab-content <?= $active_tab === 'security' ? 'active' : '' ?>">

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="settings-card">
            <div class="settings-card-head">
                <div>
                    <h2>Two-Factor Authentication</h2>
                    <p class="settings-muted">An authenticator code is required on top of your password when signing in.</p>
                </div>
                <?php if ($is_enabled): ?>
                    <span class="settings-status settings-status-on">Enabled</span>
                <?php else: ?>
                    <span class="settings-status settings-status-off">Off</span>
                <?php endif; ?>
            </div>

            <?php if ($is_enabled): ?>
                <form method="post" class="settings-center" onsubmit="return confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" name="disable_2fa" value="1" class="settings-linkbtn">Disable 2FA</button>
                </form>
            <?php elseif (isset($_GET['setup'])): ?>
                <ol class="settings-steps">
                    <li>Install an authenticator app (Google Authenticator, Authy, etc.).</li>
                    <li>Scan the QR code below, or enter the key manually.</li>
                    <li>Enter the 6-digit code to confirm.</li>
                </ol>

                <div class="qr-container">
                    <div id="qr-code-container"></div>
                    <div class="manual-entry">
                        <h3>Manual entry</h3>
                        <p>If you can't scan the code, enter this key in your app:</p>
                        <div class="secret-key"><?= htmlspecialchars($new_secret) ?></div>
                        <p class="settings-muted"><small>Set "Argo Books Admin" as the account name.</small></p>
                    </div>
                </div>

                <form method="post" class="verification-form" id="verification-form">
                    <label class="verification-heading" for="verification_code">Enter the 6-digit code from your app</label>
                    <input type="number" id="verification_code" name="verification_code" class="verification-input" required autofocus placeholder="000000" min="0" max="999999">
                    <button type="button" onclick="submitVerificationForm()" id="verify-button" class="btn btn-green">Verify and Enable</button>
                    <input type="hidden" name="enable_2fa" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                </form>
            <?php else: ?>
                <p>Add an extra layer of security so a stolen password alone can't sign in.</p>
                <a href="?setup=1" class="btn btn-blue">Set Up 2FA</a>
            <?php endif; ?>
        </div>

        <?php if ($is_enabled): ?>
            <div class="settings-card">
                <div class="settings-card-head">
                    <div>
                        <h2>Trusted Devices</h2>
                        <p class="settings-muted">Browsers allowed to skip the 2FA code for 30 days.</p>
                    </div>
                </div>
                <p>
                    <?php if ($trusted_count === 0): ?>
                        No trusted devices right now. Tick "Trust this device" at sign-in to add one.
                    <?php else: ?>
                        <?= (int)$trusted_count ?> device<?= $trusted_count === 1 ? '' : 's' ?> can currently skip the code.
                    <?php endif; ?>
                </p>
                <div class="settings-center">
                    <a href="trusted-devices.php" class="btn btn-secondary">Manage trusted devices</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===================== NOTIFICATIONS ===================== -->
    <div id="tab-notifications" class="tab-content <?= $active_tab === 'notifications' ? 'active' : '' ?>">

        <?php if ($notif_error): ?>
            <div class="error-message"><?= htmlspecialchars($notif_error) ?></div>
        <?php endif; ?>
        <?php if ($notif_success): ?>
            <div class="success-message"><?= htmlspecialchars($notif_success) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="save_notifications" value="1">

            <div class="settings-card">
                <div class="settings-card-head">
                    <div>
                        <h2>Where alerts go</h2>
                        <p class="settings-muted">All admin notifications below are sent to this address.</p>
                    </div>
                </div>
                <div class="settings-email-field">
                    <label for="notification_email">Notification email</label>
                    <input type="email" id="notification_email" name="notification_email" required
                           value="<?= htmlspecialchars($prefs['notification_email']) ?>">
                </div>
            </div>

            <?php foreach ($notif_groups as $group_label => $rows): ?>
                <div class="settings-card">
                    <div class="settings-card-head">
                        <div><h2><?= htmlspecialchars($group_label) ?></h2></div>
                    </div>
                    <?php foreach ($rows as $key => [$label, $desc]): ?>
                        <div class="notif-row">
                            <div class="notif-text">
                                <div class="notif-label"><?= htmlspecialchars($label) ?></div>
                                <div class="notif-desc settings-muted"><?= htmlspecialchars($desc) ?></div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="<?= htmlspecialchars($key) ?>" <?= (int)$prefs[$key] === 1 ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($notif_always as $group_label => $rows): ?>
                <div class="settings-card">
                    <div class="settings-card-head">
                        <div>
                            <h2><?= htmlspecialchars($group_label) ?></h2>
                            <p class="settings-muted">Always sent. These cannot be turned off.</p>
                        </div>
                    </div>
                    <?php foreach ($rows as [$label, $desc]): ?>
                        <div class="notif-row">
                            <div class="notif-text">
                                <div class="notif-label"><?= htmlspecialchars($label) ?></div>
                                <div class="notif-desc settings-muted"><?= htmlspecialchars($desc) ?></div>
                            </div>
                            <span class="notif-always-tag">Always on</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="settings-actions settings-center">
                <button type="submit" class="btn btn-blue">Save settings</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($qr_code_data)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php endif; ?>
<script>
    function submitVerificationForm() {
        var f = document.getElementById('verification-form');
        if (f) f.submit();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var qrContainer = document.getElementById('qr-code-container');
        var otpAuthUrl = <?= !empty($qr_code_data) ? json_encode($qr_code_data) : '""' ?>;

        if (qrContainer && otpAuthUrl && typeof QRCode !== 'undefined') {
            try {
                new QRCode(qrContainer, {
                    text: otpAuthUrl,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (e) {
                qrContainer.innerHTML = "<p>QR code generation failed. Please use manual entry.</p>";
            }
        }

        var verificationInput = document.getElementById('verification_code');
        if (verificationInput) {
            verificationInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    setTimeout(submitVerificationForm, 300);
                }
            });
            verificationInput.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') e.preventDefault();
                if (e.key === 'Enter' && this.value.length === 6) {
                    e.preventDefault();
                    submitVerificationForm();
                }
            });
        }
    });
</script>

        </main>
    </div>
</body>

</html>
