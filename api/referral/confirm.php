<?php
// api/referral/confirm.php
//
// JS-confirmation beacon target for referral page-view events. A real browser
// POSTs here on load; we flip js_confirmed = 1 on this visitor's recent
// unconfirmed 'landing' / 'downloads_page' rows in referral_events. Headless
// scrapers never run JavaScript, so their page-view rows stay js_confirmed = 0
// and are excluded from the marketing funnel. The visitor id comes from the
// httponly argo_visitor_id cookie, which the browser sends automatically on
// this same-origin POST (JS can't read it, but it doesn't need to).
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$visitor = $_COOKIE['argo_visitor_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $visitor) || !$pdo) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    // Confirm only this visitor's own recent page views.
    $stmt = $pdo->prepare(
        "UPDATE referral_events
            SET js_confirmed = 1
          WHERE visitor_id = ?
            AND js_confirmed = 0
            AND event_type IN ('landing', 'downloads_page')
            AND environment = ?
            AND created_at >= NOW() - INTERVAL 6 HOUR"
    );
    $stmt->execute([$visitor, current_environment()]);
    echo json_encode(['ok' => true, 'confirmed' => $stmt->rowCount()]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false]);
}
