<?php
// shared/layout.php
// Minimal self-contained page shell shared across the standalone tools
// (invoice-generator, profit-analyzer, etc.), the guides/articles, and the
// niche pages. Does NOT include the main site's header, footer, or main.js;
// these pages link out to argorobots.com rather than pulling its chrome in.

require_once __DIR__ . '/_base.php';

$page_title = $page_title ?? 'Free Invoice Generator | Argo Books';
$page_description = $page_description ?? 'Free online invoice generator. No signup required. Download PDF or Word.';
$canonical_url = $canonical_url ?? 'https://argorobots.com/invoice-generator/';
$page_schema_json = $page_schema_json ?? null;                 // Per-page primary schema (SoftwareApplication, FAQPage, Article, HowTo, etc.)
$breadcrumb_schema_json = $breadcrumb_schema_json ?? null;     // Per-page BreadcrumbList
$hreflang_alternates = $hreflang_alternates ?? [];             // [['lang' => 'en-ca', 'href' => 'https://...'], ...]
$og_image = $og_image ?? 'https://argorobots.com/resources/images/og-default.png'; // Update to match project image path
$body_content = $body_content ?? '';
$extra_head = $extra_head ?? '';
$extra_scripts = $extra_scripts ?? '';
// Optional back-link to the /tools/ hub, shown top-left of the header. Opt-in:
// set by the tool pages only, so guides / articles / niche pages are unaffected.
$tools_back = $tools_back ?? null; // ['href' => ..., 'label' => ...]
// Light/dark toggle in the header. Opt-in, same as $tools_back: only pages
// whose stylesheets have been checked against the dark palette switch it on.
$theme_toggle = $theme_toggle ?? false;

// Sitewide Organization + WebSite schema. Baked in for E-E-A-T.
// Update logo path and sameAs URLs to match the project's real assets / social profiles.
$site_schema = [
  '@context' => 'https://schema.org',
  '@graph' => [
    [
      '@type' => 'Organization',
      '@id' => 'https://argorobots.com/#organization',
      'name' => 'Argo Books',
      'url' => 'https://argorobots.com/',
      'logo' => 'https://argorobots.com/resources/images/logo.png',
      'sameAs' => [
        // Real social URLs go here when available. Empty array is fine for launch.
      ],
    ],
    [
      '@type' => 'WebSite',
      '@id' => 'https://argorobots.com/#website',
      'url' => 'https://argorobots.com/',
      'name' => 'Argo Books',
      'publisher' => ['@id' => 'https://argorobots.com/#organization'],
    ],
  ],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($page_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($page_description) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">
<link rel="icon" href="<?= INVGEN_BASE ?>/resources/images/argo-logo/argo-icon.ico" sizes="any">

<?php /* Open Graph */ ?>
<meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Argo Books">
<meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">

<?php /* Twitter Cards */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>">

<?php /* Hreflang alternates (used by country-specific niche pages; see Task A.20) */ ?>
<?php foreach ($hreflang_alternates as $alt): ?>
<link rel="alternate" hreflang="<?= htmlspecialchars($alt['lang']) ?>" href="<?= htmlspecialchars($alt['href']) ?>">
<?php endforeach; ?>

<link rel="stylesheet" href="<?= INVGEN_BASE ?>/invoice-generator/styles/tool.css">
<?php if ($theme_toggle): ?>
<link rel="stylesheet" href="<?= INVGEN_BASE ?>/resources/styles/theme-dark.css">
<?php /* Runs before first paint on purpose. A deferred script would let the
         page render light and then flip, which is worse than no toggle. */ ?>
<script>
(function(){try{var t=localStorage.getItem('argo-theme');var d=(t==='dark')||(t!=='light'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);if(d){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();
</script>
<?php endif; ?>
<script>window.INVGEN_BASE = <?= json_encode(INVGEN_BASE, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?= $extra_head ?>

<?php /* Sitewide Organization + WebSite JSON-LD (E-E-A-T) */ ?>
<script type="application/ld+json"><?= json_encode($site_schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php /* Per-page primary schema (SoftwareApplication on the tool, FAQPage on niche pages, etc.) */ ?>
<?php if ($page_schema_json !== null): ?>
<script type="application/ld+json"><?= $page_schema_json ?></script>
<?php endif; ?>

<?php /* Per-page BreadcrumbList */ ?>
<?php if ($breadcrumb_schema_json !== null): ?>
<script type="application/ld+json"><?= $breadcrumb_schema_json ?></script>
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="site-header-inner">
    <a class="site-brand" href="<?= INVGEN_BASE ?>/" aria-label="Argo Books home">
      <?php if ($theme_toggle): ?>
      <?php /* Two files rather than a CSS filter, because the mark has colour in
               it. Swapped on [data-theme] rather than prefers-color-scheme, or the
               logo would follow the OS while the page follows the toggle. */ ?>
      <img class="site-logo-light" src="<?= INVGEN_BASE ?>/resources/images/argo-logo/argo-logo-black.png" alt="Argo Books" width="160" height="28">
      <img class="site-logo-dark" src="<?= INVGEN_BASE ?>/resources/images/argo-logo/argo-logo-white.png" alt="" aria-hidden="true" width="160" height="28">
      <?php /* The wordmark is 164px wide, which does not leave room for the nav
               and the theme switch on a phone. Below 540px the mark replaces it.
               One file for both themes: it is a coloured logo on transparency,
               legible on either ground. */ ?>
      <img class="site-logo-mark" src="<?= INVGEN_BASE ?>/resources/images/argo-logo/argo-books-icon-transparent.png" alt="" aria-hidden="true" width="38" height="28">
      <?php else: ?>
      <img src="<?= INVGEN_BASE ?>/resources/images/argo-logo/argo-logo-black.png" alt="Argo Books" width="160" height="28">
      <?php endif; ?>
    </a>
    <?php if (!empty($header_nav)): ?>
    <nav class="site-header-nav" aria-label="Section navigation">
      <?php foreach ($header_nav as $nav_item): ?>
      <a href="<?= INVGEN_BASE ?>/<?= ltrim(htmlspecialchars($nav_item['href']), '/') ?>"><?= htmlspecialchars($nav_item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($theme_toggle): ?>
    <div class="site-header-actions">
      <?php /* A radio group, not three buttons: exactly one is chosen, and
               arrow keys move between them. Device is the default and is
               stored as the absence of a preference. */ ?>
      <div class="theme-switch" role="radiogroup" aria-label="Colour theme">
        <button type="button" class="theme-option is-active" role="radio" aria-checked="true"  data-theme="device" title="Match my device" aria-label="Match my device"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></button>
        <button type="button" class="theme-option"           role="radio" aria-checked="false" data-theme="light"  title="Light theme"    aria-label="Light theme" tabindex="-1"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button>
        <button type="button" class="theme-option"           role="radio" aria-checked="false" data-theme="dark"   title="Dark theme"     aria-label="Dark theme" tabindex="-1"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</header>
<?php if ($tools_back): ?>
<nav class="tool-breadcrumb" aria-label="Breadcrumb">
  <a class="site-back" href="<?= htmlspecialchars($tools_back['href']) ?>">
    <span class="site-back-arrow" aria-hidden="true">&larr;</span> <?= htmlspecialchars($tools_back['label']) ?>
  </a>
</nav>
<?php endif; ?>
<?= $body_content ?>
<?php if ($theme_toggle): ?>
<script src="<?= INVGEN_BASE ?>/resources/scripts/theme-toggle.js" defer></script>
<?php endif; ?>
<?= $extra_scripts ?>
<?php if (!empty($GLOBALS['__client_page_view'])): ?>
<script>
// Records this page view only when a real browser runs JS, filtering out the
// headless scrapers that inflate the tool stats. Posts back to track.php, which
// applies the usual admin/bot/dedup filtering server-side.
(function () {
  try {
    var p = <?= json_encode($GLOBALS['__client_page_view'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var url = (window.INVGEN_BASE || '') + '/api/invoice-generator/track.php';
    var payload = JSON.stringify({ event_type: 'page_view', event_data: p, referrer: document.referrer || '' });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }));
    } else {
      fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true }).catch(function () {});
    }
  } catch (e) { /* never break the page over analytics */ }
})();
</script>
<?php endif; ?>
<?php
// Shared with the footer through one partial, and the URL is a JS expression because the base path is only known there.
$confirm_url_js = "(window.INVGEN_BASE || '') + '/api/referral/confirm.php'";
include __DIR__ . '/../resources/referral-confirm-beacon.php';
?>
</body>
</html>
