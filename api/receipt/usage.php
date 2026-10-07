<?php
/**
 * Receipt Scan Usage Tracking API
 * Tracks and enforces monthly scan limits.
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

// Rate limit per IP, as on api/invoice/usage.php and api/ai-import/usage.php.
$ip = get_client_ip();
if (rate_limit_hit('receipt_usage', $ip)) {
    send_rate_limited_response('receipt_usage');
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

// Load database connection and pricing config
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/pricing.php';
require_once __DIR__ . '/scan_quota.php';

/**
 * Determine tier and validate identity.
 * Premium users (license key) get 500 scans/month.
 * Free users (device ID) get the configured free monthly limit.
 *
 * Delegates to receipt_scan_quota_identity() so this endpoint and completions.php,
 * which now spends the quota, always key the same row. This one receives the raw
 * device id and hashes it; the shared helper takes the hash.
 */
function validateAndGetTier($pdo, $license_key, $device_id) {
    return receipt_scan_quota_identity(
        $pdo,
        (string)$license_key,
        $device_id !== '' && $device_id !== null ? hash('sha256', $device_id) : null
    );
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
        FROM receipt_scan_usage
        WHERE license_key = ? AND usage_month = ? AND environment = ?
    ");
    $stmt->execute([$license_key, $usage_month, $environment]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        return $record;
    }

    // Create new record for this month. INSERT IGNORE because the free-tier row
    // key is shared with completions.php, which may create it concurrently.
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO receipt_scan_usage (license_key, usage_month, scan_count, monthly_limit, environment)
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
 * @param int $scan_count
 * @param int $monthly_limit
 * @param string $tier
 * @param bool $can_scan
 * @return array
 */
function buildResponse($scan_count, $monthly_limit, $tier, $can_scan = null) {
    $remaining = max(0, $monthly_limit - $scan_count);
    if ($can_scan === null) {
        $can_scan = $remaining > 0;
    }

    $usage_month = date('Y-m-01');
    $resets_at = date('Y-m-01', strtotime('first day of next month'));

    return [
        'success' => true,
        'can_scan' => $can_scan,
        'scan_count' => (int)$scan_count,
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
    $monthly_limit = $tierInfo['limit'];
    $identifier = $tierInfo['identifier'];

    $usage = getOrCreateUsageRecord($pdo, $identifier, $monthly_limit);
    $scan_count = $usage['scan_count'];
    // Use the limit from the tier info (in case it changed)
    $monthly_limit = $tierInfo['limit'];

    if ($action === 'check') {
        // Just return current status
        echo json_encode(buildResponse($scan_count, $monthly_limit, $tier));
        exit();
    }

    if ($action === 'increment') {
        // Counted by the request that calls Gemini instead, which is what makes the limit hold when a client under-reports or skips this call.
        echo json_encode(buildResponse($scan_count, $monthly_limit, $tier));
        exit();
    }

} catch (PDOException $e) {
    error_log("Receipt usage API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}
