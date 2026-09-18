<?php
/**
 * Invoice Send Usage Tracking API
 * Tracks and enforces monthly invoice send limits for free-tier users.
 * Free users get 25 invoice sends per month; premium users are unlimited.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../portal/portal-helper.php';

set_portal_headers();
require_method(['POST']);

// Rate limit: 30 requests per 15 minutes per IP to prevent abuse
$ip = get_client_ip();
if (rate_limit_hit('invoice_usage', $ip)) {
    send_rate_limited_response('invoice_usage');
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    send_error_response(400, 'Invalid JSON input');
}

// Accept either license_key (premium) or device_id (free)
$license_key = trim($input['license_key'] ?? '');
$device_id = trim($input['device_id'] ?? '');

// Reject overly long identifiers
if (strlen($license_key) > 100 || strlen($device_id) > 200) {
    send_error_response(400, 'Invalid identifier length.', 'INVALID_INPUT');
}

if (empty($license_key) && empty($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Either license_key or device_id is required']);
    exit();
}

if (!isset($input['action']) || !in_array($input['action'], ['check', 'increment'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid action (check or increment) is required']);
    exit();
}

$action = $input['action'];

// Load database connection and pricing config
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/pricing.php';

/**
 * Determine tier and validate identity.
 * Premium users (license key) get unlimited sends.
 * Free users (device ID) get the configured monthly limit.
 */
function validateAndGetTier($pdo, $license_key, $device_id) {
    $config = get_pricing_config();
    $free_limit = $config['free_invoice_monthly_limit'];

    if (!empty($license_key)) {
        // Check if it's a Premium key (starts with PREM-)
        if (strpos($license_key, 'PREM-') === 0) {
            // Look up the key and verify it has been redeemed
            $stmt = $pdo->prepare("
                SELECT subscription_key, subscription_id, redeemed_at
                FROM premium_subscription_keys
                WHERE subscription_key = ?
            ");
            $stmt->execute([$license_key]);
            $premiumKey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($premiumKey && $premiumKey['redeemed_at'] !== null) {
                // Verify the linked subscription is active and not expired
                $stmt = $pdo->prepare("
                    SELECT status, end_date
                    FROM premium_subscriptions
                    WHERE subscription_id = ?
                    AND environment = ?
                    AND status IN ('active', 'cancelled')
                    AND end_date > NOW()
                ");
                $stmt->execute([$premiumKey['subscription_id'], current_environment()]);
                if ($stmt->fetch()) {
                    return ['tier' => 'premium', 'limit' => PHP_INT_MAX, 'identifier' => $license_key];
                }
            }
        }

        // Check free license keys table
        $stmt = $pdo->prepare("SELECT id FROM license_keys WHERE license_key = ?");
        $stmt->execute([$license_key]);
        if ($stmt->fetch()) {
            return ['tier' => 'free', 'limit' => $free_limit, 'identifier' => $license_key];
        }
    }

    if (!empty($device_id)) {
        // Free tier via device ID
        $identifier = 'device_' . hash('sha256', $device_id);
        return ['tier' => 'free', 'limit' => $free_limit, 'identifier' => $identifier];
    }

    return null;
}

/**
 * Get or create usage record for current month.
 */
function getOrCreateUsageRecord($pdo, $identifier, $monthly_limit) {
    $usage_month = date('Y-m-01');
    $environment = current_environment();

    $stmt = $pdo->prepare("
        SELECT id, send_count, monthly_limit
        FROM invoice_send_usage
        WHERE license_key = ? AND usage_month = ? AND environment = ?
    ");
    $stmt->execute([$identifier, $usage_month, $environment]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        return $record;
    }

    // environment has to be supplied explicitly, not left to the column default:
    // it is part of the unique key, so the ON DUPLICATE KEY clause only matches
    // the right row when all three key columns are in the INSERT.
    $stmt = $pdo->prepare("
        INSERT INTO invoice_send_usage (license_key, usage_month, send_count, monthly_limit, environment)
        VALUES (?, ?, 0, ?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");
    $stmt->execute([$identifier, $usage_month, $monthly_limit, $environment]);

    // Re-select to get the actual row (handles concurrent insert race)
    $stmt = $pdo->prepare("
        SELECT id, send_count, monthly_limit
        FROM invoice_send_usage
        WHERE license_key = ? AND usage_month = ? AND environment = ?
    ");
    $stmt->execute([$identifier, $usage_month, $environment]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record ?: [
        'id' => $pdo->lastInsertId(),
        'send_count' => 0,
        'monthly_limit' => $monthly_limit
    ];
}

/**
 * Build response array.
 */
function buildResponse($send_count, $monthly_limit, $tier, $can_send = null) {
    $is_premium = $tier === 'premium';
    $remaining = $is_premium ? PHP_INT_MAX : max(0, $monthly_limit - $send_count);
    if ($can_send === null) {
        $can_send = $is_premium || $remaining > 0;
    }

    $usage_month = date('Y-m-01');
    $resets_at = date('Y-m-01', strtotime('first day of next month'));

    return [
        'success' => true,
        'can_send' => $can_send,
        'send_count' => (int)$send_count,
        'monthly_limit' => $is_premium ? -1 : (int)$monthly_limit,
        'remaining' => $is_premium ? -1 : (int)$remaining,
        'tier' => $tier,
        'usage_month' => $usage_month,
        'resets_at' => $resets_at
    ];
}

try {
    if ($pdo === null) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Service temporarily unavailable']);
        exit();
    }

    $tierInfo = validateAndGetTier($pdo, $license_key, $device_id);

    if (!$tierInfo) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired credentials']);
        exit();
    }

    $tier = $tierInfo['tier'];
    $monthly_limit = $tierInfo['limit'];
    $identifier = $tierInfo['identifier'];

    // Premium users are unlimited; skip DB tracking
    if ($tier === 'premium') {
        echo json_encode(buildResponse(0, $monthly_limit, $tier));
        exit();
    }

    // Free tier: track usage
    $usage = getOrCreateUsageRecord($pdo, $identifier, $monthly_limit);
    $send_count = $usage['send_count'];

    if ($action === 'check') {
        echo json_encode(buildResponse($send_count, $monthly_limit, $tier));
        exit();
    }

    if ($action === 'increment') {
        if ($send_count >= $monthly_limit) {
            $response = buildResponse($send_count, $monthly_limit, $tier, false);
            $response['success'] = false;
            $response['error'] = 'Monthly invoice send limit reached';
            http_response_code(429);
            echo json_encode($response);
            exit();
        }

        // Atomic conditional update to prevent concurrent requests exceeding the limit
        $usage_month = date('Y-m-01');
        $stmt = $pdo->prepare("
            UPDATE invoice_send_usage
            SET send_count = send_count + 1
            WHERE license_key = ? AND usage_month = ? AND environment = ? AND send_count < ?
        ");
        $stmt->execute([$identifier, $usage_month, current_environment(), $monthly_limit]);

        if ($stmt->rowCount() === 0) {
            // Another request incremented past the limit concurrently
            $response = buildResponse($send_count, $monthly_limit, $tier, false);
            $response['success'] = false;
            $response['error'] = 'Monthly invoice send limit reached';
            http_response_code(429);
            echo json_encode($response);
            exit();
        }

        $new_send_count = $send_count + 1;
        echo json_encode(buildResponse($new_send_count, $monthly_limit, $tier));
        exit();
    }

} catch (Throwable $e) {
    error_log("Invoice send usage API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}
