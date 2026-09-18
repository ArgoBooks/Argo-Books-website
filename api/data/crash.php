<?php
session_start();

// Receives crash reports from the desktop app's CrashReporter. Mirrors the
// security model of upload.php (device/license auth, per-tier rate limit, JSON
// validation, file storage) but is intentionally self-contained so it can never
// affect the working telemetry upload endpoint.

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../portal/portal-helper.php';      // authenticate_device_request / authenticate_license_request
require_once __DIR__ . '/../../track_referral_event.php';   // lookup_country_for_ip()

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

define('CRASH_DIR', __DIR__ . '/../../admin/data-logs/crashes');
define('CRASH_MAX_SIZE_PREMIUM', 1 * 1024 * 1024);   // 1MB
define('CRASH_MAX_SIZE_FREE', 256 * 1024);           // 256KB
define('CRASH_ALLOWED_MIME', ['application/json', 'text/plain']);

/**
 * Atomic check-and-bump on a single rate-limit bucket, held under an exclusive
 * lock so two concurrent requests can't both pass a nearly-full bucket.
 */
function crashCheckBucket(string $bucketKey, int $max, int $windowSeconds): bool
{
    $rate_file = sys_get_temp_dir() . '/crash_rate_' . hash('sha256', $bucketKey);
    $handle = fopen($rate_file, 'c+');
    if (!$handle) {
        return true; // fail open on local I/O trouble
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return true;
    }
    try {
        $content = stream_get_contents($handle);
        $hits = json_decode($content ?: '[]', true) ?: [];
        $now = time();
        $hits = array_values(array_filter($hits, fn ($t) => ($now - $t) < $windowSeconds));
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($hits));
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function crashCheckRateLimit(string $authId, string $limitName, ?string $ipLimitName = null): bool
{
    $ip = get_client_ip();
    if (!crashCheckBucket($authId . '|' . $ip, rate_limit_max($limitName), rate_limit_window($limitName))) {
        return false;
    }
    if ($ipLimitName !== null
        && !crashCheckBucket('ip:' . $ip, rate_limit_max($ipLimitName), rate_limit_window($ipLimitName))) {
        return false;
    }
    return true;
}

/** Authenticate as Premium (license key) or Free (device ID). */
function crashAuthenticate(): ?array
{
    $license = authenticate_license_request();
    if ($license !== null) {
        return ['tier' => 'premium', 'authId' => 'subscription:' . ($license['subscription_id'] ?? 'unknown')];
    }
    $deviceHash = authenticate_device_request();
    if ($deviceHash !== null) {
        return ['tier' => 'free', 'authId' => 'device:' . $deviceHash];
    }
    return null;
}

function crashLog(string $event, string $details = ''): void
{
    error_log(json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => get_client_ip(),
        'event' => 'crash_' . $event,
        'details' => $details,
    ]));
}

/** Reject non-JSON or content carrying obvious script-injection markers. */
function crashValidateJson(string $content): bool
{
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    $dangerous = ['/<script[^>]*>/i', '/<iframe[^>]*>/i', '/javascript:/i', '/<\?php/i'];
    foreach ($dangerous as $pattern) {
        if (preg_match($pattern, $content)) {
            return false;
        }
    }
    return true;
}

/**
 * Keep only the fields we expect on a crash report, with hard length caps so a
 * malformed or oversized field can't bloat storage. Unknown fields are dropped.
 */
function crashSanitizeReport(array $r): array
{
    $str = fn ($v, int $max) => is_scalar($v) ? mb_substr((string) $v, 0, $max) : null;
    $breadcrumbs = [];
    if (isset($r['breadcrumbs']) && is_array($r['breadcrumbs'])) {
        foreach (array_slice($r['breadcrumbs'], -25) as $b) {
            $breadcrumbs[] = is_scalar($b) ? mb_substr((string) $b, 0, 500) : null;
        }
        $breadcrumbs = array_values(array_filter($breadcrumbs, fn ($b) => $b !== null));
    }
    return array_filter([
        'dataId'        => $str($r['dataId'] ?? null, 64),
        'timestamp'     => $str($r['timestamp'] ?? null, 40),
        'handler'       => $str($r['handler'] ?? null, 40),
        'exceptionType' => $str($r['exceptionType'] ?? null, 200),
        'message'       => $str($r['message'] ?? null, 2000),
        'source'        => $str($r['source'] ?? null, 300),
        'stackTrace'    => $str($r['stackTrace'] ?? null, 8000),
        'innerException' => $str($r['innerException'] ?? null, 2000),
        'osVersion'     => $str($r['osVersion'] ?? null, 120),
        'appVersion'    => $str($r['appVersion'] ?? null, 40),
        'platform'      => $str($r['platform'] ?? null, 40),
        'breadcrumbs'   => $breadcrumbs ?: null,
    ], fn ($v) => $v !== null);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Only POST method allowed']);
        crashLog('invalid_method', $_SERVER['REQUEST_METHOD']);
        exit;
    }

    $auth = crashAuthenticate();
    if ($auth === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        crashLog('invalid_auth');
        exit;
    }

    $tier = $auth['tier'];
    $authId = $auth['authId'];

    $maxSize = $tier === 'premium' ? CRASH_MAX_SIZE_PREMIUM : CRASH_MAX_SIZE_FREE;
    $limitName = $tier === 'premium' ? 'crash_upload_premium' : 'crash_upload_free';
    $ipLimitName = $tier === 'free' ? 'crash_upload_free_ip' : null;

    if (!crashCheckRateLimit($authId, $limitName, $ipLimitName)) {
        http_response_code(429);
        header('Retry-After: ' . rate_limit_window($limitName));
        echo json_encode(['error' => 'Rate limit exceeded', 'errorCode' => 'RATE_LIMITED']);
        crashLog('rate_limit_exceeded', $tier);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload error', 'code' => $_FILES['file']['error'] ?? 'no_file']);
        crashLog('upload_error');
        exit;
    }

    $uploaded = $_FILES['file'];
    if ($uploaded['size'] === 0 || $uploaded['size'] > $maxSize) {
        http_response_code(413);
        echo json_encode(['error' => 'Invalid file size', 'max_size' => $maxSize]);
        crashLog('bad_size', $uploaded['size'] . ' tier=' . $tier);
        exit;
    }

    // Sniff the MIME type when fileinfo is available. If the extension is
    // missing, finfo_open() returns false; skip the sniff in that case and rely
    // on the JSON content validation below, rather than emit warnings.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mime = finfo_file($finfo, $uploaded['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, CRASH_ALLOWED_MIME, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type', 'detected' => $mime]);
            crashLog('invalid_mime', (string) $mime);
            exit;
        }
    }

    $content = file_get_contents($uploaded['tmp_name']);
    if ($content === false || !crashValidateJson($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or malicious content']);
        crashLog('invalid_content');
        exit;
    }

    $payload = json_decode($content, true);
    if (!is_array($payload) || !isset($payload['crashes']) || !is_array($payload['crashes'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload structure']);
        crashLog('invalid_structure');
        exit;
    }

    // Rebuild from an explicit allowlist so nothing unexpected reaches disk.
    $reports = [];
    foreach (array_slice($payload['crashes'], 0, 50) as $report) {
        if (is_array($report)) {
            $reports[] = crashSanitizeReport($report);
        }
    }
    if (count($reports) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid crash reports']);
        crashLog('no_reports');
        exit;
    }

    $appVersion = isset($payload['appVersion']) && is_scalar($payload['appVersion'])
        ? mb_substr((string) $payload['appVersion'], 0, 40) : null;
    $platform = isset($payload['platform']) && is_scalar($payload['platform'])
        ? mb_substr((string) $payload['platform'], 0, 40) : null;

    // Best-effort country from the request IP (same source the funnel/telemetry use).
    $country = null;
    if (function_exists('lookup_country_for_ip')) {
        try {
            $country = lookup_country_for_ip(get_client_ip());
        } catch (Throwable $e) {
            $country = null;
        }
    }

    $stored = [
        'tier' => $tier,
        'authId' => $authId,
        'appVersion' => $appVersion,
        'platform' => $platform,
        'countryCode' => $country,
        'receivedAt' => date('Y-m-d H:i:s'),
        'crashes' => $reports,
    ];

    if (!is_dir(CRASH_DIR)) {
        if (!mkdir(CRASH_DIR, 0755, true) && !is_dir(CRASH_DIR)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create crash directory']);
            crashLog('mkdir_failure');
            exit;
        }
        file_put_contents(CRASH_DIR . '/.htaccess', "Order deny,allow\nDeny from all\n");
    }

    $timestamp = date('Ymd_His');
    $suffix = bin2hex(random_bytes(4));
    $filename = CRASH_DIR . "/argo_crash_{$tier}_{$timestamp}_{$suffix}.json";
    $counter = 1;
    $base = $filename;
    while (file_exists($filename)) {
        $filename = str_replace('.json', "_{$counter}.json", $base);
        if (++$counter > 1000) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to generate unique filename']);
            crashLog('filename_failure');
            exit;
        }
    }

    $json = json_encode($stored);
    if ($json === false || file_put_contents($filename, $json, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save crash report']);
        crashLog('write_failure');
        exit;
    }
    chmod($filename, 0644);

    echo json_encode([
        'status' => 'success',
        'file' => basename($filename),
        'received' => count($reports),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    crashLog('exception', $e->getMessage());
    error_log('Crash endpoint error: ' . $e->getMessage());
}
