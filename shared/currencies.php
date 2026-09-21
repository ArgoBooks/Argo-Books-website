<?php
// shared/currencies.php
//
// The website's single source of truth for supported currencies. Mirrors the
// desktop app's ArgoBooks.Core/Models/Common/CurrencyInfo.cs (code, symbol,
// name) and ArgoBooks/Data/Currencies.cs (which of them appear in a picker,
// and the "Common" group pinned to the top).
//
// Before this file the list was copy-pasted into three places on the website
// and two in the desktop app, and INR had already drifted out of two of them.
// Anything on the website that offers a currency choice reads from here.
//
// 'locale' is the Intl.NumberFormat fallback locale for that currency, so
// separators and symbol placement are right when the user switches.
// See read-me/Tool page standards.md for which tools offer which currencies.

/** Currencies pinned to the top of every picker, in this order. */
function argo_currencies_common(): array
{
    return ['USD', 'EUR', 'CAD', 'AUD'];
}

/**
 * code => ['name', 'symbol', 'locale', 'decimals'], alphabetical by code.
 * 'decimals' is how many minor units the currency uses; the zero-decimal ones
 * (CLP, HUF, ISK, JPY, KRW, VND) matter when parsing an amount out of a spreadsheet.
 */
function argo_currencies_all(): array
{
    static $all = [
        'AED' => ['name' => 'UAE Dirham',                'symbol' => 'AED',  'locale' => 'en-AE', 'decimals' => 2],
        'ALL' => ['name' => 'Albanian Lek',              'symbol' => 'L',    'locale' => 'sq-AL', 'decimals' => 2],
        'ARS' => ['name' => 'Argentine Peso',            'symbol' => 'AR$',  'locale' => 'es-AR', 'decimals' => 2],
        'AUD' => ['name' => 'Australian Dollar',         'symbol' => '$',    'locale' => 'en-AU', 'decimals' => 2],
        'BAM' => ['name' => 'Bosnia-Herzegovina Mark',   'symbol' => 'KM',   'locale' => 'bs-BA', 'decimals' => 2],
        'BDT' => ['name' => 'Bangladeshi Taka',          'symbol' => '৳',    'locale' => 'en-BD', 'decimals' => 2],
        'BGN' => ['name' => 'Bulgarian Lev',             'symbol' => 'лв',   'locale' => 'bg-BG', 'decimals' => 2],
        'BRL' => ['name' => 'Brazilian Real',            'symbol' => 'R$',   'locale' => 'pt-BR', 'decimals' => 2],
        'BYN' => ['name' => 'Belarusian Ruble',          'symbol' => 'Br',   'locale' => 'be-BY', 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar',           'symbol' => '$',    'locale' => 'en-CA', 'decimals' => 2],
        'CHF' => ['name' => 'Swiss Franc',               'symbol' => 'CHF',  'locale' => 'de-CH', 'decimals' => 2],
        'CLP' => ['name' => 'Chilean Peso',              'symbol' => 'CL$',  'locale' => 'es-CL', 'decimals' => 0],
        'CNY' => ['name' => 'Chinese Yuan',              'symbol' => '¥',    'locale' => 'zh-CN', 'decimals' => 2],
        'COP' => ['name' => 'Colombian Peso',            'symbol' => 'CO$',  'locale' => 'es-CO', 'decimals' => 2],
        'CZK' => ['name' => 'Czech Koruna',              'symbol' => 'Kč',   'locale' => 'cs-CZ', 'decimals' => 2],
        'DKK' => ['name' => 'Danish Krone',              'symbol' => 'kr',   'locale' => 'da-DK', 'decimals' => 2],
        'EGP' => ['name' => 'Egyptian Pound',            'symbol' => 'E£',   'locale' => 'en-EG', 'decimals' => 2],
        'EUR' => ['name' => 'Euro',                      'symbol' => '€',    'locale' => 'en-IE', 'decimals' => 2],
        'GBP' => ['name' => 'British Pound',             'symbol' => '£',    'locale' => 'en-GB', 'decimals' => 2],
        'GHS' => ['name' => 'Ghanaian Cedi',             'symbol' => '₵',    'locale' => 'en-GH', 'decimals' => 2],
        'HKD' => ['name' => 'Hong Kong Dollar',          'symbol' => 'HK$',  'locale' => 'en-HK', 'decimals' => 2],
        'HUF' => ['name' => 'Hungarian Forint',          'symbol' => 'Ft',   'locale' => 'hu-HU', 'decimals' => 0],
        'IDR' => ['name' => 'Indonesian Rupiah',         'symbol' => 'Rp',   'locale' => 'id-ID', 'decimals' => 2],
        'ILS' => ['name' => 'Israeli Shekel',            'symbol' => '₪',    'locale' => 'he-IL', 'decimals' => 2],
        'INR' => ['name' => 'Indian Rupee',              'symbol' => '₹',    'locale' => 'en-IN', 'decimals' => 2],
        'ISK' => ['name' => 'Icelandic Króna',           'symbol' => 'kr',   'locale' => 'is-IS', 'decimals' => 0],
        'JPY' => ['name' => 'Japanese Yen',              'symbol' => '¥',    'locale' => 'ja-JP', 'decimals' => 0],
        'KES' => ['name' => 'Kenyan Shilling',           'symbol' => 'KSh',  'locale' => 'en-KE', 'decimals' => 2],
        'KRW' => ['name' => 'South Korean Won',          'symbol' => '₩',    'locale' => 'ko-KR', 'decimals' => 0],
        'LKR' => ['name' => 'Sri Lankan Rupee',          'symbol' => 'Rs',   'locale' => 'en-LK', 'decimals' => 2],
        'MAD' => ['name' => 'Moroccan Dirham',           'symbol' => 'MAD',  'locale' => 'fr-MA', 'decimals' => 2],
        'MKD' => ['name' => 'Macedonian Denar',          'symbol' => 'ден',  'locale' => 'mk-MK', 'decimals' => 2],
        'MXN' => ['name' => 'Mexican Peso',              'symbol' => 'MX$',  'locale' => 'es-MX', 'decimals' => 2],
        'MYR' => ['name' => 'Malaysian Ringgit',         'symbol' => 'RM',   'locale' => 'ms-MY', 'decimals' => 2],
        'NGN' => ['name' => 'Nigerian Naira',            'symbol' => '₦',    'locale' => 'en-NG', 'decimals' => 2],
        'NOK' => ['name' => 'Norwegian Krone',           'symbol' => 'kr',   'locale' => 'nb-NO', 'decimals' => 2],
        'NZD' => ['name' => 'New Zealand Dollar',        'symbol' => 'NZ$',  'locale' => 'en-NZ', 'decimals' => 2],
        'PEN' => ['name' => 'Peruvian Sol',              'symbol' => 'S/',   'locale' => 'es-PE', 'decimals' => 2],
        'PHP' => ['name' => 'Philippine Peso',           'symbol' => '₱',    'locale' => 'en-PH', 'decimals' => 2],
        'PKR' => ['name' => 'Pakistani Rupee',           'symbol' => '₨',    'locale' => 'en-PK', 'decimals' => 2],
        'PLN' => ['name' => 'Polish Złoty',              'symbol' => 'zł',   'locale' => 'pl-PL', 'decimals' => 2],
        'QAR' => ['name' => 'Qatari Riyal',              'symbol' => 'QAR',  'locale' => 'en-QA', 'decimals' => 2],
        'RON' => ['name' => 'Romanian Leu',              'symbol' => 'lei',  'locale' => 'ro-RO', 'decimals' => 2],
        'RSD' => ['name' => 'Serbian Dinar',             'symbol' => 'дин',  'locale' => 'sr-RS', 'decimals' => 2],
        'RUB' => ['name' => 'Russian Ruble',             'symbol' => '₽',    'locale' => 'ru-RU', 'decimals' => 2],
        'SAR' => ['name' => 'Saudi Riyal',               'symbol' => 'SAR',  'locale' => 'en-SA', 'decimals' => 2],
        'SEK' => ['name' => 'Swedish Krona',             'symbol' => 'kr',   'locale' => 'sv-SE', 'decimals' => 2],
        'SGD' => ['name' => 'Singapore Dollar',          'symbol' => 'S$',   'locale' => 'en-SG', 'decimals' => 2],
        'THB' => ['name' => 'Thai Baht',                 'symbol' => '฿',    'locale' => 'th-TH', 'decimals' => 2],
        'TRY' => ['name' => 'Turkish Lira',              'symbol' => '₺',    'locale' => 'tr-TR', 'decimals' => 2],
        'TWD' => ['name' => 'Taiwan Dollar',             'symbol' => 'NT$',  'locale' => 'zh-TW', 'decimals' => 2],
        'UAH' => ['name' => 'Ukrainian Hryvnia',         'symbol' => '₴',    'locale' => 'uk-UA', 'decimals' => 2],
        'USD' => ['name' => 'US Dollar',                 'symbol' => '$',    'locale' => 'en-US', 'decimals' => 2],
        'VND' => ['name' => 'Vietnamese Dong',           'symbol' => '₫',    'locale' => 'vi-VN', 'decimals' => 0],
        'ZAR' => ['name' => 'South African Rand',        'symbol' => 'R',    'locale' => 'en-ZA', 'decimals' => 2],
    ];
    return $all;
}

/** code => locale, for handing to Intl.NumberFormat on the client. */
function argo_currency_locales(): array
{
    return array_map(static fn($c) => $c['locale'], argo_currencies_all());
}

/** The label shown in a picker, e.g. "USD - US Dollar ($)". */
function argo_currency_label(string $code): string
{
    $all = argo_currencies_all();
    if (!isset($all[$code])) {
        return $code;
    }
    return $code . ' - ' . $all[$code]['name'] . ' (' . $all[$code]['symbol'] . ')';
}

/**
 * The symbol to prepend to a formatted amount, e.g. "CA$" for 1,234.00 CAD.
 *
 * Two rules separate this from the raw 'symbol' above, both about reading an
 * amount on its own rather than in a picker:
 *
 * - USD, CAD and AUD all carry a bare "$", so CAD and AUD get their region
 *   letters. Without them "$1,234.00" is three different amounts.
 * - A symbol that ends in a letter (CHF, kr, Kč, zł, lei) needs a space before
 *   the digits, or "CHF1234.00" runs together.
 *
 * Falls back to "$" for an unknown code, which is what the customer-facing
 * portal pages did before they shared this.
 */
function argo_currency_display_symbol(string $code): string
{
    $symbol = argo_currencies_all()[$code]['symbol'] ?? '$';

    return preg_match('/\p{L}$/u', $symbol) ? $symbol . ' ' : $symbol;
}

/**
 * Render the standard <option> set: a "Common" optgroup, then all currencies.
 *
 * No option carries `selected`. USD leads the Common group, so the browser's
 * default selection is already USD, and the tools drive the real value from JS
 * state after load. Marking one selected here would fight that, and would also
 * be ambiguous, since the common codes appear in both groups.
 */
function argo_currency_options(): string
{
    $option = static fn(string $code) => '<option value="' . htmlspecialchars($code) . '">'
        . htmlspecialchars(argo_currency_label($code)) . '</option>';

    $html = '<optgroup label="Common">';
    foreach (argo_currencies_common() as $code) {
        $html .= $option($code);
    }
    $html .= '</optgroup><optgroup label="All currencies">';
    foreach (array_keys(argo_currencies_all()) as $code) {
        $html .= $option($code);
    }
    return $html . '</optgroup>';
}
