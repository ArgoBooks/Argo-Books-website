<?php
/**
 * Exchange Rates Proxy Endpoint
 *
 * GET /api/exchange-rates - USD rates for one date.
 *
 * Every date, today included, is fetched once and then stored in exchange_rates for good,
 * so each date has one set of rates. Sources and limits are in api/_exchange_rate_sources.php.
 *
 * Query parameters:
 *   - date: Optional date (YYYY-MM-DD). If omitted, today.
 */

require_once __DIR__ . '/portal/portal-helper.php';

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['GET']);

// Exchange rates are a free feature available to all users; no license key required.
// Rate limiting uses device ID if provided, otherwise falls back to IP address.
$deviceId = authenticate_device_request();
if ($deviceId) {
    $rateLimitId = 'dev_' . substr($deviceId, 0, 16);
} else {
    $rateLimitId = 'ip_' . substr(hash('sha256', get_client_ip()), 0, 16);
}
// Shares the 'rates_' bucket with the batch endpoint; kept generous so the per-date fallback never
// trips it during a legitimate import (the app does not fan out on a rate limit).
if (rate_limit_hit('exchange_rates', $rateLimitId, 'rates')) {
    send_rate_limited_response('exchange_rates');
}

// Parse date parameter
$date = $_GET['date'] ?? '';
$isLatest = empty($date) || $date === date('Y-m-d');

if (!empty($date) && !$isLatest) {
    // Validate date format
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        send_error_response(400, 'Invalid date format. Use YYYY-MM-DD.', 'INVALID_DATE');
    }
    // Don't allow future dates
    if ($parsed > new DateTime()) {
        send_error_response(400, 'Date cannot be in the future.', 'INVALID_DATE');
    }
}

$lookupDate = $isLatest ? date('Y-m-d') : $date;

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/_exchange_rate_sources.php';
if (!$pdo) {
    send_error_response(500, 'Database connection failed.', 'DB_ERROR');
}

$stmt = $pdo->prepare("SELECT rates FROM exchange_rates WHERE rate_date = ?");
$stmt->execute([$lookupDate]);
$cached = json_decode((string) $stmt->fetchColumn(), true);
if (is_array($cached) && $cached) {
    send_json_response(200, [
        'success' => true,
        'base' => 'USD',
        'date' => $lookupDate,
        'rates' => $cached,
        'cached' => true,
        'timestamp' => date('c'),
    ]);
}

$rates = exchange_rates_fetch_and_store($pdo, [$lookupDate], date('Y-m-d'))[$lookupDate] ?? null;
if ($rates === null) {
    send_error_response(502, 'Exchange rates for this date are not available right now.', 'UPSTREAM_ERROR');
}

send_json_response(200, [
    'success' => true,
    'base' => 'USD',
    'date' => $lookupDate,
    'rates' => $rates,
    'cached' => false,
    'timestamp' => date('c'),
]);
