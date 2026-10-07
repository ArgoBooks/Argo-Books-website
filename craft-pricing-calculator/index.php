<?php
// craft-pricing-calculator/index.php
// Free craft / handmade-product pricing calculator with the full SEO content
// layer (quick answer, how-to, formula, markup-by-channel table, mistakes,
// worked example, FAQ). Reuses the shared tool shell (invoice-generator/
// layout.php): header, "All tools" breadcrumb, OG/Twitter/canonical, schema.
// Calculation is client-side (scripts/main.js + calc.js).

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../shared/currencies.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('craftcalc_tool');
}

$page_title = 'Free Craft Pricing Calculator: Price Your Handmade Products | Argo Books';
$page_description = 'Free craft pricing calculator for handmade sellers. Add your material cost, labour, and markup to find a selling price that actually pays you, with profit and margin worked out. Works for soap, candles, jewellery, and more.';
$canonical_url = 'https://argorobots.com/craft-pricing-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=craftcalc-tool&amp;utm_source=craft-pricing-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

// Markup / margin guidance by sales channel. Drives the on-page table and the
// quick-fill buttons in the calculator (preset = a sensible mid-range markup).
$channels = [
    ['name' => 'Online shop / Etsy', 'markup' => '100–200%', 'margin' => '50–67%', 'preset' => 150],
    ['name' => 'Craft fairs / markets', 'markup' => '150–250%', 'margin' => '60–71%', 'preset' => 200],
    ['name' => 'Wholesale to shops', 'markup' => '50–100%', 'margin' => '33–50%', 'preset' => 75],
    ['name' => 'Boutique / consignment', 'markup' => '200–300%', 'margin' => '67–75%', 'preset' => 250],
];

// FAQ defined once, rendered into both the visible section AND the FAQPage
// JSON-LD below, so the two can never drift apart.
$faqs = [
    [
        'q' => 'How do I price a handmade product?',
        'a' => 'Add up what one item costs you to make: your materials plus the value of your own time (labour). Then add a markup on top to create your profit. A common starting point for handmade sellers is a markup of 100% to 200%, which leaves you a healthy profit margin of 50% to 67%.',
    ],
    [
        'q' => 'What is the formula for pricing crafts?',
        'a' => 'Selling price = (material cost + labour cost) × (1 + markup %). For example, if an item costs you $10 in materials and time and you apply a 150% markup, your price is $10 × 2.5 = $25. The $15 above your cost is your profit.',
    ],
    [
        'q' => 'Should I include my own labour when pricing?',
        'a' => 'Yes. This is the single most common pricing mistake. Your time is a real cost even though no money leaves your pocket. Decide on an hourly rate you would be happy to earn, multiply it by how long one item takes to make, and include that as labour. If you skip it, you are effectively working for free.',
    ],
    [
        'q' => 'What is a good profit margin for handmade goods?',
        'a' => 'For direct sales (your own shop, Etsy, or markets), aim for a profit margin around 50% to 67%. When you sell wholesale to other shops, a margin of 33% to 50% is normal because the retailer needs room to mark the item up again for their own customers.',
    ],
    [
        'q' => 'How do I work out my material cost per item?',
        'a' => 'Use the per-item cost, not the price of the whole pack. If a $20 bag of supplies makes 8 items, your material cost is $20 ÷ 8 = $2.50 per item. Do this for every ingredient and add them together, including small things like packaging and labels, which add up.',
    ],
    [
        'q' => 'What is the difference between wholesale and retail price?',
        'a' => 'Retail is the price you charge the end customer. Wholesale is the lower price you charge a shop that resells your product. A common rule is "keystone" pricing, where the shop pays roughly half your retail price so they can double it and still make a profit. Use a smaller markup for wholesale than for direct sales.',
    ],
];

// Schema: SoftwareApplication (the calculator) + FAQPage, as a @graph. The FAQPage node is built from $faqs by partials/faq.php, the same array the visible accordion renders from.

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Free Craft Pricing Calculator',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'creator' => ['@id' => 'https://argorobots.com/#organization'],
            'url' => $canonical_url,
        ],
        argo_faq_schema_node($faqs),
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema(['Home' => '/', 'Free Tools' => '/tools/', 'Craft Pricing Calculator' => $canonical_url]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/craft-pricing-calculator/styles/craft-calculator.css">'
    . '<script>window.ARGO_CURRENCY_LOCALES = ' . json_encode(argo_currency_locales(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/craft-pricing-calculator/scripts/main.js"></script>';

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Free Craft Pricing Calculator</h1>
    <p class="site-hero-tagline">Stop guessing what to charge. Enter your costs and a markup to find a price that actually pays you back, with your profit and margin worked out for you.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Selling regularly? Track your real costs and profit with Argo Books.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="calc-grid">
    <form class="calc-form" autocomplete="off" aria-label="Craft pricing inputs">
      <div class="calc-field">
        <label for="cc-currency">Currency</label>
        <select id="cc-currency" data-cc="currency">
          <?= argo_currency_options() ?>
        </select>
      </div>

      <div class="calc-field">
        <label for="cc-material">Material cost (per item)</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="cc-material" data-cc="material" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
        </div>
        <p class="calc-hint">Supplies for one item. Made in batches? Divide the batch cost by how many it makes.</p>
      </div>

      <div class="calc-field">
        <label for="cc-labor">Labour cost (per item)</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="cc-labor" data-cc="labor" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
        </div>
        <p class="calc-hint">Your time for one item. For batches, divide the batch's time across all of them.</p>
      </div>

      <div class="calc-field">
        <label for="cc-markup">Markup</label>
        <div class="calc-money calc-money-percent">
          <input id="cc-markup" data-cc="markup" type="number" inputmode="decimal" min="0" step="1" placeholder="150">
          <span class="calc-money-affix calc-money-affix-right">%</span>
        </div>
        <div class="calc-presets" role="group" aria-label="Quick markup presets by where you sell">
          <?php foreach ($channels as $c): ?>
            <button type="button" class="calc-preset-btn" data-cc-preset="<?= (int)$c['preset'] ?>"><?= htmlspecialchars(strtok($c['name'], ' /')) ?> <span class="calc-preset-pct"><?= (int)$c['preset'] ?>%</span></button>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <div class="calc-results" data-cc-results aria-live="polite">
      <div class="calc-headline">
        <span class="calc-headline-label">Suggested selling price</span>
        <span class="calc-headline-amount" data-cc="price">$0.00</span>
      </div>
      <dl class="calc-breakdown">
        <div class="calc-breakdown-row">
          <dt>Materials &amp; labour</dt>
          <dd data-cc="cost">$0.00</dd>
        </div>
        <div class="calc-breakdown-row calc-breakdown-profit">
          <dt>Your profit</dt>
          <dd data-cc="profit">$0.00</dd>
        </div>
        <div class="calc-breakdown-row calc-breakdown-rate">
          <dt>Profit margin</dt>
          <dd data-cc="margin">0%</dd>
        </div>
      </dl>
    </div>
  </div>

  <article class="calc-content">

    <section>
      <h2>How to price your handmade products (quick answer)</h2>
      <p>Add your <strong>material cost</strong> and <strong>labour cost</strong> together to get what one item really costs you to make. Then apply a <strong>markup</strong> to set your selling price (see the formula below).</p>
      <p>Most handmade sellers use a markup of <strong>100% to 200%</strong>, which leaves a profit margin of about <strong>50% to 67%</strong>. The calculator above does the work for you and shows your profit and margin as you type.</p>
    </section>

    <section>
      <h2>How to use the calculator</h2>
      <h3>The three inputs</h3>
      <ul>
        <li><strong>Material cost</strong>: everything you use to make one item, including packaging and labels. Use the cost <em>per item</em>, not the price of the whole pack.</li>
        <li><strong>Labour cost</strong>: your time, for one item. Pick an hourly rate you would be happy to earn and multiply by how long one item takes. If you work in batches, divide the batch's time by how many items it makes.</li>
        <li><strong>Markup</strong>: the percentage you add on top of your cost. Tap a preset for where you sell, or type your own.</li>
      </ul>
      <h3>Reading the results</h3>
      <ul>
        <li><strong>Suggested selling price</strong>: what to charge per item at that markup.</li>
        <li><strong>Materials &amp; labour</strong>: your total cost to make one item.</li>
        <li><strong>Your profit</strong>: what you keep per item after costs.</li>
        <li><strong>Profit margin</strong>: your profit as a share of the selling price.</li>
      </ul>
    </section>

    <section>
      <h2>Markup and margin by where you sell</h2>
      <p>Where you sell changes how much to mark up. Selling direct lets you keep more; selling wholesale means leaving room for the shop to mark the item up again.</p>
      <table class="calc-table">
        <thead>
          <tr><th scope="col">Where you sell</th><th scope="col">Typical markup</th><th scope="col">Profit margin</th></tr>
        </thead>
        <tbody>
          <?php foreach ($channels as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars($c['markup']) ?></td>
              <td><?= htmlspecialchars($c['margin']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section>
      <h2>The formula explained</h2>
      <p>Two quick calculations sit behind the result:</p>
      <p class="calc-formula">Selling price = (material cost + labour cost) &times; (1 + markup %)</p>
      <p class="calc-formula">Profit margin = (selling price &minus; total cost) &divide; selling price</p>
      <p>Markup and margin are not the same number. A 100% markup (doubling your cost) is a 50% margin. That difference trips up a lot of sellers, so the calculator shows both.</p>
    </section>

    <section>
      <h2>A real example</h2>
      <div class="calc-example">
        <p>Say you make soap in batches. A batch of <strong>20 bars</strong> uses <strong>$40</strong> of oils, lye, fragrance, and packaging, so materials are $40 &divide; 20 = <strong>$2.00 per bar</strong>. The batch takes about an hour and you value your time at $20/hour, so labour is $20 &divide; 20 = <strong>$1.00 per bar</strong>. Dividing the batch cost across every bar gives your true per-item cost. You sell on Etsy, so you pick a <strong>150% markup</strong>.</p>
        <ul>
          <li>Total cost: $2.00 + $1.00 = <strong>$3.00 per bar</strong></li>
          <li>Selling price: $3.00 &times; 2.5 = <strong>$7.50</strong></li>
          <li>Your profit: <strong>$4.50</strong> per bar</li>
          <li>Profit margin: <strong>60%</strong></li>
        </ul>
      </div>
    </section>

    <section>
      <h2>Common pricing mistakes</h2>
      <ol class="calc-list">
        <li><strong>Not paying yourself for labour.</strong> Your time is a real cost. Leave it out and your "profit" is really just unpaid wages.</li>
        <li><strong>Using the pack price instead of the per-item cost.</strong> Divide the cost of a pack by how many items it makes.</li>
        <li><strong>Copying a competitor's price.</strong> You do not know their costs. Price from your own numbers, then reality-check against the market.</li>
        <li><strong>Forgetting overhead.</strong> Stall fees, online listing fees, and equipment add up. Build a little extra into your markup to cover them.</li>
      </ol>
    </section>

    <section>
      <h2>Who is this calculator for?</h2>
      <p>Anyone selling something they make by hand: soap and candle makers, jewellers, bakers, potters, woodworkers, and crafters selling on Etsy, at markets, or wholesale to shops. If you make it and sell it, this gets you to a price that pays you fairly.</p>
    </section>

    <section>
      <h2>When to move beyond a calculator</h2>
      <p>A calculator prices one product at a time. Once you are selling regularly, the bigger question is whether the whole business is profitable across every sale, every supply run, and every fee. That is where <a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> comes in: it tracks your income and expenses, shows your real profit month to month, and helps you send invoices and stay ready for tax time. It is free to start.</p>
    </section>

  </article>

  <section class="calc-faqs">
    <h2>Frequently asked questions</h2>
    <?= argo_faq_grid($faqs) ?>
  </section>

</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
