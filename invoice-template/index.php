<?php
// invoice-template/index.php
// Hub page listing every style x format combination.
// Conventions match the other tool pages: real site header from layout.php,
// SoftwareApplication is NOT the right schema here (the page is a directory,
// not a tool); use CollectionPage instead.

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../shared/_base.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    defer_client_page_view('invgen_template_hub');
}

$page_title = 'Free Invoice Templates | Argo Books';
$page_description = 'Free invoice templates in PDF, Word, Excel, Google Docs, and Google Sheets. Pick a style: Classic, Modern, Formal, Elegant, or Ribbon.';
$canonical_url = 'https://argorobots.com/invoice-template/';

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Free Invoice Templates',
    'url' => $canonical_url,
    'description' => $page_description,
], JSON_UNESCAPED_SLASHES);

$breadcrumb_schema_json = argo_breadcrumb_schema(['Home' => '/', 'Invoice Templates' => $canonical_url]);

$styles = ['classic', 'modern', 'formal', 'elegant', 'ribbon'];
$formats = [
    'pdf' => 'PDF',
    'word' => 'Word',
    'excel' => 'Excel',
    'google-docs' => 'Google Docs',
    'google-sheets' => 'Google Sheets',
];

$invgen_ref = 'invgen-template-hub';
$ref_qs = '?source=' . htmlspecialchars($invgen_ref) . '&amp;utm_source=invoice-generator&amp;utm_medium=template&amp;utm_campaign=phase1';

ob_start();
?>
<article class="template-hub">

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Want to handle payments, refunds, and track everything?</span>
    <a class="link page-banner-link" data-pitch-placement="template-hub-banner" href="<?= INVGEN_BASE ?>/features/invoicing/<?= $ref_qs ?>&amp;placement=banner">Try Argo Books <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <h1>Free invoice templates</h1>

  <section class="template-hub-intro">
    <p>Pick a style and a file format. Every template is free and downloads in seconds. PDF and Word templates let you customize every field before saving. Excel, Google Docs, and Google Sheets templates are downloadable files ready to fill in.</p>
  </section>

  <section class="template-hub-formats">
    <h2>By format</h2>
    <ul class="template-hub-format-list">
      <?php foreach ($formats as $slug => $name): ?>
        <li><a class="link" href="<?= INVGEN_BASE ?>/invoice-template/<?= htmlspecialchars($slug) ?>/"><?= htmlspecialchars($name) ?> invoice templates</a></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="template-hub-grid">
    <h2>By style and format</h2>
    <?php foreach ($styles as $style): ?>
      <div class="template-hub-style-block">
        <h3><?= htmlspecialchars(ucfirst($style)) ?></h3>
        <img class="template-hub-preview"
             src="<?= INVGEN_BASE ?>/invoice-template/preview/<?= rawurlencode($style) ?>.png"
             alt="<?= htmlspecialchars(ucfirst($style)) ?> invoice template preview"
             loading="lazy" width="400" height="300">
        <ul class="template-hub-format-list">
          <?php foreach ($formats as $f => $fname): ?>
            <li><a class="link" href="<?= INVGEN_BASE ?>/invoice-template/<?= htmlspecialchars($style) ?>-<?= htmlspecialchars($f) ?>/"><?= htmlspecialchars(ucfirst($style)) ?> <?= htmlspecialchars($fname) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">If you want to handle payments, refunds, and track everything,</span>
    <a class="link page-banner-link"
       data-pitch-placement="template-hub-footer"
       href="<?= INVGEN_BASE ?>/?source=invgen-template-hub&amp;utm_source=invoice-generator&amp;utm_medium=template&amp;utm_campaign=phase1&amp;placement=footer">
      use Argo Books <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>

</article>
<?php
$body_content = ob_get_clean();
$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

include __DIR__ . '/../shared/layout.php';
