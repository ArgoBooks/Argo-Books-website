<?php
// hourly-rate-calculator/index.php
// Works out the hourly rate a self-employed person must charge to actually take
// home a target income. Tier 1 (see read-me/Tool page standards.md).
//
// The point of the tool is the gap between the naive figure (salary divided by
// hours worked) and the real one, which has to absorb unbillable time, business
// costs, and tax. Math lives in shared/scripts/business-calcs.js.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../shared/currencies.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/tool-email-capture.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('hourlyratecalc_tool');
}

$page_title = 'Hourly Rate Calculator: What to Charge When Self-Employed | Argo Books';
$page_description = 'Free hourly rate calculator for freelancers, contractors, and trades. Works out what you must charge once unbillable time, business costs, and tax are all covered.';
$canonical_url = 'https://argorobots.com/hourly-rate-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=hourlyratecalc-tool&amp;utm_source=hourly-rate-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

// Typical billable percentages, used for the quick-fill buttons and the table.
$profiles = [
    ['name' => 'Trades and site work', 'billable' => 75, 'note' => 'Travel between jobs and quoting eat the rest.'],
    ['name' => 'Freelance and consulting', 'billable' => 60, 'note' => 'Pitching, admin, and business development are unpaid.'],
    ['name' => 'Design and creative', 'billable' => 55, 'note' => 'Revisions, sourcing, and client wrangling add up fast.'],
    ['name' => 'Cleaning and services', 'billable' => 80, 'note' => 'Mostly on site, but travel and supplies runs are not billed.'],
];

$faqs = [
    [
        'q' => 'How do I work out my hourly rate when self-employed?',
        'a' => 'Start from what you need to take home, not from what competitors charge. Add your business costs, gross the total up for tax, then divide by the hours you can actually bill, which is never the hours you work. A target of $60,000 with $12,000 of costs and 60% billable time usually needs a rate above $80 an hour, not the $30 that dividing salary by hours suggests.',
    ],
    [
        'q' => 'Why is my real rate so much higher than my old salary rate?',
        'a' => 'Because an employed hourly rate hides three things your business now pays for. You only bill a fraction of the hours you work; you cover your own equipment, insurance, software, and travel; and nobody withholds your tax. Once those are added back, a rate two to three times the employed equivalent is normal rather than greedy.',
    ],
    [
        'q' => 'What percentage of my time is actually billable?',
        'a' => 'Rarely more than 80% and often nearer 55%. Quoting, invoicing, chasing payment, buying materials, travelling, marketing, and bookkeeping are all real work that no client pays for directly. Track a normal fortnight honestly before you guess, because most people overestimate this by a wide margin.',
    ],
    [
        'q' => 'Should I include tax in my hourly rate?',
        'a' => 'Yes. As an employee, tax came out before you saw the money. Self-employed, it comes out after, so a rate that ignores it leaves you with a bill you have already spent. Enter your marginal rate and the calculator grosses your target up so what lands is what you actually wanted.',
    ],
    [
        'q' => 'What should I count as business expenses?',
        'a' => 'Everything the business pays for over a year: tools and equipment, software subscriptions, insurance, accountancy, phone and internet, vehicle costs, materials you do not bill on, workspace, training, and bank charges. Annual figures are easier to be honest about than monthly ones, and they are what this calculator wants.',
    ],
    [
        'q' => 'My calculated rate is higher than what people around me charge. What now?',
        'a' => 'That is worth knowing rather than ignoring. Either your costs are higher than theirs, your billable percentage is lower, or they are undercharging and will not last. The useful responses are to increase billable time, reduce overheads, or move to work that pays for expertise rather than hours. Quietly dropping the rate to match just moves the problem to next year.',
    ],
    [
        'q' => 'Should I charge a day rate instead?',
        'a' => 'Often yes. Day rates suit work that fills a day anyway, reduce the argument about half hours, and read as more professional in some markets. Work the hourly rate out first and multiply, which is what the day rate shown here does, so you are never quoting a day rate that undercuts your own number.',
    ],
    [
        'q' => 'How often should I review my rate?',
        'a' => 'At least once a year, and whenever your costs jump. Insurance renewals, a new vehicle, or a software price rise all come straight out of your take-home unless the rate moves with them. Rerun this calculation each January with real figures from the previous year.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Hourly Rate Calculator',
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
    'Hourly Rate Calculator' => $canonical_url,
]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . tool_email_capture_head()
    . '<script>window.ARGO_CURRENCY_LOCALES = ' . json_encode(argo_currency_locales(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/hourly-rate-calculator/scripts/main.js"></script>'
    . tool_email_capture_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Hourly Rate Calculator</h1>
    <p class="site-hero-tagline">What you have to charge to actually take home what you want, once unbillable hours, business costs, and tax are all paid for.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Argo Books tracks the costs this calculation depends on, so next year's number comes from real figures.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="calc-grid">
    <form class="calc-form" autocomplete="off" aria-label="Hourly rate inputs">

      <div class="calc-field">
        <label for="hr-income">What you want to take home, per year</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="hr-income" data-hr="targetIncome" type="number" inputmode="decimal" min="0" step="1000" placeholder="60000">
        </div>
        <p class="calc-hint">After tax, in your pocket. Not turnover.</p>
      </div>

      <div class="calc-field">
        <label for="hr-expenses">Business costs, per year</label>
        <div class="calc-money">
          <span class="calc-money-affix">$</span>
          <input id="hr-expenses" data-hr="businessExpenses" type="number" inputmode="decimal" min="0" step="500" placeholder="12000">
        </div>
        <p class="calc-hint">Tools, insurance, software, vehicle, accountant, phone, workspace, training.</p>
      </div>

      <div class="calc-field">
        <label for="hr-tax">Your marginal tax rate</label>
        <div class="calc-money calc-money-suffix">
          <input id="hr-tax" data-hr="taxPercent" type="number" inputmode="decimal" min="0" max="99" step="1" placeholder="25">
          <span class="calc-money-affix calc-money-affix-right">%</span>
        </div>
        <p class="calc-hint">Income tax plus self-employment or national insurance contributions. A rough figure is fine.</p>
      </div>

      <div class="calc-field">
        <label for="hr-hours">Hours you work per week</label>
        <input id="hr-hours" data-hr="hoursPerWeek" type="number" inputmode="decimal" min="0" max="168" step="1" value="40">
      </div>

      <div class="calc-field">
        <label for="hr-weeksoff">Weeks off per year</label>
        <input id="hr-weeksoff" data-hr="weeksOff" type="number" inputmode="numeric" min="0" max="51" step="1" value="6">
        <p class="calc-hint">Holiday, sickness, and the quiet weeks. Nobody bills 52.</p>
      </div>

      <div class="calc-field">
        <label for="hr-billable">Share of your hours you can actually bill</label>
        <div class="calc-money calc-money-suffix">
          <input id="hr-billable" data-hr="billablePercent" type="number" inputmode="decimal" min="1" max="100" step="1" value="60">
          <span class="calc-money-affix calc-money-affix-right">%</span>
        </div>
        <div class="calc-presets" role="group" aria-label="Typical billable percentages">
          <?php foreach ($profiles as $p): ?>
            <button type="button" class="calc-preset-btn" data-hr-preset="<?= (int)$p['billable'] ?>">
              <?= htmlspecialchars(strtok($p['name'], ' ')) ?> <span class="calc-preset-pct"><?= (int)$p['billable'] ?>%</span>
            </button>
          <?php endforeach; ?>
        </div>
        <p class="calc-hint">Quoting, admin, travel, and marketing are real work that nobody pays for directly.</p>
      </div>

      <div class="calc-field">
        <label for="hr-currency">Currency</label>
        <select id="hr-currency" data-hr="currency"><?= argo_currency_options() ?></select>
      </div>
    </form>

    <div class="calc-results" data-hr-results aria-live="polite">
      <div class="calc-headline">
        <span class="calc-headline-label">Charge at least</span>
        <span class="calc-headline-amount" data-tec-row="Charge at least (per billable hour)" data-hr="rate">$0.00</span>
        <span class="calc-headline-sub" data-hr="rateSub">per billable hour</span>
      </div>

      <dl class="calc-breakdown">
        <div class="calc-breakdown-row"><dt>Day rate (8 hours)</dt><dd data-tec-row="Day rate (8 hours)" data-hr="dayRate">$0.00</dd></div>
        <div class="calc-group">To make that work</div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Take-home target</dt><dd data-tec-row="Take-home target" data-hr="takeHome">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Income before tax</dt><dd data-hr="preTax">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Business costs</dt><dd data-tec-row="Business costs" data-hr="expenses">$0.00</dd></div>
        <div class="calc-breakdown-row calc-row-subtotal"><dt>Revenue you must bill</dt><dd data-tec-row="Revenue you must bill" data-hr="revenue">$0.00</dd></div>
        <div class="calc-group">Your year</div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Weeks worked</dt><dd data-hr="weeks">0</dd></div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Hours worked</dt><dd data-tec-row="Hours worked" data-hr="hoursWorked">0</dd></div>
        <div class="calc-breakdown-row calc-row-cost"><dt>Hours you can bill</dt><dd data-tec-row="Hours you can bill" data-hr="billableHours">0</dd></div>
        <div class="calc-breakdown-row calc-row-rate"><dt>Unbillable hours</dt><dd data-hr="unbillable">0</dd></div>
      </dl>

      <div class="calc-callout" data-hr-gap hidden>
        <span class="calc-headline-label">Why not just divide?</span>
        <dl class="calc-breakdown">
          <div class="calc-breakdown-row">
            <dt>Take-home &divide; hours worked</dt><dd data-hr="naiveRate">$0.00</dd>
          </div>
          <div class="calc-breakdown-row calc-row-profit">
            <dt>What that rate would really pay you</dt><dd data-hr="naiveTakeHome">$0.00</dd>
          </div>
          <div class="calc-breakdown-row calc-row-rate">
            <dt>You asked for</dt><dd data-hr="targetEcho">$0.00</dd>
          </div>
        </dl>
        <p data-hr="gapText"></p>
      </div>
    </div>
  </div>

  <?php tool_email_capture([
      'source' => 'hourly_rate_calculator',
      'title'  => 'Email these figures to yourself',
      'blurb'  => 'Your rate and the breakdown behind it, so you have them when you next quote.',
  ]); ?>

  <article class="calc-content">

    <section>
      <h2>Why dividing salary by hours gives the wrong answer</h2>
      <p>The instinct when going self-employed is to take the salary you want, divide by roughly 2,000 hours, and quote that. It produces a number that feels reasonable and cannot possibly work.</p>
      <p class="calc-formula">Rate = (take-home &divide; (1 &minus; tax) + business costs) &divide; billable hours</p>
      <p>Three things sit between the hours you work and the money you keep:</p>
      <ul>
        <li><strong>You cannot bill every hour.</strong> Quoting, invoicing, chasing late payers, buying materials, driving between jobs, marketing, and bookkeeping are unpaid. For most people the billable share is between 55% and 80%.</li>
        <li><strong>The business has costs.</strong> Insurance, tools, software, an accountant, a vehicle, a phone. As an employee somebody else bought all of that.</li>
        <li><strong>Tax comes out afterwards.</strong> Nobody is withholding it for you, so a rate that ignores tax leaves you with a bill you have already spent.</li>
      </ul>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>You want <strong>$60,000</strong> in your pocket. Business costs run <strong>$12,000</strong> a year. You work 40 hours a week, take 6 weeks off, bill 60% of your time, and pay about 25% in tax.</p>
        <ul>
          <li>Working weeks: <strong>46</strong>. Hours worked: <strong>1,840</strong>. Billable: <strong>1,104</strong>.</li>
          <li>To keep $60,000 after 25% tax you must earn <strong>$80,000</strong> before it.</li>
          <li>Plus $12,000 of costs: you need to bill <strong>$92,000</strong>.</li>
          <li><strong>$92,000 &divide; 1,104 = $83.33 an hour.</strong></li>
        </ul>
        <p>The naive calculation, $60,000 divided by 1,840 hours worked, gives <strong>$32.61</strong>. Charging that would leave you roughly <strong>$27,000</strong> short of your target before you had noticed anything was wrong.</p>
      </div>
    </section>

    <section>
      <h2>Being honest about billable hours</h2>
      <p>This input moves the answer more than any other, and almost everyone overstates it.</p>
      <div class="calc-table-wrap">
        <table class="calc-table">
          <thead><tr><th scope="col">Type of work</th><th scope="col">Typically billable</th><th scope="col">Where the rest goes</th></tr></thead>
          <tbody>
            <?php foreach ($profiles as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= (int)$p['billable'] ?>%</td>
                <td><?= htmlspecialchars($p['note']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p>If you want a real figure rather than an estimate, track one ordinary fortnight. Write down every hour and mark it billable or not. The result is usually sobering and it makes every future pricing decision better.</p>
    </section>

    <section>
      <h2>What to do when the number feels too high</h2>
      <p>The calculator will often produce a rate above what you have been charging. That is the point, but it is not always the whole story, and there are four honest responses.</p>
      <ol class="calc-list">
        <li><strong>Raise the billable percentage.</strong> Better systems for quoting, invoicing, and scheduling convert unpaid hours into paid ones. Going from 55% to 65% cuts your required rate by roughly 15%.</li>
        <li><strong>Cut a real cost.</strong> Look at subscriptions, insurance, and vehicle costs. Small annual figures move the hourly rate less than people expect, but they compound.</li>
        <li><strong>Work more weeks, deliberately.</strong> Taking four weeks off instead of eight lowers the rate you need. Do this with your eyes open rather than by accident.</li>
        <li><strong>Change what you sell.</strong> Move from hours to outcomes, packages, or retainers so the price reflects the value rather than the clock.</li>
      </ol>
      <p>What is not on the list is dropping your take-home target to make the arithmetic comfortable. That is not a pricing decision, it is a pay cut you have chosen not to notice.</p>
    </section>

    <section>
      <h2>Hourly, day rate, or fixed price?</h2>
      <p>Work out the hourly number first regardless, because it is the floor everything else has to clear.</p>
      <p><strong>Day rates</strong> suit work that fills a day anyway and stop the argument about half hours. <strong>Fixed prices</strong> reward you for getting faster, which hourly billing punishes, but they carry the risk if a job runs long. <strong>Retainers</strong> smooth your income and are worth a discount against your hourly rate because they remove the gaps.</p>
      <p>Whichever you quote, check it against the hourly figure. If a fixed price divided by the hours it will really take comes out below your number, it is a job that costs you money to accept.</p>
    </section>

    <section>
      <h2>Where a calculator stops helping</h2>
      <p>This gives you a rate from figures you estimated. Next year you should be using figures you actually measured: what your costs really were, how many hours you really billed, and what you really took home.</p>
      <p><a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> records your income and expenses so that number comes from your books rather than a guess, and shows whether the rate you set is actually delivering. It runs on your own computer and it is free to start.</p>
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
