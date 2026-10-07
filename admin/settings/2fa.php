<?php
date_default_timezone_set('UTC');
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/../trusted_devices.php';

/**
 * Get user by username (case-insensitive)
 * 
 * @param string $username Username
 * @return array|null User data or null if not found
 */
function get_user_by_username($username)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE LOWER(username) = LOWER(?)');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return $user;
}

/**
 * Save a 2FA secret for a user
 * 
 * @param string $username Username
 * @param string $secret TOTP secret
 * @return bool Success status
 */
function save_2fa_secret($username, $secret)
{
    $user = get_user_by_username($username);
    if (!$user) return false;

    try {
        // Encrypt the 2FA secret before storing to protect against DB compromise
        $encrypted_secret = portal_encrypt($secret);
        global $pdo;
        $stmt = $pdo->prepare('UPDATE admin_users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE username = ?');
        if (!$stmt->execute([$encrypted_secret, $user['username']])) {
            error_log("2FA setup failed: DB update error");
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("2FA setup failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Disable 2FA for a user
 * 
 * @param string $username Username
 * @return bool Success status
 */
function disable_2fa($username)
{
    $user = get_user_by_username($username);
    if (!$user) return false;

    try {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE admin_users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE username = ?');
        $success = $stmt->execute([$user['username']]) && $stmt->rowCount() > 0;

        // Trusted-device cookies only make sense while 2FA is enabled. They bypass the TOTP step.
        if ($success) {
            revoke_all_trusted_devices((int)$user['id']);
        }

        return $success;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get a user's 2FA secret
 * 
 * @param string $username Username
 * @return string|null 2FA secret or null if not found
 */
function get_2fa_secret($username)
{
    try {
        $user = get_user_by_username($username);
        if (!$user || empty($user['two_factor_secret'])) return null;
        // Decrypt the 2FA secret from storage
        return portal_decrypt($user['two_factor_secret']);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if 2FA is enabled for a user
 * 
 * @param string $username Username
 * @return bool True if 2FA is enabled
 */
function is_2fa_enabled($username)
{
    try {
        $user = get_user_by_username($username);
        return $user && $user['two_factor_enabled'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate a new 2FA secret
 * 
 * @return string New 2FA secret
 */
function generate_2fa_secret()
{
    return TOTP::generateSecret();
}

/**
 * Verify a 2FA code
 *
 * @param string $secret 2FA secret
 * @param string $code Code to verify
 * @return bool True if code is valid
 */
function verify_2fa_code($secret, $code)
{
    return !empty($secret) && TOTP::verify($secret, $code);
}

/**
 * Verify a 2FA login code with replay protection.
 *
 * A TOTP code is valid for ~90 seconds (current ±1 step). Without replay
 * protection an attacker who intercepts a code can re-use it within that
 * window. This atomically claims the matching counter so the same code
 * cannot succeed twice for the same user.
 *
 * Returns true on first successful use, false if the code is invalid OR if
 * a code with the same (or earlier) counter has already been consumed.
 */
function verify_2fa_login_code($username, $code)
{
    if (empty($username)) {
        return false;
    }
    $secret = get_2fa_secret($username);
    if (empty($secret)) {
        return false;
    }

    $counter = TOTP::verifyAndGetCounter($secret, $code);
    if ($counter === 0) {
        return false;
    }

    global $pdo;
    // Atomic compare-and-set: only succeeds if no code with this counter
    // (or a later one) has already been claimed for this user.
    $stmt = $pdo->prepare(
        'UPDATE admin_users SET last_2fa_counter = ? WHERE LOWER(username) = LOWER(?) AND last_2fa_counter < ?'
    );
    $stmt->execute([$counter, $username, $counter]);
    return $stmt->rowCount() > 0;
}

/**
 * Get a QR code URL for 2FA setup
 * 
 * @param string $username Username
 * @param string $secret 2FA secret
 * @param string $issuer Issuer name
 * @return string QR code URL
 */
function get_qr_code_url($username, $secret, $issuer = 'Argo Books Admin')
{
    $params = [
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => '6',
        'period' => '30'
    ];

    return "otpauth://totp/" . urlencode($issuer) . ":" . urlencode($username) . "?" . http_build_query($params);
}
