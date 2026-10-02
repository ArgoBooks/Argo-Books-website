<?php
// candle-pricing-calculator/index.php
// Batch-to-unit pricing calculator for candle makers. Tier 1 (see
// read-me/Tool-page-standards.md): the math is currency-agnostic, so the picker
// carries the full supported list.
//
// The calculator surface, math, and styling are shared: partials/craft-calculator.php,
// shared/scripts/craft-engine.js, shared/styles/craft-calculator.css. This file
// owns the material rows, the wording, and the article beneath.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/craft-calculator.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('candlecalc_tool');
}

$page_title = 'Candle Pricing Calculator: What to Charge Per Candle | Argo Books';
$page_description = 'Free candle pricing calculator. Enter what a batch costs you in wax, wicks, jars, and fragrance, and get the price per candle that actually pays you, with profit and margin worked out.';
$canonical_url = 'https://argorobots.com/candle-pricing-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=candlecalc-tool&amp;utm_source=candle-pricing-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

$craft = [
    'unit' => 'candle',
    'unit_plural' => 'candles',
    'materials' => [
        ['label' => 'Wax', 'hint' => 'What the wax for this whole batch cost you, not the price of the full case.'],
        ['label' => 'Wicks', 'hint' => 'Wicks, wick stickers, and centring bars for the batch.'],
        ['label' => 'Jars or vessels', 'hint' => 'Containers and lids. Usually the biggest single line.'],
        ['label' => 'Fragrance oil', 'hint' => 'Fragrance and any dye for the batch.'],
        ['label' => 'Labels and packaging', 'hint' => 'Warning labels, boxes, tissue, stickers.'],
    ],
    'yield' => [
        'label' => 'Candles this batch makes',
        'hint' => 'How many finished candles you pour from one batch.',
        'default' => 12,
    ],
    'time' => [
        'label' => 'Time for the whole batch',
        'hint' => 'Melting, pouring, wicking, labelling, cleanup. The whole batch, start to finish. We divide it across your candles.',
        'default' => 180,
    ],
    'rate' => ['default' => 20],
    'overhead' => [
        'label' => 'Selling costs per candle',
        'hint' => 'Listing fees, market stall share, card fees, shipping supplies. Small numbers here change the price more than makers expect.',
    ],
    'channels' => [
        ['name' => 'Etsy or your own shop', 'markup' => 150],
        ['name' => 'Craft fairs and markets', 'markup' => 200],
        ['name' => 'Wholesale to shops', 'markup' => 75],
        ['name' => 'Boutique or consignment', 'markup' => 250],
    ],
    'channel_note' => 'Wholesale looks low because the shop needs room to mark it up again and still make money.',
];

$faqs = [
    [
        'q' => 'How much should I charge for a candle?',
        'a' => 'Work it out from your own batch rather than copying a competitor. Add up what one batch costs in wax, wicks, vessels, fragrance, and labels, divide by how many candles it makes, add your time, then apply a markup. Most handmade candle makers land between 150% and 250% markup, which puts a typical 8oz jar candle somewhere around three to four times its material cost.',
    ],
    [
        'q' => 'How do I work out the wax cost per candle?',
        'a' => 'Pour one candle, weigh the wax you actually used, and divide the cost of your wax bag by how many of those weights it contains. A 10lb bag at $40 is $0.25 an ounce, so a candle using 6oz of wax costs $1.50 in wax. Guessing from vessel size is where most people go wrong, because the vessel volume and the wax weight are not the same number.',
    ],
    [
        'q' => 'Should I pay myself for the time making candles?',
        'a' => 'Yes, and it is the most commonly skipped cost. Melting, pouring, wicking, labelling, and packing take real time. Pick an hourly rate you would accept from someone else, work out how long one candle takes across the whole batch, and put that in as labour. If you leave it out, what looks like profit is really unpaid wages.',
    ],
    [
        'q' => 'What is a normal profit margin for candles?',
        'a' => 'Selling direct, aim for a margin around 60% to 70%, which is a markup of 150% to 233%. Wholesale is lower, usually 33% to 50% margin, because the shop buying from you has to mark the candle up again for their own customer. Markup and margin are different numbers, which is why the calculator shows both.',
    ],
    [
        'q' => 'Why is my candle profit lower than I expected?',
        'a' => 'Usually one of three things: pricing off the case price instead of the per-candle cost, leaving labour out entirely, or forgetting the selling costs. Listing fees, market stall fees, card processing, and shipping supplies come off every sale, and together they often eat more than the fragrance oil does.',
    ],
    [
        'q' => 'Should larger candles cost proportionally more?',
        'a' => 'No. Wax and fragrance scale with size but the wick, vessel, label, and your pouring time do not scale nearly as fast. Run each size through the calculator separately. Small candles usually need a higher markup to be worth making, which is why a 4oz tin is rarely half the price of an 8oz jar.',
    ],
    [
        'q' => 'Do I need to include packaging in the price?',
        'a' => 'Yes. Boxes, tissue, warning labels, and shipping mailers are a real cost of selling the candle, not an extra. Put them in the batch materials if they are per candle, or in the selling costs line if they only apply to online orders.',
    ],
    [
        'q' => 'Is candle making profitable?',
        'a' => 'It can be, but only if the price covers materials, your time, and the cost of selling. The makers who struggle are almost always the ones pricing against the cheapest seller on Etsy without knowing their own numbers. Work out your real cost per candle first, then decide whether the market price leaves you a margin worth having.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Candle Pricing Calculator',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'creator' => ['@id' => 'https://argorobots.com/#organization'],
            'url' => $canonical_url,
        ],
        argo_faq_schema_node($faqs),
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Free Tools' => '/tools/',
    'Candle Pricing Calculator' => $canonical_url,
]);

$extra_head = craft_calculator_head();
$extra_scripts = craft_calculator_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Candle Pricing Calculator</h1>
    <p class="site-hero-tagline">Work out what one candle really costs you to make, then what to charge for it. Enter your batch, not the price on the bag.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Selling every week? Argo Books tracks your supply runs and real profit per product.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <?= craft_calculator_render($craft) ?>

  <article class="calc-content">

    <section>
      <h2>How much should you charge for a candle?</h2>
      <p>The honest answer is that nobody can tell you without your numbers, because a candle maker buying wax by the case in bulk has a completely different cost base to one buying 10lb bags. What everyone shares is the method.</p>
      <p class="calc-formula">Price = (materials &divide; batch size + your time + selling costs) &times; (1 + markup)</p>
      <p>Most handmade candle makers selling direct use a markup between <strong>150% and 250%</strong>, which leaves a profit margin of 60% to 71%. That sounds enormous until you remember the markup has to cover the hours you spent, the jars that arrived cracked, the fragrance that did not throw, and the fees taken out of every sale.</p>
    </section>

    <section>
      <h2>Getting your wax cost right</h2>
      <p>This is where most candle pricing goes wrong, so it is worth doing carefully once.</p>
      <p>Vessel size and wax weight are not the same number. A jar described as 8oz holds 8 fluid ounces of water, but the wax that fills it weighs less than that, and how much less depends on the wax. Do not estimate it.</p>
      <p>Instead, pour one candle and weigh the wax you actually used. Then work out the cost per ounce of your wax, and multiply:</p>
      <ul>
        <li>A 10lb bag of wax is 160oz. At $40 a bag, that is <strong>$0.25 an ounce</strong>.</li>
        <li>Your test candle used 6.2oz of wax, so wax costs <strong>$1.55</strong> per candle.</li>
        <li>Twelve candles a batch means <strong>$18.60</strong> of wax in the batch materials.</li>
      </ul>
      <p>Do the same for fragrance. Your wax has a maximum fragrance load, usually printed on the bag or the supplier's page, and the percentage you use is by weight of wax. Weigh what you pour rather than counting bottles.</p>
    </section>

    <section>
      <h2>The costs candle makers forget</h2>
      <ol class="calc-list">
        <li><strong>Wick stickers, centring bars, and warning labels.</strong> Individually trivial, but they are on every candle you make, and together they are often more than the dye.</li>
        <li><strong>The candles you cannot sell.</strong> Frosting, sinkholes, wet spots, a fragrance that seized. Build a little slack into your markup rather than pretending every pour is sellable.</li>
        <li><strong>Curing space and time.</strong> Not a cash cost, but a real one if your batch ties up a room for two weeks before it can ship.</li>
        <li><strong>Shipping supplies.</strong> Candle mailers are not cheap and glass needs protecting. If you ship, that belongs in your selling costs.</li>
        <li><strong>Sample and testing burns.</strong> Every new fragrance costs you at least one candle you will never sell.</li>
      </ol>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>A batch of <strong>12</strong> eight-ounce soy candles:</p>
        <ul>
          <li>Wax: <strong>$18.60</strong>. Wicks and stickers: <strong>$3.00</strong>. Jars with lids: <strong>$26.40</strong>. Fragrance: <strong>$11.00</strong>. Labels and boxes: <strong>$7.20</strong>.</li>
          <li>Batch materials: <strong>$66.20</strong>, so <strong>$5.52</strong> per candle.</li>
          <li>The batch takes about three hours all in, so 15 minutes a candle. At $20 an hour that is <strong>$5.00</strong> of your time.</li>
          <li>Etsy fees and a padded mailer come to about <strong>$1.80</strong> a candle.</li>
          <li><strong>True cost per candle: $12.32.</strong></li>
        </ul>
        <p>At a 150% markup that candle sells for <strong>$30.80</strong> and leaves you <strong>$18.48</strong>. At the $22 a lot of makers guess at, you would be earning <strong>$9.68</strong>, and after your own time that is closer to $4.68 an hour of actual profit.</p>
      </div>
    </section>

    <section>
      <h2>Pricing different sizes</h2>
      <p>Do not scale the price with the wax. A 4oz tin uses less wax and less fragrance than an 8oz jar, but it needs the same wick trimming, the same label, the same packing time, and the same listing. Run each size through the calculator on its own.</p>
      <p>You will usually find small sizes need a higher markup to be worth making at all. That is normal, and it is why experienced makers sell small candles mainly as add-ons, gift sets, and market impulse buys rather than as the main product.</p>
    </section>

    <section>
      <h2>Markup or margin?</h2>
      <p>These get mixed up constantly, and the difference is money.</p>
      <p class="calc-formula">Markup is on top of your cost. Margin is a share of your price.</p>
      <p>Doubling your cost is a <strong>100% markup</strong> but only a <strong>50% margin</strong>. A 150% markup is a 60% margin. If you have been told to aim for a 60% margin and you have been adding 60% to your cost, you have been leaving roughly a quarter of your price on the table on every candle. The calculator shows both so you can see which one you are actually setting.</p>
    </section>

    <section>
      <h2>When one candle stops being the question</h2>
      <p>A calculator prices one product. It cannot tell you whether the shop as a whole made money last quarter, which fragrances actually sell, or what you spent on supplies across the year. Once you are pouring every week, those are the questions that matter, and they are hard to answer from a shoebox of receipts.</p>
      <p>That is where <a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> comes in: it records your supply runs and your sales, keeps fees as their own category, and shows your real profit month by month. It runs on your own computer and it is free to start.</p>
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
