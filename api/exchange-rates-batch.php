<?php
/**
 * Batch Exchange Rates Endpoint
 *
 * POST /api/exchange-rates-batch - Fetch exchange rates for multiple dates at once.
 *
 * Request body (JSON):
 *   { "dates": ["2025-01-15", "2025-03-20", ...] }
 *
 * Returns stored rates, and fetches and stores the dates not stored yet. Every date, today
 * included, is fetched once and kept for good. Sources and limits are in
 * api/_exchange_rate_sources.php.
 */

require_once __DIR__ . '/portal/portal-helper.php';

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

set_portal_headers();
require_method(['POST']);

// Batch requests may fetch many dates from OER, allow up to 2 minutes
set_time_limit(120);

// Rate limiting (counts as 1 request regardless of how many dates)
$deviceId = authenticate_device_request();
if ($deviceId) {
    $rateLimitId = 'dev_' . substr($deviceId, 0, 16);
} else {
    $rateLimitId = 'ip_' . substr(hash('sha256', get_client_ip()), 0, 16);
}
// Generous limit: a normal import is a single batch request, so 1000 per 15 minutes is far beyond
// any legitimate use while still guarding the OpenExchangeRates quota against a runaway client.
if (rate_limit_hit('exchange_rates', $rateLimitId, 'rates')) {
    send_rate_limited_response('exchange_rates');
}

// Parse request body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['dates']) || !is_array($body['dates'])) {
    send_error_response(400, 'Request body must contain a "dates" array.', 'INVALID_REQUEST');
}

$requestedDates = $body['dates'];
if (count($requestedDates) > 500) {
    send_error_response(400, 'Maximum 500 dates per request.', 'TOO_MANY_DATES');
}

// Validate all dates
$today = date('Y-m-d');
$validDates = [];
foreach ($requestedDates as $date) {
    if (!is_string($date)) continue;
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) continue;
    if ($parsed > new DateTime()) continue; // skip future dates
    $validDates[] = $date;
}
$validDates = array_values(array_unique($validDates));

if (empty($validDates)) {
    send_json_response(200, [
        'success' => true,
        'base' => 'USD',
        'results' => new stdClass(),
        'failed' => [],
        'timestamp' => date('c'),
    ]);
}

// Connect to database
require_once __DIR__ . '/../db_connect.php';
if (!$pdo) {
    send_error_response(500, 'Database connection failed.', 'DB_ERROR');
}

require_once __DIR__ . '/_exchange_rate_sources.php';

// Fetch all stored rates from MySQL in one query
$placeholders = implode(',', array_fill(0, count($validDates), '?'));
$stmt = $pdo->prepare("SELECT rate_date, rates FROM exchange_rates WHERE rate_date IN ($placeholders)");
$stmt->execute($validDates);

$results = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rates = json_decode($row['rates'], true);
    if (is_array($rates) && $rates) {
        $results[$row['rate_date']] = $rates;
    }
}
$datesToFetch = array_values(array_filter($validDates, fn($d) => !isset($results[$d])));

// Dates are fetched eight at a time, so 150 keeps one request well inside the time limit, and the app fetches any left over one date at a time.
$maxFetches = 150;
$fetched = exchange_rates_fetch_and_store($pdo, array_slice($datesToFetch, 0, $maxFetches), $today);
$results += $fetched;
$failed = array_values(array_filter($datesToFetch, fn($d) => !isset($results[$d])));

send_json_response(200, [
    'success' => true,
    'base' => 'USD',
    'results' => empty($results) ? new stdClass() : $results,
    'failed' => $failed,
    'timestamp' => date('c'),
]);
