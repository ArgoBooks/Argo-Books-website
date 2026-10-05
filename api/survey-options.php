<?php
/**
 * Source Survey Options Endpoint
 *
 * GET /api/survey-options.php
 *
 * Returns the choices the desktop app offers in its surveys: "options" for
 * "Where did you hear about Argo Books?" and "goals" for "What did you come to
 * do?". Both are defined in config/survey-options.json so a new choice (e.g. a
 * new platform) can be added without releasing a new app version.
 *
 * The app falls back to bundled default lists when this endpoint is unreachable
 * or returns a non-2xx, and to its bundled goals when "goals" is absent.
 *
 * Response (200):
 *   {
 *     "options": [
 *       { "key": "google", "label": "Google" },
 *       ...
 *       { "key": "other", "label": "Other", "freeform": true }
 *     ],
 *     "goals": [
 *       { "key": "invoices", "label": "Send invoices and get paid" },
 *       ...
 *     ]
 *   }
 */

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/survey_options.php';

$options = get_survey_options();
if ($options === null) {
    // JSON missing/malformed. Return an error (not a partial list) so the app
    // falls back to its own bundled default list, the single offline fallback.
    http_response_code(500);
    echo json_encode(['error' => 'Options unavailable']);
    exit;
}

// Allow brief client/proxy caching; option changes propagate within minutes.
header('Cache-Control: public, max-age=300');
$payload = ['options' => $options];
$goals = get_survey_goals();
if ($goals !== null) {
    $payload['goals'] = $goals;
}
echo json_encode($payload);
