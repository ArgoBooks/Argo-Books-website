<?php
// invoice-template/template-page.php
//
// Shared template for every page under /invoice-template/. Driven by data
// files in invoice-template/data/{slug}.php.
//
// Routing:
//   - /invoice-template/{slug}/  rewrites here with ?slug={slug}
//   - /invoice-template/         serves invoice-template/index.php (hub).
//
// Heading structure:
//   <h1>     target keyword
//   <h2>     About this template
//   <h2>     Download (style-format pages) OR Choose a style (format-generic pages)
//   <h2>     Frequently asked questions
//   <h2>     Related templates

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../shared/_base.php';

// --- 1. Sanitize the slug -----------------------------------------------------

$slug_raw = $_GET['slug'] ?? '';
$slug = is_string($slug_raw) ? strtolower($slug_raw) : '';

if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    template_render_404();
    exit;
}

// --- 2. Load the data file ----------------------------------------------------

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    template_render_404();
    exit;
}

$data = require $data_file;

if (!is_array($data) || empty($data['h1']) || empty($data['format'])) {
    template_render_404();
    exit;
}

// --- 3. Server-side page view (post-404) --------------------------------------

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    $safe_slug_for_event = preg_replace('/[^a-z0-9_-]/', '', $slug);
    defer_client_page_view('invgen_template_' . $safe_slug_for_event);
}

// --- 4. Page metadata ---------------------------------------------------------

$page_title = $data['meta_title'] ?? ($data['h1'] . ' | Argo Books');
$page_description = $data['meta_description'] ?? '';
$canonical_url = 'https://argorobots.com/invoice-template/' . $slug . '/';

// --- 5. JSON-LD ---------------------------------------------------------------

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

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Invoice Templates' => '/invoice-template/',
    $data['h1'] => $canonical_url,
]);

// --- 6. Asset config ----------------------------------------------------------

$assets_file = __DIR__ . '/../invoice-generator/data/template-assets.json';
$assets = is_file($assets_file) ? json_decode(file_get_contents($assets_file), true) : [];
$assets = is_array($assets) ? $assets : [];

// --- 7. Compose the body ------------------------------------------------------

$kind = $data['kind'] ?? 'format-generic';
$style = $data['style'] ?? null;
$format = $data['format'] ?? '';
$invgen_ref = 'invgen-template-' . $slug;
$ref_qs = '?source=' . htmlspecialchars($invgen_ref) . '&amp;utm_source=invoice-generator&amp;utm_medium=template&amp;utm_campaign=phase1';

ob_start();
?>
<article class="template-page" data-kind="<?= htmlspecialchars($kind) ?>" data-format="<?= htmlspecialchars($format) ?>"<?php if ($style): ?> data-style="<?= htmlspecialchars($style) ?>"<?php endif; ?>>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Want to handle payments, refunds, and track everything?</span>
    <a class="link page-banner-link" data-pitch-placement="template-banner" href="<?= INVGEN_BASE ?>/features/invoicing/<?= $ref_qs ?>&amp;placement=banner">Try Argo Books <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <nav class="template-breadcrumb" aria-label="Breadcrumb">
    <a class="link" href="<?= INVGEN_BASE ?>/invoice-template/"><span aria-hidden="true">&larr;</span> All invoice templates</a>
  </nav>

  <h1><?= htmlspecialchars($data['h1']) ?></h1>

  <section class="template-intro">
    <?= $data['intro_html'] ?? '' ?>
  </section>

  <section class="template-body">
    <h2>About this template</h2>
    <?= $data['body_html'] ?? '' ?>
  </section>

  <?php if ($kind === 'style-format' && $style && $format): ?>
  <section class="template-download">
    <h2>Download this template</h2>
    <?php
      // Preview thumbnail. PNG is committed in invoice-template/preview/{style}.png.
      $preview_src = INVGEN_BASE . '/invoice-template/preview/' . rawurlencode($style) . '.png';
    ?>
    <img class="template-preview"
         src="<?= htmlspecialchars($preview_src) ?>"
         alt="<?= htmlspecialchars(ucfirst($style)) ?> style invoice preview"
         loading="lazy"
         width="600" height="450">

    <?php
      // CTA varies by format. PDF and Word link into the live tool with ?template=<style>; Excel serves a static file; Google Docs/Sheets open a "Make a copy" Google URL.
      $cta_label = '';
      $cta_href  = '';
      $cta_event = 'invgen_template_cta_clicked';

      if ($format === 'pdf' || $format === 'word') {
          $cta_label = $format === 'pdf' ? 'Customize and download PDF' : 'Customize and download Word';
          $cta_href = INVGEN_BASE . '/invoice-generator/?template=' . rawurlencode($style) . '&source=' . rawurlencode($invgen_ref);
      } elseif ($format === 'excel') {
          $filename = $assets['excel'][$style] ?? ($style . '.xlsx');
          $cta_label = 'Download Excel template';
          $cta_href = INVGEN_BASE . '/invoice-generator/templates/' . rawurlencode($filename);
          $cta_event = 'invgen_template_download';
      } elseif ($format === 'google-docs') {
          $cta_label = 'Make a copy in Google Docs';
          $cta_href = $assets['google-docs'][$style] ?? '#';
          $cta_event = 'invgen_template_download';
      } elseif ($format === 'google-sheets') {
          $cta_label = 'Make a copy in Google Sheets';
          $cta_href = $assets['google-sheets'][$style] ?? '#';
          $cta_event = 'invgen_template_download';
      }
    ?>

    <a class="link template-cta-button"
       href="<?= htmlspecialchars($cta_href) ?>"
       data-template-cta="<?= htmlspecialchars($cta_event) ?>"
       data-template-style="<?= htmlspecialchars($style) ?>"
       data-template-format="<?= htmlspecialchars($format) ?>"<?php if (in_array($format, ['google-docs', 'google-sheets'], true)): ?> target="_blank" rel="noopener"<?php endif; ?>>
      <?= htmlspecialchars($cta_label) ?>
    </a>
  </section>
  <?php elseif ($kind === 'format-generic' && $format): ?>
  <section class="template-style-grid">
    <h2>Choose a style</h2>
    <ul class="template-style-list">
      <?php foreach (['classic', 'modern', 'formal', 'elegant', 'ribbon'] as $s): ?>
        <li class="template-style-card">
          <a class="link" href="<?= INVGEN_BASE ?>/invoice-template/<?= htmlspecialchars($s) ?>-<?= htmlspecialchars($format) ?>/">
            <img src="<?= INVGEN_BASE ?>/invoice-template/preview/<?= rawurlencode($s) ?>.png"
                 alt="<?= htmlspecialchars(ucfirst($s)) ?> invoice template preview"
                 loading="lazy" width="300" height="225">
            <span class="template-style-name"><?= htmlspecialchars(ucfirst($s)) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if (!empty($data['faqs'])): ?>
  <section class="template-faqs">
    <h2>Frequently asked questions</h2>
    <?= argo_faq_grid($data['faqs']) ?>
  </section>
  <?php endif; ?>

  <section class="template-related">
    <h2>Related templates</h2>
    <?php $rel = $data['related_slugs'] ?? []; ?>
    <?php if (!empty($rel)): ?>
      <ul class="template-related-list">
        <?php foreach ($rel as $rs): ?>
          <?php if (!is_string($rs) || !preg_match('/^[a-z0-9-]+$/', $rs)) continue; ?>
          <li><a class="link" href="<?= INVGEN_BASE ?>/invoice-template/<?= htmlspecialchars($rs) ?>/"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $rs))) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>More templates are on the way.</p>
    <?php endif; ?>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">If you want to handle payments, refunds, and track everything,</span>
    <a class="link page-banner-link"
       data-pitch-placement="template-footer"
       href="<?= INVGEN_BASE ?>/?source=<?= htmlspecialchars($invgen_ref) ?>&amp;utm_source=invoice-generator&amp;utm_medium=template&amp;utm_campaign=phase1&amp;placement=footer">
      use Argo Books <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>

</article>
<?php
$body_content = ob_get_clean();

$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/invoice-template/scripts/template-page-tracker.js"></script>';


include __DIR__ . '/../shared/layout.php';

// -----------------------------------------------------------------------------

function template_render_404(): void
{
    invgen_render_404(
        'Template not found',
        '<p>The template you asked for does not exist. Browse <a class="link" href="' . INVGEN_BASE . '/invoice-template/">all templates</a>.</p>'
    );
}
