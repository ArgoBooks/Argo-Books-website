<?php
// estimate-generator/index.php
// Standalone free estimate generator tool page.
//
// Thin shell over the SHARED generator engine that lives in /invoice-generator/.
// It picks the 'estimate' document-type config, embeds the same editor surface
// (_fragment.php), mirrors the JS-relevant config to window.DOC_CONFIG, and
// defers to the same layout.php + main.js. See invoice-generator/doc-config.php
// for the full config and the consolidation rationale.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/tool-email-capture.php';
require_once __DIR__ . '/../invoice-generator/doc-config.php';

$doc_type = 'estimate';
$dc = invgen_doc_config($doc_type);

// Server-side page view. track_page_view() filters admins, bots, and duplicates
// itself, so calling it unconditionally is safe. Skip during PHP CLI smoke
// tests (no $_SERVER['REMOTE_ADDR'], no real visitor).
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('estgen_tool');
}

$page_title = $dc['page_title'];
$page_description = $dc['page_description'];
$canonical_url = $dc['canonical_url'];

// Referral source baked into every conversion-pitch CTA inside _fragment.php.
$invgen_ref = $dc['default_ref'];

// Show the hero (H1 + tagline) only on the standalone tool page.
$show_tool_hero = true;

$page_schema_json = json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'SoftwareApplication',
  'name' => $dc['schema_name'],
  'applicationCategory' => 'BusinessApplication',
  'operatingSystem' => 'Web',
  'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
  'creator' => ['@id' => 'https://argorobots.com/#organization'],
  'url' => $dc['canonical_url'],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Mirror the JS-relevant config to window.DOC_CONFIG so the shared engine
// modules (state/pdf/docx/main) know this is an estimate. Emitted into the
// <head> via $extra_head, ahead of main.js which loads at end of <body>.
$extra_head = '<script>window.DOC_CONFIG = '
    . json_encode(invgen_doc_config_js($dc), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';</script>';

ob_start();
include __DIR__ . '/../invoice-generator/_fragment.php';
// Opt-in only: the estimate is built in the browser and never reaches the
// server, so there is no document to mail and nothing to attach.
tool_email_capture([
    'source' => 'estimate_generator',
    'mode'   => 'optin',
    'title'  => 'Get occasional Argo Books updates',
    'blurb'  => 'Your estimate stays in your browser and is never sent to us. This is just the mailing list: new features, and tips for keeping books without an accountant.',
    'cta'    => 'Sign me up',
]);
$body_content = ob_get_clean();

$extra_head .= tool_email_capture_head();
$extra_scripts = '<script type="module" src="' . INVGEN_BASE . '/invoice-generator/scripts/main.js"></script>'
    . tool_email_capture_scripts();
$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];

include __DIR__ . '/../shared/layout.php';
