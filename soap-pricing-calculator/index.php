<?php
// soap-pricing-calculator/index.php
// Batch cost and pricing calculator for soap makers. Tier 1 (see
// read-me/Tool-page-standards.md). Shares the craft calculator surface and math.
//
// Deliberately NOT a lye calculator. Saponification maths is safety-critical and
// well served by the established tools; getting it wrong burns someone. This
// prices the batch, which is the part nobody does well.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/craft-calculator.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('soapcalc_tool');
}

$page_title = 'Soap Pricing Calculator: Cost Per Bar and What to Charge | Argo Books';
$page_description = 'Free soap pricing calculator for handmade soap makers. Enter your batch costs in oils, lye, and fragrance to get cost per bar, a selling price, and your real margin.';
$canonical_url = 'https://argorobots.com/soap-pricing-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=soapcalc-tool&amp;utm_source=soap-pricing-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

$craft = [
    'unit' => 'bar',
    'unit_plural' => 'bars',
    'materials' => [
        ['label' => 'Oils and butters', 'hint' => 'Olive, coconut, palm, shea, castor. What this batch used, not the price of the tub.'],
        ['label' => 'Lye and water', 'hint' => 'Sodium hydroxide, plus distilled water or milk.'],
        ['label' => 'Fragrance or essential oil', 'hint' => 'Usually the most expensive ingredient per ounce, and the one most often under-costed.'],
        ['label' => 'Colourants and additives', 'hint' => 'Micas, clays, oats, botanicals, exfoliants.'],
        ['label' => 'Labels and packaging', 'hint' => 'Wraps, boxes, ingredient labels, shrink bands.'],
    ],
    'yield' => [
        'label' => 'Bars this batch cuts into',
        'hint' => 'Count sellable bars after trimming the ends, not the theoretical number.',
        'default' => 10,
    ],
    'time' => [
        'label' => 'Time for the whole batch',
        'hint' => 'Measuring, mixing, pouring, cutting, and wrapping, for the whole batch. Do not count the four to six weeks of curing, because you are not working during it.',
        'default' => 80,
    ],
    'rate' => ['default' => 20],
    'overhead' => [
        'label' => 'Selling costs per bar',
        'hint' => 'Listing fees, stall fees, card fees, shipping supplies, and your safety gear amortised across batches.',
    ],
    'channels' => [
        ['name' => 'Etsy or your own shop', 'markup' => 150],
        ['name' => 'Farmers markets', 'markup' => 200],
        ['name' => 'Wholesale to shops', 'markup' => 75],
        ['name' => 'Subscription or gift sets', 'markup' => 175],
    ],
    'channel_note' => 'Wholesale is deliberately low: the shop has to double it again and still make a margin.',
];

$faqs = [
    [
        'q' => 'How much should I charge for a bar of handmade soap?',
        'a' => 'Work it out from your batch. Add up the oils, lye, fragrance, colour, and packaging for the whole batch, divide by the number of sellable bars, add your time, then apply a markup. Most makers selling direct use 150% to 200%. A typical cold process bar costing $1.80 to $3.00 to make usually sells between $6 and $10.',
    ],
    [
        'q' => 'How do I work out the cost per bar?',
        'a' => 'Cost the batch, then divide by the bars you can actually sell after trimming the ends. That last part matters: a loaf that theoretically cuts into 12 often yields 10 sellable bars, and using 12 quietly understates your cost per bar by about 17%.',
    ],
    [
        'q' => 'Is this a lye calculator?',
        'a' => 'No, deliberately. This tool prices your batch; it does not calculate saponification values or lye quantities. Getting lye maths wrong causes chemical burns, and that job is already well handled by the established soap calculators. Work out your recipe there, then bring the costs here.',
    ],
    [
        'q' => 'Should I count the curing time in my price?',
        'a' => 'Not as labour, because you are not working during it. Four to six weeks of curing costs you space and cash flow rather than hours. What it does mean is that your money is tied up for over a month before a bar can be sold, which is a good reason to keep a healthy margin rather than a thin one.',
    ],
    [
        'q' => 'Why is my fragrance cost so high per bar?',
        'a' => 'Because fragrance and essential oils are priced per ounce at a level nothing else in the batch approaches, and usage is a percentage of your oil weight. A batch using 2oz of a $4-an-ounce fragrance carries $8 of scent across maybe ten bars, so 80 cents a bar from one ingredient. Costing fragrance by the bottle rather than by what the batch used is one of the most common pricing errors in soap making.',
    ],
    [
        'q' => 'What margin should handmade soap makers aim for?',
        'a' => 'Selling direct, aim for a margin around 60% to 67%, which is a markup of 150% to 200%. Wholesale sits nearer 43%, a 75% markup, because the shop needs headroom. The calculator shows both markup and margin, since they are different numbers and mixing them up costs real money.',
    ],
    [
        'q' => 'Do I need to label handmade soap in a particular way?',
        'a' => 'Usually yes. Most countries require an ingredient list, weight, and your business details, and rules differ sharply depending on whether the product is sold as soap or makes a cosmetic claim like moisturising or anti-acne. Cosmetic claims typically trigger extra requirements such as safety assessments. Check your national regulator before printing a large label run.',
    ],
    [
        'q' => 'Is selling handmade soap profitable?',
        'a' => 'It can be, because the material cost per bar is genuinely low. The constraint is time and volume: at $6 a bar you need real throughput for the numbers to add up, which is why successful soap makers batch large, sell wholesale alongside direct, and keep a tight grip on what a bar actually costs them.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Soap Pricing Calculator',
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
    'Soap Pricing Calculator' => $canonical_url,
]);

$extra_head = craft_calculator_head();
$extra_scripts = craft_calculator_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Soap Pricing Calculator</h1>
    <p class="site-hero-tagline">Cost a whole batch, get your true cost per bar, and find a price that pays you for the work. Not a lye calculator.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Making batches regularly? Argo Books tracks your oils, fragrance orders, and real profit per bar.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <?= craft_calculator_render($craft) ?>

  <article class="calc-content">

    <section>
      <h2>What this tool does, and what it does not</h2>
      <p>This calculator prices your soap. It works out what a batch costs, divides it into a true cost per bar, and shows you what to charge to make a living from it.</p>
      <p>It is <strong>not a lye calculator</strong>. It will not tell you how much sodium hydroxide your oils need, and you should not use it for that. Saponification maths is safety-critical, the established soap calculators do it well, and a mistake there causes chemical burns rather than a bad margin. Work your recipe out there, then bring the costs here.</p>
    </section>

    <section>
      <h2>Cost the batch, not the shopping</h2>
      <p class="calc-formula">Cost per bar = batch materials &divide; sellable bars + your time + selling costs</p>
      <p>Two details separate an accurate number from a comfortable guess:</p>
      <ul>
        <li><strong>Use what the batch consumed.</strong> A $22 gallon of olive oil is roughly $0.17 an ounce, so a batch using 30oz costs $5.10, not $22.</li>
        <li><strong>Count sellable bars, not theoretical ones.</strong> A loaf that cuts into 12 usually gives 10 you would actually put on a table once the end pieces are trimmed. Using the theoretical number understates your cost per bar by about 17%, which is roughly the whole margin difference between a good month and a bad one.</li>
      </ul>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>A cold process batch cutting into <strong>10</strong> sellable bars:</p>
        <ul>
          <li>Oils and butters: <strong>$9.40</strong>. Lye and distilled water: <strong>$1.60</strong>. Fragrance: <strong>$8.00</strong>. Mica and oats: <strong>$2.10</strong>. Wraps and labels: <strong>$3.50</strong>.</li>
          <li>Batch materials: <strong>$24.60</strong>, so <strong>$2.46</strong> a bar.</li>
          <li>Roughly 80 minutes across the whole batch, so 8 minutes a bar. At $20 an hour that is <strong>$2.67</strong>.</li>
          <li>Market stall share and card fees: <strong>$0.60</strong> a bar.</li>
          <li><strong>True cost per bar: $5.73.</strong></li>
        </ul>
        <p>At the $6 a bar that feels normal at a market, you are making <strong>27 cents</strong>. At a 150% markup the bar is <strong>$14.33</strong>, which is high for a market table, and that tension is the real finding: this batch is too small and too slow. Doubling the batch size spreads the same 80 minutes across 20 bars and drops the true cost to around <strong>$4.40</strong>, which makes $9 a bar a genuinely good business.</p>
      </div>
    </section>

    <section>
      <h2>Batch size is the lever that matters</h2>
      <p>Soap is unusual among crafts: the material cost per bar is genuinely low, but the setup is nearly identical whether you are making ten bars or forty. Measuring, heating, mixing, cleaning, and the safety routine take about the same time either way.</p>
      <p>That means volume moves your cost per bar more than any ingredient substitution will. Before you switch to a cheaper oil to save eight cents, try making twice as much at once and watch the labour per bar halve.</p>
    </section>

    <section>
      <h2>The cost soap makers most often miss</h2>
      <ol class="calc-list">
        <li><strong>Fragrance, costed by the bottle instead of by the batch.</strong> It is the most expensive thing per ounce in most recipes and usage is a percentage of oil weight, so it scales with batch size in a way the mica does not.</li>
        <li><strong>Trimmed ends and failed batches.</strong> Seizing, ricing, partial gel, a scent that faded in cure. Build slack into the markup.</li>
        <li><strong>Cure time as cash flow.</strong> Six weeks between spending the money and being able to sell the bar is a real business cost even though it never appears on a receipt.</li>
        <li><strong>Safety gear and equipment.</strong> Goggles, gloves, dedicated pots and moulds. Spread across batches, small; ignored entirely, invisible.</li>
        <li><strong>Labelling compliance.</strong> Ingredient lists and required details mean label reprints when a recipe changes.</li>
      </ol>
    </section>

    <section>
      <h2>Selling soap wholesale</h2>
      <p>Wholesale looks brutal at a 75% markup until you see what it does to your time. Selling 60 bars in one invoice to a shop takes a fraction of the hours of selling 60 bars individually at a market, and there is no stall fee, no travel, and no standing in the rain.</p>
      <p>Run both through the calculator. The wholesale margin per bar is lower and the profit per hour is frequently higher, which is why most soap businesses that grow end up doing both.</p>
    </section>

    <section>
      <h2>When one batch stops being the question</h2>
      <p>A calculator prices one batch. It cannot tell you which scents actually sell, what you spent on oils across the year, or whether the market stall pays for itself once travel is counted. Those are the questions that decide whether soap making becomes a business.</p>
      <p><a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> records your supply orders and sales, keeps fees as their own category, and shows your real profit month by month. It runs on your own computer and it is free to start.</p>
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
