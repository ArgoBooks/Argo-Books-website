<?php
// niches/niche-page.php
//
// Shared template for every niche landing page under /free-invoice-generator/.
// Driven entirely by data files in niches/data/{slug}.php.
//
// Routing:
//   - /free-invoice-generator/{slug}/  rewrites here with ?slug={slug}
//     (see .htaccess Rewrite rule).
//   - /free-invoice-generator/  serves the generic seed page via
//     free-invoice-generator/index.php, which sets $_GET['slug'] = 'generic'
//     and requires this file directly.
//
// Heading structure (strict, for SEO):
//   <h1>            target keyword (from $data['h1'])
//   <h2>            Generator
//   <h2>            Sample line items
//   <h2>            Typical payment terms
//   <h2>            Tax notes
//   <h2>            Frequently asked questions
//     <h3>            one per FAQ question
//   <h2>            Related guides and tools
//
// The intro section sits between the H1 and the first H2 without its own
// H2 (the H1 already serves as that section's heading; adding another H2
// would create a duplicate logical section).

// _base.php defines INVGEN_BASE and the shared invgen_render_404() helper that niche_render_404() at the bottom of this file delegates to. Must be required BEFORE the 404 fallbacks fire below.
require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../shared/_base.php';

// --- 1. Sanitize the slug -----------------------------------------------------

$slug_raw = $_GET['slug'] ?? '';
$slug = is_string($slug_raw) ? strtolower($slug_raw) : '';

if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    niche_render_404();
    exit;
}

// --- 2. Load the data file ----------------------------------------------------

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    niche_render_404();
    exit;
}

$data = require $data_file;

if (!is_array($data) || empty($data['h1'])) {
    // Malformed data file. Treat as not found rather than 500.
    niche_render_404();
    exit;
}

// Server-side page view, scoped per niche so the admin dashboard can compare niche pages without one slug drowning the others.
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    $safe_slug_for_event = preg_replace('/[^a-z0-9_-]/', '', $slug);
    defer_client_page_view('invgen_niche_' . $safe_slug_for_event);
}

// Referral source baked into every conversion-pitch CTA inside _fragment.php when this page embeds the generator.
$invgen_ref = $slug === 'generic' ? 'invgen-tool' : ('invgen-' . $slug);

// --- 3. Build page metadata ---------------------------------------------------

$page_title = $data['meta_title'] ?? ($data['h1'] . ' | Argo Books');
$page_description = $data['meta_description'] ?? '';

// Generic slug is canonicalized to /free-invoice-generator/ (no slug segment).
if ($slug === 'generic') {
    $canonical_url = 'https://argorobots.com/free-invoice-generator/';
} else {
    $canonical_url = 'https://argorobots.com/free-invoice-generator/' . $slug . '/';
}

// --- 4. Build JSON-LD ---------------------------------------------------------

// FAQPage from $data['faqs']
$faq_items = [];
foreach (($data['faqs'] ?? []) as $faq) {
    if (empty($faq['q']) || empty($faq['a'])) {
        continue;
    }
    $faq_items[] = [
        '@type' => 'Question',
        'name' => $faq['q'],
        'acceptedAnswer' => [
            '@type' => 'Answer',
            'text' => $faq['a'],
        ],
    ];
}
$page_schema_json = null;
if (!empty($faq_items)) {
    $page_schema_json = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faq_items,
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// BreadcrumbList: Home > Free Invoice Generator > [Niche Name]
// For the generic page, only Home > Free Invoice Generator (two items).
$breadcrumb_crumbs = ['Home' => '/', 'Free Invoice Generator' => '/free-invoice-generator/'];
if ($slug !== 'generic') {
    $breadcrumb_crumbs[$data['h1']] = $canonical_url;
}
$breadcrumb_schema_json = argo_breadcrumb_schema($breadcrumb_crumbs);

// --- 4b. Hreflang alternates -------------------------------------------------- Country-specific
// niche pages reference each other via hreflang so the right page surfaces in the right region.

$hreflang_alternates = [];
if (!empty($data['concept']) && !empty($data['country'])) {
    // glob() can return false on I/O error (open_basedir, transient permission);
    // foreach over false fatals in PHP 8. Default to an empty list instead.
    $sibling_files = glob(__DIR__ . '/data/*.php') ?: [];
    foreach ($sibling_files as $sibling_file) {
        $sibling_slug = basename($sibling_file, '.php');
        if ($sibling_slug === '_template') continue;
        // A malformed sibling data file (returns non-array, or has a runtime error)
        // must not bring down every country-tagged niche page.
        try {
            $sibling = require $sibling_file;
        } catch (\Throwable $_e) {
            continue;
        }
        if (!is_array($sibling)) continue;
        if (($sibling['concept'] ?? null) !== $data['concept']) continue;

        $sibling_slug_val = $sibling['slug'] ?? $sibling_slug;
        $sibling_href = $sibling_slug_val === 'generic'
            ? 'https://argorobots.com/free-invoice-generator/'
            : "https://argorobots.com/free-invoice-generator/{$sibling_slug_val}/";

        if (empty($sibling['country'])) {
            $hreflang_alternates[] = ['lang' => 'x-default', 'href' => $sibling_href];
        } else {
            $lang = 'en-' . strtolower($sibling['country']);
            $hreflang_alternates[] = ['lang' => $lang, 'href' => $sibling_href];
        }
    }
}

// --- 5. Compose the page body -------------------------------------------------

$related_slugs = $data['related_slugs'] ?? [];
$is_dev = !defined('APP_ENV') || (defined('APP_ENV') && APP_ENV !== 'production');
// If db_connect.php has been loaded earlier, prefer the official helper.
if (function_exists('current_environment')) {
    $is_dev = current_environment() !== 'production';
}

// INVGEN_BASE has to be defined before the body buffer starts so the related-niche and related- template anchors below can prefix their hrefs.
require_once __DIR__ . '/../shared/_base.php';

ob_start();
?>
<article class="niche-page">

  <h1><?= htmlspecialchars($data['h1']) ?></h1>

  <section class="niche-intro">
    <?= $data['intro_html'] ?? '' ?>
  </section>

  <section class="niche-generator">
    <h2>Generator</h2>
    <?php include __DIR__ . '/../invoice-generator/_fragment.php'; ?>
  </section>

  <?php if (!empty($data['sample_line_items'])): ?>
  <section class="niche-samples">
    <h2>Sample line items</h2>
    <table class="niche-samples-table">
      <thead>
        <tr>
          <th scope="col">Item</th>
          <th scope="col" class="num">Rate</th>
          <th scope="col" class="num">Qty</th>
          <th scope="col" class="num">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['sample_line_items'] as $item): ?>
          <?php
            $desc = $item['description'] ?? '';
            $rate = isset($item['rate']) ? (float)$item['rate'] : 0.0;
            $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
            $amount = $rate * $qty;
          ?>
          <tr>
            <th scope="row" class="niche-sample-desc"><?= htmlspecialchars($desc) ?></th>
            <td class="num">$<?= htmlspecialchars(number_format($rate, 2)) ?></td>
            <td class="num"><?= htmlspecialchars(rtrim(rtrim(number_format($qty, 2), '0'), '.')) ?></td>
            <td class="num niche-sample-amount">$<?= htmlspecialchars(number_format($amount, 2)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <?php if (!empty($data['typical_payment_terms_html'])): ?>
  <section class="niche-terms">
    <h2>Typical payment terms</h2>
    <?= $data['typical_payment_terms_html'] ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($data['tax_notes_html'])): ?>
  <section class="niche-tax">
    <h2>Tax notes</h2>
    <?= $data['tax_notes_html'] ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($data['faqs'])): ?>
  <section class="niche-faqs">
    <h2>Frequently asked questions</h2>
    <?= argo_faq_grid($data['faqs']) ?>
  </section>
  <?php endif; ?>

  <section class="niche-related">
    <h2>Related guides and tools</h2>
    <?php if (!empty($related_slugs)): ?>
      <ul class="niche-related-list">
        <?php foreach ($related_slugs as $rs): ?>
          <?php if (!is_string($rs) || !preg_match('/^[a-z0-9-]+$/', $rs)) continue; ?>
          <li>
            <a href="<?= INVGEN_BASE ?>/free-invoice-generator/<?= htmlspecialchars($rs) ?>/">
              <?= htmlspecialchars(ucwords(str_replace('-', ' ', $rs))) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>More niche-specific guides are on the way.</p>
    <?php endif; ?>

    <?php if ($is_dev && count($related_slugs) < 3): ?>
      <p class="niche-dev-warning" style="border:2px solid #c00;background:#fff5f5;color:#900;padding:10px 14px;margin-top:12px;font-weight:600;">
        MISSING INTERNAL LINKS: <?= count($related_slugs) ?>/3
      </p>
    <?php endif; ?>
  </section>

  <?php
    $related_template_slugs = $data['related_template_slugs'] ?? [];
    $related_template_slugs = array_values(array_filter(
      $related_template_slugs,
      fn($s) => is_string($s) && preg_match('/^[a-z0-9-]+$/', $s)
    ));
  ?>
  <?php if (!empty($related_template_slugs)): ?>
  <section class="niche-related-templates">
    <h2>Related templates to download</h2>
    <ul class="niche-related-list">
      <?php foreach ($related_template_slugs as $ts): ?>
        <li>
          <a href="<?= INVGEN_BASE ?>/invoice-template/<?= htmlspecialchars($ts) ?>/">
            <?= htmlspecialchars(ucwords(str_replace('-', ' ', $ts))) ?> template
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">If you want to handle payments, refunds, and track everything,</span>
    <a class="link page-banner-link"
       data-pitch-placement="footer"
       href="<?= INVGEN_BASE ?>/?source=<?= htmlspecialchars($invgen_ref) ?>&amp;utm_source=invoice-generator&amp;utm_medium=niche&amp;utm_campaign=phase1&amp;placement=footer&amp;niche=<?= htmlspecialchars($slug) ?>">
      use Argo Books <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>

</article>
<?php
$body_content = ob_get_clean();

$extra_scripts = '';
// The slug is emitted even with no generator defaults, so main.js can label its event with the niche they came from.
$extra_scripts .= '<script>window.INVOICE_NICHE_SLUG = '
  . json_encode($slug, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
  . ';</script>';
if (!empty($data['generator_defaults'])) {
  $extra_scripts .= '<script>window.INVOICE_NICHE_DEFAULTS = '
    . json_encode($data['generator_defaults'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)
    . ';</script>';
}
require_once __DIR__ . '/../shared/_base.php';
$extra_scripts .= '<script type="module" src="' . INVGEN_BASE . '/invoice-generator/scripts/main.js"></script>';

include __DIR__ . '/../shared/layout.php';

// -----------------------------------------------------------------------------

function niche_render_404(): void
{
    invgen_render_404(
        'Page not found',
        '<p>The page you asked for does not exist. Try the <a href="' . INVGEN_BASE . '/free-invoice-generator/">free invoice generator</a>.</p>'
    );
}
