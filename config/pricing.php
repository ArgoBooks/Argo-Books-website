<?php
/**
 * Centralized Pricing Configuration
 *
 * All prices are in CAD. Override any price via environment variables.
 * Default values match current production prices so the system works
 * identically without any env vars set.
 *
 * Environment variables:
 *   PREMIUM_MONTHLY_PRICE       - Premium monthly subscription (default: 15.00)
 *   PREMIUM_YEARLY_PRICE        - Premium yearly subscription (default: 150.00)
 *   PREMIUM_DISCOUNT            - Standard premium discount applied to credited subscriptions (default: 20.00)
 *   PROCESSING_FEE_PERCENT      - Payment processing fee percentage (default: 2.90)
 *   PROCESSING_FEE_FIXED        - Payment processing fixed fee in CAD (default: 0.30)
 *   RECEIPT_SCAN_MONTHLY_LIMIT       - Monthly receipt scan limit for premium tier (default: 500)
 *   FREE_RECEIPT_SCAN_MONTHLY_LIMIT  - Monthly receipt scan limit for free tier (default: 10)
 *   AI_IMPORT_MONTHLY_LIMIT          - Monthly AI spreadsheet import limit for free tier (default: 100)
 *   PREMIUM_AI_IMPORT_MONTHLY_LIMIT  - Monthly AI spreadsheet import limit for premium tier (default: 500)
 *   BANK_IMPORT_MONTHLY_LIMIT        - Monthly AI bank statement import limit for free tier (default: 25)
 *   PREMIUM_BANK_IMPORT_MONTHLY_LIMIT- Monthly AI bank statement import limit for premium tier (default: 500)
 *   FREE_INVOICE_MONTHLY_LIMIT       - Monthly invoice send limit for free tier (default: 25)
 *   WEB_RECEIPT_SCAN_DAILY_LIMIT       - Free web scanner per-visitor daily limit (default: 10)
 *   WEB_RECEIPT_SCAN_GLOBAL_DAILY_CAP  - Free web scanner total scans/day across all visitors (default: 500)
 */

/**
 * Get the pricing configuration array.
 * Uses static caching so env vars are only parsed once per request.
 *
 * @return array Pricing configuration with keys:
 *   - premium_monthly_price (float)
 *   - premium_yearly_price (float)
 *   - processing_fee_percent (float)
 *   - processing_fee_fixed (float)
 *   - currency (string)
 */
function get_pricing_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'premium_monthly_price' => _pricing_parse_env('PREMIUM_MONTHLY_PRICE', 15.00),
        'premium_yearly_price'  => _pricing_parse_env('PREMIUM_YEARLY_PRICE', 150.00),
        'premium_discount'       => _pricing_parse_env('PREMIUM_DISCOUNT', 20.00),
        'processing_fee_percent' => _pricing_parse_env('PROCESSING_FEE_PERCENT', 2.90),
        'processing_fee_fixed'   => _pricing_parse_env('PROCESSING_FEE_FIXED', 0.30),
        'receipt_scan_monthly_limit'      => _pricing_parse_int_env('RECEIPT_SCAN_MONTHLY_LIMIT', 500),
        'free_receipt_scan_monthly_limit' => _pricing_parse_int_env('FREE_RECEIPT_SCAN_MONTHLY_LIMIT', 10),
        'ai_import_monthly_limit'         => _pricing_parse_int_env('AI_IMPORT_MONTHLY_LIMIT', 100),
        'premium_ai_import_monthly_limit' => _pricing_parse_int_env('PREMIUM_AI_IMPORT_MONTHLY_LIMIT', 500),
        'bank_import_monthly_limit'        => _pricing_parse_int_env('BANK_IMPORT_MONTHLY_LIMIT', 25),
        'premium_bank_import_monthly_limit' => _pricing_parse_int_env('PREMIUM_BANK_IMPORT_MONTHLY_LIMIT', 500),
        'free_invoice_monthly_limit'      => _pricing_parse_int_env('FREE_INVOICE_MONTHLY_LIMIT', 25),
        'web_receipt_scan_daily_limit'      => _pricing_parse_int_env('WEB_RECEIPT_SCAN_DAILY_LIMIT', 10),
        'web_receipt_scan_global_daily_cap' => _pricing_parse_int_env('WEB_RECEIPT_SCAN_GLOBAL_DAILY_CAP', 500),
        'currency'              => 'CAD',
    ];

    return $config;
}

/**
 * Parse a price value from an environment variable.
 * Returns the default if the env var is missing, empty, non-numeric, or negative.
 *
 * @param string $key     Environment variable name
 * @param float  $default Default value if env var is invalid
 * @return float Validated price value rounded to 2 decimal places
 */
function _pricing_parse_env($key, $default) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '' || !is_numeric($_ENV[$key])) {
        return $default;
    }

    $value = round(floatval($_ENV[$key]), 2);

    if ($value < 0) {
        return $default;
    }

    return $value;
}

/**
 * Parse an integer value from an environment variable.
 * Returns the default if the env var is missing, empty, non-numeric, or less than 1.
 *
 * @param string $key     Environment variable name
 * @param int    $default Default value if env var is invalid
 * @return int Validated integer value
 */
function _pricing_parse_int_env($key, $default) {
    if (!isset($_ENV[$key]) || $_ENV[$key] === '' || !is_numeric($_ENV[$key])) {
        return $default;
    }

    $value = intval($_ENV[$key]);

    if ($value < 1) {
        return $default;
    }

    return $value;
}

/**
 * Get the plan features configuration from plans.json.
 *
 * Placeholders in the JSON (e.g. {free_invoice_monthly_limit}) are substituted
 * against the env-driven pricing config so the four monthly limits stay in
 * sync with .env without having to edit plans.json by hand.
 *
 * Uses static caching so the file is only read once per request.
 *
 * @return array Plan data with 'free' and 'premium' keys
 */
function get_plan_features() {
    static $plans = null;

    if ($plans !== null) {
        return $plans;
    }

    $cfg = get_pricing_config();
    $json = file_get_contents(__DIR__ . '/plans.json');
    $json = strtr($json, [
        '{free_invoice_monthly_limit}'      => (string) $cfg['free_invoice_monthly_limit'],
        '{free_receipt_scan_monthly_limit}' => (string) $cfg['free_receipt_scan_monthly_limit'],
        '{ai_import_monthly_limit}'         => (string) $cfg['ai_import_monthly_limit'],
        '{premium_ai_import_monthly_limit}' => (string) $cfg['premium_ai_import_monthly_limit'],
        '{bank_import_monthly_limit}'       => (string) $cfg['bank_import_monthly_limit'],
        '{premium_bank_import_monthly_limit}' => (string) $cfg['premium_bank_import_monthly_limit'],
        '{receipt_scan_monthly_limit}'      => (string) $cfg['receipt_scan_monthly_limit'],
    ]);
    $plans = json_decode($json, true);
    // plans.json keeps its editing rules in a "_comment" key because JSON has no comments, so it is dropped before any caller sees it.
    unset($plans['_comment']);
    return $plans;
}

/**
 * Render a feature label, appending the detail in a <span> if present.
 *
 * @param array|string $feature Feature array with 'label' and optional 'detail', or plain string
 * @return string HTML-safe feature text
 */
function render_feature_label($feature) {
    if (is_string($feature)) {
        return htmlspecialchars($feature);
    }
    $html = htmlspecialchars($feature['label']);
    if (!empty($feature['detail'])) {
        $html .= ' <span>(' . htmlspecialchars($feature['detail']) . ')</span>';
    }
    return $html;
}

/**
 * Get the competitor pricing data from competitors.json.
 *
 * Single source of truth for prices of other accounting tools quoted on the
 * comparison pages and in articles. Update a price in competitors.json and
 * every page that references it via competitor_price() or {placeholder}
 * substitution will update on the next render.
 *
 * Uses static caching so the file is only read once per request.
 *
 * @return array Competitor data keyed by competitor slug
 */
function get_competitors() {
    static $competitors = null;

    if ($competitors !== null) {
        return $competitors;
    }

    $json = file_get_contents(__DIR__ . '/competitors.json');
    $competitors = json_decode($json, true) ?: [];
    return $competitors;
}

/**
 * Look up a competitor plan price.
 *
 * @param string $competitor Slug from competitors.json (e.g. 'quickbooks')
 * @param string $plan       Plan key within that competitor (e.g. 'easystart')
 * @param string $period     'monthly' (default) or 'yearly'
 * @return int|float|null    Price, or null if the competitor/plan/period isn't defined
 */
function competitor_price($competitor, $plan, $period = 'monthly') {
    $competitors = get_competitors();
    return $competitors[$competitor]['plans'][$plan][$period] ?? null;
}

/**
 * Build the full placeholder map used by pricing_substitute().
 *
 * Keys are the literal strings written into article HTML and other templates
 * (e.g. "{argo_premium_monthly}", "{quickbooks_easystart}"). Values are
 * stringified numbers ready to be dropped into the surrounding "$..." copy.
 *
 * Argo's own prices come from .env via get_pricing_config(); competitor
 * prices come from competitors.json. This is the single function that knows
 * the placeholder naming convention.
 */
function pricing_template_vars() {
    static $vars = null;

    if ($vars !== null) {
        return $vars;
    }

    $cfg = get_pricing_config();
    $monthly       = (float) $cfg['premium_monthly_price'];
    $yearly        = (float) $cfg['premium_yearly_price'];
    $yearlyPerMo   = $monthly > 0 ? $yearly / 12 : 0;

    $vars = [
        '{argo_premium_monthly}'         => _pricing_format_amount($monthly),
        '{argo_premium_yearly}'          => _pricing_format_amount($yearly),
        '{argo_premium_yearly_per_month}' => $monthly > 0 ? _pricing_format_amount($yearlyPerMo, 2) : '0',
        // Plan limits, so articles can quote them without hardcoding the numbers.
        '{argo_free_invoice_limit}'       => (string) $cfg['free_invoice_monthly_limit'],
        '{argo_free_receipt_scan_limit}'  => (string) $cfg['free_receipt_scan_monthly_limit'],
        '{argo_receipt_scan_limit}'       => (string) $cfg['receipt_scan_monthly_limit'],
        '{argo_ai_import_limit}'          => (string) $cfg['ai_import_monthly_limit'],
    ];

    foreach (get_competitors() as $slug => $brand) {
        foreach (($brand['plans'] ?? []) as $planKey => $plan) {
            if (isset($plan['monthly'])) {
                $vars['{' . $slug . '_' . $planKey . '}'] = _pricing_format_amount($plan['monthly']);
                $vars['{' . $slug . '_' . $planKey . '_monthly}'] = _pricing_format_amount($plan['monthly']);
            }
            if (isset($plan['yearly'])) {
                $vars['{' . $slug . '_' . $planKey . '_yearly}'] = _pricing_format_amount($plan['yearly']);
            }
        }
    }

    return $vars;
}

/**
 * Replace pricing placeholders in a chunk of HTML/text.
 *
 * Articles, comparison-page copy, and other templates can include
 * {argo_premium_monthly} / {quickbooks_easystart} / etc. and this function
 * substitutes the current values from .env + competitors.json. Strings with
 * no placeholders pass through unchanged.
 */
function pricing_substitute($html) {
    if ($html === null || $html === '') {
        return $html;
    }
    return strtr($html, pricing_template_vars());
}

/**
 * Format a price for inline display: integer if whole, two decimals otherwise.
 */
function _pricing_format_amount($amount, $forceDecimals = null) {
    if ($forceDecimals !== null) {
        return number_format($amount, $forceDecimals, '.', '');
    }
    if (floor($amount) == $amount) {
        return (string) (int) $amount;
    }
    return number_format($amount, 2, '.', '');
}

/**
 * Calculate the processing fee for a given subtotal.
 * Returns 0 if subtotal is zero or negative (e.g. credit-covered payments).
 *
 * @param float $subtotal The pre-fee charge amount
 * @return float Fee amount rounded to 2 decimal places
 */
function calculate_processing_fee($subtotal) {
    if ($subtotal <= 0) {
        return 0.00;
    }
    $config = get_pricing_config();
    return round(($subtotal * $config['processing_fee_percent'] / 100) + $config['processing_fee_fixed'], 2);
}

/**
 * Calculate the processing fee for an invoice payment, with currency conversion
 * for the fixed fee portion.
 *
 * The percentage portion (2.90%) is currency-agnostic. The fixed portion ($0.30 CAD)
 * is converted to the invoice's currency using exchange rates from the database.
 *
 * @param float  $amount   The payment amount
 * @param string $currency The invoice currency code (e.g., "USD", "CAD", "EUR")
 * @return float Fee amount rounded to 2 decimal places
 */
function calculate_invoice_processing_fee($amount, $currency = 'CAD') {
    if ($amount <= 0) {
        return 0.00;
    }

    $config = get_pricing_config();
    $percentFee = $amount * $config['processing_fee_percent'] / 100;
    $fixedFeeCAD = $config['processing_fee_fixed'];

    // If invoice is in CAD, no conversion needed
    $currency = strtoupper($currency);
    if ($currency === 'CAD') {
        return round($percentFee + $fixedFeeCAD, 2);
    }

    // Convert the fixed fee from CAD to the invoice currency using exchange rates
    $fixedFeeConverted = _convert_fixed_fee_to_currency($fixedFeeCAD, $currency);

    return round($percentFee + $fixedFeeConverted, 2);
}

/**
 * Convert a CAD amount to the target currency using exchange rates from the database.
 * Rates in the exchange_rates table are relative to USD.
 *
 * @param float  $amountCAD The amount in CAD
 * @param string $currency  Target currency code
 * @return float Converted amount (falls back to original amount if no rates available)
 */
function _convert_fixed_fee_to_currency($amountCAD, $currency) {
    try {
        global $pdo;
        $stmt = $pdo->prepare(
            'SELECT rates FROM exchange_rates WHERE rate_date <= CURDATE() ORDER BY rate_date DESC LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row || empty($row['rates'])) {
            return $amountCAD; // Fallback: use same numeric value
        }

        $rates = json_decode($row['rates'], true);
        if (!$rates) {
            return $amountCAD;
        }

        $cadRate = $rates['CAD'] ?? null;
        $targetRate = $rates[$currency] ?? null;

        if (!$cadRate || $cadRate <= 0 || !$targetRate || $targetRate <= 0) {
            return $amountCAD;
        }

        // Convert: CAD -> USD -> target currency
        // $amountCAD / cadRate = amount in USD; * targetRate = amount in target currency
        return round($amountCAD / $cadRate * $targetRate, 4);
    } catch (\Throwable $e) {
        error_log('Fee currency conversion failed: ' . $e->getMessage());
        return $amountCAD; // Fallback: use same numeric value
    }
}


/**
 * The payroll providers from competitors.json, in file order.
 *
 * Payroll vendors price differently from the accounting tools: a monthly base
 * fee plus a fee per employee per month. That second axis is the whole argument
 * on /payroll/, so it needs its own accessor rather than being filtered out of
 * get_competitors() at each call site.
 *
 * @return array Slug => competitor entry, for entries marked "category":"payroll"
 */
function get_payroll_competitors() {
    static $payroll = null;

    if ($payroll !== null) {
        return $payroll;
    }

    $payroll = [];
    foreach (get_competitors() as $slug => $brand) {
        if (($brand['category'] ?? '') === 'payroll') {
            $payroll[$slug] = $brand;
        }
    }
    return $payroll;
}

/**
 * What a payroll provider's plan costs per month for a given headcount.
 *
 * base + (per-employee fee x employees). Returns null when the plan is unknown,
 * so a caller can drop a row rather than print a zero that reads as "free".
 *
 * @param string $competitor Slug from competitors.json
 * @param string $plan       Plan key within that competitor
 * @param int    $employees  Headcount
 * @return float|null Monthly cost, or null if the plan isn't defined
 */
function payroll_monthly_cost($competitor, $plan, $employees) {
    $plans = get_competitors()[$competitor]['plans'] ?? [];
    if (!isset($plans[$plan])) {
        return null;
    }
    $p = $plans[$plan];
    return (float) ($p['monthly'] ?? 0) + ((float) ($p['per_employee'] ?? 0) * max(0, (int) $employees));
}
