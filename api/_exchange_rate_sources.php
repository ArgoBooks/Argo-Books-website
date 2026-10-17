<?php
// Where the server gets a day's USD exchange rates, shared by the two rate endpoints and the profit analyzer: Frankfurter's central-bank rates first, then Open Exchange Rates within the plan's allowance, then the free fawazahmed0 currency API for the dates it covers.

require_once __DIR__ . '/../shared/currencies.php';

const EXCHANGE_RATE_SOURCE_FRANKFURTER = 'frankfurter';
const EXCHANGE_RATE_SOURCE_OER = 'openexchangerates';
const EXCHANGE_RATE_SOURCE_FAWAZ = 'fawazahmed0';

/** Central banks publish nothing on weekends and holidays, so a date's rate is the latest one published, but nothing older than this. */
const EXCHANGE_RATE_MAX_CARRY_DAYS = 7;

/** Bulgarian lev per euro, fixed by law since 1999; Frankfurter stopped publishing the lev when Bulgaria adopted the euro. */
const EXCHANGE_RATE_BGN_PER_EUR = 1.95583;

/** Currencies the app supports that did not exist before these dates, so a day before then is complete without them. */
const EXCHANGE_RATE_CURRENCY_INTRODUCED = ['GHS' => '2007-07-03', 'BYN' => '2016-07-01'];

function exchange_rate_frankfurter_url(string $date): string
{
    return "https://api.frankfurter.dev/v2/rates?base=USD&date={$date}";
}

/** Whether a day's rates hold every currency the app supports that existed on that day, since a stored day is never fetched again. */
function exchange_rate_is_complete(array $rates, string $date): bool
{
    foreach (array_keys(argo_currencies_all()) as $code) {
        $introduced = EXCHANGE_RATE_CURRENCY_INTRODUCED[$code] ?? null;
        if ($code !== 'USD' && !isset($rates[$code]) && ($introduced === null || $date >= $introduced)) {
            return false;
        }
    }
    return true;
}

/** Rates from a Frankfurter v2 response body for $date, in Open Exchange Rates' shape, or null when they cannot stand as that day's rates. */
function exchange_rate_parse_frankfurter(?string $body, string $date, string $today): ?array
{
    $quotes = json_decode((string) $body, true);
    if (!is_array($quotes) || !$quotes) {
        return null;
    }
    $oldest = (new DateTimeImmutable($date))->modify('-' . EXCHANGE_RATE_MAX_CARRY_DAYS . ' days')->format('Y-m-d');
    $rates = ['USD' => 1.0];
    $publishedToday = 0;
    $supported = argo_currencies_all();
    foreach ($quotes as $quote) {
        if (!is_array($quote)) {
            continue;
        }
        $code = $quote['quote'] ?? null;
        $quoteDate = (string) ($quote['date'] ?? '');
        $rate = $quote['rate'] ?? null;
        if (!is_string($code) || !preg_match('/^[A-Z]{3}$/', $code) || !is_numeric($rate) || $rate <= 0
            || $quoteDate > $date || $quoteDate < $oldest) {
            continue;
        }
        $rates[$code] = (float) $rate;
        if ($quoteDate === $date && isset($supported[$code])) {
            $publishedToday++;
        }
    }
    // Until most central banks have published today, and all weekend, the latest rates are mostly an earlier day's, which must not be stored as today's.
    if (!isset($rates['EUR']) || ($date === $today && $publishedToday * 2 < count($supported) - 1)) {
        return null;
    }
    return exchange_rate_complete_or_null($rates, $date);
}

/** A day's rates with the lev filled in from the euro when missing, or null when a currency the app supports is still missing. */
function exchange_rate_complete_or_null(?array $rates, string $date): ?array
{
    if ($rates === null) {
        return null;
    }
    if (!isset($rates['BGN']) && isset($rates['EUR'])) {
        $rates['BGN'] = $rates['EUR'] * EXCHANGE_RATE_BGN_PER_EUR;
    }
    return exchange_rate_is_complete($rates, $date) ? $rates : null;
}

/** The first date the fawazahmed0 currency API publishes, checked against the API: earlier dates return 404. */
const EXCHANGE_RATE_FAWAZ_FIRST_DATE = '2024-03-02';

/** How many Open Exchange Rates requests the server allows itself in any 31 days, below the free plan's 1,000 a month so failed requests and other tools have room. */
function exchange_rate_oer_budget(): int
{
    $configured = $_ENV['OER_MONTHLY_BUDGET'] ?? getenv('OER_MONTHLY_BUDGET');
    return is_numeric($configured) ? max(0, (int) $configured) : 900;
}

/** Requests left in the budget; every stored Open Exchange Rates row was one request, and 31 days covers any billing month whatever day it resets. */
function exchange_rate_oer_calls_left(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM exchange_rates WHERE source = ? AND fetched_at > NOW() - INTERVAL 31 DAY'
    );
    $stmt->execute([EXCHANGE_RATE_SOURCE_OER]);
    return max(0, exchange_rate_oer_budget() - (int) $stmt->fetchColumn());
}

function exchange_rate_oer_url(string $date, string $today, string $apiKey): string
{
    return $date === $today
        ? "https://openexchangerates.org/api/latest.json?app_id={$apiKey}&base=USD"
        : "https://openexchangerates.org/api/historical/{$date}.json?app_id={$apiKey}&base=USD";
}

/** The fawazahmed0 file for a date, from jsDelivr first and from the project's own Cloudflare mirror second. */
function exchange_rate_fawaz_urls(string $date): array
{
    return [
        "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@{$date}/v1/currencies/usd.json",
        "https://{$date}.currency-api.pages.dev/v1/currencies/usd.json",
    ];
}

/** Rates from an Open Exchange Rates response body, or null when it holds none. */
function exchange_rate_parse_oer(?string $body): ?array
{
    $data = json_decode((string) $body, true);
    $rates = $data['rates'] ?? null;
    return is_array($rates) && $rates ? $rates : null;
}

/** Rates from a fawazahmed0 response body, in Open Exchange Rates' shape (upper-case codes, units per US dollar), or null when it holds none. */
function exchange_rate_parse_fawaz(?string $body, string $date): ?array
{
    $data = json_decode((string) $body, true);
    // A file for another day would store that day's rates under this date.
    if (!is_array($data) || ($data['date'] ?? null) !== $date || !is_array($data['usd'] ?? null)) {
        return null;
    }
    $rates = [];
    foreach ($data['usd'] as $code => $rate) {
        if (is_string($code) && preg_match('/^[a-z]{3}$/', $code) && is_numeric($rate) && $rate > 0) {
            $rates[strtoupper($code)] = (float) $rate;
        }
    }
    $rates['USD'] = 1.0;
    // The app reads EUR to decide whether a date is priced at all.
    return isset($rates['EUR']) ? $rates : null;
}

/** GETs each URL, eight at a time, and returns [HTTP status, body] under the same keys. */
function exchange_rate_http_get_many(array $urls): array
{
    $results = [];
    foreach (array_chunk($urls, 8, true) as $chunk) {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($chunk as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            // select() returns -1 when there is nothing to wait on yet, which libcurl can do while resolving a name.
            if ($running && curl_multi_select($multi, 1.0) === -1) {
                usleep(1000);
            }
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $results[$key] = [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), is_string($body) ? $body : null];
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);
    }
    return $results;
}

/** Fetches and stores USD rates for dates not yet stored, returning date => rates for each one it could price; $getMany replaces exchange_rate_http_get_many() in tests. */
function exchange_rates_fetch_and_store(PDO $pdo, array $dates, string $today, ?callable $getMany = null): array
{
    $getMany ??= 'exchange_rate_http_get_many';
    $dates = array_values(array_unique(array_filter($dates, fn($d) => is_string($d) && $d <= $today)));
    if (!$dates) {
        return [];
    }

    $fetched = []; // date => [rates, source]

    $urls = [];
    foreach ($dates as $date) {
        $urls[$date] = exchange_rate_frankfurter_url($date);
    }
    foreach ($getMany($urls) as $date => [$status, $body]) {
        $rates = $status === 200 ? exchange_rate_parse_frankfurter($body, (string) $date, $today) : null;
        if ($rates !== null) {
            $fetched[$date] = [$rates, EXCHANGE_RATE_SOURCE_FRANKFURTER];
        }
    }

    $apiKey = (string) ($_ENV['OPENEXCHANGERATES_API_KEY'] ?? getenv('OPENEXCHANGERATES_API_KEY') ?: '');
    $unpriced = array_values(array_filter($dates, fn($d) => !isset($fetched[$d])));
    $oerDates = $apiKey !== '' && $unpriced ? array_slice($unpriced, 0, exchange_rate_oer_calls_left($pdo)) : [];
    if ($oerDates) {
        $urls = [];
        foreach ($oerDates as $date) {
            $urls[$date] = exchange_rate_oer_url($date, $today, $apiKey);
        }
        foreach ($getMany($urls) as $date => [$status, $body]) {
            $rates = $status === 200 ? exchange_rate_complete_or_null(exchange_rate_parse_oer($body), (string) $date) : null;
            if ($rates !== null) {
                $fetched[$date] = [$rates, EXCHANGE_RATE_SOURCE_OER];
            } else {
                error_log("Open Exchange Rates returned HTTP $status for $date: " . substr((string) $body, 0, 200));
            }
        }
    }

    // Each mirror is asked only for what the one before it could not answer.
    $missing = array_values(array_filter($dates, fn($d) => !isset($fetched[$d]) && $d >= EXCHANGE_RATE_FAWAZ_FIRST_DATE));
    foreach ([0, 1] as $mirror) {
        if (!$missing) {
            break;
        }
        $urls = [];
        foreach ($missing as $date) {
            $urls[$date] = exchange_rate_fawaz_urls($date)[$mirror];
        }
        foreach ($getMany($urls) as $date => [$status, $body]) {
            $rates = $status === 200 ? exchange_rate_complete_or_null(exchange_rate_parse_fawaz($body, (string) $date), (string) $date) : null;
            if ($rates !== null) {
                $fetched[$date] = [$rates, EXCHANGE_RATE_SOURCE_FAWAZ];
            }
        }
        $missing = array_values(array_filter($missing, fn($d) => !isset($fetched[$d])));
    }

    // A date keeps the first rates stored for it, so every transaction on that date converts the same way whichever request priced it.
    $insert = $pdo->prepare('INSERT IGNORE INTO exchange_rates (rate_date, rates, source, fetched_at) VALUES (?, ?, ?, NOW())');
    $read = $pdo->prepare('SELECT rates FROM exchange_rates WHERE rate_date = ?');
    $result = [];
    foreach ($fetched as $date => [$rates, $source]) {
        $insert->execute([$date, json_encode($rates), $source]);
        if ($insert->rowCount() === 0) {
            $read->execute([$date]);
            $stored = json_decode((string) $read->fetchColumn(), true);
            $rates = is_array($stored) && $stored ? $stored : $rates;
        }
        $result[$date] = $rates;
    }
    return $result;
}
