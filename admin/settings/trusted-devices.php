<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/2fa.php';
require_once __DIR__ . '/../trusted_devices.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Trusted Devices';
$page_description = 'Devices that can skip the 2FA code on this account. Revoke any device that is no longer yours.';

$username = $_SESSION['admin_username'];
$user = get_user_by_username($username);

$error = '';
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cast guards against array input (e.g. ?csrf_token[]=x) which would TypeError hash_equals.
    $posted_csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $posted_csrf)) {
        $error = 'Security token mismatch. Please try again.';
    } elseif (!$user) {
        $error = 'Could not load your account.';
    } elseif (isset($_POST['revoke_all'])) {
        $n = revoke_all_trusted_devices((int)$user['id']);
        $success = $n === 1 ? '1 trusted device revoked.' : "$n trusted devices revoked.";
        clear_trusted_device_cookie();
    } elseif (!empty($_POST['revoke_id'])) {
        $device_id = (int)$_POST['revoke_id'];
        if (revoke_trusted_device((int)$user['id'], $device_id)) {
            $success = 'Trusted device revoked.';
        } else {
            $error = 'Could not revoke that device.';
        }
    }
}

$devices = $user ? list_trusted_devices((int)$user['id']) : [];
$csrf = $_SESSION['csrf_token'];

include __DIR__ . '/../admin_header.php';
?>

<div class="container">
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <p>
        <a href="index.php?tab=security" class="link">&larr; Back to Settings</a>
    </p>

    <?php if (empty($devices)): ?>
        <div class="center">
            <h2>No trusted devices</h2>
            <p>When you sign in, tick "Trust this device for 30 days" to skip the verification code on this browser for 30 days.</p>
        </div>
    <?php else: ?>
        <div class="center" style="margin-bottom: 16px;">
            <form method="post" style="display: inline;" onsubmit="return confirm('Revoke ALL trusted devices? You will need to enter a 2FA code on every device next time you sign in.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" name="revoke_all" value="1" class="btn btn-red">Revoke all trusted devices</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table-auto-size" data-paginate="25">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>IP</th>
                        <th>Added</th>
                        <th>Last used</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $d): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($d['label'] ?: 'Unknown device') ?></strong>
                                <?php if (!empty($d['user_agent'])): ?>
                                    <br><small style="opacity: 0.7;"><?= htmlspecialchars($d['user_agent']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($d['ip_address'] ?: '—') ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($d['created_at']))) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($d['last_used_at']))) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($d['expires_at']))) ?></td>
                            <td>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Revoke this device? You will need to enter a 2FA code from this browser next time.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="revoke_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-red">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

        </main>
    </div>
</body>

</html>
