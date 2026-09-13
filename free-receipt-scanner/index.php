<?php
// free-receipt-scanner/index.php
// Free public AI receipt scanner. Tier 3 (see read-me/Tool page standards.md):
// the currency is read off the receipt. Client logic in scanner.js mirrors the
// desktop scanner's review UI and funnels to the free app + Premium at the
// daily limit.

require_once __DIR__ . '/../shared/_base.php'; // defines INVGEN_BASE
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php'; // self-loads db_connect.php
    track_page_view('receipt_scanner_tool');
}

require_once __DIR__ . '/../config/pricing.php';
$cfg = get_pricing_config();
$perVisitor = $cfg['web_receipt_scan_daily_limit'];
$base = INVGEN_BASE; // '' in production, '/argo-books-website' on Laragon
$dl = $base . '/downloads/?source=receipt-scanner';

$page_title = 'Free Receipt Scanner Online — reads every line and tax | Argo Books';
$page_description = 'Scan a receipt and get every line item, each tax line (GST, HST, PST), and the total in any currency. Free, no signup. Download as CSV or JSON.';
$canonical_url = 'https://argorobots.com/free-receipt-scanner/';

$tools_back = ['href' => $base . '/tools/', 'label' => 'All tools'];

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'Free Receipt Scanner',
  'applicationCategory' => 'FinanceApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => $canonical_url,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Free Tools' => '/tools/',
    'Free Receipt Scanner' => $canonical_url,
]);

$turnstileSiteKey = $_ENV['TURNSTILE_SITE_KEY'] ?? '';

$extra_head = '<link rel="stylesheet" href="' . $base . '/shared/styles/calculator.css">'
    . '<link rel="stylesheet" href="' . $base . '/free-receipt-scanner/scanner.css">'
    . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit" async defer></script>'
    . '<script>window.RS_CONFIG = ' . json_encode([
        'base' => $base,
        'turnstileSiteKey' => $turnstileSiteKey,
        'perVisitor' => $perVisitor,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';

// FingerprintJS is imported inside scanner.js as an ES module; no separate tag needed.
$extra_scripts = '<script src="' . $base . '/free-receipt-scanner/scanner.js" type="module"></script>';

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Free Receipt Scanner</h1>
    <p class="site-hero-tagline">Upload a photo of a receipt and get the line items, each tax line, and the total, ready to check and download as Excel, CSV, or JSON.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Receipts scanned in Argo Books are saved and filed as expenses.</span>
    <a class="page-banner-link" href="<?= $base ?>/features/receipt-scanning/?source=receipt-scanner-banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <div id="rs-stage" class="rs-stage" data-state="upload">
    <div id="rs-upload" class="rs-upload">
      <div id="rs-dropzone" class="rs-dropzone" tabindex="0" role="button" aria-label="Upload a receipt">
        <span class="rs-dropzone-title">Drop a receipt here</span>
        <span class="rs-dropzone-sub">A photo or a scan in JPEG, PNG, or WebP. Drop up to 10 to scan them together.</span>
        <span class="rs-pick">Choose files</span>
        <input type="file" id="rs-file-input" accept="image/jpeg,image/png,image/webp" multiple hidden>
      </div>
      <p class="rs-sample">No receipt to hand? <a id="rs-sample" class="calc-link" href="#">Try a sample receipt</a>.</p>
      <div id="rs-turnstile" class="rs-turnstile"></div>
      <p class="rs-scans-left"><span id="rs-scans-left"><?= (int)$perVisitor ?></span> free scans left today. No account needed.</p>
    </div>

    <div id="rs-review" class="rs-review" hidden></div>

    <div id="rs-limit" class="rs-limit" hidden></div>
  </div>

  <article class="calc-content">

    <section id="how">
      <h2>What it reads</h2>
      <ul>
        <li><strong>Every line item.</strong> Each product, its quantity, unit price, and line total, in the order they appear.</li>
        <li><strong>Each tax line, separately.</strong> GST, HST, PST, QST, and VAT broken out on their own, so the numbers match your books.</li>
        <li><strong>Totals and discounts.</strong> Subtotal, discounts, and the final total, with a confidence score on the read.</li>
        <li><strong>Currency and payment.</strong> The currency is taken from the receipt, along with how it was paid.</li>
      </ul>
    </section>

    <section>
      <h2>How to use it</h2>
      <p>Upload a photo or a scan of the receipt. It is read by the same AI engine as the receipt scanner in Argo Books, and the fields it finds appear next to the image so you can compare them. Correct anything it misread, add or remove line items, then copy the data or download it as Excel, CSV, or JSON.</p>
    </section>

    <section id="privacy">
      <h2>What happens to your receipt</h2>
      <p>Receipts are processed in memory and never written to disk, and nothing is saved after you get your result. They are read by a paid AI service that does not train on your data. No account or email address is required. The details are in the <a class="calc-link" href="<?= $base ?>/legal/privacy.php">privacy notice</a> and <a class="calc-link" href="<?= $base ?>/legal/terms.php">terms of use</a>.</p>
    </section>

    <section>
      <h2>Where this scanner stops</h2>
      <p>This reads one receipt at a time and keeps nothing, so the data is gone once you leave the page.</p>
      <p>In <a class="calc-link" href="<?= $dl ?>-bridge">Argo Books</a>, each scanned receipt is saved and filed as an expense, alongside your invoices, taxes, and reports. The free plan includes <?= (int)$cfg['free_receipt_scan_monthly_limit'] ?> scans a month, and <a class="calc-link" href="<?= $base ?>/pricing/?source=receipt-scanner-bridge">Premium</a> includes <?= (int)$cfg['receipt_scan_monthly_limit'] ?>.</p>
    </section>

  </article>

</div>

<div class="rs-overlay" id="rs-scanning" hidden>
  <div class="rs-overlay-card" role="status">
    <div class="rs-overlay-spinner" aria-hidden="true"></div>
    <div class="rs-overlay-title" id="rs-overlay-title">Reading your receipt…</div>
    <div class="rs-overlay-sub" id="rs-overlay-sub">Pulling out every line, tax, and total. This takes a few seconds.</div>
    <div class="rs-bulk" id="rs-bulk" hidden>
      <div class="rs-bulk-bar"><div class="rs-bulk-fill" id="rs-bulk-fill"></div></div>
      <ul class="rs-bulk-list" id="rs-bulk-list"></ul>
    </div>
    <button type="button" class="rs-cancel" id="rs-cancel">Cancel</button>
  </div>
</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
