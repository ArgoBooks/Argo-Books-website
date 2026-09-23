<?php
// invoice-generator/index.php
// Standalone free invoice generator tool page.
// Thin shell: sets page metadata, embeds the invoice surface from _fragment.php,
// then defers to layout.php for the HTML shell.

$page_title = 'Free Invoice Generator | Argo Books';
$page_description = 'Free online invoice generator. No signup required. Download PDF or Word.';
$canonical_url = 'https://argorobots.com/invoice-generator/';

// Server-side page view. track_page_view() filters admins, bots, and duplicates
// itself, so calling it unconditionally is safe. Skip during PHP CLI smoke
// tests (no $_SERVER['REMOTE_ADDR'], no real visitor).
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    defer_client_page_view('invgen_tool');
}

// Referral source baked into every conversion-pitch CTA inside _fragment.php.
// Standalone tool page uses 'invgen-tool'.
$invgen_ref = 'invgen-tool';

// Show the hero (H1 + tagline) only on the standalone tool page. Niche pages
// have their own H1 from the niche data file and skip this.
$show_tool_hero = true;

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => 'Free Invoice Generator',
  'applicationCategory' => 'BusinessApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => 'https://argorobots.com/invoice-generator/',
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/tool-email-capture.php';

ob_start();
include __DIR__ . '/_fragment.php';
// Opt-in only: the invoice is built in the browser and never reaches the
// server, so there is no document to mail and nothing to attach.
tool_email_capture([
    'source' => 'invoice_generator',
    'mode'   => 'optin',
    'title'  => 'Get occasional Argo Books updates',
    'blurb'  => 'Your invoice stays in your browser and is never sent to us. This is just the mailing list: new features, and tips for keeping books without an accountant.',
    'cta'    => 'Sign me up',
]);
$body_content = ob_get_clean();

$extra_head = tool_email_capture_head();
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/invoice-generator/scripts/main.js"></script>'
    . tool_email_capture_scripts();
$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

include __DIR__ . '/../shared/layout.php';
