<?php
/**
 * Batch Exchange Rates Endpoint
 *
 * POST /api/exchange-rates-batch - Fetch exchange rates for multiple dates at once.
 *
 * Request body (JSON):
 *   { "dates": ["2025-01-15", "2025-03-20", ...] }
 *
 * Returns all rates from MySQL cache, fetching from OpenExchangeRates only for
 * dates not yet cached. Historical rates are stored permanently (they never change).
 * Today's rate is refreshed if older than 1 hour.
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

// Validate server configuration
$apiKey = $_ENV['OPENEXCHANGERATES_API_KEY'] ?? '';
if (empty($apiKey)) {
    send_error_response(500, 'Exchange rate service not configured on server.', 'CONFIG_ERROR');
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

// Fetch all cached rates from MySQL in one query
$placeholders = implode(',', array_fill(0, count($validDates), '?'));
$stmt = $pdo->prepare("SELECT rate_date, rates, fetched_at FROM exchange_rates WHERE rate_date IN ($placeholders)");
$stmt->execute($validDates);
$cachedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
$datesToFetch = [];
$oneHourAgo = date('Y-m-d H:i:s', time() - 3600);

foreach ($cachedRows as $row) {
    $dateStr = $row['rate_date'];
    $rates = json_decode($row['rates'], true);
    if ($rates === null) {
        $datesToFetch[] = $dateStr;
        continue;
    }
    // Today's rate: check freshness (1hr TTL)
    if ($dateStr === $today && $row['fetched_at'] < $oneHourAgo) {
        $datesToFetch[] = $dateStr;
        continue;
    }
    $results[$dateStr] = $rates;
}

// Find dates not in DB at all
$cachedDateSet = array_column($cachedRows, 'rate_date');
foreach ($validDates as $date) {
    if (!in_array($date, $cachedDateSet) && !isset($results[$date])) {
        $datesToFetch[] = $date;
    }
}
$datesToFetch = array_unique($datesToFetch);

// Cap OER calls per batch to protect API quota
$maxOerCalls = 30;
$failed = [];
if (count($datesToFetch) > $maxOerCalls) {
    // Fetch the first $maxOerCalls, mark the rest as failed
    $overflow = array_slice($datesToFetch, $maxOerCalls);
    $datesToFetch = array_slice($datesToFetch, 0, $maxOerCalls);
    $failed = $overflow;
}

// Fetch missing dates from OpenExchangeRates and store in MySQL
$insertStmt = $pdo->prepare(
    "INSERT INTO exchange_rates (rate_date, rates, fetched_at) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE rates = VALUES(rates), fetched_at = NOW()"
);

// Fetched concurrently, not one after another.
//
// This request is a batch only between the app and here; upstream still needs one
// call per date, and running them in series meant an import waited the sum of every
// round trip. Five cold dates was routinely fifteen seconds, with the desktop app
// showing nothing, which reads as a hang. Concurrently it costs about as long as the
// slowest single date.
//
// Windowed rather than all at once: $maxOerCalls is 30, and thirty simultaneous
// connections is the kind of thing an upstream rate limiter exists to stop.
$concurrency = 8;

foreach (array_chunk(array_values($datesToFetch), $concurrency) as $chunk) {
    $multi = curl_multi_init();
    $handles = [];

    foreach ($chunk as $date) {
        $url = $date === $today
            ? "https://openexchangerates.org/api/latest.json?app_id={$apiKey}&base=USD"
            : "https://openexchangerates.org/api/historical/{$date}.json?app_id={$apiKey}&base=USD";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        curl_multi_add_handle($multi, $ch);
        $handles[$date] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            // Blocks until something moves, rather than spinning on the CPU.
            // select() returns -1 when there is no descriptor to wait on, which
            // libcurl can do while resolving a name. It did not happen in testing
            // here (one already-resolved host), so this is a guard rather than a
            // fix: without it that case would busy-loop for the whole fetch.
            if (curl_multi_select($multi, 1.0) === -1) {
                usleep(1000);
            }
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $date => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($multi, $ch);
        // The old serial loop never closed its handle. Harmless when the script was
        // about to end anyway, not harmless once there are several per request.
        curl_close($ch);

        if ($response === false || $response === null || $httpCode !== 200) {
            $failed[] = $date;
            continue;
        }

        $data = json_decode($response, true);
        if ($data === null || !isset($data['rates'])) {
            $failed[] = $date;
            continue;
        }

        // Store in MySQL
        $insertStmt->execute([$date, json_encode($data['rates'])]);

        $results[$date] = $data['rates'];
    }

    curl_multi_close($multi);
}

send_json_response(200, [
    'success' => true,
    'base' => 'USD',
    'results' => empty($results) ? new stdClass() : $results,
    'failed' => array_values(array_unique($failed)),
    'timestamp' => date('c'),
]);
