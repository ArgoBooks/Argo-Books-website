<?php
/**
 * AI Import Usage Tracking API
 * Tracks and enforces monthly import limits per user, per import type.
 * Spreadsheet imports and bank statement imports have separate monthly counters
 * and separate limits (see config/pricing.php). Pass `type` = "spreadsheet" (default)
 * or "bank".
 */

require_once __DIR__ . '/../portal/portal-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Rate limit per IP, as on api/invoice/usage.php.
$ip = get_client_ip();
if (rate_limit_hit('ai_import_usage', $ip)) {
    send_rate_limited_response('ai_import_usage');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit();
}

// Accept either license_key (premium) or device_id (free)
$license_key = trim($input['license_key'] ?? '');
$device_id = trim($input['device_id'] ?? '');

// Reject overly long identifiers
if (strlen($license_key) > 100 || strlen($device_id) > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid identifier length.']);
    exit();
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

// Import type. Bank and spreadsheet imports track separate monthly counters.
$type = $input['type'] ?? 'spreadsheet';
if (!in_array($type, ['spreadsheet', 'bank'], true)) {
    $type = 'spreadsheet';
}

// Load database connection and pricing config
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/pricing.php';

/**
 * Determine tier and validate identity.
 * AI import is available to all users (free and premium) with 100 imports/month.
 * Premium users authenticate with a license key; free users authenticate with a device ID.
 * @param PDO $pdo
 * @param string $license_key
 * @param string $device_id
 * @return array|null Returns ['tier' => string, 'limit' => N, 'identifier' => string]
 *                    for valid identities, or null if invalid
 */
function validateAndGetTier($pdo, $license_key, $device_id) {
    $config = get_pricing_config();
    $limit = $config['ai_import_monthly_limit'];

    if (!empty($license_key)) {
        // Check if it's a Premium key (starts with PREM-)
        if (strpos($license_key, 'PREM-') === 0) {
            // Require the key to be redeemed AND linked to an active, unexpired
            // subscription before granting premium tier (mirrors receipt/invoice
            // usage). An unredeemed promo code must not count as premium.
            $stmt = $pdo->prepare("
                SELECT subscription_id, redeemed_at
                FROM premium_subscription_keys
                WHERE subscription_key = ?
            ");
            $stmt->execute([$license_key]);
            $premiumKey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($premiumKey && $premiumKey['redeemed_at'] !== null) {
                $stmt = $pdo->prepare("
                    SELECT id FROM premium_subscriptions
                    WHERE subscription_id = ?
                    AND environment = ?
                    AND status IN ('active', 'cancelled')
                    AND end_date > NOW()
                ");
                $stmt->execute([$premiumKey['subscription_id'], current_environment()]);
                if ($stmt->fetch()) {
                    return ['tier' => 'premium', 'limit' => $limit, 'identifier' => $license_key];
                }
            }

            // Fallback: subscription_id may have been used directly as the license key
            $stmt = $pdo->prepare("
                SELECT id FROM premium_subscriptions
                WHERE subscription_id = ?
                AND environment = ?
                AND status IN ('active', 'cancelled')
                AND end_date > NOW()
            ");
            $stmt->execute([$license_key, current_environment()]);
            if ($stmt->fetch()) {
                return ['tier' => 'premium', 'limit' => $limit, 'identifier' => $license_key];
            }

            return null;
        }

        // Check if it's a valid free license key
        $stmt = $pdo->prepare("SELECT id FROM license_keys WHERE license_key = ?");
        $stmt->execute([$license_key]);
        if ($stmt->fetch()) {
            return ['tier' => 'free', 'limit' => $limit, 'identifier' => $license_key];
        }
    }

    if (!empty($device_id)) {
        // Free tier via device ID
        $identifier = 'device_' . hash('sha256', $device_id);
        return ['tier' => 'free', 'limit' => $limit, 'identifier' => $identifier];
    }

    return null;
}

/**
 * Get or create usage record for current month
 * @param PDO $pdo
 * @param string $license_key
 * @param int $monthly_limit
 * @return array
 */
function getOrCreateUsageRecord($pdo, $license_key, $monthly_limit) {
    $usage_month = date('Y-m-01');
    $environment = current_environment();

    // Try to get existing record
    $stmt = $pdo->prepare("
        SELECT id, scan_count, monthly_limit
        FROM ai_import_usage
        WHERE license_key = ? AND usage_month = ? AND environment = ?
    ");
    $stmt->execute([$license_key, $usage_month, $environment]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        return $record;
    }

    // Create new record for this month. INSERT IGNORE so two concurrent
    // first-of-the-month requests don't collide on the unique key.
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO ai_import_usage (license_key, usage_month, scan_count, monthly_limit, environment)
        VALUES (?, ?, 0, ?, ?)
    ");
    $stmt->execute([$license_key, $usage_month, $monthly_limit, $environment]);

    return [
        'id' => $pdo->lastInsertId(),
        'scan_count' => 0,
        'monthly_limit' => $monthly_limit
    ];
}

/**
 * Build response array
 * @param int $import_count
 * @param int $monthly_limit
 * @param string $tier
 * @param bool $can_import
 * @return array
 */
function buildResponse($import_count, $monthly_limit, $tier, $can_import = null) {
    $remaining = max(0, $monthly_limit - $import_count);
    if ($can_import === null) {
        $can_import = $remaining > 0;
    }

    $usage_month = date('Y-m-01');
    $resets_at = date('Y-m-01', strtotime('first day of next month'));

    return [
        'success' => true,
        'can_import' => $can_import,
        'import_count' => (int)$import_count,
        'monthly_limit' => (int)$monthly_limit,
        'remaining' => (int)$remaining,
        'tier' => $tier,
        'usage_month' => $usage_month,
        'resets_at' => $resets_at
    ];
}

try {
    // Validate identity and get tier
    $tierInfo = validateAndGetTier($pdo, $license_key, $device_id);

    if (!$tierInfo) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired license key']);
        exit();
    }

    $tier = $tierInfo['tier'];
    $identifier = $tierInfo['identifier'];

    // Resolve the limit by tier + import type. Bank imports use a separate, prefixed
    // identifier so they get their own monthly counter row, distinct from spreadsheet imports.
    $config = get_pricing_config();
    if ($type === 'bank') {
        $identifier = 'bank:' . $identifier;
        $monthly_limit = $tier === 'premium'
            ? $config['premium_bank_import_monthly_limit']
            : $config['bank_import_monthly_limit'];
    } else {
        $monthly_limit = $tier === 'premium'
            ? $config['premium_ai_import_monthly_limit']
            : $config['ai_import_monthly_limit'];
    }

    $usage = getOrCreateUsageRecord($pdo, $identifier, $monthly_limit);
    $import_count = $usage['scan_count'];

    if ($action === 'check') {
        // Just return current status
        echo json_encode(buildResponse($import_count, $monthly_limit, $tier));
        exit();
    }

    if ($action === 'increment') {
        // Check if limit reached before incrementing
        if ($import_count >= $monthly_limit) {
            $response = buildResponse($import_count, $monthly_limit, $tier, false);
            $response['success'] = false;
            $response['error'] = 'Monthly import limit reached';
            http_response_code(429);
            echo json_encode($response);
            exit();
        }

        // Atomic conditional update so two concurrent requests can't both pass
        // the read-then-check above and increment past the cap.
        $usage_month = date('Y-m-01');
        $stmt = $pdo->prepare("
            UPDATE ai_import_usage
            SET scan_count = scan_count + 1
            WHERE license_key = ? AND usage_month = ? AND environment = ? AND scan_count < ?
        ");
        $stmt->execute([$identifier, $usage_month, current_environment(), $monthly_limit]);

        if ($stmt->rowCount() === 0) {
            $response = buildResponse($import_count, $monthly_limit, $tier, false);
            $response['success'] = false;
            $response['error'] = 'Monthly import limit reached';
            http_response_code(429);
            echo json_encode($response);
            exit();
        }

        // Return updated status
        $new_import_count = $import_count + 1;
        echo json_encode(buildResponse($new_import_count, $monthly_limit, $tier));
        exit();
    }

} catch (PDOException $e) {
    error_log("AI import usage API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}
