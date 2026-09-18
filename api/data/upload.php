<?php
session_start();

// Load environment variables from .env file
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../portal/portal-helper.php';
require_once __DIR__ . '/telemetry_filter.php';
// Provides lookup_country_for_ip(): DB-cached + ipinfo.io fallback.
require_once __DIR__ . '/../../track_referral_event.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// Configuration
define('MAX_FILE_SIZE_PREMIUM', 10 * 1024 * 1024); // 10MB max for Premium uploads
define('MAX_FILE_SIZE_FREE', 256 * 1024);          // 256KB max for free-tier uploads
define('ALLOWED_MIME_TYPES', ['application/json', 'text/plain']);
define('DATA_DIR', __DIR__ . '/../../admin/data-logs/telemetry');

define('MAX_FILENAME_LENGTH', 255);
// The founder's own installs (FOUNDER_AUTH_IDS in .env) upload on exactly the same
// path as everyone else. Their data is kept so it can be reviewed on the app-stats
// User Activity tab; every other read site skips it. See founder_identity.php.

/**
 * Atomic check-and-bump on a single rate-limit bucket. Held under an exclusive
 * file lock so concurrent requests cannot both see "under the limit" and both
 * succeed past it. Returns true if the bump was accepted, false if the bucket
 * is full.
 */
function checkBucket(string $bucketKey, int $max, int $windowSeconds): bool
{
    $rate_file = sys_get_temp_dir() . '/upload_rate_' . hash('sha256', $bucketKey);
    $handle = fopen($rate_file, 'c+');
    if (!$handle) {
        // Fail open if we can't access the temp file; surface as success so we
        // don't block uploads on local I/O issues.
        return true;
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return true;
    }

    try {
        $content = stream_get_contents($handle);
        $uploads = json_decode($content ?: '[]', true) ?: [];

        $current_time = time();
        $uploads = array_values(array_filter($uploads, function ($timestamp) use ($current_time, $windowSeconds) {
            return ($current_time - $timestamp) < $windowSeconds;
        }));

        if (count($uploads) >= $max) {
            return false;
        }

        $uploads[] = $current_time;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($uploads));
        fflush($handle);

        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Per-tier rate limit check. Free tier gets a per-(device,IP) cap plus a coarser
 * per-IP cap as defense against device-ID rotation. Premium tier is keyed only on
 * (subscription,IP) since license keys are server-verified and can't be rotated
 * the same way.
 */
function checkRateLimit(string $authIdentifier, string $limitName, ?string $ipLimitName = null): bool
{
    $ip = get_client_ip();

    if (!checkBucket($authIdentifier . '|' . $ip, rate_limit_max($limitName), rate_limit_window($limitName))) {
        return false;
    }
    if ($ipLimitName !== null
        && !checkBucket('ip:' . $ip, rate_limit_max($ipLimitName), rate_limit_window($ipLimitName))) {
        return false;
    }
    return true;
}

/**
 * Map a 2-letter ISO country code to a full country name. Falls back to the
 * code itself when not in the map. Mirrors the table used in
 * admin/referral-links/index.php so admin pages render consistent names.
 */
function telemetry_country_name_from_code(?string $code): ?string
{
    if (empty($code)) return null;
    static $map = [
        'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
        'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France', 'JP' => 'Japan',
        'CN' => 'China', 'IN' => 'India', 'BR' => 'Brazil', 'MX' => 'Mexico',
        'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'SE' => 'Sweden',
        'CH' => 'Switzerland', 'PL' => 'Poland', 'BE' => 'Belgium', 'NO' => 'Norway',
        'AT' => 'Austria', 'DK' => 'Denmark', 'FI' => 'Finland', 'IE' => 'Ireland',
        'NZ' => 'New Zealand', 'SG' => 'Singapore', 'HK' => 'Hong Kong', 'KR' => 'South Korea',
        'RU' => 'Russia', 'ZA' => 'South Africa', 'AR' => 'Argentina', 'CL' => 'Chile',
    ];
    return $map[strtoupper($code)] ?? $code;
}

/**
 * Backfill geoLocation when the desktop app couldn't resolve it client-side
 * (firewall, rate limiting, etc). Reuses the website's lookup_country_for_ip()
 * so a single shared IP lookup serves both the funnel tracker and telemetry.
 * Only fills missing fields, never overwrites whatever the client sent.
 */
function backfill_geolocation_from_request(array $payload): array
{
    $existing = $payload['geoLocation'] ?? null;
    $hasCountry = is_array($existing) && !empty($existing['country']) && $existing['country'] !== 'Unknown';
    if ($hasCountry) {
        return $payload;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $code = lookup_country_for_ip($ip);
    if (empty($code)) {
        return $payload;
    }

    $name = telemetry_country_name_from_code($code);
    $payload['geoLocation'] = [
        'country' => $name ?? $code,
        'countryCode' => strtoupper($code),
        'region' => $existing['region'] ?? '',
        'timezone' => $existing['timezone'] ?? '',
    ];
    return $payload;
}

/**
 * Authenticate a telemetry upload as either Premium (license key) or Free (device ID).
 * Returns ['tier' => 'premium'|'free', 'authId' => string] on success, null on failure.
 */
function authenticate_telemetry_request(): ?array
{
    $license = authenticate_license_request();
    if ($license !== null) {
        return [
            'tier' => 'premium',
            'authId' => 'subscription:' . ($license['subscription_id'] ?? 'unknown'),
        ];
    }

    $deviceHash = authenticate_device_request();
    if ($deviceHash !== null) {
        return [
            'tier' => 'free',
            'authId' => 'device:' . $deviceHash,
        ];
    }

    return null;
}

function validateJsonContent($content)
{
    // Attempt to decode the content to ensure it's valid JSON
    json_decode($content);

    // Check if JSON decoding resulted in an error
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Uploaded content not in correct JSON format: " . json_last_error_msg());
        return false;
    }

    // Check for potentially malicious content
    $dangerous_patterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload=/i',
        '/onerror=/i',
        '/eval\s*\(/i',
        '/document\.cookie/i',
        '/document\.write/i',
        '/window\.location/i',
        '/<\?php/i',
        '/<\%/i'
    ];

    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            error_log("Malicious content detected in upload: " . $pattern);
            return false;
        }
    }

    return true;
}

// Log security events
function logSecurityEvent($event, $details = '')
{
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'license_key_provided' => !empty($_SERVER['HTTP_X_LICENSE_KEY']),
        'event' => $event,
        'details' => $details
    ];

    error_log(json_encode($log_entry));
}

// Main upload handling
try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Only POST method allowed']);
        logSecurityEvent('invalid_method', $_SERVER['REQUEST_METHOD']);
        exit;
    }

    // Authenticate: try Premium (license key) first, then Free (device ID)
    $auth = authenticate_telemetry_request();
    if ($auth === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        logSecurityEvent('invalid_auth');
        exit;
    }

    $tier = $auth['tier'];
    $authId = $auth['authId'];

    $maxFileSize = $tier === 'premium' ? MAX_FILE_SIZE_PREMIUM : MAX_FILE_SIZE_FREE;
    $limitName = $tier === 'premium' ? 'telemetry_upload_premium' : 'telemetry_upload_free';
    // Free tier also gets a coarse per-IP cap so device-ID rotation can't bypass the per-device limit.
    $ipLimitName = $tier === 'free' ? 'telemetry_upload_free_ip' : null;

    // Rate limiting check (bucket keyed on tier-specific authId, with optional per-IP secondary)
    if (!checkRateLimit($authId, $limitName, $ipLimitName)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window($limitName));
        echo json_encode(['error' => 'Rate limit exceeded', 'errorCode' => 'RATE_LIMITED']);
        logSecurityEvent('rate_limit_exceeded', $tier);
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['file']['error'] ?? 'no_file';
        http_response_code(400);
        echo json_encode(['error' => 'Upload error', 'code' => $error_code]);
        logSecurityEvent('upload_error', $error_code);
        exit;
    }

    $uploaded_file = $_FILES['file'];

    // Validate file size (per-tier)
    if ($uploaded_file['size'] > $maxFileSize) {
        http_response_code(413);
        echo json_encode(['error' => 'File too large', 'max_size' => $maxFileSize]);
        logSecurityEvent('file_too_large', $uploaded_file['size'] . ' tier=' . $tier);
        exit;
    }

    if ($uploaded_file['size'] === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty file']);
        logSecurityEvent('empty_file');
        exit;
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $uploaded_file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_MIME_TYPES, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type', 'detected' => $mime_type]);
        logSecurityEvent('invalid_mime_type', $mime_type);
        exit;
    }

    // Read and validate file content
    $content = file_get_contents($uploaded_file['tmp_name']);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read uploaded file']);
        logSecurityEvent('read_failure');
        exit;
    }

    if (!validateJsonContent($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or malicious content']);
        logSecurityEvent('invalid_content');
        exit;
    }

    // Decode payload so we can tag it with tier/authId and (for free) apply allowlist
    $payload = json_decode($content, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload structure']);
        logSecurityEvent('invalid_payload_structure');
        exit;
    }

    // Rebuild payload from scratch using server-side allowlist (applied to both tiers
    // so the on-the-wire shape is identical regardless of subscription status).
    $payload = filter_telemetry_payload($payload);

    // When the desktop app's IP-geo lookup fails (firewalled, rate-limited), the
    // payload arrives without geoLocation and every event renders "Unknown" in
    // /admin/app-stats/. Resolve country from the request IP server-side so we
    // get a usable country for the vast majority of uploads.
    $payload = backfill_geolocation_from_request($payload);

    // Server-injected tier + authId always override anything in the uploaded payload
    unset($payload['tier'], $payload['authId']);
    $tagged = ['tier' => $tier, 'authId' => $authId] + $payload;
    $content = json_encode($tagged);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode payload']);
        logSecurityEvent('encode_failure');
        exit;
    }

    // Create secure directory if needed
    if (!is_dir(DATA_DIR)) {
        if (!mkdir(DATA_DIR, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create data directory']);
            logSecurityEvent('mkdir_failure');
            exit;
        }

        // Create .htaccess to prevent direct access
        $htaccess_content = "Order deny,allow\nDeny from all\n";
        file_put_contents(DATA_DIR . '/.htaccess', $htaccess_content);
    }

    // Generate secure filename. Prefix encodes tier so admin can filter at the directory level.
    $timestamp = date('Ymd_His');
    $random_suffix = bin2hex(random_bytes(4));
    $filename = DATA_DIR . "/argo_data_{$tier}_{$timestamp}_{$random_suffix}.json";

    // Ensure filename doesn't already exist
    $counter = 1;
    $base_filename = $filename;
    while (file_exists($filename)) {
        $filename = str_replace('.json', "_{$counter}.json", $base_filename);
        $counter++;

        if ($counter > 1000) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to generate unique filename']);
            logSecurityEvent('filename_generation_failure');
            exit;
        }
    }

    // Write file with secure permissions
    $bytes_written = file_put_contents($filename, $content, LOCK_EX);

    if ($bytes_written === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        logSecurityEvent('write_failure');
        exit;
    }

    // Set secure permissions
    chmod($filename, 0644);

    // Success response
    $response = [
        'status' => 'success',
        'file' => basename($filename),
        'bytes' => $bytes_written,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    logSecurityEvent('exception', $e->getMessage());
    error_log("Upload error: " . $e->getMessage());
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    logSecurityEvent('fatal_error', $e->getMessage());
    error_log("Upload fatal error: " . $e->getMessage());
}
