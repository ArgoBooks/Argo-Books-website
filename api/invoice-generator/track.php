<?php
// api/invoice-generator/track.php
//
// Lightweight client-side event endpoint for the free invoice generator tool.
// The browser POSTs JSON like { "event_type": "invgen_pdf_downloaded", "event_data": "classic" }
// and we forward to track_event() which handles admin-skip, bot-skip, and
// one-per-IP-per-day dedup internally.
//
// All accepted event types are prefixed "invgen_" and listed in $allowed below
// so the endpoint cannot be used to spray arbitrary statistics rows.

require_once __DIR__ . '/../../statistics.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$event_type = is_array($body) ? (string)($body['event_type'] ?? '') : '';
$event_data = is_array($body) ? ($body['event_data'] ?? '') : '';

// Recorded as an ordinary page_view so the dashboards keep working, but behind real JS so a headless scraper is left out.
if ($event_type === 'page_view') {
    if (!preg_match('/^(invgen|estgen|pogen)_[a-z0-9_-]+$/', (string)$event_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'bad page id']);
        exit;
    }
    track_page_view($event_data);
    echo json_encode(['ok' => true]);
    exit;
}

// Allowlist of event types the tool is allowed to emit. Keep this list in sync with invoice- generator/scripts/tracker.js + its callers.
$allowed = [
    // --- Invoice generator (invgen_*) ---
    'invgen_pdf_downloaded',
    'invgen_docx_downloaded',
    'invgen_cta_clicked',
    'invgen_template_changed',
    'invgen_country_changed',
    'invgen_currency_changed',
    'invgen_niche_default_used',
    'invgen_logo_uploaded',
    // Phase B: template library
    'invgen_template_cta_clicked',  // Customize and download (PDF/Word) on a template page
    'invgen_template_download',     // Excel direct download / Google Docs or Sheets copy click
    // Phase E: share-link copy
    'invgen_share_link_copied',     // User clicked the toolbar "Copy share link" button
    // --- Estimate generator (estgen_*) ---
    'estgen_pdf_downloaded',
    'estgen_docx_downloaded',
    'estgen_cta_clicked',
    'estgen_template_changed',
    'estgen_country_changed',
    'estgen_currency_changed',
    'estgen_niche_default_used',
    'estgen_logo_uploaded',
    'estgen_share_link_copied',
    // --- Purchase order generator (pogen_*) ---
    'pogen_pdf_downloaded',
    'pogen_docx_downloaded',
    'pogen_cta_clicked',
    'pogen_template_changed',
    'pogen_country_changed',
    'pogen_currency_changed',
    'pogen_niche_default_used',
    'pogen_logo_uploaded',
    'pogen_share_link_copied',
];
if (!in_array($event_type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown event_type']);
    exit;
}

// Cap event_data so a malformed client cannot bloat the statistics table.
if (!is_string($event_data)) {
    $event_data = '';
}
if (strlen($event_data) > 200) {
    $event_data = substr($event_data, 0, 200);
}

$ok = track_event($event_type, $event_data);
echo json_encode(['ok' => (bool)$ok]);
