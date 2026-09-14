<?php
/**
 * Desktop-app telemetry receiver for funnel events.
 *
 * The Avalonia app POSTs here on first launch after install:
 *   {
 *     "token":       "8c4e2f1a",        // HMAC token from installer filename
 *     "event":       "app_first_run",
 *     "platform":    "win|mac|linux",
 *     "app_version": "2.1.0",
 *     "machine_uuid": "<stable per-machine id>"
 *   }
 *
 * Verification path:
 *   1. Compute the expected HMAC for each visitor_id that has a recent landing
 *      event and compare to the submitted token. The match yields the
 *      originating visitor_id, which we use to write the new event.
 *   2. If no visitor matches, we still log the event with visitor_id=null so
 *      the funnel sees "first run without attribution" rather than dropping it.
 *
 * Dedupes on (visitor_id|null, machine_uuid) so a retry from the app doesn't
 * double-count.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../track_referral_event.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$event_type   = (string)($data['event']        ?? '');
$token        = (string)($data['token']        ?? '');
$platform     = (string)($data['platform']     ?? '');
$app_version  = (string)($data['app_version']  ?? '');
$machine_uuid = (string)($data['machine_uuid'] ?? '');

if ($event_type !== 'app_first_run' && $event_type !== 'signup_survey') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported event']);
    exit;
}

if (!preg_match('/^[a-z]{3,8}$/', $platform)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid platform']);
    exit;
}

// signup_survey: the user answered "Where did you hear about Argo Books?".
// Update the existing app_first_run row for this machine_uuid in place rather
// than inserting a new event row (a single first-run row per machine is the
// funnel's source of truth).
if ($event_type === 'signup_survey') {
    $answer = strtolower((string)($data['answer'] ?? ''));
    // Valid answers come from config/survey-options.json (same source the app
    // reads), so adding a survey option needs only that one file edit.
    require_once __DIR__ . '/../config/survey_options.php';
    $allowed = get_survey_option_keys();
    if ($allowed === null) {
        // The options JSON is unavailable (broken deploy). The app is serving its
        // own bundled fallback list, so validate leniently rather than rejecting
        // every answer and silently losing survey responses during the outage.
        // Use the same key format as the JSON path so degraded mode stays consistent.
        if (!preg_match(SURVEY_KEY_PATTERN, $answer)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid answer']);
            exit;
        }
    } elseif (!in_array($answer, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid answer']);
        exit;
    }
    if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $machine_uuid)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid machine_uuid']);
        exit;
    }

    // Freeform text accompanies any option flagged freeform in the JSON (today
    // just "other"). Trim, cap at 200 chars, strip control characters so it
    // stores cleanly. Ignore for non-freeform answers. When the JSON is
    // unavailable, fall back to the conventional "other" freeform key.
    $freeform_keys = get_survey_freeform_keys();
    $is_freeform = $freeform_keys === null
        ? ($answer === 'other')
        : in_array($answer, $freeform_keys, true);
    $other_text = null;
    if ($is_freeform) {
        $raw_other = trim((string)($data['other_text'] ?? ''));
        if ($raw_other === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing other_text']);
            exit;
        }
        $other_text = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw_other);
        if (mb_strlen($other_text) > 200) {
            $other_text = mb_substr($other_text, 0, 200);
        }
    }

    try {
        // Find the most recent app_first_run row for this machine, in this
        // environment. Production and sandbox share one database, so without
        // the filter a sandbox test row would answer a production survey.
        $find = $pdo->prepare(
            "SELECT id FROM referral_events
              WHERE event_type = 'app_first_run'
                AND environment = ?
                AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = ?
              ORDER BY created_at DESC LIMIT 1"
        );
        $find->execute([current_environment(), $machine_uuid]);
        $row = $find->fetch();
        if ($row === false) {
            // Survey arrived before first-run was logged. Desktop only fires
            // the survey after the first-run marker is written, so this is
            // exceptional. Respond 200 with deferred=true so the client doesn't
            // retry forever.
            echo json_encode(['success' => true, 'deferred' => true]);
            exit;
        }

        // The IS NULL guard makes this idempotent: a second submission for the
        // same machine is silently dropped.
        $upd = $pdo->prepare(
            "UPDATE referral_events
                SET source_survey_answer = ?,
                    source_survey_other_text = ?,
                    source_survey_answered_at = NOW()
              WHERE id = ? AND source_survey_answer IS NULL"
        );
        $upd->execute([$answer, $other_text, $row['id']]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        error_log('track-app-event signup_survey failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
        exit;
    }
}

/**
 * Resolve a visitor_id from an installer token by recomputing the HMAC for
 * every distinct visitor_id seen in the last ~14 days. Returns null on miss
 * (untokenized installer, manually renamed file, etc).
 */
function resolve_visitor_from_token(string $token): ?string
{
    global $pdo;
    if (!preg_match('/^[0-9a-f]{8}$/i', $token)) {
        return null;
    }
    // Limit the scan to recent visitors so this stays fast as the table grows.
    $stmt = $pdo->prepare(
        'SELECT DISTINCT visitor_id FROM referral_events
          WHERE event_type IN ("landing","downloads_page","download_click")
            AND created_at >= NOW() - INTERVAL 14 DAY'
    );
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $candidate = $row['visitor_id'];
        // Shared recipe with the installer-filename side; see
        // referral_install_token() in track_referral_event.php.
        $expected = referral_install_token($candidate);
        if ($expected !== '' && hash_equals($expected, strtolower($token))) {
            return $candidate;
        }
    }
    return null;
}

$visitor_id = $token !== '' ? resolve_visitor_from_token($token) : null;

// Resolve source_code from the most recent landing event for this visitor
$source_code = null;
if ($visitor_id !== null) {
    try {
        $stmt = $pdo->prepare(
            "SELECT source_code FROM referral_events
              WHERE visitor_id = ? AND event_type = 'landing' AND source_code IS NOT NULL
              ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$visitor_id]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $source_code = $row['source_code'];
        }
    } catch (PDOException $e) {
        error_log('track-app-event source lookup failed: ' . $e->getMessage());
    }
}

// Dedup: skip if we've already logged a first_run for this machine in this
// environment. Sandbox and production share a database, so an unscoped check
// let a test install permanently suppress the real one from the same machine.
// When visitor_id is null (token didn't resolve), dedupe by machine_uuid
// alone so retries from an untokenized installer don't create multiple rows.
if ($machine_uuid !== '') {
    try {
        if ($visitor_id !== null) {
            $dedup = $pdo->prepare(
                "SELECT 1 FROM referral_events
                  WHERE event_type = 'app_first_run'
                    AND environment = ?
                    AND visitor_id = ?
                    AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = ?
                  LIMIT 1"
            );
            $dedup->execute([current_environment(), $visitor_id, $machine_uuid]);
        } else {
            $dedup = $pdo->prepare(
                "SELECT 1 FROM referral_events
                  WHERE event_type = 'app_first_run'
                    AND environment = ?
                    AND visitor_id IS NULL
                    AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = ?
                  LIMIT 1"
            );
            $dedup->execute([current_environment(), $machine_uuid]);
        }
        if ($dedup->fetch() !== false) {
            echo json_encode(['success' => true, 'duplicate' => true]);
            exit;
        }
    } catch (PDOException $e) {
        // Continue: duplicate detection failure shouldn't drop the event.
        error_log('track-app-event dedup check failed: ' . $e->getMessage());
    }
}

$event_data = [
    'platform'     => $platform,
    'app_version'  => $app_version,
    'machine_uuid' => $machine_uuid,
    'token_match'  => $visitor_id !== null,
];
// Same hash api/data/upload.php files a free install's telemetry under, so the
// admin user cards can show where that install came from.
$device_id = (string)($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
if ($device_id !== '') {
    $event_data['device_hash'] = hash('sha256', $device_id);
}

$ok = track_referral_event('app_first_run', [
    'visitor_id'  => $visitor_id,
    'source_code' => $source_code,
    'event_data'  => $event_data,
    'allow_bot' => true,  // desktop app HTTP client has no browser UA
]);

echo json_encode(['success' => (bool)$ok]);
