<?php
// profit-analyzer/lib/import/currency.php
//
// Multi-currency support for the analyzer. The importer captures a per-row
// `originalCurrency` (an ISO code, or a symbol the AI lifted from the amount
// cell). This module:
//   1. picks the sheet's DOMINANT currency (the one most rows are in),
//   2. converts every monetary field on the money entities into it, using the
//      HISTORICAL USD-based rate for each row's own date, and
//   3. reports the dominant ISO code so meta/formatting can label it correctly.
//
// Rates come from the same `exchange_rates` table the desktop app's rate proxy
// (api/exchange-rates.php) fills, fetched on demand from OpenExchangeRates and
// cached permanently per date. Conversion is best-effort: if rates can't be
// obtained (no API key, network failure), it degrades to no conversion, so a
// single-currency sheet is still labeled right and a mixed sheet is no worse
// than before this feature existed.

// Cap on live (uncached) historical-rate fetches per analysis, so a file spanning hundreds of distinct dates can't fan out into hundreds of API calls.
require_once __DIR__ . '/../../../shared/currencies.php';

const PA_FX_MAX_LIVE_FETCHES = 40;

// --- CurrencyInfo: symbol and code ---
// Reads shared/currencies.php, the site's single source of truth, which mirrors the desktop app.

/** code => [symbol, name, decimals]. Positional, matching CurrencyInfo.All. */
function pa_currency_all(): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (argo_currencies_all() as $code => $c) {
            $all[$code] = [$c['symbol'], $c['name'], $c['decimals']];
        }
    }
    return $all;
}

/** Common currencies shown first; used to order ambiguous-symbol candidates. */
function pa_currency_priority_codes(): array
{
    return ['USD', 'EUR', 'CAD', 'AUD', 'GBP'];
}

/**
 * symbol => priority-ordered list of ISO codes that use it (e.g. "$" => [USD,CAD,AUD],
 * "¥" => [CNY,JPY], "kr" => [DKK,ISK,NOK,SEK]). Mirrors CurrencyInfo.CodesBySymbol.
 */
function pa_currency_codes_by_symbol(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $priority = array_flip(pa_currency_priority_codes());
    $groups = [];
    foreach (pa_currency_all() as $code => [$symbol]) {
        $groups[$symbol][] = $code;
    }
    foreach ($groups as $symbol => $codes) {
        usort($codes, function ($a, $b) use ($priority) {
            $ra = $priority[$a] ?? PHP_INT_MAX;
            $rb = $priority[$b] ?? PHP_INT_MAX;
            return $ra === $rb ? strcmp($a, $b) : $ra - $rb;
        });
        $groups[$symbol] = $codes;
    }
    $map = $groups;
    return $map;
}

/** Every ISO code that uses a symbol (priority-ordered), or [] if unknown. */
function pa_currency_candidates_for_symbol(string $symbol): array
{
    return pa_currency_codes_by_symbol()[$symbol] ?? [];
}

/** ISO code -> display symbol (mirrors CurrencyInfo). Multi-char symbols get a space. */
function pa_currency_symbol(string $code): string
{
    $code = strtoupper(trim($code));
    $all = pa_currency_all();
    if (isset($all[$code])) {
        $sym = $all[$code][0];
        // Glyph symbols ($, €, £, ¥, …) sit flush; alpha symbols (CHF, kr, zł) get a space.
        return preg_match('/[^\p{L}]/u', $sym) ? $sym : $sym . ' ';
    }
    return $code !== '' ? $code . ' ' : '$';
}

/**
 * Port of CurrencyCellDetector.Detect: read currency + amount from a cell string
 * (e.g. "$10 CAD", "£100", "95,000 CAD", "1,234.56"). Precedence: an explicit ISO
 * code wins; then an unambiguous symbol; then an ambiguous symbol (reported with
 * its candidates); otherwise no currency.
 *
 * @return array{amount:float, code:?string, ambiguous:?string, candidates:string[]}
 */
function pa_currency_detect(?string $cell): array
{
    $none = ['amount' => 0.0, 'code' => null, 'ambiguous' => null, 'candidates' => []];
    if ($cell === null || trim($cell) === '') {
        return $none;
    }
    $raw = trim($cell);
    $amount = pa_currency_parse_amount($raw);

    // 1. Explicit ISO code: a 3-letter alphabetic token that is a known code.
    $explicit = null;
    $conflict = false;
    foreach (pa_currency_alpha_tokens($raw) as $token) {
        $up = strtoupper($token);
        if (strlen($token) === 3 && isset(pa_currency_all()[$up])) {
            if ($explicit === null) {
                $explicit = $up;
            } elseif ($explicit !== $up) {
                $conflict = true;
            }
        }
    }
    if ($explicit !== null && !$conflict) {
        return ['amount' => $amount, 'code' => $explicit, 'ambiguous' => null, 'candidates' => []];
    }

    // 2. Symbol: prefer a glyph symbol (longest-first), else an alphabetic symbol token.
    $symbol = pa_currency_find_glyph($raw) ?? pa_currency_find_alpha_symbol($raw);
    if ($symbol !== null) {
        $codes = pa_currency_candidates_for_symbol($symbol);
        if (count($codes) === 1) {
            return ['amount' => $amount, 'code' => $codes[0], 'ambiguous' => null, 'candidates' => []];
        }
        if (count($codes) > 1) {
            return ['amount' => $amount, 'code' => null, 'ambiguous' => $symbol, 'candidates' => $codes];
        }
    }

    // 3. No currency marker.
    return ['amount' => $amount, 'code' => null, 'ambiguous' => null, 'candidates' => []];
}

/**
 * Resolve a currency token or cell to a single ISO code, picking the priority
 * default for an ambiguous symbol (the web tool has no user-resolution dialog
 * like the desktop app, so it takes the most common candidate). Null if none.
 */
function pa_currency_canon(?string $raw): ?string
{
    $d = pa_currency_detect($raw);
    if ($d['code'] !== null) {
        return $d['code'];
    }
    if ($d['ambiguous'] !== null && $d['candidates']) {
        return $d['candidates'][0]; // priority default, e.g. "$" -> USD
    }
    return null;
}

/** Port of CurrencyCellDetector.ParseAmount: strip currency tokens, parens=negative. */
function pa_currency_parse_amount(?string $s): float
{
    if ($s === null || trim($s) === '') {
        return 0.0;
    }
    $cleaned = trim($s);
    foreach (pa_currency_strip_tokens() as $token) {
        $cleaned = str_ireplace($token, '', $cleaned);
    }
    $cleaned = trim($cleaned);
    if ($cleaned !== '' && $cleaned[0] === '(' && substr($cleaned, -1) === ')') {
        $cleaned = '-' . substr($cleaned, 1, -1);
    }
    // Normalize thousands/decimal separators, then parse.
    $cleaned = str_replace(' ', '', $cleaned);
    if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
        $cleaned = str_replace(',', '', $cleaned);
    } elseif (preg_match('/,\d{1,2}$/', $cleaned) && substr_count($cleaned, ',') === 1) {
        $cleaned = str_replace(',', '.', $cleaned);
    } else {
        $cleaned = str_replace(',', '', $cleaned);
    }
    return is_numeric($cleaned) ? (float) $cleaned : 0.0;
}

/** Maximal runs of letters in a string (currency-code candidates). */
function pa_currency_alpha_tokens(string $s): array
{
    return preg_match_all('/\p{L}+/u', $s, $m) ? $m[0] : [];
}

/** Glyph symbols (contain a non-letter char, e.g. "$", "R$"), longest first. */
function pa_currency_glyph_symbols(): array
{
    static $syms = null;
    if ($syms !== null) {
        return $syms;
    }
    $syms = [];
    foreach (array_keys(pa_currency_codes_by_symbol()) as $sym) {
        if (preg_match('/[^\p{L}]/u', $sym)) {
            $syms[] = $sym;
        }
    }
    usort($syms, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
    return $syms;
}

/** Alphabetic-only symbols (e.g. "kr", "CHF", "lei"), keyed lowercase. */
function pa_currency_alpha_symbols(): array
{
    static $syms = null;
    if ($syms !== null) {
        return $syms;
    }
    $syms = [];
    foreach (array_keys(pa_currency_codes_by_symbol()) as $sym) {
        if (!preg_match('/[^\p{L}]/u', $sym)) {
            $syms[mb_strtolower($sym)] = $sym;
        }
    }
    return $syms;
}

/** All currency tokens to strip when isolating the number (symbols + codes), longest first. */
function pa_currency_strip_tokens(): array
{
    static $tokens = null;
    if ($tokens !== null) {
        return $tokens;
    }
    $tokens = array_unique(array_merge(
        array_keys(pa_currency_codes_by_symbol()),
        array_keys(pa_currency_all())
    ));
    usort($tokens, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
    $tokens = array_values($tokens);
    return $tokens;
}

function pa_currency_find_glyph(string $raw): ?string
{
    foreach (pa_currency_glyph_symbols() as $sym) {
        if (mb_strpos($raw, $sym) !== false) {
            return $sym;
        }
    }
    return null;
}

function pa_currency_find_alpha_symbol(string $raw): ?string
{
    $alpha = pa_currency_alpha_symbols();
    foreach (pa_currency_alpha_tokens($raw) as $token) {
        $key = mb_strtolower($token);
        if (isset($alpha[$key])) {
            return $alpha[$key];
        }
    }
    return null;
}

/**
 * USD-based rate table (code => units per 1 USD, with 'USD' => 1.0) for a date.
 * Reads the exchange_rates cache first, then fetches+caches from
 * OpenExchangeRates. Returns null only when nothing usable can be obtained.
 * Memoized per date; live fetches are capped per request.
 */
function pa_currency_rates(string $date): ?array
{
    global $pdo;
    static $memo = [];
    static $liveFetches = 0;
    static $lastGood = null;

    $today = date('Y-m-d');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}/', $date) ? substr($date, 0, 10) : $today;
    if ($date > $today) {
        $date = $today; // no future rates
    }

    if (array_key_exists($date, $memo)) {
        return $memo[$date];
    }

    // 1. Cache table (historical rates are permanent; today's may be refreshed).
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare('SELECT rates FROM exchange_rates WHERE rate_date = ?');
            $stmt->execute([$date]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $rates = json_decode($raw, true);
                if (is_array($rates) && $rates) {
                    $rates['USD'] = 1.0;
                    $memo[$date] = $rates;
                    $lastGood = $rates;
                    return $rates;
                }
            }
        } catch (Throwable $e) {
            // fall through to fetch
        }
    }

    // 2. Bounded live fetch from OpenExchangeRates.
    if ($liveFetches >= PA_FX_MAX_LIVE_FETCHES) {
        return $memo[$date] = $lastGood;
    }
    $apiKey = $_ENV['OPENEXCHANGERATES_API_KEY'] ?? getenv('OPENEXCHANGERATES_API_KEY') ?: '';
    if (!$apiKey) {
        return $memo[$date] = $lastGood;
    }

    $isLatest = ($date === $today);
    $url = $isLatest
        ? "https://openexchangerates.org/api/latest.json?app_id={$apiKey}&base=USD"
        : "https://openexchangerates.org/api/historical/{$date}.json?app_id={$apiKey}&base=USD";

    $liveFetches++;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        return $memo[$date] = $lastGood;
    }
    $data = json_decode($resp, true);
    if (!isset($data['rates']) || !is_array($data['rates'])) {
        return $memo[$date] = $lastGood;
    }

    if (isset($pdo)) {
        try {
            $pdo->prepare(
                'INSERT INTO exchange_rates (rate_date, rates, fetched_at) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE rates = VALUES(rates), fetched_at = NOW()'
            )->execute([$date, json_encode($data['rates'])]);
        } catch (Throwable $e) {
            // caching is best-effort
        }
    }

    $rates = $data['rates'];
    $rates['USD'] = 1.0;
    $memo[$date] = $rates;
    $lastGood = $rates;
    return $rates;
}

/** Convert an amount between two ISO codes via the USD-based rate table. */
function pa_currency_convert(float $amount, string $from, string $to, ?array $rates): float
{
    if ($from === $to || $amount == 0.0) {
        return $amount;
    }
    if (!$rates) {
        return $amount; // no rates available: leave untouched
    }
    $rf = $rates[$from] ?? null;
    $rt = $rates[$to] ?? null;
    if ($rf === null || $rt === null || (float) $rf == 0.0) {
        return $amount; // unknown currency: leave untouched
    }
    return $amount / (float) $rf * (float) $rt; // from -> USD -> to
}

/**
 * Detect the dominant currency across the money entities and convert every
 * monetary field into it (historical per-row rate). Mutates $entities in place
 * and returns the dominant ISO code. When no currency is detected anywhere,
 * returns 'USD' and changes nothing (legacy behavior).
 */
function pa_apply_currency(array &$entities): string
{
    // Money entities -> the fields on each that hold an amount.
    $moneyFields = [
        'revenue'  => ['unitPrice', 'amount', 'taxAmount', 'total'],
        'expenses' => ['amount', 'taxAmount', 'total'],
        'invoices' => ['subtotal', 'taxAmount', 'total', 'amountPaid', 'balance'],
        'payments' => ['amount', 'taxAmount', 'total'],
    ];

    // 1. Tally detected currencies to find the dominant (by row count).
    $counts = [];
    foreach (['revenue', 'expenses', 'invoices'] as $k) {
        foreach ($entities[$k] ?? [] as $r) {
            $c = pa_currency_canon($r['originalCurrency'] ?? null);
            if ($c) {
                $counts[$c] = ($counts[$c] ?? 0) + 1;
            }
        }
    }
    if (!$counts) {
        return 'USD'; // no currency signal anywhere
    }
    arsort($counts);
    $dominant = array_key_first($counts);

    // 2. If more than one currency is present, convert the minority rows.
    if (count($counts) > 1) {
        foreach ($moneyFields as $k => $fields) {
            if (empty($entities[$k])) {
                continue;
            }
            foreach ($entities[$k] as &$r) {
                $from = pa_currency_canon($r['originalCurrency'] ?? null) ?? $dominant;
                if ($from === $dominant) {
                    continue;
                }
                $date = (string) ($r['date'] ?? ($r['issueDate'] ?? ''));
                $rates = pa_currency_rates($date);
                foreach ($fields as $f) {
                    if (isset($r[$f]) && is_numeric($r[$f])) {
                        $r[$f] = round(pa_currency_convert((float) $r[$f], $from, $dominant, $rates), 2);
                    }
                }
                $r['originalCurrency'] = $dominant; // row is now expressed in the dominant currency
            }
            unset($r);
        }
    }

    return $dominant;
}
