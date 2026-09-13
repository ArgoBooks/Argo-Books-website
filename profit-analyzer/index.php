<?php
// profit-analyzer/index.php
// Owner-facing landing page for the free Profit Analyzer. Tier 1 (see
// read-me/Tool page standards.md). Upload handling in assets/upload.js, the
// sample money-flow chart in assets/owner-sample.js.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('profit_analyzer');
}

$page_title = 'Free Profit Analyzer — see where your business is losing money | Argo Books';
$page_description = 'Upload your spreadsheet and instantly see where your business is losing money: fees, unprofitable products, and your true margin. Free, no signup.';
$canonical_url = 'https://argorobots.com/profit-analyzer/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

// Conversion CTA target + tracking. "Try Argo" funnels into the download page.
$cta = INVGEN_BASE . '/downloads/?source=profit-analyzer-tool&amp;utm_source=profit-analyzer&amp;utm_medium=tool&amp;utm_campaign=launch';
$results = INVGEN_BASE . '/profit-analyzer/results/';

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'Free Profit Analyzer',
  'applicationCategory' => 'FinanceApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => $canonical_url,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Free Tools' => '/tools/',
    'Profit Analyzer' => $canonical_url,
]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/profit-analyzer/assets/profit-analyzer.css">';

$extra_scripts = '<script>'
    . 'window.PA_TOOL = ' . json_encode(INVGEN_BASE . '/profit-analyzer/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
    . 'window.PA_RESULTS = ' . json_encode($results, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';'
    . '</script>'
    . '<script src="' . INVGEN_BASE . '/profit-analyzer/assets/echarts.min.js"></script>'
    . '<script src="' . INVGEN_BASE . '/profit-analyzer/assets/owner-sample.js"></script>'
    . '<script src="' . INVGEN_BASE . '/profit-analyzer/assets/upload.js"></script>';

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Profit Analyzer</h1>
    <p class="site-hero-tagline">Upload a sales or expense spreadsheet and see where the money goes: fees, costs, your best and worst sellers, and the margin you keep.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Argo Books keeps these figures up to date as you record sales and expenses.</span>
    <a class="page-banner-link" href="<?= $cta ?>&amp;placement=banner">Try Argo Books free <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div class="pa-upload-panel">
    <input type="file" id="paFile" accept=".xlsx,.csv" hidden>
    <label class="pa-dropzone" for="paFile" id="paDrop">
      <span class="pa-dropzone-title">Drop a spreadsheet here</span>
      <span class="pa-dropzone-sub">.xlsx or .csv, up to 5 MB</span>
      <span class="pa-pick">Choose file</span>
    </label>
    <p class="pa-sample">No file to hand? <a class="calc-link" href="<?= $results ?>?sample=1">Open the results for a sample shop</a>.</p>
    <div class="pa-error" id="paError" role="alert" hidden></div>
  </div>

  <section class="pa-preview" aria-labelledby="pa-preview-title">
    <h2 class="pa-preview-title" id="pa-preview-title">What the results look like</h2>
    <p class="pa-preview-lead">This is part of the dashboard for a sample online shop selling totes, mugs and candles. Your file produces the same charts from your own numbers.</p>

    <div class="pa-frame">
      <div class="pa-frame-bar">maple-goods-sales-2024.xlsx</div>
      <div class="pa-frame-body">

        <div class="pa-insight">
          <h3>You're losing 9% of revenue to payment &amp; processing fees</h3>
          <p>That's <b>$2,230</b> over the last 90 days, and after every cost you keep <b>19%</b> as profit.</p>
        </div>

        <div class="pa-card">
          <div class="pa-card-head">
            <div>
              <h3 class="pa-card-title">Follow your money</h3>
              <p class="pa-card-meta">Where each dollar of revenue goes before it reaches you</p>
            </div>
            <p class="pa-kept"><b>19%</b> kept as profit</p>
          </div>
          <div id="sankeyChart" style="width:100%;height:360px"></div>
        </div>

        <div class="pa-card">
          <h3 class="pa-card-title">Top products by revenue</h3>
          <p class="pa-card-meta">Revenue per product, last 90 days</p>
          <div class="revbars">
            <div class="pa-bar-row"><div class="pa-bar-name">Totes</div><div class="pa-bar-track"><div class="pa-bar" style="width:100%"></div></div><div class="pa-bar-val">$8,240</div></div>
            <div class="pa-bar-row"><div class="pa-bar-name">Mugs</div><div class="pa-bar-track"><div class="pa-bar" style="width:78%"></div></div><div class="pa-bar-val">$6,460</div></div>
            <div class="pa-bar-row"><div class="pa-bar-name">Candles</div><div class="pa-bar-track"><div class="pa-bar" style="width:57%"></div></div><div class="pa-bar-val">$4,720</div></div>
            <div class="pa-bar-row"><div class="pa-bar-name">Greeting cards</div><div class="pa-bar-track"><div class="pa-bar" style="width:25%"></div></div><div class="pa-bar-val">$2,040</div></div>
            <div class="pa-bar-row"><div class="pa-bar-name">Enamel pins</div><div class="pa-bar-track"><div class="pa-bar" style="width:16%"></div></div><div class="pa-bar-val">$1,280</div></div>
            <div class="pa-bar-row"><div class="pa-bar-name">Stickers</div><div class="pa-bar-track"><div class="pa-bar" style="width:12%"></div></div><div class="pa-bar-val">$960</div></div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <article class="calc-content">

    <section id="how">
      <h2>What file to upload</h2>
      <p>Any spreadsheet of sales, expenses, or both, saved as .xlsx or .csv. An export from a shop platform, a payment processor, or a sheet you keep by hand all work. Files with several tabs or unusual column names are fine.</p>
      <p>The analyzer uses the same AI import engine as Argo Books to work out what each column holds, so there is nothing to map by hand. It takes about a minute, and you can also download the data it read as a cleaned, organized Excel workbook.</p>
    </section>

    <section>
      <h2>What it shows you</h2>
      <ul>
        <li><strong>Fees and processing costs.</strong> How much of each sale goes to payment and platform fees before it reaches you.</li>
        <li><strong>Top and bottom sellers.</strong> Which products and services bring in the most revenue, and which barely move.</li>
        <li><strong>Your biggest expenses.</strong> Where the money goes, ranked by amount.</li>
        <li><strong>Your real margin.</strong> The share of revenue left after every cost in the file.</li>
      </ul>
    </section>

    <section id="trust">
      <h2>What happens to your file</h2>
      <p>Your file is encrypted in transit and at rest, and deleted automatically after the analysis. It is processed by a paid AI service that does not train on your data. You do not need an account or an email address to see results. The details are in the <a class="calc-link" href="<?= INVGEN_BASE ?>/profit-analyzer/legal/privacy.php">privacy notice</a> and <a class="calc-link" href="<?= INVGEN_BASE ?>/profit-analyzer/legal/terms.php">terms of use</a>.</p>
    </section>

    <section>
      <h2>Where a one-off analysis stops helping</h2>
      <p>This reads one file at one point in time. Next month it is out of date, and you would have to export and upload again to see what changed.</p>
      <p><a class="calc-link" href="<?= $cta ?>&amp;placement=content">Argo Books</a> tracks the same figures as you record sales and expenses, alongside invoices and tax-ready reports, so the numbers stay current without rebuilding a spreadsheet.</p>
    </section>

  </article>

</div>

<div class="pa-overlay" id="paOverlay" hidden>
  <div class="pa-overlay-card" role="status">
    <div class="pa-spinner" aria-hidden="true"></div>
    <div class="pa-overlay-title">Reading your spreadsheet…</div>
    <div class="pa-overlay-sub" id="paOverlaySub">Detecting columns and cleaning your data. This takes about a minute.</div>
    <button type="button" class="pa-cancel" id="paCancel">Cancel</button>
  </div>
</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
