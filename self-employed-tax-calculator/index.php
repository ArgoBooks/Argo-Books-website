<?php
// self-employed-tax-calculator/index.php
// Standalone free self-employed tax calculator (US + Canada, 2026).
//
// Reuses the shared tool shell (shared/layout.php): same header,
// "All tools" breadcrumb, SEO/OG/schema, and tool.css chrome. The calculation
// is client-side (scripts/main.js + calc.js + data/tax-rates-2026.js).

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/tool-email-capture.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('taxcalc_tool');
}

$page_title = 'Free Self-Employed Tax Calculator (US & Canada) | Argo Books';
$page_description = 'Free self-employed tax calculator for the US and Canada. Estimate your self-employment/CPP tax and income tax for 2026, and see how much to set aside each quarter. No signup.';
$canonical_url = 'https://argorobots.com/self-employed-tax-calculator/';

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'Free Self-Employed Tax Calculator',
  'applicationCategory' => 'FinanceApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => $canonical_url,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/self-employed-tax-calculator/styles/calculator.css">'
    . tool_email_capture_head();
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/self-employed-tax-calculator/scripts/main.js"></script>'
    . tool_email_capture_scripts();

// Conversion-pitch CTA target: a self-employed tax tool funnels naturally into
// year-round expense tracking.
$cta_qs = '?source=taxcalc-tool&amp;utm_source=tax-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

ob_start();
?>
<div class="taxcalc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Free Self-Employed Tax Calculator</h1>
    <p class="site-hero-tagline">Estimate your self-employment taxes and how much to set aside. United States &amp; Canada, 2026 tax year. No signup.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Want this worked out automatically all year?</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $cta_qs ?>&amp;placement=banner">Track income &amp; expenses with Argo Books <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="taxcalc-grid">
    <form class="taxcalc-form" autocomplete="off" aria-label="Tax calculator inputs">
      <div class="taxcalc-field">
        <label for="tc-country">Country</label>
        <select id="tc-country" data-tc="country">
          <option value="US">United States</option>
          <option value="CA">Canada</option>
        </select>
      </div>

      <div class="taxcalc-field" data-tc-province-field hidden>
        <label for="tc-province">Province or territory</label>
        <select id="tc-province" data-tc="province"></select>
      </div>

      <div class="taxcalc-field">
        <label for="tc-income">Self-employed income (for the year)</label>
        <div class="taxcalc-money">
          <span class="taxcalc-money-affix">$</span>
          <input id="tc-income" data-tc="income" type="number" inputmode="decimal" min="0" step="100" placeholder="0">
        </div>
      </div>

      <div class="taxcalc-field">
        <label for="tc-expenses">Business expenses (for the year)</label>
        <div class="taxcalc-money">
          <span class="taxcalc-money-affix">$</span>
          <input id="tc-expenses" data-tc="expenses" type="number" inputmode="decimal" min="0" step="100" placeholder="0">
        </div>
        <p class="taxcalc-hint">Income minus expenses is your taxable profit.</p>
      </div>

      <p class="taxcalc-note" data-tc="region-note"></p>
    </form>

    <div class="taxcalc-results" data-tc-results aria-live="polite">
      <div class="taxcalc-headline">
        <span class="taxcalc-headline-label">Set aside about</span>
        <span class="taxcalc-headline-amount" data-tec-row="Set aside for tax" data-tc="setaside">$0</span>
        <span class="taxcalc-headline-sub"><span data-tc="setaside-pct">0%</span> of your income &middot; roughly <span data-tc="quarterly">$0</span> per quarter</span>
      </div>

      <dl class="taxcalc-breakdown">
        <div class="taxcalc-breakdown-row">
          <dt>Taxable profit</dt>
          <dd data-tec-row="Net profit" data-tc="netprofit">$0</dd>
        </div>
        <div class="taxcalc-breakdown-row">
          <dt data-tc="contribution-label">Self-employment tax</dt>
          <dd data-tec-row="Contributions" data-tc="contribution">$0</dd>
        </div>
        <div class="taxcalc-breakdown-row">
          <dt>Income tax</dt>
          <dd data-tec-row="Income tax" data-tc="incometax">$0</dd>
        </div>
        <div class="taxcalc-breakdown-row taxcalc-breakdown-total">
          <dt>Total estimated tax</dt>
          <dd data-tec-row="Total owed" data-tc="total">$0</dd>
        </div>
        <div class="taxcalc-breakdown-row taxcalc-breakdown-rate">
          <dt>Effective tax rate</dt>
          <dd data-tec-row="Effective rate" data-tc="effrate">0%</dd>
        </div>
      </dl>
    </div>
  </div>

  <?php tool_email_capture([
      'source' => 'self_employed_tax',
      'title'  => 'Email this estimate to yourself',
      'blurb'  => 'The figures above, so you know what to put aside before the next instalment is due.',
  ]); ?>

  <p class="taxcalc-disclaimer">
    <strong>Estimate only &mdash; not tax advice.</strong> This is a simplified 2026 estimate for a single, basic filer using the standard deduction (US) or basic personal amount (Canada). It leaves out credits, other deductions, and (for the US) state income tax, so your actual tax will differ. Check with a tax professional before making decisions.
  </p>

</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
