<?php
// etsy-fee-calculator/index.php
// Free Etsy fee calculator: works out every Etsy fee on a sale and what the
// seller actually keeps, and solves the same equation backwards to find the
// price that hits a target profit. Four countries, because payment processing
// and the regulatory operating fee are set by where the shop is based.
//
// Reuses the shared tool shell (shared/layout.php): header, "All tools"
// breadcrumb, canonical/OG, schema. Math is client-side (scripts/calc.js).
// Every rate comes from data/fees.php, which also feeds the on-page tables, so
// the numbers in the copy and the numbers in the calculator cannot drift apart.

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../shared/_base.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('etsycalc_tool');
}

$fees      = require __DIR__ . '/data/fees.php';
$shared    = $fees['shared'];
$countries = $fees['countries'];
$verified  = date('F Y', strtotime($fees['verified']));

$page_title = 'Etsy Fee Calculator: What You Actually Keep Per Sale | Argo Books';
$page_description = 'Free Etsy fee calculator. Enter your price to see every Etsy fee and your real profit, or enter the profit you want and get the price to list at. Covers US, Canada, UK, and Australia rates.';
$canonical_url = 'https://argorobots.com/etsy-fee-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=etsycalc-tool&amp;utm_source=etsy-fee-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

// Percentages formatted once here so the copy, the tables, and the calculator
// all read from data/fees.php rather than from hardcoded strings.
$pct = static function (float $rate, int $decimals = 2): string {
    return rtrim(rtrim(number_format($rate * 100, $decimals), '0'), '.') . '%';
};

// Rates handed to the client. Keys match the shape scripts/calc.js expects.
$client_rates = [];
foreach ($countries as $code => $c) {
    $client_rates[$code] = [
        'name'                 => $c['name'],
        'currency'             => $c['currency'],
        'locale'               => $c['locale'],
        'transactionPct'       => $shared['transaction_pct'],
        'processingPct'        => $c['processing_pct'],
        'processingFlat'       => $c['processing_flat'],
        'regulatoryPct'        => $c['regulatory_pct'],
        'offsiteAdsCap'        => $shared['offsite_ads_cap'],
        'currencyConversionPct'=> $shared['currency_conversion_pct'],
    ];
}

// Fee reference, rendered into the "every fee explained" section. Kept as data
// so the fee list and the calculator's breakdown rows stay in the same order.
$fee_reference = [
    [
        'key'   => 'listing',
        'name'  => 'Listing fee',
        'rate'  => '$0.20 USD',
        'base'  => 'Per listing, per sale',
        'body'  => 'Charged when you publish a listing, and again each time one sells, because the listing renews automatically. A listing lasts four months. Sellers outside the US are billed the US dollar amount converted to their currency, which is why the calculator lets you edit it.',
    ],
    [
        'key'   => 'transaction',
        'name'  => 'Transaction fee',
        'rate'  => $pct($shared['transaction_pct'], 1),
        'base'  => 'Item price plus shipping and gift wrap',
        'body'  => 'The one most sellers know about, and the one most sellers get wrong. It applies to the whole amount the buyer pays, so the shipping you charge is included. Charging $5 shipping does not hand you $5.',
    ],
    [
        'key'   => 'processing',
        'name'  => 'Payment processing',
        'rate'  => 'Varies by country',
        'base'  => 'Order total, percentage plus a flat amount',
        'body'  => 'Etsy Payments takes a cut of the transaction plus a small fixed charge. The rate depends on where your shop is based, not where the buyer is. See the country table below.',
    ],
    [
        'key'   => 'regulatory',
        'name'  => 'Regulatory operating fee',
        'rate'  => 'Some countries only',
        'base'  => 'Order total',
        'body'  => 'A percentage Etsy charges in countries with digital services taxes and similar regulation. It does not apply in the US or Australia. Canada, the UK, and much of Europe pay it, and it is the fee most calculators forget.',
    ],
    [
        'key'   => 'offsiteAds',
        'name'  => 'Offsite Ads',
        'rate'  => $pct($shared['offsite_ads_under'], 0) . ' or ' . $pct($shared['offsite_ads_over'], 0),
        'base'  => 'Order total, capped per order',
        'body'  => 'Only charged when a buyer reaches you through an ad Etsy paid for elsewhere. Under $' . number_format($shared['offsite_ads_threshold']) . ' in sales over the past year you pay ' . $pct($shared['offsite_ads_under'], 0) . ' and can opt out. At or above that, the rate drops to ' . $pct($shared['offsite_ads_over'], 0) . ' but you can no longer opt out. Capped at $' . number_format($shared['offsite_ads_cap']) . ' per order.',
    ],
    [
        'key'   => 'currencyConversion',
        'name'  => 'Currency conversion',
        'rate'  => $pct($shared['currency_conversion_pct'], 1),
        'base'  => 'Order total',
        'body'  => 'Charged when your listings are in one currency and your bank account is in another. Listing in the same currency you get paid in removes it entirely.',
    ],
];

$mistakes = [
    ['t' => 'Assuming shipping income is yours to keep.', 'b' => 'Etsy applies the transaction and processing fees to the shipping you charge, the same as the item. Free shipping with the cost built into the price works out the same way, so pick whichever gets you more sales.'],
    ['t' => 'Forgetting the listing fee renews on every sale.', 'b' => 'It is $0.20 each time an item sells, not $0.20 once. On a multi-quantity listing that is a fresh 20 cents per unit sold.'],
    ['t' => 'Ignoring Offsite Ads until the bill arrives.', 'b' => 'A 15% fee on one order can wipe out the profit on several. If you are under the threshold and your margins are thin, check whether you are opted in.'],
    ['t' => 'Pricing off revenue instead of profit.', 'b' => 'A $30 sale is not $30. Once fees and materials come out, the take-home is often under a third of the sticker price. Price from what you keep, not what the buyer pays.'],
    ['t' => 'Never counting your own labour.', 'b' => 'If your time is not in the cost, the profit on screen is really just unpaid wages. Put an hourly rate on it, even a modest one.'],
];

$lower_fees = [
    ['t' => 'Opt out of Offsite Ads while you still can.', 'b' => 'Below $' . number_format($shared['offsite_ads_threshold']) . ' in trailing-year sales, opting out is your call and removes the single largest fee on this page. Above it, the choice is gone.'],
    ['t' => 'List in the currency you get paid in.', 'b' => 'That deletes the ' . $pct($shared['currency_conversion_pct'], 1) . ' conversion fee outright.'],
    ['t' => 'Sell more per order.', 'b' => 'The flat parts of the fees, the listing fee and the fixed slice of processing, do not grow with the order. One $60 order costs less in fees than two $30 orders.'],
    ['t' => 'Raise the price rather than trimming the product.', 'b' => 'Fees are a percentage, so cheapening materials to protect a margin usually costs you more in reviews than the few cents it saves.'],
];

// FAQ defined once, rendered into both the visible section AND the FAQPage
// JSON-LD below, so the two can never drift apart.
$faqs = [
    [
        'q' => 'How much does Etsy take per sale?',
        'a' => 'On a $30 US sale with no Offsite Ads, Etsy takes about 11% of the order: a 6.5% transaction fee, 3% plus $0.25 for payment processing, and a $0.20 listing fee. The share is higher on cheaper items, around 14% on a $10 order, because the flat charges stay the same no matter the price. If the sale came through Offsite Ads, add another 12% or 15% on top, which pushes the total past 25%. Sellers in Canada and the UK pay slightly more again because of the regulatory operating fee.',
    ],
    [
        'q' => 'Does Etsy charge fees on shipping?',
        'a' => 'Yes. The transaction fee and payment processing fee both apply to the total amount the buyer pays, and that includes shipping and gift wrapping. Charging $5 for shipping does not put $5 in your pocket. This is the single most common surprise in an Etsy payout.',
    ],
    [
        'q' => 'Are Etsy fees tax deductible?',
        'a' => 'In the US, Canada, the UK, and Australia, fees charged by a selling platform are an ordinary business expense and are deductible against your business income, provided you are selling as a business rather than a hobby. They are only deductible if you record them, which means keeping the fee figures from your Etsy statements rather than only the amount that landed in your bank account.',
    ],
    [
        'q' => 'Why is my Etsy payout lower than the sale price?',
        'a' => 'Because the deposit you see is the order total minus every fee. Between the transaction fee, payment processing, the listing renewal, and possibly Offsite Ads, a $30 order can deposit closer to $22 before you have paid for a single material. The breakdown in the calculator above shows exactly where each piece goes.',
    ],
    [
        'q' => 'Can I avoid Offsite Ads fees?',
        'a' => 'Only below the threshold. If your shop has made less than $10,000 in the past 365 days you can opt out in your shop settings, and the fee disappears. Once you pass $10,000, participation becomes mandatory, although the rate drops from 15% to 12%. The fee is capped at $100 per order either way.',
    ],
    [
        'q' => 'What is the Etsy regulatory operating fee?',
        'a' => 'It is a percentage of the order total that Etsy charges sellers in certain countries to cover digital services taxes and similar regulation. Canada pays 1.15% and the UK pays 0.32%. There is no regulatory operating fee for sellers based in the US or Australia. Most fee calculators leave it out, which makes them wrong for Canadian and UK shops.',
    ],
    [
        'q' => 'What should I charge on Etsy to make a decent profit?',
        'a' => 'Work backwards instead of forwards. Decide what you want to earn from one sale, add up your materials, your time, and your shipping cost, then let the calculator solve for the price. Switch it to "What should I charge?" mode, enter your target profit, and it returns the listing price that leaves you with exactly that after every fee.',
    ],
    [
        'q' => 'Do these fee rates change?',
        'a' => 'Yes. Etsy has raised the transaction fee before and adjusts payment processing rates by country from time to time. The rates on this page were checked in ' . $verified . ', and the date is printed next to the calculator so you can see how current they are.',
    ],
];

// Schema: SoftwareApplication (the calculator) + FAQPage, as a @graph. The FAQPage node is built from $faqs by partials/faq.php, the same array the visible accordion renders from.
$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Etsy Fee Calculator',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'creator' => ['@id' => 'https://argorobots.com/#organization'],
            'url' => $canonical_url,
        ],
        argo_faq_schema_node($faqs),
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema(['Home' => '/', 'Free Tools' => '/tools/', 'Etsy Fee Calculator' => $canonical_url]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/etsy-fee-calculator/styles/etsy-calculator.css">'
    . '<script>window.ETSY_FEES = ' . json_encode($client_rates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/etsy-fee-calculator/scripts/main.js"></script>';

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Etsy Fee Calculator</h1>
    <p class="site-hero-tagline">See every fee Etsy takes out of a sale and what actually lands in your account. Or work it the other way: tell it the profit you want, and it tells you what to charge.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Selling regularly? Argo Books tracks your fees, materials, and real profit in one place.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="etsy-modes" role="tablist" aria-label="Calculator mode">
    <button type="button" class="etsy-mode-btn is-active" data-ec-mode="forward" role="tab" aria-selected="true">What do I keep?</button>
    <button type="button" class="etsy-mode-btn" data-ec-mode="reverse" role="tab" aria-selected="false">What should I charge?</button>
  </div>

  <div class="calc-grid">
    <form class="calc-form" autocomplete="off" aria-label="Etsy fee inputs">

      <div class="etsy-fieldset">
        <h2 class="etsy-legend">The sale</h2>

        <div class="calc-field">
          <label for="ec-country">Where your shop is based</label>
          <select id="ec-country" data-ec="country">
            <?php foreach ($countries as $code => $c): ?>
              <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['currency']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <p class="calc-hint">Sets your payment processing rate and whether a regulatory operating fee applies.</p>
        </div>

        <div class="calc-field" data-ec-when="forward">
          <label for="ec-price">Item price</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-price" data-ec="itemPrice" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">What the listing sells for, before shipping.</p>
        </div>

        <div class="calc-field" data-ec-when="reverse" hidden>
          <label for="ec-target">Profit you want, per sale</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-target" data-ec="targetProfit" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">What you want left over after Etsy and after your own costs.</p>
        </div>

        <div class="calc-field">
          <label for="ec-ship-charged">Shipping you charge the buyer</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-ship-charged" data-ec="shippingCharged" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">Leave at zero for free shipping. Etsy charges fees on this too.</p>
        </div>
      </div>

      <div class="etsy-fieldset">
        <h2 class="etsy-legend">Your costs</h2>

        <div class="calc-field">
          <label for="ec-materials">Materials, per item</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-materials" data-ec="materials" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">Supplies plus packaging and labels. Made in batches? Divide the batch cost by how many it makes.</p>
        </div>

        <div class="calc-field">
          <label for="ec-labour">Your time, per item</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-labour" data-ec="labour" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">An hourly rate you would be happy with, times how long one takes.</p>
        </div>

        <div class="calc-field">
          <label for="ec-ship-cost">Shipping and packing you pay</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-ship-cost" data-ec="shippingCost" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
          </div>
          <p class="calc-hint">The label plus the box, mailer, and filler.</p>
        </div>
      </div>

      <details class="etsy-advanced">
        <summary>Fees that only apply sometimes</summary>

        <div class="calc-field">
          <label for="ec-ads">Offsite Ads</label>
          <select id="ec-ads" data-ec="offsiteAdsRate">
            <option value="0">This sale did not come from Offsite Ads</option>
            <option value="<?= htmlspecialchars((string)$shared['offsite_ads_under']) ?>">Yes, under $<?= number_format($shared['offsite_ads_threshold']) ?> in sales (<?= $pct($shared['offsite_ads_under'], 0) ?>)</option>
            <option value="<?= htmlspecialchars((string)$shared['offsite_ads_over']) ?>">Yes, over $<?= number_format($shared['offsite_ads_threshold']) ?> in sales (<?= $pct($shared['offsite_ads_over'], 0) ?>)</option>
          </select>
          <p class="calc-hint">Only charged on orders that came through an ad Etsy paid for. Capped at $<?= number_format($shared['offsite_ads_cap']) ?> per order.</p>
        </div>

        <div class="calc-field calc-field-check">
          <label for="ec-conversion">
            <input id="ec-conversion" data-ec="currencyConversion" type="checkbox">
            <span>My listings are in a different currency than my bank account</span>
          </label>
          <p class="calc-hint">Adds Etsy's <?= $pct($shared['currency_conversion_pct'], 1) ?> currency conversion fee.</p>
        </div>

        <div class="calc-field">
          <label for="ec-listing">Listing fee</label>
          <div class="calc-money">
            <span class="calc-money-affix" data-ec-symbol>$</span>
            <input id="ec-listing" data-ec="listingFee" type="number" inputmode="decimal" min="0" step="0.01" value="<?= htmlspecialchars((string)$shared['listing_fee']) ?>">
          </div>
          <p class="calc-hint">Etsy bills $<?= number_format($shared['listing_fee'], 2) ?> USD, so outside the US your amount moves with the exchange rate. Edit it if you want to be exact.</p>
        </div>
      </details>

      <div class="etsy-fieldset">
        <h2 class="etsy-legend">Your volume</h2>
        <div class="calc-field">
          <label for="ec-volume">Sales per month</label>
          <input id="ec-volume" data-ec="salesPerMonth" type="number" inputmode="numeric" min="0" step="1" placeholder="0">
          <p class="calc-hint">Optional. Turns the per-sale numbers into a yearly picture.</p>
        </div>
      </div>
    </form>

    <div class="calc-results" data-ec-results aria-live="polite">
      <div class="calc-headline">
        <span class="calc-headline-label" data-ec="headlineLabel">You keep, per sale</span>
        <span class="calc-headline-amount" data-ec="headline">$0.00</span>
        <span class="calc-headline-sub" data-ec="headlineSub">Enter a price to see the breakdown</span>
      </div>

      <dl class="calc-breakdown">
        <div class="calc-breakdown-row etsy-row-total">
          <dt>Order total</dt>
          <dd data-ec="orderTotal">$0.00</dd>
        </div>

        <div class="calc-breakdown-group">Etsy takes</div>
        <div class="calc-breakdown-row etsy-row-fee">
          <dt>Listing fee</dt>
          <dd data-ec="fee-listing">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-fee">
          <dt>Transaction fee <span class="etsy-row-note"><?= $pct($shared['transaction_pct'], 1) ?></span></dt>
          <dd data-ec="fee-transaction">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-fee">
          <dt>Payment processing <span class="etsy-row-note" data-ec="note-processing"></span></dt>
          <dd data-ec="fee-processing">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-fee" data-ec-row="regulatory">
          <dt>Regulatory operating fee <span class="etsy-row-note" data-ec="note-regulatory"></span></dt>
          <dd data-ec="fee-regulatory">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-fee" data-ec-row="offsiteAds" hidden>
          <dt>Offsite Ads</dt>
          <dd data-ec="fee-offsiteAds">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-fee" data-ec-row="currencyConversion" hidden>
          <dt>Currency conversion</dt>
          <dd data-ec="fee-currencyConversion">$0.00</dd>
        </div>
        <div class="calc-breakdown-row etsy-row-subtotal">
          <dt>Total Etsy fees <span class="etsy-row-note" data-ec="feePct"></span></dt>
          <dd data-ec="totalFees">$0.00</dd>
        </div>

        <div class="calc-breakdown-row etsy-row-costs">
          <dt>Your costs</dt>
          <dd data-ec="costs">$0.00</dd>
        </div>
        <div class="calc-breakdown-row calc-row-profit">
          <dt>Your profit</dt>
          <dd data-ec="profit">$0.00</dd>
        </div>
        <div class="calc-breakdown-row calc-row-rate">
          <dt>Profit margin</dt>
          <dd data-ec="margin">0%</dd>
        </div>
      </dl>

      <div class="etsy-year" data-ec-year hidden>
        <h3 class="etsy-year-title" data-ec="yearTitle">Over a year</h3>
        <dl class="etsy-year-rows">
          <div class="calc-breakdown-row">
            <dt>Sales</dt>
            <dd data-ec="year-sales">0</dd>
          </div>
          <div class="calc-breakdown-row">
            <dt>Revenue</dt>
            <dd data-ec="year-revenue">$0.00</dd>
          </div>
          <div class="calc-breakdown-row etsy-row-fee">
            <dt>Paid to Etsy</dt>
            <dd data-ec="year-fees">$0.00</dd>
          </div>
          <div class="calc-breakdown-row calc-row-profit">
            <dt>Your profit</dt>
            <dd data-ec="year-profit">$0.00</dd>
          </div>
        </dl>
        <p class="etsy-year-note">
          That fee total is a deductible business expense, but only if it is written down somewhere. Etsy reports it in your payment account; most sellers only ever see the deposit.
        </p>
      </div>

      <p class="etsy-verified">Rates checked <?= htmlspecialchars($verified) ?>.</p>
    </div>
  </div>

  <article class="calc-content">

    <section>
      <h2>How much does Etsy take per sale?</h2>
      <p>On a straightforward US sale of around $30, Etsy takes about <strong>11%</strong> of the order: a <?= $pct($shared['transaction_pct'], 1) ?> transaction fee, payment processing of <?= $pct($countries['US']['processing_pct'], 0) ?> plus $<?= number_format($countries['US']['processing_flat'], 2) ?>, and the $<?= number_format($shared['listing_fee'], 2) ?> listing fee. On a $10 order the same fees come to <strong>14%</strong>, because the flat charges do not shrink with the price. Small orders always cost you a bigger share.</p>
      <p>If the order came through <strong>Offsite Ads</strong>, add another <?= $pct($shared['offsite_ads_over'], 0) ?> or <?= $pct($shared['offsite_ads_under'], 0) ?>, which takes the total past <strong>25%</strong>. Sellers in Canada and the UK pay a little more again because of the regulatory operating fee. The calculator above works out your exact figure rather than the average.</p>
    </section>

    <section>
      <h2>Every Etsy fee, explained</h2>
      <p>Six fees can appear on a single order. Three of them apply to almost every sale; the other three depend on your country, your ad settings, and your bank.</p>
      <?php foreach ($fee_reference as $f): ?>
        <div class="etsy-fee-card">
          <div class="etsy-fee-head">
            <h3><?= htmlspecialchars($f['name']) ?></h3>
            <span class="etsy-fee-rate"><?= htmlspecialchars($f['rate']) ?></span>
          </div>
          <p class="etsy-fee-base"><?= htmlspecialchars($f['base']) ?></p>
          <p><?= htmlspecialchars($f['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </section>

    <section>
      <h2>Etsy fees by country</h2>
      <p>The transaction fee, listing fee, and Offsite Ads rates are the same everywhere. Payment processing and the regulatory operating fee are not.</p>
      <div class="calc-table-wrap">
        <table class="calc-table">
          <thead>
            <tr>
              <th scope="col">Shop based in</th>
              <th scope="col">Payment processing</th>
              <th scope="col">Regulatory operating fee</th>
              <th scope="col">Total on a $30 order</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($countries as $c):
                // Worked at a flat $30 order with no ads and no conversion, so the
                // column compares like with like across countries.
                $order = 30.0;
                $total = $shared['listing_fee']
                    + $order * $shared['transaction_pct']
                    + $order * $c['processing_pct'] + $c['processing_flat']
                    + $order * $c['regulatory_pct'];
            ?>
              <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= $pct($c['processing_pct'], 0) ?> + <?= htmlspecialchars($c['symbol']) ?><?= number_format($c['processing_flat'], 2) ?></td>
                <td><?= $c['regulatory_pct'] > 0 ? $pct($c['regulatory_pct']) : 'None' ?></td>
                <td><?= htmlspecialchars($c['symbol']) ?><?= number_format($total, 2) ?> (<?= $pct($total / $order, 1) ?>)</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="calc-table-note">Excludes Offsite Ads and currency conversion, which depend on the individual order. Rates checked <?= htmlspecialchars($verified) ?>.</p>
      <?php foreach ($countries as $c): if (empty($c['note'])) continue; ?>
        <p><strong><?= htmlspecialchars($c['name']) ?>:</strong> <?= htmlspecialchars($c['note']) ?></p>
      <?php endforeach; ?>
    </section>

    <section>
      <h2>How to use the calculator</h2>
      <h3>Mode one: what do I keep?</h3>
      <p>Enter what the item sells for and what you charge for shipping, then add your materials, your time, and what postage actually costs you. The right-hand panel splits the order into Etsy's cut, your costs, and your profit, line by line.</p>
      <h3>Mode two: what should I charge?</h3>
      <p>This is the one worth using. Instead of guessing a price and hoping, enter the profit you want from one sale and let it solve backwards. It returns the listing price that leaves you with exactly that amount once every fee and every cost is paid.</p>
      <h3>The yearly view</h3>
      <p>Add your rough sales per month and the per-sale numbers scale out to a year. Most sellers have never added their fees up across twelve months, and the total is usually larger than the guess.</p>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>You sell a candle for <strong>$28</strong> and charge <strong>$5</strong> shipping, so the order total is <strong>$33</strong>. Wax, wick, jar, and label come to <strong>$6</strong>. It takes you half an hour and you value your time at $16 an hour, so labour is <strong>$8</strong>. Postage and the mailer cost you <strong>$4.50</strong>. You are a US seller and this sale did not come through Offsite Ads.</p>
        <ul>
          <li>Listing fee: <strong>$0.20</strong></li>
          <li>Transaction fee, <?= $pct($shared['transaction_pct'], 1) ?> of $33: <strong>$2.15</strong></li>
          <li>Payment processing, 3% of $33 plus $0.25: <strong>$1.24</strong></li>
          <li>Total Etsy fees: <strong>$3.59</strong>, or 10.9% of the order</li>
          <li>Your costs: <strong>$18.50</strong></li>
          <li><strong>Profit: $10.92 per candle, a 33% margin</strong></li>
        </ul>
        <p>Now the part that changes decisions. At 15 sales a month, that is <strong>$645 a year to Etsy</strong> and <strong>$1,965 of profit</strong>. If that same order had come through Offsite Ads at 15%, the fee would jump by $4.95 and the profit would fall to <strong>$5.97</strong>, cutting what you earn almost in half.</p>
      </div>
    </section>

    <section>
      <h2>How to pay less in Etsy fees</h2>
      <ol class="etsy-list">
        <?php foreach ($lower_fees as $m): ?>
          <li><strong><?= htmlspecialchars($m['t']) ?></strong> <?= htmlspecialchars($m['b']) ?></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section>
      <h2>Are Etsy fees tax deductible?</h2>
      <p>Yes. In the US, Canada, the UK, and Australia, fees a selling platform charges you are an ordinary business expense, deductible against your business income, as long as you are selling as a business rather than as a hobby.</p>
      <p>The catch is in the record keeping. Etsy deposits the <em>net</em> amount, so if you only ever record what hit your bank account, you have quietly understated both your revenue and your expenses by the amount of the fees. The totals are correct either way, but you lose the ability to see what the platform is costing you, and a tax return built from deposits alone will not match the income Etsy reports. Record the gross sale and the fee separately.</p>
    </section>

    <section>
      <h2>Mistakes that quietly cost sellers money</h2>
      <ol class="etsy-list">
        <?php foreach ($mistakes as $m): ?>
          <li><strong><?= htmlspecialchars($m['t']) ?></strong> <?= htmlspecialchars($m['b']) ?></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section>
      <h2>When one sale stops being the question</h2>
      <p>A calculator answers a single order. It cannot tell you which of your listings actually make money, what your supply runs cost last quarter, or whether the shop as a whole is ahead. Once you are selling every week, those are the questions that matter, and a spreadsheet rebuilt from scratch each January is a painful way to answer them.</p>
      <p>That is the gap <a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> fills: it records your sales and expenses, keeps fees as their own category so you can see the real cost of selling, and shows your profit month by month instead of once a year. It runs on your own computer and it is free to start.</p>
    </section>

  </article>

  <section class="calc-faqs">
    <h2>Frequently asked questions</h2>
    <?= argo_faq_grid($faqs) ?>
  </section>

  <p class="etsy-disclaimer">Argo Books is not affiliated with or endorsed by Etsy. Etsy is a trademark of Etsy, Inc. Fee rates are published by Etsy and change from time to time; check your own shop's fee schedule before making a pricing decision.</p>

</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
