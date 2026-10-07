<?php
/**
 * Reusable substantial SVG illustrations for in-article section breaks.
 *
 * Authoring: drop a token into a section's HTML where it logically fits, e.g.
 *   {{illustration:spreadsheet-to-books}}
 * guides/article-page.php expands the token to the markup below.
 *
 * Style rules:
 *   - Line work uses stroke="currentColor"; the figure sets the line color via
 *     CSS (.article-illustration), so it stays theme-consistent.
 *   - Accent strokes use class="ai-accent" (CSS -> var(--primary-blue)).
 *   - Accent fills use class="ai-accent-fill"; soft background fills use
 *     class="ai-soft". Sizing comes from the figure modifier (--band / --spot).
 *   - SVGs are decorative; the wrapping <figure> is aria-hidden.
 *
 * Each entry: ['kind' => 'band'|'spot', 'svg' => '<svg>...</svg>'].
 *
 * Available names and what they depict (use the closest fit per article):
 *   price-trend          rising cost / price climbing            (spot)
 *   forecast             a forecast: solid past + dotted future  (spot)
 *   coins                money, free/cheap, savings              (spot)
 *   invoice-doc          an invoice / billing                    (spot)
 *   receipt-scan         scanning a receipt with a phone         (spot)
 *   checklist            steps / what-to-include / a process     (spot)
 *   calendar-due         due dates / payment terms / deadlines   (spot)
 *   compare-scale        comparing two options (X vs Y)          (spot)
 *   inventory-boxes      stock / inventory                       (spot)
 *   cashflow-cycle       cash flow / recurring / seasonal money  (spot)
 *   spreadsheet-to-books spreadsheet -> organized books, switching (band)
 *   bank-import          bank statement / CSV import -> records  (band)
 */

function article_illustration(string $name): string
{
    static $lib = null;
    if ($lib === null) {
        $lib = _article_illustration_definitions();
    }
    if (!isset($lib[$name])) {
        return '<!-- unknown illustration: ' . htmlspecialchars($name) . ' -->';
    }
    $kind = $lib[$name]['kind'] ?? 'spot';
    return '<figure class="article-illustration article-illustration--' . $kind . '" aria-hidden="true">'
         . $lib[$name]['svg']
         . '</figure>';
}

function _article_illustration_definitions(): array
{
    return [

        // Rising price / cost trend.
        'price-trend' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 260 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect class="ai-soft" x="42" y="104" width="34" height="52" rx="5" stroke="none"/>
  <rect class="ai-soft" x="92" y="74" width="34" height="82" rx="5" stroke="none"/>
  <rect class="ai-soft" x="142" y="50" width="34" height="106" rx="5" stroke="none"/>
  <path d="M30 156 H214"/>
  <path class="ai-accent" d="M48 116 L109 86 L159 60 L205 36"/>
  <circle class="ai-accent" cx="205" cy="36" r="15" fill="#fff"/>
  <text class="ai-accent-fill" x="205" y="42" font-size="17" text-anchor="middle" font-weight="700" stroke="none">$</text>
</svg>
SVG,
        ],

        // Forecast: solid past line continuing as a dotted future projection, inside a widening confidence band. The signature visual for the predictive-analytics / forecasting cluster.
        'forecast' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 260 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path class="ai-soft" d="M150 92 L216 46 L216 86 Z" stroke="none"/>
  <path d="M30 22 V150 H232"/>
  <path d="M42 125 L72 110 L100 118 L126 100 L150 92"/>
  <path class="ai-accent" d="M150 92 L216 64" stroke-dasharray="2 7"/>
  <circle cx="150" cy="92" r="4" fill="currentColor" stroke="none"/>
  <circle class="ai-accent" cx="216" cy="64" r="6" fill="#fff"/>
</svg>
SVG,
        ],

        // Stacked coins: money, free/cheap, savings.
        'coins' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <ellipse class="ai-soft" cx="74" cy="150" rx="40" ry="13" stroke="none"/>
  <ellipse cx="74" cy="58" rx="30" ry="11"/>
  <path d="M44 58 V78 c0 6 13.4 11 30 11 s30 -5 30 -11 V58"/>
  <path d="M44 78 V98 c0 6 13.4 11 30 11 s30 -5 30 -11 V78"/>
  <path d="M44 98 V118 c0 6 13.4 11 30 11 s30 -5 30 -11 V98"/>
  <circle class="ai-accent" cx="158" cy="62" r="30"/>
  <text class="ai-accent-fill" x="158" y="71" font-size="30" text-anchor="middle" font-weight="700" stroke="none">$</text>
</svg>
SVG,
        ],

        // Invoice / billing document.
        'invoice-doc' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path class="ai-soft" d="M52 24 h70 l28 28 v118 h-98 z" stroke="none"/>
  <path d="M44 16 h70 l28 28 v118 h-98 z"/>
  <path d="M114 16 v28 h28"/>
  <line x1="60" y1="66" x2="126" y2="66"/>
  <line x1="60" y1="86" x2="126" y2="86"/>
  <line x1="60" y1="106" x2="104" y2="106"/>
  <circle class="ai-accent" cx="120" cy="138" r="22" fill="#fff"/>
  <text class="ai-accent-fill" x="120" y="146" font-size="22" text-anchor="middle" font-weight="700" stroke="none">$</text>
</svg>
SVG,
        ],

        // Phone scanning a receipt.
        'receipt-scan' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 200" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="62" y="18" width="96" height="164" rx="14"/>
  <path class="ai-soft" d="M88 48 H132 V146 L125 140 L118 146 L111 140 L104 146 L97 140 L88 146 Z" stroke="none"/>
  <path d="M88 48 H132 V146 L125 140 L118 146 L111 140 L104 146 L97 140 L88 146 Z"/>
  <line x1="97" y1="66" x2="123" y2="66"/>
  <line x1="97" y1="82" x2="123" y2="82"/>
  <line x1="97" y1="98" x2="115" y2="98"/>
  <line class="ai-accent" x1="70" y1="112" x2="150" y2="112" stroke-width="3"/>
</svg>
SVG,
        ],

        // Clipboard checklist: steps, what-to-include, a process.
        'checklist' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 190 200" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="40" y="26" width="110" height="150" rx="10"/>
  <rect x="74" y="16" width="42" height="22" rx="6"/>
  <path class="ai-accent" d="M58 66 l7 7 l13 -14"/>
  <line x1="92" y1="66" x2="132" y2="66"/>
  <path class="ai-accent" d="M58 102 l7 7 l13 -14"/>
  <line x1="92" y1="102" x2="132" y2="102"/>
  <path class="ai-accent" d="M58 138 l7 7 l13 -14"/>
  <line x1="92" y1="138" x2="132" y2="138"/>
</svg>
SVG,
        ],

        // Calendar with a marked date: due dates, terms, deadlines.
        'calendar-due' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="30" y="34" width="140" height="130" rx="12"/>
  <line x1="30" y1="68" x2="170" y2="68"/>
  <line x1="64" y1="22" x2="64" y2="46"/>
  <line x1="136" y1="22" x2="136" y2="46"/>
  <circle class="ai-soft" cx="100" cy="118" r="26" stroke="none"/>
  <circle class="ai-accent" cx="100" cy="118" r="26"/>
  <path class="ai-accent" d="M88 118 l8 8 l16 -18"/>
</svg>
SVG,
        ],

        // Balance scale: comparing two options.
        'compare-scale' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 240 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <line x1="120" y1="34" x2="120" y2="158"/>
  <line x1="86" y1="158" x2="154" y2="158"/>
  <line x1="56" y1="50" x2="184" y2="50"/>
  <circle cx="120" cy="34" r="6"/>
  <path d="M56 50 L38 92 a18 18 0 0 0 36 0 Z" class="ai-soft" stroke="none"/>
  <path d="M56 50 L38 92 a18 18 0 0 0 36 0 Z"/>
  <path class="ai-accent" d="M184 50 L166 92 a18 18 0 0 0 36 0 Z"/>
</svg>
SVG,
        ],

        // Stacked boxes: inventory / stock.
        'inventory-boxes' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 230 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect class="ai-soft" x="48" y="92" width="62" height="58" rx="4" stroke="none"/>
  <rect x="48" y="92" width="62" height="58" rx="4"/>
  <line x1="48" y1="112" x2="110" y2="112"/>
  <line x1="79" y1="92" x2="79" y2="112"/>
  <rect x="120" y="92" width="62" height="58" rx="4"/>
  <line x1="120" y1="112" x2="182" y2="112"/>
  <line x1="151" y1="92" x2="151" y2="112"/>
  <rect class="ai-accent" x="84" y="32" width="62" height="58" rx="4"/>
  <line class="ai-accent" x1="84" y1="52" x2="146" y2="52"/>
  <line class="ai-accent" x1="115" y1="32" x2="115" y2="52"/>
</svg>
SVG,
        ],

        // Circular arrows with $: cash flow, recurring, seasonal.
        'cashflow-cycle' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <circle class="ai-soft" cx="100" cy="96" r="48" stroke="none"/>
  <path class="ai-accent" d="M100 44 a52 52 0 0 1 45 26"/>
  <path class="ai-accent" d="M145 44 v28 h-28"/>
  <path class="ai-accent" d="M100 148 a52 52 0 0 1 -45 -26"/>
  <path class="ai-accent" d="M55 148 v-28 h28"/>
  <text class="ai-accent-fill" x="100" y="106" font-size="34" text-anchor="middle" font-weight="700" stroke="none">$</text>
</svg>
SVG,
        ],

        // Messy spreadsheet -> arrow -> organized books. Switching/migration.
        'spreadsheet-to-books' => [
            'kind' => 'band',
            'svg' => <<<'SVG'
<svg viewBox="0 0 600 150" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="55" y="32" width="150" height="92" rx="6"/>
  <line x1="55" y1="56" x2="205" y2="56"/><line x1="55" y1="80" x2="205" y2="80"/><line x1="55" y1="104" x2="205" y2="104"/>
  <line x1="105" y1="32" x2="105" y2="124"/><line x1="155" y1="32" x2="155" y2="124"/>
  <line x1="250" y1="78" x2="338" y2="78"/><path d="M323 66 L342 78 L323 90"/>
  <rect x="392" y="30" width="118" height="94" rx="8"/>
  <line x1="410" y1="54" x2="492" y2="54"/><line x1="410" y1="72" x2="492" y2="72"/><line x1="410" y1="90" x2="468" y2="90"/>
  <circle class="ai-accent" cx="506" cy="102" r="19" fill="#fff"/>
  <path class="ai-accent" d="M497 102 l7 7 l12 -14"/>
</svg>
SVG,
        ],

        // Bank statement / CSV -> organized rows. Bank/CSV import.
        'bank-import' => [
            'kind' => 'band',
            'svg' => <<<'SVG'
<svg viewBox="0 0 600 150" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path d="M70 44 L130 22 L190 44"/>
  <line x1="78" y1="44" x2="78" y2="104"/><line x1="110" y1="44" x2="110" y2="104"/>
  <line x1="150" y1="44" x2="150" y2="104"/><line x1="182" y1="44" x2="182" y2="104"/>
  <line x1="62" y1="104" x2="198" y2="104"/><line x1="56" y1="118" x2="204" y2="118"/>
  <line x1="252" y1="78" x2="340" y2="78"/><path d="M325 66 L344 78 L325 90"/>
  <rect x="392" y="30" width="142" height="94" rx="8"/>
  <line x1="392" y1="56" x2="534" y2="56"/>
  <line x1="428" y1="30" x2="428" y2="124"/>
  <path class="ai-accent" d="M404 88 l7 7 l12 -14"/>
  <line x1="446" y1="74" x2="516" y2="74"/><line x1="446" y1="100" x2="516" y2="100"/>
</svg>
SVG,
        ],

        // Financial statement / report with a small bar chart: P&L, income
        // statement, tax summary, any generated report.
        'report-statement' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path class="ai-soft" d="M48 24 h104 v150 h-104 z" stroke="none"/>
  <rect x="40" y="20" width="104" height="150" rx="8"/>
  <line x1="58" y1="46" x2="126" y2="46"/>
  <line x1="58" y1="62" x2="104" y2="62"/>
  <rect class="ai-soft" x="58" y="120" width="15" height="28" rx="2" stroke="none"/>
  <rect class="ai-soft" x="82" y="104" width="15" height="44" rx="2" stroke="none"/>
  <rect class="ai-accent" x="106" y="86" width="15" height="62" rx="2"/>
  <line x1="52" y1="148" x2="132" y2="148"/>
</svg>
SVG,
        ],

        // T-account with an equals badge: a balance sheet (assets = liabilities
        // + equity), things that balance.
        'balance-sheet' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="34" y="26" width="152" height="140" rx="8"/>
  <line x1="34" y1="54" x2="186" y2="54"/>
  <line x1="110" y1="54" x2="110" y2="166"/>
  <line x1="50" y1="76" x2="96" y2="76"/>
  <line x1="50" y1="96" x2="96" y2="96"/>
  <line x1="50" y1="116" x2="88" y2="116"/>
  <line x1="124" y1="76" x2="170" y2="76"/>
  <line x1="124" y1="96" x2="162" y2="96"/>
  <circle class="ai-accent" cx="110" cy="150" r="15" fill="#fff"/>
  <path class="ai-accent" d="M103 146 h14 M103 154 h14"/>
</svg>
SVG,
        ],

        // Stacked bar split into an accent profit segment and a soft cost/tax
        // segment: gross vs net, what you keep vs what goes out.
        'profit-split' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect class="ai-soft" x="82" y="98" width="58" height="54" rx="5" stroke="none"/>
  <rect x="82" y="98" width="58" height="54" rx="5"/>
  <rect class="ai-accent" x="82" y="40" width="58" height="56" rx="5"/>
  <path d="M58 152 h108"/>
  <path class="ai-accent" d="M150 52 h16"/>
  <path d="M150 124 h16"/>
</svg>
SVG,
        ],

        // Jar of coins with a percent badge: setting money aside, tax savings.
        'tax-jar' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 190" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path class="ai-soft" d="M54 80 h84 v66 a14 14 0 0 1 -14 14 h-56 a14 14 0 0 1 -14 -14 z" stroke="none"/>
  <path d="M54 80 h84 v66 a14 14 0 0 1 -14 14 h-56 a14 14 0 0 1 -14 -14 z"/>
  <rect x="64" y="60" width="64" height="22" rx="6"/>
  <ellipse cx="96" cy="134" rx="24" ry="8"/>
  <ellipse cx="96" cy="118" rx="24" ry="8"/>
  <circle class="ai-accent" cx="150" cy="60" r="22" fill="#fff"/>
  <text class="ai-accent-fill" x="150" y="68" font-size="20" text-anchor="middle" font-weight="700" stroke="none">%</text>
</svg>
SVG,
        ],

        // Two separated wallets with a dashed divider: keeping business and
        // personal money apart.
        'wallet-split' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 240 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="28" y="62" width="80" height="58" rx="9"/>
  <line x1="28" y1="84" x2="108" y2="84"/>
  <circle cx="68" cy="100" r="8"/>
  <rect class="ai-accent" x="132" y="62" width="80" height="58" rx="9"/>
  <line class="ai-accent" x1="132" y1="84" x2="212" y2="84"/>
  <circle class="ai-accent" cx="172" cy="100" r="8"/>
  <path class="ai-accent" d="M120 34 v112" stroke-dasharray="2 9"/>
</svg>
SVG,
        ],

        // Price tag with a $: pricing your work, setting rates.
        'price-tag' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 200 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect class="ai-soft" x="48" y="52" width="104" height="76" rx="12" stroke="none"/>
  <rect x="48" y="52" width="104" height="76" rx="12"/>
  <circle cx="70" cy="74" r="7"/>
  <path d="M64 60 l-22 -22"/>
  <text class="ai-accent-fill" x="112" y="102" font-size="34" text-anchor="middle" font-weight="700" stroke="none">$</text>
</svg>
SVG,
        ],

        // App window with a check: choosing / comparing software, the pick.
        'app-check' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <rect x="34" y="34" width="152" height="112" rx="10"/>
  <line x1="34" y1="60" x2="186" y2="60"/>
  <circle cx="50" cy="47" r="3.5" fill="currentColor" stroke="none"/>
  <circle cx="62" cy="47" r="3.5" fill="currentColor" stroke="none"/>
  <circle cx="74" cy="47" r="3.5" fill="currentColor" stroke="none"/>
  <circle class="ai-soft" cx="110" cy="102" r="30" stroke="none"/>
  <circle class="ai-accent" cx="110" cy="102" r="30"/>
  <path class="ai-accent" d="M97 102 l9 9 l18 -20"/>
</svg>
SVG,
        ],

        // Shop front with a check badge: a new business getting set up.
        'storefront' => [
            'kind' => 'spot',
            'svg' => <<<'SVG'
<svg viewBox="0 0 220 180" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
  <path d="M46 80 l64 -36 l64 36"/>
  <path class="ai-soft" d="M58 80 h104 v74 h-104 z" stroke="none"/>
  <rect x="58" y="80" width="104" height="74" rx="4"/>
  <rect x="98" y="112" width="24" height="42" rx="3"/>
  <rect x="72" y="98" width="18" height="18" rx="2"/>
  <rect x="130" y="98" width="18" height="18" rx="2"/>
  <circle class="ai-accent" cx="152" cy="66" r="14" fill="#fff"/>
  <path class="ai-accent" d="M146 66 l5 5 l9 -10"/>
</svg>
SVG,
        ],

    ];
}
