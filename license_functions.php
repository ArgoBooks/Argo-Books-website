<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/track_referral_event.php';

/**
 * Generate a random license key
 *
 * @param string $type The type of license (always 'premium')
 * @return string A 20-character alphanumeric license key in format PREM-XXXX-XXXX-XXXX-XXXX
 */
function generate_license_key($type = 'premium')
{
    // Alphabet excludes ambiguous characters (no 0 1 I L O U), matching
    // generate_pairing_short_code() in api/sync/sync-helper.php.
    $chars = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    $prefix = 'PREM';

    // Generate remaining 16 random characters
    $key = $prefix;
    for ($i = 0; $i < 16; $i++) {
        if ($i % 4 == 0) {
            $key .= '-';
        }
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }

    // Format: PREM-XXXX-XXXX-XXXX-XXXX
    return $key;
}

/**
 * Validate a free/promo premium subscription key
 * @param string $key The premium subscription key to validate
 * @return array Response array with validation result
 */
function validate_premium_key($key) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT subscription_key, email, duration_months, created_at,
                redeemed_at, device_id, subscription_id, notes
            FROM premium_subscription_keys
            WHERE subscription_key = ?
        ");
        $stmt->execute([$key]);
        $premium_key = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$premium_key) {
            return [
                'success' => false,
                'message' => 'Invalid premium key.'
            ];
        }

        // Check if already redeemed
        if ($premium_key['redeemed_at'] !== null) {
            return [
                'success' => true,
                'type' => 'premium_key',
                'status' => 'redeemed',
                'message' => 'Premium key has already been redeemed.',
                'key' => $premium_key['subscription_key'],
                'redeemed_at' => $premium_key['redeemed_at'],
                'subscription_id' => $premium_key['subscription_id']
            ];
        }

        // Key is valid and not yet redeemed
        return [
            'success' => true,
            'type' => 'premium_key',
            'status' => 'valid',
            'message' => 'Premium key is valid and can be redeemed.',
            'key' => $premium_key['subscription_key'],
            'duration_months' => $premium_key['duration_months'],
            'restricted_email' => $premium_key['email']
        ];
    } catch (PDOException $e) {
        error_log("Premium key validation error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error validating premium key. Please try again.'
        ];
    }
}

/**
 * Whether we still have no way to contact whoever redeemed this key.
 *
 * Keys sold through a reseller are handed over as a pre-generated batch and the reseller does
 * not pass buyer details back, so redemption in the app is the only moment their address can be
 * asked for. Keys issued through the website already carry one and must never be asked again.
 *
 * Reads BOTH columns on purpose. `email` is the redemption restriction set when a key is issued
 * to a named buyer; `customer_email` is contact detail captured afterwards. Either one means we
 * can reach them, and neither is worth a second prompt.
 *
 * @param string $key The subscription key
 * @return bool True when nothing on record identifies the buyer
 */
function license_key_needs_email($key) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT email, customer_email
            FROM premium_subscription_keys
            WHERE subscription_key = ?
        ");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return trim((string) ($row['email'] ?? '')) === ''
            && trim((string) ($row['customer_email'] ?? '')) === '';
    } catch (PDOException $e) {
        // Never let this decide the redemption. A failure here means we skip the prompt and
        // lose one address; throwing would fail an activation the customer already paid for.
        error_log('license_key_needs_email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Redeem a free/promo premium subscription key (device-based).
 * On first redemption: validates the key, creates a premium subscription, and marks the key as redeemed.
 * On re-redemption: allows transferring the key to a new device by updating device_id.
 *
 * @param string $key The premium subscription key to redeem
 * @param string $device_id The hashed machine identifier of the redeeming device
 * @return array Response array with redemption result
 */
function redeem_premium_key($key, $device_id) {
    global $pdo;

    // First validate the key
    $validation = validate_premium_key($key);

    if (!$validation['success']) {
        return [
            'success' => false,
            'status' => 'invalid_key',
            'message' => 'Invalid license key.'
        ];
    }

    // Key already redeemed: handle re-redemption (device transfer)
    if ($validation['status'] === 'redeemed') {
        return _handle_re_redemption($key, $device_id, $validation['subscription_id']);
    }

    // First-time redemption
    $duration_months = $validation['duration_months'];

    try {
        $pdo->beginTransaction();

        $subscriptionId = generate_license_key('premium');

        // Calculate subscription dates
        $startDate = date('Y-m-d H:i:s');
        if ($duration_months == 0) {
            // Permanent subscription: set end date far in the future
            $endDate = date('Y-m-d H:i:s', strtotime('+100 years'));
            $billingCycle = 'yearly';
        } elseif ($duration_months >= 12) {
            $endDate = date('Y-m-d H:i:s', strtotime("+$duration_months months"));
            $billingCycle = 'yearly';
        } else {
            $endDate = date('Y-m-d H:i:s', strtotime("+$duration_months months"));
            $billingCycle = 'monthly';
        }

        // Create the premium subscription (no user account, so user_id and email are NULL)
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscriptions (
                subscription_id, billing_cycle, amount, currency,
                start_date, end_date, status, payment_method, transaction_id,
                auto_renew, environment, created_at
            ) VALUES (
                ?, ?, 0.00, 'CAD',
                ?, ?, 'active', 'free_key', ?,
                0, ?, NOW()
            )
        ");
        $stmt->execute([
            $subscriptionId,
            $billingCycle,
            $startDate,
            $endDate,
            $key,
            current_environment()
        ]);

        // The redeemed_at IS NULL guard makes the claim atomic, so when two requests race for one key exactly one UPDATE matches.
        $stmt = $pdo->prepare("
            UPDATE premium_subscription_keys
            SET redeemed_at = NOW(),
                device_id = ?,
                subscription_id = ?
            WHERE subscription_key = ?
              AND redeemed_at IS NULL
        ");
        $stmt->execute([$device_id, $subscriptionId, $key]);

        if ($stmt->rowCount() === 0) {
            // Another concurrent request already redeemed this key first.
            $pdo->rollBack();
            return redeem_premium_key($key, $device_id);
        }

        $pdo->commit();

        // Fire premium_signup for free-key redemption. No premium_paid because no money changed hands.
        try {
            $visitorId   = $_COOKIE[ARGO_VISITOR_COOKIE] ?? null;
            $sourceCode  = $_SESSION['referral_source']   ?? null;

            track_referral_event('premium_signup', [
                'visitor_id'      => $visitorId,
                'source_code'     => $sourceCode,
                'subscription_id' => $subscriptionId,
                'event_data'      => [
                    'amount'     => 0,
                    'redemption' => 'free_key',
                    'duration_months' => $duration_months,
                ],
                'allow_bot' => true,
            ]);
            if (!empty($visitorId)) {
                backfill_visitor_events($visitorId, $subscriptionId);
            }
        } catch (Exception $e) {
            error_log('Free-key premium_signup event failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'type' => 'premium',
            'status' => 'active',
            'message' => 'License activated successfully!',
            'subscription_id' => $subscriptionId,
            'end_date' => $endDate,
            'duration_months' => $duration_months,
            'needs_email' => license_key_needs_email($key)
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Premium key redemption error: " . $e->getMessage());
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Error redeeming premium key. Please try again.'
        ];
    }
}

/**
 * Handle re-redemption of an already-redeemed key (device transfer).
 * If the subscription is still active, updates the device_id on the key.
 * If the subscription is expired, returns an error.
 * If the linked subscription is missing, recreates it from the key's metadata.
 *
 * @param string $key The premium subscription key
 * @param string $device_id The new device's hashed machine identifier
 * @param string $subscription_id The subscription ID linked to this key
 * @return array Response array
 */
/**
 * Makes a key's subscription belong to this environment, and says whether it can.
 *
 * A key carries no environment of its own, so one redeemed on the other site is used here
 * too. Its subscription is moved across rather than refused: left stamped with the other
 * environment, every query that filters on environment would miss it, which is what made
 * the app's launch check clear the key after each switch between dev and production.
 *
 * Only a subscription the key itself created, which is what free_key marks. A subscription
 * paid for by a sandbox checkout must not be dragged into production, because that really
 * would be premium for test money.
 */
function _move_subscription_to_current_environment(array &$subscription): bool {
    global $pdo;

    if ($subscription['environment'] === current_environment()) {
        return true;
    }
    if (($subscription['payment_method'] ?? '') !== 'free_key') {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE premium_subscriptions
        SET environment = ?
        WHERE subscription_id = ? AND payment_method = 'free_key'
    ");
    $stmt->execute([current_environment(), $subscription['subscription_id']]);
    $subscription['environment'] = current_environment();
    return true;
}

function _handle_re_redemption($key, $device_id, $subscription_id) {
    global $pdo;

    try {
        // Look up the linked subscription to check status/expiry
        $subscription = null;
        if ($subscription_id) {
            $stmt = $pdo->prepare("
                SELECT subscription_id, status, end_date, environment, payment_method
                FROM premium_subscriptions
                WHERE subscription_id = ?
            ");
            $stmt->execute([$subscription_id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($subscription && !_move_subscription_to_current_environment($subscription)) {
            return [
                'success' => false,
                'status' => 'invalid_key',
                'message' => 'Invalid license key.'
            ];
        }

        if (!$subscription) {
            // Linked subscription is missing (deleted or never created).
            // Recreate the subscription from the key's metadata.
            return _recreate_subscription_for_key($key, $device_id);
        }

        // Check if subscription has expired
        $now = new DateTime();
        $end_date = new DateTime($subscription['end_date']);

        if ($subscription['status'] === 'expired' || $end_date <= $now) {
            // Mark as expired if not already
            if ($subscription['status'] !== 'expired') {
                $stmt = $pdo->prepare("UPDATE premium_subscriptions SET status = 'expired' WHERE subscription_id = ?");
                $stmt->execute([$subscription_id]);
            }
            return [
                'success' => false,
                'status' => 'expired',
                'message' => "This license key's subscription has expired."
            ];
        }

        // Subscription is still active. Transfer to new device
        $stmt = $pdo->prepare("
            UPDATE premium_subscription_keys
            SET device_id = ?
            WHERE subscription_key = ?
        ");
        $stmt->execute([$device_id, $key]);

        return [
            'success' => true,
            'type' => 'premium',
            'status' => 'active',
            'message' => 'License activated successfully!',
            'subscription_id' => $subscription['subscription_id'],
            'end_date' => $subscription['end_date'],
            'needs_email' => license_key_needs_email($key)
        ];

    } catch (PDOException $e) {
        error_log("Premium key re-redemption error: " . $e->getMessage());
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Error redeeming premium key. Please try again.'
        ];
    }
}

/**
 * Recreate a missing subscription for an already-redeemed key.
 * This handles the case where a key was marked as redeemed but the linked
 * subscription row in premium_subscriptions is missing (deleted or never created).
 *
 * @param string $key The premium subscription key
 * @param string $device_id The device's hashed machine identifier
 * @return array Response array with redemption result
 */
function _recreate_subscription_for_key($key, $device_id) {
    global $pdo;

    try {
        // redeemed_at and created_at anchor a recreated term to the original start, so a customer whose subscription row went missing gets no extra term.
        $stmt = $pdo->prepare("
            SELECT duration_months, redeemed_at, created_at
            FROM premium_subscription_keys
            WHERE subscription_key = ?
        ");
        $stmt->execute([$key]);
        $key_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$key_data) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'License key not found.'
            ];
        }

        $duration_months = $key_data['duration_months'];
        // Original start = when the key was redeemed (or created); fall back to
        // now only if neither is recorded.
        $originalStart = $key_data['redeemed_at'] ?? $key_data['created_at'] ?? date('Y-m-d H:i:s');

        // Calculate subscription dates from the original start, not today.
        $startDate = $originalStart;
        if ($duration_months == 0) {
            $endDate = date('Y-m-d H:i:s', strtotime('+100 years'));
            $billingCycle = 'yearly';
        } else {
            $endDate = date('Y-m-d H:i:s', strtotime("$originalStart +$duration_months months"));
            $billingCycle = ($duration_months >= 12) ? 'yearly' : 'monthly';
        }

        // If the original term has already elapsed, don't hand out a fresh
        // active subscription — the customer's access has expired.
        if ($duration_months != 0 && strtotime($endDate) <= time()) {
            return [
                'success' => false,
                'status' => 'expired',
                'message' => "This license key's subscription has expired."
            ];
        }

        $pdo->beginTransaction();

        $newSubscriptionId = generate_license_key('premium');

        // Create the premium subscription
        $stmt = $pdo->prepare("
            INSERT INTO premium_subscriptions (
                subscription_id, billing_cycle, amount, currency,
                start_date, end_date, status, payment_method, transaction_id,
                auto_renew, environment, created_at
            ) VALUES (
                ?, ?, 0.00, 'CAD',
                ?, ?, 'active', 'free_key', ?,
                0, ?, NOW()
            )
        ");
        $stmt->execute([
            $newSubscriptionId,
            $billingCycle,
            $startDate,
            $endDate,
            $key,
            current_environment()
        ]);

        // Update the key with the new subscription_id and device_id
        $stmt = $pdo->prepare("
            UPDATE premium_subscription_keys
            SET device_id = ?,
                subscription_id = ?
            WHERE subscription_key = ?
        ");
        $stmt->execute([$device_id, $newSubscriptionId, $key]);

        $pdo->commit();

        error_log("Recreated missing subscription for key $key: new subscription_id = $newSubscriptionId");

        return [
            'success' => true,
            'type' => 'premium',
            'status' => 'active',
            'message' => 'License activated successfully!',
            'subscription_id' => $newSubscriptionId,
            'end_date' => $endDate,
            'duration_months' => $duration_months,
            'needs_email' => license_key_needs_email($key)
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error recreating subscription for key $key: " . $e->getMessage());
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Error activating license key. Please try again.'
        ];
    }
}

/**
 * Validate a license key against a device.
 * Checks that the key exists, is redeemed, matches the device, and the subscription is active.
 *
 * @param string $key The premium subscription key to validate
 * @param string $device_id The hashed machine identifier to check against
 * @return array Response array with validation result
 */
function validate_license($key, $device_id) {
    global $pdo;

    if ($pdo === null) {
        error_log("License validation failed: database connection unavailable");
        return [
            'success' => false,
            'status' => 'db_error',
            'message' => 'Service temporarily unavailable.'
        ];
    }

    try {
        // Look up the key
        $stmt = $pdo->prepare("
            SELECT subscription_key, device_id, subscription_id, redeemed_at
            FROM premium_subscription_keys
            WHERE subscription_key = ?
        ");
        $stmt->execute([$key]);
        $premium_key = $stmt->fetch(PDO::FETCH_ASSOC);

        // Key not found or not yet redeemed
        if (!$premium_key || $premium_key['redeemed_at'] === null) {
            return [
                'success' => false,
                'status' => 'invalid_key',
                'message' => 'License key is not valid.'
            ];
        }

        // Check device_id matches
        if ($premium_key['device_id'] !== $device_id) {
            return [
                'success' => false,
                'status' => 'wrong_device',
                'message' => 'This license key is active on a different device.'
            ];
        }

        // Look up the linked subscription
        $stmt = $pdo->prepare("
            SELECT subscription_id, status, end_date, environment, payment_method
            FROM premium_subscriptions
            WHERE subscription_id = ?
        ");
        $stmt->execute([$premium_key['subscription_id']]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription || !_move_subscription_to_current_environment($subscription)) {
            return [
                'success' => false,
                'status' => 'invalid_key',
                'message' => 'License key is not valid.'
            ];
        }

        // Check if subscription has expired
        $now = new DateTime();
        $end_date = new DateTime($subscription['end_date']);

        if ($subscription['status'] === 'expired' || $end_date <= $now) {
            // Mark as expired if not already
            if ($subscription['status'] !== 'expired') {
                $stmt = $pdo->prepare("UPDATE premium_subscriptions SET status = 'expired' WHERE subscription_id = ?");
                $stmt->execute([$premium_key['subscription_id']]);
            }
            return [
                'success' => false,
                'status' => 'expired',
                'message' => 'Your premium subscription has expired.'
            ];
        }

        // Key is valid, device matches, subscription active
        return [
            'success' => true,
            'status' => 'valid',
            'message' => 'License is valid.'
        ];

    } catch (PDOException $e) {
        error_log("License validation error: " . $e->getMessage());
        return [
            'success' => false,
            'status' => 'error',
            'message' => 'Error validating license. Please try again.'
        ];
    }
}
