<?php
// profit-analyzer/lib/analytics.php
//
// The analytics engine: pure computation over NormalizedData → the data the
// result page renders (insights, KPIs, chart series, the money-flow, the
// cleaned table). No importer dependency; tested against fixtures.
//
// Only the tabs the uploaded data actually supports are returned, so a
// sales-only export yields Dashboard/Products/Customers/Taxes/Geographic, not
// all nine.

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/export.php'; // pa_build_workbook + pa_lookup, reused for the cleaned table
require_once __DIR__ . '/import/currency.php'; // pa_currency_symbol for currency-aware formatting

/**
 * Currency symbol used by pa_money() and the cleaned-table money cells. Set once
 * per compute from the normalized meta, so all server-rendered amounts carry the
 * dominant currency's symbol instead of a hardcoded "$".
 */
function pa_set_money_symbol(string $code): void { $GLOBALS['pa_cur_symbol'] = pa_currency_symbol($code); }
function pa_money(float $n): string { return ($GLOBALS['pa_cur_symbol'] ?? '$') . number_format(round($n)); }
function pa_pct(float $n, float $of): int { return $of > 0 ? (int) round($n / $of * 100) : 0; }

/** Sum a numeric field over an array of rows. */
function pa_sum(array $rows, string $field): float
{
    $t = 0.0;
    foreach ($rows as $r) { $t += (float) ($r[$field] ?? 0); }
    return $t;
}

/** Group rows by a key callback, summing $field; returns [key => total]. */
function pa_group(array $rows, callable $keyFn, string $field): array
{
    $out = [];
    foreach ($rows as $r) {
        $k = $keyFn($r);
        if ($k === null || $k === '') { continue; }
        $out[$k] = ($out[$k] ?? 0) + (float) ($r[$field] ?? 0);
    }
    return $out;
}

/** Ordered month buckets (Jan..Dec) that actually appear in the rows. */
function pa_month_series(array $rows, string $dateField, string $valField): array
{
    $byMonth = [];
    foreach ($rows as $r) {
        $m = (int) date('n', strtotime($r[$dateField] ?? 'now'));
        $byMonth[$m] = ($byMonth[$m] ?? 0) + (float) ($r[$valField] ?? 0);
    }
    ksort($byMonth);
    $cats = []; $data = [];
    foreach ($byMonth as $m => $v) { $cats[] = date('M', mktime(0, 0, 0, $m, 1)); $data[] = round($v, 2); }
    return ['cats' => $cats, 'data' => $data];
}

/** Top-N [name=>total] map plus an "Other" remainder, as pie data. */
function pa_top_pie(array $map, int $n, string $otherLabel = 'Other'): array
{
    arsort($map);
    $out = []; $i = 0; $other = 0;
    foreach ($map as $name => $val) {
        if ($i < $n) { $out[] = ['name' => $name, 'value' => round($val)]; }
        else { $other += $val; }
        $i++;
    }
    if ($other > 0) { $out[] = ['name' => $otherLabel, 'value' => round($other)]; }
    return $out;
}

/**
 * Compute the full result-page payload from NormalizedData.
 */
function pa_compute_analytics(array $normalized): array
{
    $n = pa_normalize($normalized);
    $E = $n['entities'];
    $meta = $n['meta'];
    $currency = $meta['currency'] ?? 'USD';
    pa_set_money_symbol($currency); // all pa_money() output below uses this currency's symbol

    $revenue   = $E['revenue'];
    $expenses  = $E['expenses'];
    $products  = $E['products'];
    $customers = $E['customers'];
    $suppliers = $E['suppliers'];

    $revTotal = pa_sum($revenue, 'amount');
    $expTotal = pa_sum($expenses, 'amount');
    $netProfit = $revTotal - $expTotal;
    $margin = pa_pct($netProfit, $revTotal);

    // Expense category buckets for the money-flow.
    $catTotals = pa_group($expenses, fn($r) => $r['category'] ?? 'Other', 'amount');
    $cogs = $catTotals['Cost of goods'] ?? 0;
    $ads  = $catTotals['Advertising'] ?? 0;
    $fees = $catTotals['Fees'] ?? 0;
    $other = max(0, $expTotal - $cogs - $ads - $fees);

    // Total records analyzed across every entity, for the header badge.
    $totalRows = 0;
    foreach ($E as $arr) { $totalRows += count($arr); }

    $result = [
        'meta' => [
            'filename' => $meta['filename'] ?? 'your-spreadsheet.xlsx',
            'rows' => $totalRows,
            'flagged' => (int) ($meta['flagged'] ?? 0),
            'currency' => $currency,
            'currencySymbol' => pa_currency_symbol($currency),
        ],
        'tabs' => [],
        'headline' => null,
    ];

    // ---- Headline insight (fees + margin, both supportable) ----
    if ($revTotal > 0) {
        $feePct = pa_pct($fees, $revTotal);
        if ($fees > 0) {
            $result['headline'] = [
                'title' => "You're losing {$feePct}% of revenue to payment &amp; processing fees",
                'detail' => "That's " . pa_money($fees) . " over the period, and after every cost you keep just <b>{$margin}%</b> as profit.",
            ];
        } else {
            $result['headline'] = [
                'title' => "You keep {$margin}% of your revenue as profit",
                'detail' => "On " . pa_money($revTotal) . " in revenue, " . pa_money($netProfit) . " is left after every cost.",
            ];
        }
    }

    // ---- Dashboard ----
    if ($revTotal > 0 || $expTotal > 0) {
        $result['tabs'][] = 'dashboard';

        $nodes = []; $links = [];
        if ($netProfit >= 0) {
            // A waterfall: revenue peels off each cost and the remainder carries on, which holds because every remainder stays at or above profit.
            $nodes = ['Revenue' => round($revTotal)];
            $surv = 'Revenue'; $survVal = $revTotal;
            $stages = [
                ['leak' => 'Cost of goods',    'val' => $cogs,  'next' => 'Gross profit'],
                ['leak' => 'Ads & marketing',  'val' => $ads,   'next' => 'After ads'],
                ['leak' => 'Fees',             'val' => $fees,  'next' => 'After fees'],
                ['leak' => 'Other costs',      'val' => $other, 'next' => 'Profit'],
            ];
            $stages = array_values(array_filter($stages, fn($s) => $s['val'] > 0));
            foreach ($stages as $i => $s) {
                $isLast = ($i === count($stages) - 1);
                $nextName = $isLast ? 'Profit' : $s['next'];
                $nextVal = $survVal - $s['val'];
                $nodes[$s['leak']] = round($s['val']);
                $nodes[$nextName] = round($nextVal);
                $links[] = [$surv, $s['leak'], round($s['val'])];
                $links[] = [$surv, $nextName, round($nextVal)];
                $surv = $nextName; $survVal = $nextVal;
            }
        } else {
            // Revenue cannot cover the costs here, so a balanced waterfall would need a fake inflow that reads like income.
            $nodes = ['Total costs' => round($expTotal)];
            $cats = array_filter([
                'Cost of goods'   => $cogs,
                'Ads & marketing' => $ads,
                'Fees'            => $fees,
                'Other costs'     => $other,
            ], fn($v) => $v > 0);
            foreach ($cats as $name => $val) {
                $nodes[$name] = round($val);
                $links[] = ['Total costs', $name, round($val)];
            }
        }

        $revByProductMap = [];
        foreach ($revenue as $r) {
            $pid = $r['productId'] ?? null;
            $name = $pid ? (pa_product_name($products, $pid) ?? $r['description'] ?? 'Other') : ($r['description'] ?? 'Other');
            $revByProductMap[$name] = ($revByProductMap[$name] ?? 0) + (float) ($r['amount'] ?? 0);
        }

        $result['dashboard'] = [
            'kpis' => [
                ['lbl' => 'Total Expenses', 'val' => pa_money($expTotal), 'cls' => 'bad', 'sub' => pa_pct($expTotal, $revTotal) . '% of revenue', 'subcls' => 'down'],
                ['lbl' => 'Total Revenue',  'val' => pa_money($revTotal), 'cls' => 'good'],
                ['lbl' => 'Net Profit',     'val' => pa_money($netProfit), 'cls' => ''],
                ['lbl' => 'Profit Margin',  'val' => $margin . '%', 'cls' => ''],
            ],
            'flow' => ['kept' => $margin, 'nodes' => $nodes, 'links' => $links],
            'profitTrend' => pa_month_diff_series($revenue, $expenses),
            'salesVsExp' => [
                'revenue' => pa_month_series($revenue, 'date', 'amount'),
                'expenses' => pa_month_series($expenses, 'date', 'amount'),
            ],
            'revByProduct' => pa_top_pie($revByProductMap, 6),
            'expDist' => pa_top_pie($catTotals, 6),
        ];
    }

    // ---- Products (renders whenever products exist, with or without sales) ----
    if ($products) {
        $result['tabs'][] = 'products';
        $catCount = []; $typeCount = [];
        foreach ($products as $p) {
            $cat = trim((string) ($p['categoryName'] ?? '')); if ($cat === '') { $cat = 'Uncategorized'; }
            $catCount[$cat] = ($catCount[$cat] ?? 0) + 1;
            $ty = trim((string) ($p['type'] ?? '')); if ($ty === '') { $ty = 'Other'; }
            $typeCount[$ty] = ($typeCount[$ty] ?? 0) + 1;
        }
        $cards = [];
        if (count($catCount) > 1) {
            $cards[] = ['title' => 'Products by category', 'meta' => 'Catalog breakdown', 'type' => 'pie', 'data' => pa_top_pie($catCount, 8)];
        }
        if (count($typeCount) > 1) {
            $cards[] = ['title' => 'Products by type', 'meta' => 'Sold vs purchased vs rental', 'type' => 'pie', 'data' => pa_top_pie($typeCount, 5)];
        }
        if ($revenue) {
            $revByProd = [];
            foreach ($revenue as $r) {
                $name = trim((string) ($r['description'] ?? '')); if ($name === '') { continue; }
                $revByProd[$name] = ($revByProd[$name] ?? 0) + (float) ($r['amount'] ?? 0);
            }
            if ($revByProd) {
                $cards[] = ['title' => 'Top products by revenue', 'meta' => 'By sales total', 'type' => 'pie', 'money' => true, 'data' => pa_top_pie($revByProd, 8)];
            }
        }
        $catalog = [];
        foreach ($products as $p) {
            $catalog[] = [
                (string) ($p['name'] ?? ''), (string) ($p['sku'] ?? ''),
                (string) ($p['categoryName'] ?? ''), (string) ($p['type'] ?? ''),
                pa_lookup($suppliers, $p['supplierId'] ?? '', 'name'),
            ];
        }
        $cards[] = ['title' => 'Product catalog', 'meta' => count($products) . ' items', 'type' => 'table', 'span2' => true, 'noun' => 'product',
            'columns' => ['Name', 'SKU', 'Category', 'Type', 'Supplier'], 'rows' => $catalog];
        $result['products'] = [
            'kpis' => [
                ['lbl' => 'Products', 'val' => (string) count($products), 'cls' => 'good'],
                ['lbl' => 'Categories', 'val' => (string) count($catCount), 'cls' => ''],
                ['lbl' => 'Types', 'val' => (string) count($typeCount), 'cls' => ''],
            ],
            'cards' => $cards,
        ];
    }

    // ---- Customers (renders whenever customers exist, with or without sales) ----
    if ($customers) {
        $result['tabs'][] = 'customers';
        $countryCount = []; $statusCount = [];
        foreach ($customers as $c) {
            $co = trim((string) ($c['country'] ?? '')); if ($co !== '') { $countryCount[$co] = ($countryCount[$co] ?? 0) + 1; }
            $st = trim((string) ($c['status'] ?? '')); if ($st === '') { $st = 'Unknown'; } $statusCount[$st] = ($statusCount[$st] ?? 0) + 1;
        }
        $cards = [];
        if ($revenue) {
            $byCust = pa_group($revenue, fn($r) => pa_customer_name($customers, $r['customerId'] ?? '') ?? ($r['customerId'] ?? ''), 'amount');
            if ($byCust) { $cards[] = ['title' => 'Top customers by revenue', 'meta' => 'Highest spenders', 'type' => 'pie', 'money' => true, 'data' => pa_top_pie($byCust, 6)]; }
            $payMap = [];
            foreach ($revenue as $r) { $s = $r['paymentStatus'] ?? 'Unknown'; $payMap[$s] = ($payMap[$s] ?? 0) + 1; }
            $cards[] = ['title' => 'Payment status', 'meta' => 'Paid / partial / unpaid', 'type' => 'pie', 'data' => pa_top_pie($payMap, 6)];
        } else {
            $byPur = [];
            foreach ($customers as $c) { $v = (float) ($c['totalPurchases'] ?? 0); if ($v > 0) { $byPur[(string) ($c['name'] ?? '')] = $v; } }
            if ($byPur) { $cards[] = ['title' => 'Top customers by purchases', 'meta' => 'Lifetime value', 'type' => 'pie', 'money' => true, 'data' => pa_top_pie($byPur, 6)]; }
        }
        if (count($countryCount) > 1) { $cards[] = ['title' => 'Customers by country', 'meta' => 'Where they are', 'type' => 'pie', 'data' => pa_top_pie($countryCount, 8)]; }
        if (count($statusCount) > 1) { $cards[] = ['title' => 'Active vs inactive', 'meta' => 'Account status', 'type' => 'pie', 'data' => pa_top_pie($statusCount, 5)]; }
        $list = [];
        foreach ($customers as $c) {
            $list[] = [(string) ($c['name'] ?? ''), (string) ($c['company'] ?? ''), (string) ($c['email'] ?? ''),
                (string) ($c['city'] ?? ''), (string) ($c['country'] ?? ''), (string) ($c['status'] ?? '')];
        }
        $cards[] = ['title' => 'Customer list', 'meta' => count($customers) . ' customers', 'type' => 'table', 'span2' => true, 'noun' => 'customer',
            'columns' => ['Name', 'Company', 'Email', 'City', 'Country', 'Status'], 'rows' => $list];
        $avgVal = $revenue && count($customers) > 0 ? pa_money($revTotal / count($customers)) : null;
        $kpis = [
            ['lbl' => 'Total Customers', 'val' => (string) count($customers), 'cls' => ''],
            ['lbl' => 'Active', 'val' => (string) count(array_filter($customers, fn($c) => ($c['status'] ?? '') === 'Active')), 'cls' => 'good'],
            ['lbl' => 'Countries', 'val' => (string) count($countryCount), 'cls' => ''],
        ];
        if ($avgVal !== null) { $kpis[] = ['lbl' => 'Avg Customer Value', 'val' => $avgVal, 'cls' => '']; }
        $result['customers'] = ['kpis' => $kpis, 'cards' => $cards];
    }

    // ---- Taxes ----
    $taxCollected = pa_sum($revenue, 'taxAmount');
    $taxPaid = pa_sum($expenses, 'taxAmount');
    if ($taxCollected > 0 || $taxPaid > 0) {
        $result['tabs'][] = 'taxes';
        $taxByCat = pa_group($expenses, fn($r) => $r['category'] ?? 'Other', 'taxAmount');
        $result['taxes'] = [
            'kpis' => [
                ['lbl' => 'Tax Collected', 'val' => pa_money($taxCollected), 'cls' => 'good'],
                ['lbl' => 'Tax Paid', 'val' => pa_money($taxPaid), 'cls' => 'bad'],
                ['lbl' => 'Net Tax Liability', 'val' => pa_money($taxCollected - $taxPaid), 'cls' => ''],
            ],
            'collectedVsPaid' => [
                'collected' => pa_month_series($revenue, 'date', 'taxAmount'),
                'paid' => pa_month_series($expenses, 'date', 'taxAmount'),
            ],
            'byCategory' => pa_top_pie(array_filter($taxByCat, fn($v) => $v > 0), 6),
        ];
    }

    // --- Geographic (customer and supplier countries) ---
    // Names are canonicalised to the map's GeoJSON names, or the choropleth colours nothing.
    $destCountries = [];
    foreach ($revenue as $r) {
        $c = pa_canon_country(pa_customer_country($customers, $r['customerId'] ?? ''));
        if ($c) { $destCountries[$c] = ($destCountries[$c] ?? 0) + (float) ($r['amount'] ?? 0); }
    }
    $origCountries = [];
    foreach ($expenses as $r) {
        $c = pa_canon_country(pa_supplier_country($suppliers, $r['supplierId'] ?? ''));
        if ($c) { $origCountries[$c] = ($origCountries[$c] ?? 0) + (float) ($r['amount'] ?? 0); }
    }
    if ($destCountries || $origCountries) {
        $result['tabs'][] = 'geographic';
        $result['geographic'] = [
            'destination' => pa_top_pie($destCountries, 6),
            'origin' => pa_top_pie($origCountries, 6),
            'map' => array_map(fn($k, $v) => ['name' => $k, 'value' => round($v)], array_keys($destCountries), array_values($destCountries)),
        ];
    }

    // ---- Cleaned data: one tab per entity present, matching the download ----
    $result['cleanedSheets'] = pa_cleaned_sheets($n);

    return $result;
}

/**
 * Display-ready version of the cleaned workbook: the same per-entity sheets the
 * download produces (pa_build_workbook), but with money columns formatted and
 * numeric columns flagged for right-alignment, so the on-page table and the
 * .xlsx stay in lockstep.
 */
function pa_cleaned_sheets(array $normalized): array
{
    $money = ['Unit Price', 'Tax', 'Total', 'Amount', 'Subtotal', 'Paid', 'Balance'];
    $numeric = array_merge($money, ['Quantity', 'Reorder Point']);
    $out = [];
    foreach (pa_build_workbook($normalized) as $label => $sheet) {
        $headers = $sheet['headers'];
        $aligns = array_map(fn($h) => in_array($h, $numeric, true) ? 'right' : 'left', $headers);
        $rows = array_map(function ($r) use ($headers, $money) {
            $cells = [];
            foreach ($r as $i => $v) {
                $h = $headers[$i] ?? '';
                if (is_int($v) || is_float($v)) {
                    if (in_array($h, $money, true)) {
                        $cells[] = ($GLOBALS['pa_cur_symbol'] ?? '$') . number_format((float) $v, 2);
                    } else {
                        // Quantity / reorder point: plain integer-ish.
                        $cells[] = rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
                    }
                } else {
                    $cells[] = (string) $v;
                }
            }
            return $cells;
        }, $sheet['rows']);
        $out[] = ['label' => $label, 'columns' => $headers, 'aligns' => $aligns, 'rows' => $rows];
    }
    return $out;
}

function pa_product_name(array $products, string $id): ?string
{
    foreach ($products as $p) { if (($p['id'] ?? '') === $id) { return $p['name'] ?? null; } }
    return null;
}
function pa_customer_name(array $customers, string $id): ?string
{
    foreach ($customers as $c) { if (($c['id'] ?? '') === $id) { return $c['name'] ?? null; } }
    return null;
}
function pa_customer_country(array $customers, string $id): ?string
{
    foreach ($customers as $c) { if (($c['id'] ?? '') === $id) { return $c['country'] ?? null; } }
    return null;
}
function pa_supplier_country(array $suppliers, string $id): ?string
{
    foreach ($suppliers as $s) { if (($s['id'] ?? '') === $id) { return $s['country'] ?? null; } }
    return null;
}

/**
 * Canonicalize a country name to the world-map GeoJSON's spelling so the
 * choropleth matches. Unknown / already-canonical names pass through unchanged.
 */
function pa_canon_country(?string $name): string
{
    $n = trim((string) $name);
    if ($n === '') { return ''; }
    $key = trim(strtolower(preg_replace('/[.\s]+/', ' ', $n)));
    static $alias = [
        'usa' => 'United States', 'us' => 'United States', 'u s' => 'United States',
        'u s a' => 'United States', 'united states of america' => 'United States', 'america' => 'United States',
        'uk' => 'United Kingdom', 'u k' => 'United Kingdom', 'gb' => 'United Kingdom',
        'britain' => 'United Kingdom', 'great britain' => 'United Kingdom', 'england' => 'United Kingdom',
        'uae' => 'United Arab Emirates', 'u a e' => 'United Arab Emirates',
        'russian federation' => 'Russia', 'czechia' => 'Czech Rep.', 'czech republic' => 'Czech Rep.',
        'south korea' => 'Korea', 'republic of korea' => 'Korea', 'north korea' => 'Dem. Rep. Korea',
        'holland' => 'Netherlands', 'the netherlands' => 'Netherlands',
    ];
    return $alias[$key] ?? $n;
}

/** Monthly net profit (revenue - expenses) series. */
function pa_month_diff_series(array $revenue, array $expenses): array
{
    $rev = []; $exp = [];
    foreach ($revenue as $r) { $m = (int) date('n', strtotime($r['date'] ?? 'now')); $rev[$m] = ($rev[$m] ?? 0) + (float) ($r['amount'] ?? 0); }
    foreach ($expenses as $r) { $m = (int) date('n', strtotime($r['date'] ?? 'now')); $exp[$m] = ($exp[$m] ?? 0) + (float) ($r['amount'] ?? 0); }
    $months = array_unique(array_merge(array_keys($rev), array_keys($exp)));
    sort($months);
    $cats = []; $data = [];
    foreach ($months as $m) { $cats[] = date('M', mktime(0, 0, 0, $m, 1)); $data[] = round(($rev[$m] ?? 0) - ($exp[$m] ?? 0), 2); }
    return ['cats' => $cats, 'data' => $data];
}
