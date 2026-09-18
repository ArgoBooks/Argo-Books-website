<?php

namespace {
    require_once __DIR__ . '/../../db_connect.php';
    require_once __DIR__ . '/../../email_sender.php';
    require_once __DIR__ . '/../../rate_limit_helper.php';

    /**
     * Register a new user with verification code
     *
     * @param string $username Username
     * @param string $email Email address
     * @param string $password Plain text password
     * @param bool $email_marketing_consent When true, opts the user in to all 5
     *                                      email_pref_* marketing categories at
     *                                      insert time. Defaults to false (off).
     * @return array Result with success, message, and user_id
     */
    function register_user($username, $email, $password, $email_marketing_consent = false)
    {
        global $pdo;

        // Check if username exists
        $stmt = $pdo->prepare('SELECT id FROM community_users WHERE username = ?');
        $stmt->execute([$username]);
        $user_exists = $stmt->fetch();

        if ($user_exists) {
            return ['success' => false, 'message' => 'Username already exists'];
        }

        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM community_users WHERE email = ?');
        $stmt->execute([$email]);
        $email_exists = $stmt->fetch();

        if ($email_exists) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        $verification_code = generate_verification_code();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $marketing = $email_marketing_consent ? 1 : 0;

        // Insert new user. Marketing prefs default to 0 in the schema; if the
        // user ticked the signup checkbox, opt them in to all 5 categories.
        $stmt = $pdo->prepare('INSERT INTO community_users
                         (username, email, password_hash, verification_code, email_verified,
                          email_pref_product_updates, email_pref_tips_onboarding,
                          email_pref_reviews, email_pref_promotions, email_pref_community_digest)
                         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)');
        $success = $stmt->execute([
            $username, $email, $password_hash, $verification_code,
            $marketing, $marketing, $marketing, $marketing, $marketing
        ]);

        if ($success) {
            $user_id = $pdo->lastInsertId();

            // Send verification email with code
            send_verification_email($email, $verification_code, $username);

            return [
                'success' => true,
                'message' => 'Registration successful! Please check your email for the verification code.',
                'user_id' => $user_id
            ];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    /**
     * Authenticate user
     *
     * @param string $login Username or email
     * @param string $password Plain text password
     * @return array|bool User data on success, array with 'email_not_verified' key if email not verified, or false on failure
     */
    function login_user($login, $password)
    {
        global $pdo;

        // Check if login is email or username - use whitelist to prevent any injection
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Find user by login (field is safe: always 'email' or 'username' from whitelist above)
        $sql = ($field === 'email')
            ? 'SELECT * FROM community_users WHERE email = ?'
            : 'SELECT * FROM community_users WHERE username = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Check if email is verified before allowing login
            if (!$user['email_verified']) {
                return [
                    'email_not_verified' => true,
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'username' => $user['username']
                ];
            }

            // Check if user had scheduled deletion
            $deletion_was_scheduled = !is_null($user['deletion_scheduled_at']);

            // Update last login time and cancel scheduled deletion
            $stmt = $pdo->prepare('UPDATE community_users SET last_login = NOW(), deletion_scheduled_at = NULL WHERE id = ?');
            $stmt->execute([$user['id']]);

            // If deletion was scheduled, send cancellation email
            if ($deletion_was_scheduled) {
                $email_sent = send_account_deletion_cancelled_email($user['email'], $user['username']);

                if (!$email_sent) {
                    error_log("Failed to send deletion cancelled email to: " . $user['email']);
                }
            }

            // Don't return sensitive data
            unset($user['password_hash']);
            unset($user['verification_token']);
            unset($user['reset_token']);
            unset($user['reset_token_expiry']);

            // Store avatar in session for the header
            $_SESSION['avatar'] = $user['avatar'];

            return $user;
        }

        return false;
    }

    /**
     * Check for remember me token and auto-login user
     */
    function check_remember_me()
    {
        if (isset($_COOKIE['remember_me']) && !isset($_SESSION['user_id'])) {
            $token = $_COOKIE['remember_me'];
            $user = validate_remember_token($token);

            if ($user) {
                // Do not auto-login if email is not verified
                if (!$user['email_verified']) {
                    // Clear the remember me cookie since user is not verified
                    setcookie('remember_me', '', time() - 3600, '/');
                    return;
                }

                global $pdo;

                // Check if user had scheduled deletion
                $stmt = $pdo->prepare('SELECT deletion_scheduled_at FROM community_users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $user_data = $stmt->fetch();
                $deletion_was_scheduled = !is_null($user_data['deletion_scheduled_at']);

                // Update last login time and cancel scheduled deletion
                $stmt = $pdo->prepare('UPDATE community_users SET last_login = NOW(), deletion_scheduled_at = NULL WHERE id = ?');
                $stmt->execute([$user['id']]);

                // If deletion was scheduled, send cancellation email
                if ($deletion_was_scheduled) {
                    $email_sent = send_account_deletion_cancelled_email($user['email'], $user['username']);

                    if (!$email_sent) {
                        error_log("Failed to send deletion cancelled email to: " . $user['email']);
                    }
                }

                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email_verified'] = $user['email_verified'];
                $_SESSION['avatar'] = $user['avatar'];
            }
        }
    }

    /**
     * Generate a remember me token for a user
     *
     * @param int $user_id User ID
     * @return string|bool Token or false on failure
     */
    function generate_remember_token($user_id)
    {
        global $pdo;

        // Create a unique token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

        // Remove any existing tokens for this user
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$user_id]);

        // Store a hash of the token (never store raw tokens in the database)
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $success = $stmt->execute([$user_id, $token_hash, $expires]);

        if ($success) {
            return $token;
        }

        return false;
    }

    /**
     * Validate a remember me token and get the associated user
     *
     * @param string $token Remember me token
     * @return array|bool User data or false if invalid
     */
    function validate_remember_token($token)
    {
        global $pdo;

        // Hash the token to compare against the stored hash
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare('SELECT rt.user_id, u.* FROM remember_tokens rt
                         JOIN community_users u ON rt.user_id = u.id
                         WHERE rt.token = ? AND rt.expires_at > NOW()');
        $stmt->execute([$token_hash]);
        $user = $stmt->fetch();

        if ($user) {
            // Don't return sensitive data
            unset($user['password_hash']);
            unset($user['verification_token']);
            unset($user['reset_token']);
            unset($user['reset_token_expiry']);

            return $user;
        }

        return false;
    }

    /**
     * Clear remember me token when logging out
     *
     * @param int $user_id User ID
     */
    function clear_remember_token($user_id)
    {
        global $pdo;

        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$user_id]);

        // Clear the cookie
        setcookie('remember_me', '', time() - 3600, '/');
    }

    /**
     * Get user by ID
     *
     * @param int $user_id User ID
     * @return array|bool User data or false if not found
     */
    function get_user($user_id)
    {
        global $pdo;

        // Use a new database connection for each call
        $stmt = $pdo->prepare('SELECT id, username, email, bio, avatar, role, email_verified, created_at, last_login
                        FROM community_users WHERE id = ?');

        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        return $user ? $user : false;
    }

    /**
     * Request password reset
     *
     * @param string $email User's email address
     * @return bool Success status
     */
    function request_password_reset($email)
    {
        global $pdo;

        // Keyed on the address, not the IP, so the form can't flood one inbox from many IPs
        if (rate_limit_hit('community_password_reset_email', strtolower(trim($email)), PASSWORD_RESET_EMAIL_PREFIX)) {
            return false;
        }

        // Find user by email
        $stmt = $pdo->prepare('SELECT id, username FROM community_users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        // Generate cryptographically secure reset token
        $reset_token = bin2hex(random_bytes(32));

        // Set token expiry (1 hour from now) - shorter window reduces risk of token interception
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Update user with reset token
        $stmt = $pdo->prepare('UPDATE community_users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?');
        $success = $stmt->execute([$reset_token, $expiry, $user['id']]);

        if ($success) {
            return send_password_reset_email($email, $reset_token, $user['username']);
        }

        return false;
    }

    /**
     * Reset password using token
     *
     * @param string $token Reset token
     * @param string $new_password New password
     * @return bool Success status
     */
    function reset_password($token, $new_password)
    {
        global $pdo;

        // Find user by reset token and check expiry
        $stmt = $pdo->prepare('SELECT id FROM community_users WHERE reset_token = ? AND reset_token_expiry > NOW()');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update user with new password and clear reset token
        $stmt = $pdo->prepare('UPDATE community_users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?');
        $success = $stmt->execute([$password_hash, $user['id']]);

        if ($success) {
            // A stolen remember-me cookie may be the reason for the reset
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
            $stmt->execute([$user['id']]);
        }

        return $success;
    }

    const PASSWORD_RESET_EMAIL_PREFIX = 'community_password_reset_email';

    // A six-digit code is only safe with few guesses and a short life
    const EMAIL_CHANGE_MAX_ATTEMPTS = 5;
    const EMAIL_CHANGE_CODE_MINUTES = 30;

    /**
     * Start an email change: store the new address with a fresh code, replacing
     * any earlier request. The current address stays as it is until the code
     * comes back.
     *
     * @param int $user_id User ID
     * @param string $new_email Address the code will be sent to
     * @return string The 6-digit code to email to $new_email
     */
    function start_email_change($user_id, $new_email)
    {
        global $pdo;

        $code = generate_verification_code();
        $stmt = $pdo->prepare('UPDATE community_users
            SET email_change_new_email = ?, email_change_code = ?,
                email_change_expires_at = NOW() + INTERVAL ? MINUTE, email_change_attempts = 0
            WHERE id = ?');
        $stmt->execute([$new_email, $code, EMAIL_CHANGE_CODE_MINUTES, $user_id]);

        return $code;
    }

    /**
     * Check an email-change code. Every guess counts, the code stops working
     * after EMAIL_CHANGE_MAX_ATTEMPTS guesses or EMAIL_CHANGE_CODE_MINUTES, and a
     * correct code works once.
     *
     * @param int $user_id User ID
     * @param string $code Code the user typed
     * @param string|null $new_email Set on 'ok' to the address the code was sent to
     * @return string 'ok', 'invalid', 'expired', 'too_many_attempts' or 'no_code'
     */
    function verify_email_change_code($user_id, $code, &$new_email = null)
    {
        global $pdo;

        $stmt = $pdo->prepare('SELECT email_change_code, email_change_new_email,
                email_change_expires_at > NOW() AS still_valid
            FROM community_users WHERE id = ?');
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row || $row['email_change_code'] === null) {
            return 'no_code';
        }
        if (!$row['still_valid']) {
            return 'expired';
        }

        // One statement, so parallel guesses can't all read a count under the cap
        $stmt = $pdo->prepare('UPDATE community_users SET email_change_attempts = email_change_attempts + 1
            WHERE id = ? AND email_change_code IS NOT NULL AND email_change_attempts < ?');
        $stmt->execute([$user_id, EMAIL_CHANGE_MAX_ATTEMPTS]);
        if ($stmt->rowCount() === 0) {
            return 'too_many_attempts';
        }

        if (!hash_equals((string) $row['email_change_code'], (string) $code)) {
            return 'invalid';
        }

        $stmt = $pdo->prepare('UPDATE community_users
            SET email_change_code = NULL, email_change_new_email = NULL,
                email_change_expires_at = NULL, email_change_attempts = 0
            WHERE id = ? AND email_change_code = ?');
        $stmt->execute([$user_id, $row['email_change_code']]);
        if ($stmt->rowCount() !== 1) {
            return 'invalid';
        }

        $new_email = $row['email_change_new_email'];
        return 'ok';
    }

    /**
     * Upload avatar image
     *
     * @param int $user_id User ID
     * @param array $file File data from $_FILES
     * @return string|bool Image path on success, false on failure
     */
    function upload_avatar($user_id, $file)
    {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error: " . $file['error']);
            return false;
        }

        // Validate image type using server-side detection (not client-supplied MIME)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detected_type, $allowed_types)) {
            error_log("Invalid file type detected: " . $detected_type);
            return false;
        }

        // Validate file size (max 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            error_log("File too large: " . $file['size'] . " bytes");
            return false;
        }

        // Create base uploads directory first
        $base_dir = dirname(__DIR__) . '/uploads/';
        if (!file_exists($base_dir)) {
            if (!mkdir($base_dir, 0755)) {
                error_log("Failed to create base uploads directory: " . $base_dir);
                return false;
            }
            chmod($base_dir, 0755); // Ensure correct permissions
        }

        // Then create avatars subdirectory
        $upload_dir = $base_dir . 'avatars/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755)) {
                error_log("Failed to create avatars directory: " . $upload_dir);
                return false;
            }
            chmod($upload_dir, 0755); // Ensure correct permissions
        }

        // Get current avatar path before updating
        global $pdo;
        $stmt = $pdo->prepare('SELECT avatar FROM community_users WHERE id = ?');
        $stmt->execute([$user_id]);
        $old_avatar_row = $stmt->fetch();
        $old_avatar = $old_avatar_row['avatar'] ?? '';

        // Derive the extension from the server-validated MIME type, never from
        // the client-supplied filename. This stops an attacker from saving a
        // valid image under a .php name. (The upload dir also blocks script
        // execution via .htaccess, so this is defense in depth.)
        $mime_to_ext = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $extension = $mime_to_ext[$detected_type]; // $detected_type validated above
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $target_path = $upload_dir . $filename;

        // Confirm this really is a PHP HTTP upload before reading it (the safety
        // guarantee move_uploaded_file() used to give us here).
        if (!is_uploaded_file($file['tmp_name'])) {
            error_log("Avatar upload: tmp_name is not an uploaded file for user " . $user_id);
            return false;
        }

        // Re-encode the image from its decoded pixels so anything appended after
        // the real image data (e.g. a GIF/PHP polyglot) is discarded. The raw
        // uploaded bytes are never written to disk.
        $src_bytes = file_get_contents($file['tmp_name']);
        $image = ($src_bytes !== false) ? @imagecreatefromstring($src_bytes) : false;
        if ($image === false) {
            error_log("Avatar upload: could not decode image for user " . $user_id);
            return false;
        }

        switch ($detected_type) {
            case 'image/png':
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $saved = imagepng($image, $target_path);
                break;
            case 'image/gif':
                $saved = imagegif($image, $target_path);
                break;
            case 'image/webp':
                $saved = imagewebp($image, $target_path);
                break;
            case 'image/jpeg':
            default:
                $saved = imagejpeg($image, $target_path, 90);
                break;
        }
        imagedestroy($image);

        if ($saved) {
            // Set permissions for the file
            chmod($target_path, 0644);

            try {
                // Begin transaction to reduce lock time
                $pdo->beginTransaction();

                $avatar_path = 'uploads/avatars/' . $filename;
                $stmt = $pdo->prepare('UPDATE community_users SET avatar = ?, updated_at = NOW() WHERE id = ?');
                $success = $stmt->execute([$avatar_path, $user_id]);

                if (!$success) {
                    $pdo->rollBack();
                    error_log("Failed to update avatar in database");
                    return false;
                }

                // Commit transaction
                $pdo->commit();

                // Update session with avatar path
                $_SESSION['avatar'] = $avatar_path;

                // Delete old avatar file if it exists
                if (!empty($old_avatar)) {
                    $old_file_path = dirname(__DIR__) . '/' . $old_avatar;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }

                return $avatar_path;
            } catch (Exception $e) {
                // Rollback on exception
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Exception in avatar upload: " . $e->getMessage());
                return false;
            }
        }

        error_log("Avatar upload: failed to write re-encoded image to: " . $target_path);
        if (file_exists($target_path)) {
            @unlink($target_path);
        }
        return false;
    }

    /**
     * Check if user is logged in and exists in database
     *
     * @return bool True if user is logged in and exists in database
     */
    function is_user_logged_in()
    {
        // First check session variables
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Then verify user exists in database
        try {
            global $pdo;
            if (!$pdo) {
                error_log('Database connection failed in is_user_logged_in');
                return false;
            }

            $stmt = $pdo->prepare('SELECT id FROM community_users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                error_log('User with ID ' . $_SESSION['user_id'] . ' not found in database');
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log('Exception in is_user_logged_in: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Require user to be logged in, redirect to login if not
     *
     * @param string $redirect_url URL to redirect to after login (optional, defaults to current page)
     */
    function require_login($redirect_url = '')
    {
        if (!is_user_logged_in()) {
            // Use REQUEST_URI (already absolute) as default, or the explicit redirect
            $redirect = !empty($redirect_url) ? $redirect_url : ($_SERVER['REQUEST_URI'] ?? '');

            // Ensure the redirect path is absolute so it works from login.php's location
            if (!empty($redirect) && $redirect[0] !== '/') {
                $redirect = '/' . $redirect;
            }

            // Store the intended destination for after login
            $_SESSION['redirect_after_login'] = $redirect;

            // Get the web path to login.php based on where this file is located
            // __DIR__ gives us the filesystem path to community/users/
            // We need to convert this to a web-accessible URL
            $doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
            $login_dir = __DIR__;

            // Get relative path from document root to login.php
            $relative_path = str_replace($doc_root, '', $login_dir);
            $relative_path = str_replace('\\', '/', $relative_path); // Windows compatibility

            header('Location: ' . $relative_path . '/login.php');
            exit;
        }
    }
}

namespace CommunityUsers {
    /**
     * Get the current logged-in user's data
     *
     * @return array|null User data or null if not logged in
     */
    function get_current_user()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $user_id = $_SESSION['user_id'];
        global $pdo;

        $stmt = $pdo->prepare('SELECT id, username, email, bio, avatar, role, email_verified, created_at, last_login
                         FROM community_users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            // User ID in session but not found in database
            // Return basic info from session
            return [
                'id' => $user_id,
                'username' => $_SESSION['username'] ?? 'Unknown',
                'email' => $_SESSION['email'] ?? '',
                'email_verified' => $_SESSION['email_verified'] ?? 0,
                'role' => $_SESSION['role'] ?? 'user',
                'avatar' => ''
            ];
        }

        return $user;
    }
}

namespace {
    /**
     * Generate a 6-digit verification code
     *
     * @return string 6-digit code
     */
    function generate_verification_code()
    {
        return sprintf('%06d', random_int(100000, 999999));
    }

    /**
     * Get user's Premium subscription information
     *
     * @param int $user_id User ID
     * @return array|null Subscription data or null if no subscription
     */
    function get_user_premium_subscription($user_id)
    {
        global $pdo;

        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT * FROM premium_subscriptions
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            return $subscription ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching Premium subscription: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Compute proration for a mid-cycle billing-cycle switch (Stripe/Square only).
     *
     * Symmetric formula for both directions:
     *   immediate_charge_base   = max(0, new_base - prorated_credit - existing_credit)
     *   processing_fee          = (immediate_charge_base > 0) ? fee(immediate_charge_base) : 0
     *   immediate_charge_total  = immediate_charge_base + processing_fee
     *   credit_balance_after    = max(0, prorated_credit + existing_credit - new_base)
     *
     * Proration uses BASE prices from the pricing config, never the `amount`
     * column, which includes a processing fee from the prior period.
     *
     * Period length is derived from the *cycle* (end_date - 1 month or
     * end_date - 1 year), not from (end_date - start_date). The renewal cron
     * doesn't update start_date, so on a long-running subscription
     * (end_date - start_date) drifts arbitrarily large and would skew the
     * prorated credit toward zero.
     *
     * @param array  $subscription   Row from premium_subscriptions
     * @param string $newCycle       'monthly' | 'yearly'
     * @param array  $pricingConfig  Result of get_pricing_config()
     * @return array See keys below
     */
    function calculate_cycle_switch_proration($subscription, $newCycle, $pricingConfig)
    {
        $oldCycle = $subscription['billing_cycle'] ?? 'monthly';

        if ($oldCycle === $newCycle) {
            return ['direction' => 'noop'];
        }

        $monthlyBase    = (float) $pricingConfig['premium_monthly_price'];
        $yearlyBase     = (float) $pricingConfig['premium_yearly_price'];
        $existingCredit = (float) ($subscription['credit_balance'] ?? 0);

        $now            = time();
        $end            = strtotime($subscription['end_date']);
        $effectiveStart = ($oldCycle === 'yearly')
            ? strtotime('-1 year', $end)
            : strtotime('-1 month', $end);
        $periodSecs    = max(1, $end - $effectiveStart);
        $remainingSecs = max(0, $end - $now);
        $fraction      = min(1.0, $remainingSecs / $periodSecs);

        $oldBase        = ($oldCycle === 'yearly') ? $yearlyBase : $monthlyBase;
        $proratedCredit = round($oldBase * $fraction, 2);

        if ($newCycle === 'yearly') {
            $newBase    = $yearlyBase;
            $newEndDate = date('Y-m-d H:i:s', strtotime('+1 year'));
            $direction  = 'upgrade';
        } else {
            $newBase    = $monthlyBase;
            $newEndDate = date('Y-m-d H:i:s', strtotime('+1 month'));
            $direction  = 'downgrade';
        }

        $totalDiscount    = $proratedCredit + $existingCredit;
        $immediateBase    = max(0, round($newBase - $totalDiscount, 2));
        $leftoverCredit   = max(0, round($totalDiscount - $newBase, 2));
        // Processing fee applies to the actual charge amount (gateway charges fee on real $),
        // so when the discount fully covers the new base, no charge and no fee.
        $procFee = ($immediateBase > 0)
            ? calculate_processing_fee($immediateBase)
            : 0.00;
        $immediateTotal   = round($immediateBase + $procFee, 2);
        $existingConsumed = max(0, round($existingCredit - $leftoverCredit, 2));

        // The amount column reflects the *renewal* amount of the new cycle (base + fee),
        // matching the convention used by new-subscription writes.
        $newRenewalFee   = calculate_processing_fee($newBase);
        $newAmountColumn = round($newBase + $newRenewalFee, 2);

        return [
            'direction'                => $direction,
            'old_cycle'                => $oldCycle,
            'new_cycle'                => $newCycle,
            'prorated_credit'          => $proratedCredit,
            'existing_credit_consumed' => $existingConsumed,
            'immediate_charge_base'    => $immediateBase,
            'processing_fee'           => $procFee,
            'immediate_charge_total'   => $immediateTotal,
            'credit_balance_after'     => $leftoverCredit,
            'new_end_date'             => $newEndDate,
            'new_amount_column'        => $newAmountColumn,
            'days_remaining'           => intdiv($remainingSecs, 86400),
            'period_total_days'        => intdiv($periodSecs, 86400),
        ];
    }
}
