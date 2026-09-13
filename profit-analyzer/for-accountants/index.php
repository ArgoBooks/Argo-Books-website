<?php
// profit-analyzer/for-accountants/index.php
// Accountant-facing landing page: clean up a client's messy spreadsheet into a
// tidy, multi-sheet workbook. Same engine as the owner tool, different emphasis.
// CTA uses the referral-channel framing (recommend Argo to clients).

require_once __DIR__ . '/../../shared/_base.php';
require_once __DIR__ . '/../../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../../statistics.php';
    track_page_view('profit_analyzer_accountants');
}

$page_title = "Clean up a client's messy spreadsheet — Free tool for accountants | Argo Books";
$page_description = "Drop in a client's disorganized spreadsheet and get back a tidy, categorized, multi-sheet workbook in about 60 seconds. Free tool for accountants and bookkeepers.";
$canonical_url = 'https://argorobots.com/profit-analyzer/for-accountants/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

$cta = INVGEN_BASE . '/downloads/?source=profit-analyzer-accountant&amp;utm_source=profit-analyzer&amp;utm_medium=tool&amp;utm_campaign=accountants';
$results = INVGEN_BASE . '/profit-analyzer/results/';
$tool = INVGEN_BASE . '/profit-analyzer/';

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'Free Spreadsheet Cleanup for Accountants',
  'applicationCategory' => 'FinanceApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => $canonical_url,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Free Tools' => '/tools/',
    'Profit Analyzer' => '/profit-analyzer/',
    'Spreadsheet Cleanup for Accountants' => $canonical_url,
]);

$extra_head = '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/calculator.css">'
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/profit-analyzer/assets/accountant.css">';

$extra_scripts = <<<'JS'
<script>
(function(){
  var tabs = document.querySelectorAll('.cleanbar .tab');
  var tables = document.querySelectorAll('.sheet-table');
  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){
      tabs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      var s = tab.getAttribute('data-sheet');
      tables.forEach(function(tbl){ tbl.style.display = (tbl.getAttribute('data-sheet') === s) ? '' : 'none'; });
    });
  });
})();
</script>
JS;

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Spreadsheet Cleanup for Accountants</h1>
    <p class="site-hero-tagline">Turn the disorganized file a client sends into a categorized workbook, with one sheet each for sales, expenses, invoices, customers and products.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Argo Books is bookkeeping small-business clients can run themselves, so their records arrive tidy.</span>
    <a class="page-banner-link" href="<?= $cta ?>&amp;placement=banner">See Argo for your clients <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <section class="acc-demo" id="demo" aria-labelledby="acc-demo-title">
    <h2 class="acc-title" id="acc-demo-title">What goes in, and what comes out</h2>
    <p class="acc-lead">A typical client file: mixed date formats, no categories, a title row, blank cells, and amounts stored as text.</p>

    <div class="acc-ba">
      <div class="acc-sheet acc-sheet-bad">
        <div class="acc-sheet-head">client-stuff-FINAL(2).xlsx <span class="acc-sheet-tag">Before</span></div>
        <div class="acc-sheet-scroll">
          <table class="acc-xls">
            <tbody>
              <tr class="acc-titlerow"><td colspan="4">Jan stuff!!</td></tr>
              <tr><td>date</td><td>what</td><td>amt</td><td>notes</td></tr>
              <tr><td class="acc-muddle">1/5/24</td><td>home depot</td><td class="acc-muddle">45.20</td><td>supplies?</td></tr>
              <tr><td class="acc-muddle">jan 6</td><td>Client lunch</td><td>82</td><td class="acc-blank"></td></tr>
              <tr><td class="acc-blank"></td><td>TRANSFER</td><td class="acc-muddle">1,200.00</td><td>?</td></tr>
              <tr><td class="acc-muddle">01-07-2024</td><td>stripe payout</td><td>980.50</td><td>income</td></tr>
              <tr><td>1/9</td><td>gas</td><td class="acc-muddle">$60</td><td class="acc-blank"></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="acc-arrow" aria-hidden="true">&rarr;</div>

      <div class="acc-sheet acc-sheet-good">
        <div class="acc-sheet-head">cleaned-january-2024.xlsx <span class="acc-sheet-tag">After</span></div>
        <div class="acc-sheet-scroll">
          <table class="acc-xls">
            <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th></tr></thead>
            <tbody>
              <tr><td>2024-01-05</td><td>Home Depot</td><td>Supplies</td><td class="acc-amt">45.20</td></tr>
              <tr><td>2024-01-06</td><td>Client lunch</td><td>Meals</td><td class="acc-amt">82.00</td></tr>
              <tr><td>2024-01-06</td><td>Transfer</td><td>Transfer</td><td class="acc-amt">1,200.00</td></tr>
              <tr><td>2024-01-07</td><td>Stripe payout</td><td>Sales income</td><td class="acc-amt acc-inc">980.50</td></tr>
              <tr><td>2024-01-09</td><td>Fuel</td><td>Vehicle</td><td class="acc-amt">60.00</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="acc-actions">
      <a class="acc-btn" href="<?= $tool ?>">Upload a client's spreadsheet</a>
      <a class="calc-link" href="<?= $results ?>?sample=1">Open a sample result</a>
      <span class="acc-actions-note">.xlsx or .csv, up to 5 MB. No account needed.</span>
    </div>
  </section>

  <section class="acc-demo" aria-labelledby="acc-output-title">
    <h2 class="acc-title" id="acc-output-title">The workbook you get back</h2>
    <p class="acc-lead">One messy file becomes a multi-tab workbook: sales, expenses, invoices, customers and products each get their own sheet, with consistent dates, proper amounts, and records linked by ID. It downloads as an Excel file.</p>

    <div class="cleanwrap">
      <div class="cleanbar" role="tablist" aria-label="Workbook sheets">
        <button type="button" class="tab active" role="tab" aria-selected="true" data-sheet="sales">Sales</button>
        <button type="button" class="tab" role="tab" aria-selected="false" data-sheet="expenses">Expenses</button>
        <button type="button" class="tab" role="tab" aria-selected="false" data-sheet="invoices">Invoices</button>
        <button type="button" class="tab" role="tab" aria-selected="false" data-sheet="customers">Customers</button>
        <button type="button" class="tab" role="tab" aria-selected="false" data-sheet="products">Products</button>
      </div>
      <div class="acc-sheet-scroll">
        <table class="cleantable sheet-table" data-sheet="sales">
          <thead><tr><th>Date</th><th>Customer</th><th>Item</th><th class="acc-r">Amount</th><th class="acc-r">Tax</th><th class="acc-r">Total</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>2024-01-07</td><td>Riverside Co.</td><td>Totes ×20</td><td class="acc-amt">$400.00</td><td class="acc-amt">$52.00</td><td class="acc-amt">$452.00</td><td>Paid</td></tr>
            <tr><td>2024-01-12</td><td>Maple Retail</td><td>Mugs ×30</td><td class="acc-amt">$510.00</td><td class="acc-amt">$66.30</td><td class="acc-amt">$576.30</td><td>Paid</td></tr>
            <tr><td>2024-01-15</td><td>A. Whitfield</td><td>Candles ×12</td><td class="acc-amt">$192.00</td><td class="acc-amt">$24.96</td><td class="acc-amt">$216.96</td><td>Partial</td></tr>
            <tr><td>2024-01-18</td><td>J. Okafor</td><td>Greeting cards ×40</td><td class="acc-amt">$160.00</td><td class="acc-amt">$20.80</td><td class="acc-amt">$180.80</td><td>Unpaid</td></tr>
            <tr><td>2024-01-21</td><td>Bianchi Ltd</td><td>Stickers ×60</td><td class="acc-amt">$240.00</td><td class="acc-amt">$31.20</td><td class="acc-amt">$271.20</td><td>Paid</td></tr>
          </tbody>
        </table>
        <table class="cleantable sheet-table" data-sheet="expenses" style="display:none">
          <thead><tr><th>Date</th><th>Supplier</th><th>Description</th><th class="acc-r">Amount</th><th class="acc-r">Tax</th><th class="acc-r">Total</th><th>Method</th></tr></thead>
          <tbody>
            <tr><td>2024-01-05</td><td>Home Depot</td><td>Workshop supplies</td><td class="acc-amt">$45.20</td><td class="acc-amt">$5.88</td><td class="acc-amt">$51.08</td><td>Card</td></tr>
            <tr><td>2024-01-06</td><td></td><td>Client lunch</td><td class="acc-amt">$82.00</td><td class="acc-amt">$0.00</td><td class="acc-amt">$82.00</td><td>Card</td></tr>
            <tr><td>2024-01-09</td><td>Petro-Canada</td><td>Fuel</td><td class="acc-amt">$60.00</td><td class="acc-amt">$7.80</td><td class="acc-amt">$67.80</td><td>Card</td></tr>
            <tr><td>2024-01-14</td><td>Meta</td><td>Facebook Ads</td><td class="acc-amt">$210.00</td><td class="acc-amt">$0.00</td><td class="acc-amt">$210.00</td><td>Card</td></tr>
            <tr><td>2024-01-20</td><td>Pak Supplies</td><td>Raw cotton (totes)</td><td class="acc-amt">$1,340.00</td><td class="acc-amt">$174.20</td><td class="acc-amt">$1,514.20</td><td>Transfer</td></tr>
          </tbody>
        </table>
        <table class="cleantable sheet-table" data-sheet="invoices" style="display:none">
          <thead><tr><th>Invoice #</th><th>Customer</th><th>Issued</th><th>Due</th><th class="acc-r">Total</th><th class="acc-r">Balance</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>INV-2024-001</td><td>Riverside Co.</td><td>2024-01-07</td><td>2024-02-06</td><td class="acc-amt">$452.00</td><td class="acc-amt">$0.00</td><td>Paid</td></tr>
            <tr><td>INV-2024-002</td><td>Maple Retail</td><td>2024-01-12</td><td>2024-02-11</td><td class="acc-amt">$576.30</td><td class="acc-amt">$0.00</td><td>Paid</td></tr>
            <tr><td>INV-2024-003</td><td>A. Whitfield</td><td>2024-01-15</td><td>2024-02-14</td><td class="acc-amt">$216.96</td><td class="acc-amt">$108.48</td><td>Partial</td></tr>
            <tr><td>INV-2024-004</td><td>J. Okafor</td><td>2024-01-18</td><td>2024-02-17</td><td class="acc-amt">$180.80</td><td class="acc-amt">$180.80</td><td>Overdue</td></tr>
            <tr><td>INV-2024-005</td><td>Bianchi Ltd</td><td>2024-01-21</td><td>2024-02-20</td><td class="acc-amt">$271.20</td><td class="acc-amt">$0.00</td><td>Paid</td></tr>
          </tbody>
        </table>
        <table class="cleantable sheet-table" data-sheet="customers" style="display:none">
          <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>City</th><th>Status</th><th class="acc-r">Total purchases</th></tr></thead>
          <tbody>
            <tr><td>A. Whitfield</td><td></td><td>awhit@example.com</td><td>Toronto</td><td>Active</td><td class="acc-amt">$1,180</td></tr>
            <tr><td>Riverside Co.</td><td>Riverside Co.</td><td>ar@riverside.co</td><td>Vancouver</td><td>Active</td><td class="acc-amt">$2,100</td></tr>
            <tr><td>J. Okafor</td><td></td><td>jokafor@example.com</td><td>Calgary</td><td>Active</td><td class="acc-amt">$920</td></tr>
            <tr><td>Maple Retail</td><td>Maple Retail Inc.</td><td>buy@mapleretail.ca</td><td>Ottawa</td><td>Active</td><td class="acc-amt">$1,640</td></tr>
            <tr><td>M. Bianchi</td><td>Bianchi Ltd</td><td>mb@bianchi.it</td><td>Milan</td><td>Active</td><td class="acc-amt">$760</td></tr>
          </tbody>
        </table>
        <table class="cleantable sheet-table" data-sheet="products" style="display:none">
          <thead><tr><th>Name</th><th>SKU</th><th>Type</th><th>Category</th><th>Supplier</th><th class="acc-r">Reorder point</th></tr></thead>
          <tbody>
            <tr><td>Totes</td><td>TOT-01</td><td>Revenue</td><td>Bags</td><td>Pak Supplies</td><td class="acc-amt">50</td></tr>
            <tr><td>Mugs</td><td>MUG-01</td><td>Revenue</td><td>Drinkware</td><td>Northwind</td><td class="acc-amt">40</td></tr>
            <tr><td>Candles</td><td>CAN-01</td><td>Revenue</td><td>Home</td><td>Acme Co.</td><td class="acc-amt">30</td></tr>
            <tr><td>Greeting cards</td><td>CRD-01</td><td>Revenue</td><td>Stationery</td><td>Northwind</td><td class="acc-amt">100</td></tr>
            <tr><td>Stickers</td><td>STK-01</td><td>Revenue</td><td>Stationery</td><td>Acme Co.</td><td class="acc-amt">80</td></tr>
          </tbody>
        </table>
      </div>
      <div class="cleanfoot">Sample data. Rows the tool is unsure about are flagged for your review.</div>
    </div>
  </section>

  <article class="calc-content">

    <section id="how">
      <h2>What it sorts out</h2>
      <ul>
        <li><strong>Splits one file into proper books.</strong> Customers, suppliers, products, invoices, expenses, payments, inventory and more are separated out of a single jumbled sheet into their own tabs.</li>
        <li><strong>Reads any layout.</strong> Pivot tables, cross-tabs and line-item rows are handled, and scattered lines are grouped back into the right invoice or record.</li>
        <li><strong>Matches up the columns.</strong> It works out what each column means even when it is renamed, reordered, or labelled oddly ("Sales", "Purchases", and the like).</li>
        <li><strong>Standardizes dates and amounts.</strong> Every date goes to one format, and every amount becomes a real number, whether it had a currency symbol, commas, or was stored as text.</li>
        <li><strong>Categorizes and fills gaps.</strong> It assigns categories, marks income and expense, and generates IDs for records that are missing them.</li>
        <li><strong>Flags what to double-check.</strong> Ambiguous rows are marked for your review instead of guessed at quietly, then everything comes back as a formatted, multi-tab Excel workbook.</li>
      </ul>
    </section>

    <section id="trust">
      <h2>What happens to your client's file</h2>
      <p>Files are encrypted in transit and at rest, and deleted automatically after the analysis. They are processed by a paid AI service that does not train on your data. No account or email address is required to use the tool. The details are in the <a class="calc-link" href="<?= INVGEN_BASE ?>/profit-analyzer/legal/privacy.php">privacy notice</a> and <a class="calc-link" href="<?= INVGEN_BASE ?>/profit-analyzer/legal/terms.php">terms of use</a>.</p>
    </section>

    <section>
      <h2>Keeping it clean after the cleanup</h2>
      <p>A cleanup fixes one file. The next one a client sends will need the same work.</p>
      <p><a class="calc-link" href="<?= $cta ?>&amp;placement=content">Argo Books</a> is bookkeeping software your small-business clients can run themselves, so their records stay organized through the year. Recommend it, or set them up yourself.</p>
    </section>

  </article>

</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../../shared/layout.php';
