<?php
// break-even-calculator/index.php
// How many units must sell before fixed costs are covered. Tier 1 (see
// read-me/Tool page standards.md). Math in shared/scripts/business-calcs.js.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../shared/currencies.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/tool-email-capture.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('breakevencalc_tool');
}

$page_title = 'Break-Even Calculator: How Many Units You Need to Sell | Argo Books';
$page_description = 'Free break-even calculator. Enter your fixed costs, price, and cost per unit to see how many sales cover your costs, and what profit looks like beyond that point.';
$canonical_url = 'https://argorobots.com/break-even-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=breakevencalc-tool&amp;utm_source=break-even-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

$faqs = [
    [
        'q' => 'How do you calculate the break-even point?',
        'a' => 'Divide your fixed costs by the contribution each sale makes, which is the price minus the cost of producing that one unit. If your fixed costs are $6,000, you sell at $25, and each unit costs $10 to make, each sale contributes $15 and you break even after 400 units.',
    ],
    [
        'q' => 'What is the difference between fixed and variable costs?',
        'a' => 'Fixed costs are the ones you pay whether or not you sell anything: rent, insurance, software, a stall booking, your own salary. Variable costs happen only because a sale happened: materials, packaging, payment fees, shipping. If you are unsure which a cost is, ask what it would be if you sold nothing this month.',
    ],
    [
        'q' => 'What is contribution margin?',
        'a' => 'What one sale contributes towards your fixed costs, after paying for itself. Price minus variable cost. It is the single most useful number in pricing, because it tells you what each additional sale is really worth to you, and it is what break-even divides into.',
    ],
    [
        'q' => 'Why does my calculator say I can never break even?',
        'a' => 'Because your price is at or below your variable cost, so every sale loses money before fixed costs are considered. No volume fixes that; selling more only loses more. The only routes out are raising the price or lowering the cost of producing each unit.',
    ],
    [
        'q' => 'Should I include my own wages in fixed costs?',
        'a' => 'If you need to live on the business, yes. Leaving your own pay out gives a break-even point that looks reassuring and means nothing, because the business is only viable while you are working for free. Put a realistic figure in and see the honest number.',
    ],
    [
        'q' => 'How do I use break-even for a decision, not just a number?',
        'a' => 'Compare it to what you can realistically sell in the period. If breaking even needs 400 units a month and your best month ever was 250, the plan does not work yet and you have found that out on paper rather than after signing a lease. It is a feasibility test, not just a milestone.',
    ],
    [
        'q' => 'Does break-even work if I sell several different products?',
        'a' => 'Approximately. Use your average selling price and average variable cost, weighted by how much of each you sell. It is less precise than a single-product calculation but still a useful check. If your products have wildly different margins, run the important ones separately as well.',
    ],
    [
        'q' => 'What is a margin of safety?',
        'a' => 'How far your actual sales sit above break-even, usually shown as a percentage. Selling 500 units against a break-even of 400 gives a 20% margin of safety, meaning sales could fall a fifth before you started losing money. A thin margin of safety is a warning even when the business is currently profitable.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Break-Even Calculator',
            'applicationCategory' => 'FinanceApplication',
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
    'Break-Even Calculator' => $canonical_url,
]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . tool_email_capture_head()
    . '<script>window.ARGO_CURRENCY_LOCALES = ' . json_encode(argo_currency_locales(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/break-even-calculator/scripts/main.js"></script>'
    . tool_email_capture_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Break-Even Calculator</h1>
    <p class="site-hero-tagline">How many sales it takes before you stop losing money, and what each one is worth after that.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Argo Books shows where you actually are against costs each month, not just where the plan says.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="calc-grid">
    <form class="calc-form" autocomplete="off" aria-label="Break-even inputs">

      <div class="calc-field">
        <label for="be-fixed">Fixed costs for the period</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="be-fixed" data-be="fixedCosts" type="number" inputmode="decimal" min="0" step="100" placeholder="6000">
        </div>
        <p class="calc-hint">Rent, insurance, software, equipment, your own wages. Everything you pay even if you sell nothing.</p>
      </div>

      <div class="calc-field">
        <label for="be-price">Selling price per unit</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="be-price" data-be="pricePerUnit" type="number" inputmode="decimal" min="0" step="0.01" placeholder="25.00">
        </div>
      </div>

      <div class="calc-field">
        <label for="be-variable">Variable cost per unit</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="be-variable" data-be="variableCostPerUnit" type="number" inputmode="decimal" min="0" step="0.01" placeholder="10.00">
        </div>
        <p class="calc-hint">Materials, packaging, payment fees, shipping. Costs that only happen because a sale happened.</p>
      </div>

      <div class="calc-field">
        <label for="be-expected">Units you expect to sell (optional)</label>
        <input id="be-expected" data-be="expectedUnits" type="number" inputmode="numeric" min="0" step="1" placeholder="0">
        <p class="calc-hint">Enter a realistic figure to see the profit or loss, and how much cushion you have.</p>
      </div>

      <div class="calc-field">
        <label for="be-currency">Currency</label>
        <select id="be-currency" data-be="currency"><?= argo_currency_options() ?></select>
      </div>
    </form>

    <div class="calc-results" data-be-results aria-live="polite">
      <div class="calc-headline">
        <span class="calc-headline-label">Break even after</span>
        <span class="calc-headline-amount" data-tec-row="Break-even point (units)" data-be="units">0</span>
        <span class="calc-headline-sub" data-be="unitsSub">Enter your costs and price</span>
      </div>

      <dl class="calc-breakdown">
        <div class="calc-breakdown-row"><dt>Revenue at break-even</dt><dd data-tec-row="Revenue at break-even" data-be="revenue">$0.00</dd></div>
        <div class="calc-group">Per sale</div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Price</dt><dd data-be="price">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Variable cost</dt><dd data-be="variable">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-subtotal"><dt>Contribution</dt><dd data-tec-row="Contribution" data-be="contribution">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-rate"><dt>Contribution margin</dt><dd data-tec-row="Contribution margin" data-be="contributionMargin">0%</dd></div>
      </dl>

      <div class="calc-callout" data-be-expected hidden>
        <span class="calc-headline-label" data-be="expectedLabel">At your expected volume</span>
        <dl class="calc-breakdown">
          <div class="calc-breakdown-row calc-row-profit"><dt data-be="outcomeLabel">Profit</dt><dd data-be="outcome">$0.00</dd></div>
          <div class="calc-breakdown-row"><dt>Margin of safety</dt><dd data-tec-row="Margin of safety" data-be="safety">0%</dd></div>
        </dl>
        <p data-be="expectedText"></p>
      </div>

      <div class="calc-note" data-be-warn hidden>
        <p data-be="warnText"></p>
      </div>
    </div>
  </div>

  <?php tool_email_capture([
      'source' => 'break_even_calculator',
      'title'  => 'Email these figures to yourself',
      'blurb'  => 'Your break-even point and the margins behind it, so you have them when you next price something.',
  ]); ?>

  <article class="calc-content">

    <section>
      <h2>How break-even works</h2>
      <p>Every sale does two jobs. First it pays for itself, covering the materials and fees that only exist because the sale happened. Whatever is left over goes towards the costs you pay regardless.</p>
      <p class="calc-formula">Break-even units = fixed costs &divide; (price &minus; variable cost)</p>
      <p>That gap between price and variable cost is the <strong>contribution</strong>, and it is the number worth knowing by heart. Sell at $25 with $10 of materials and each sale contributes $15. With $6,000 of fixed costs to cover, you need <strong>400 sales</strong> before you have made a penny.</p>
    </section>

    <section>
      <h2>Which costs go where</h2>
      <p>Getting this split right matters more than precision in the figures. The test is simple: what would this cost be if you sold nothing at all this month?</p>
      <div class="calc-table-wrap">
        <table class="calc-table">
          <thead><tr><th scope="col">Fixed, you pay it anyway</th><th scope="col">Variable, only if you sell</th></tr></thead>
          <tbody>
            <tr><td>Rent, workspace, storage</td><td>Materials and ingredients</td></tr>
            <tr><td>Insurance and licences</td><td>Packaging and labels</td></tr>
            <tr><td>Software subscriptions</td><td>Payment and platform fees</td></tr>
            <tr><td>Equipment and tools</td><td>Shipping and postage</td></tr>
            <tr><td>Your own wages</td><td>Piece-rate or contract labour</td></tr>
            <tr><td>Market stall booked in advance</td><td>Commission on a sale</td></tr>
          </tbody>
        </table>
      </div>
      <p>Leaving your own wages out of fixed costs is the most common way to get a comforting answer that means nothing. If you need to live on this, it is a cost.</p>
    </section>

    <section>
      <h2>When break-even is impossible</h2>
      <p>If your price is at or below your variable cost, there is no volume that saves you. Every additional sale increases the loss. The calculator says so plainly rather than showing an enormous number, because "you need 40,000 sales" and "this can never work" are different messages.</p>
      <p>There are only two ways out: raise the price, or lower what each unit costs to produce. Selling harder is not one of them.</p>
    </section>

    <section>
      <h2>Use it as a feasibility test</h2>
      <p>The number on its own is trivia. It becomes useful the moment you hold it against reality.</p>
      <ul>
        <li><strong>Compare it to your actual capacity.</strong> If break-even needs 400 units a month and you can physically make 250, the plan fails on arithmetic before it fails in the market.</li>
        <li><strong>Compare it to your best month.</strong> A break-even point above anything you have ever achieved is a signal to change the model, not to try harder.</li>
        <li><strong>Test a price rise.</strong> Contribution is the lever. Raising a $25 price to $28 lifts contribution from $15 to $18 and drops break-even from 400 units to 334, a 17% easier target from a 12% price change.</li>
        <li><strong>Check your margin of safety.</strong> If you expect 450 sales against a break-even of 400, sales can fall 11% before you are losing money. That is thin.</li>
      </ul>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>A small candle business planning a month:</p>
        <ul>
          <li>Fixed costs: workshop rent $400, insurance $45, software $30, and $2,000 for your own time. <strong>$2,475.</strong></li>
          <li>Candles sell for <strong>$28</strong> and cost <strong>$11</strong> in wax, jars, fragrance, and packaging.</li>
          <li>Contribution: <strong>$17</strong> a candle, a 61% contribution margin.</li>
          <li><strong>Break-even: 146 candles</strong>, or $4,088 of revenue.</li>
        </ul>
        <p>If a good month is 180 candles, you make <strong>$585</strong> profit and have a 19% margin of safety. If a realistic month is 120, you are <strong>$435 short</strong> and the business is quietly funded by not paying yourself properly. Both are useful things to know in advance rather than in arrears.</p>
      </div>
    </section>

    <section>
      <h2>Where a calculator stops helping</h2>
      <p>This models a plan. It cannot tell you what your fixed costs actually came to last month, whether your variable cost estimate is right, or how close to break-even you really are right now.</p>
      <p><a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> tracks the real figures so the next version of this calculation uses measurements instead of guesses. It runs on your own computer and it is free to start.</p>
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
