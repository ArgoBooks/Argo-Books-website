<?php
/**
 * Desktop-app telemetry receiver for funnel events.
 *
 * The Avalonia app POSTs here on first launch after install, and again for each of
 * its two surveys (signup_survey, exit_survey), which fill in columns on the row
 * the first launch created:
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

if (!in_array($event_type, ['app_first_run', 'signup_survey', 'exit_survey'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported event']);
    exit;
}

if (!preg_match('/^[a-z]{3,8}$/', $platform)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid platform']);
    exit;
}

/** Ends the request with a 400 and the given reason. */
function survey_reject(string $error): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

/**
 * Reads one survey choice from the request: a key from the named list in
 * config/survey-options.json, plus the text that goes with a freeform choice.
 * Returns [key, text], both null when the field was not sent and is not required.
 *
 * When the options JSON is unavailable (broken deploy) the app is serving its own
 * bundled list, so the key is checked for shape only. Rejecting every answer would
 * silently lose survey responses for as long as the outage lasted.
 */
function survey_read_choice(array $data, string $field, ?string $textField, string $list, bool $required): array
{
    $key = strtolower(trim((string)($data[$field] ?? '')));
    if ($key === '') {
        if ($required) {
            survey_reject('Invalid ' . $field);
        }
        return [null, null];
    }

    $allowed = survey_choice_keys($list);
    $valid = $allowed === null ? (bool)preg_match(SURVEY_KEY_PATTERN, $key) : in_array($key, $allowed, true);
    if (!$valid) {
        survey_reject('Invalid ' . $field);
    }

    // No text field means the caller takes its text separately and does not require it.
    $freeform = survey_choice_keys($list, true);
    $is_freeform = $freeform === null ? ($key === 'other') : in_array($key, $freeform, true);
    if (!$is_freeform || $textField === null) {
        return [$key, null];
    }

    $text = survey_clean_text($data[$textField] ?? '', 200);
    if ($text === null) {
        survey_reject('Missing ' . $textField);
    }
    return [$key, $text];
}

/** Trims free text, strips control characters and caps its length. Null when empty. */
function survey_clean_text($value, int $max): ?string
{
    $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', trim((string)$value));
    $text = trim((string)$text);
    if ($text === '') {
        return null;
    }
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
}

/**
 * The app_first_run row a survey answer belongs to: the most recent one for this
 * machine, in this environment. Production and sandbox share one database, so
 * without the filter a sandbox test row would answer a production survey.
 */
function survey_first_run_row_id(string $machine_uuid): ?int
{
    global $pdo;
    $find = $pdo->prepare(
        "SELECT id FROM referral_events
          WHERE event_type = 'app_first_run'
            AND environment = ?
            AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')) = ?
          ORDER BY created_at DESC LIMIT 1"
    );
    $find->execute([current_environment(), $machine_uuid]);
    $row = $find->fetch();
    return $row === false ? null : (int)$row['id'];
}

// Both surveys update the existing app_first_run row for this machine_uuid in place rather than inserting a new event row (a single first-run row per machine is the funnel's source of truth).
if ($event_type === 'signup_survey' || $event_type === 'exit_survey') {
    require_once __DIR__ . '/../config/survey_options.php';

    if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $machine_uuid)) {
        survey_reject('Invalid machine_uuid');
    }

    if ($event_type === 'signup_survey') {
        // "Where did you hear about Argo Books?" and, from 2.0.20, "What did you come to do?". An older app sends only the first, a newer one only the second when the source is known.
        [$answer, $other_text] = survey_read_choice($data, 'answer', 'other_text', 'options', false);
        [$goal, $goal_text] = survey_read_choice($data, 'goal', 'goal_other_text', 'goals', false);
        if ($answer === null && $goal === null) {
            survey_reject('Invalid answer');
        }
    } else {
        // Asked once, when someone closes the app without having recorded anything.
        // A goal, a note on what got in the way, or both.
        [$goal, ] = survey_read_choice($data, 'answer', null, 'goals', false);
        $exit_text = survey_clean_text($data['other_text'] ?? '', 500);
        if ($goal === null && $exit_text === null) {
            survey_reject('Empty answer');
        }
    }

    try {
        $row_id = survey_first_run_row_id($machine_uuid);
        if ($row_id === null) {
            // The answer arrived before first-run was logged. The app only asks after the first-run marker is written, so this is exceptional.
            echo json_encode(['success' => true, 'deferred' => true]);
            exit;
        }

        if ($event_type === 'exit_survey') {
            // The IS NULL guard makes this idempotent: a second submission for the
            // same machine is silently dropped.
            $pdo->prepare(
                "UPDATE referral_events
                    SET exit_survey_answer = ?,
                        exit_survey_text = ?,
                        exit_survey_answered_at = NOW()
                  WHERE id = ? AND exit_survey_answered_at IS NULL"
            )->execute([$goal, $exit_text, $row_id]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($answer !== null) {
            $pdo->prepare(
                "UPDATE referral_events
                    SET source_survey_answer = ?,
                        source_survey_other_text = ?,
                        source_survey_answered_at = NOW()
                  WHERE id = ? AND source_survey_answer IS NULL"
            )->execute([$answer, $other_text, $row_id]);
        }
    } catch (PDOException $e) {
        error_log("track-app-event $event_type failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
        exit;
    }

    // The goal is written on its own so that a problem with it, such as its columns
    // not having been added yet, cannot lose the source answer above.
    if ($goal !== null) {
        try {
            $pdo->prepare(
                "UPDATE referral_events
                    SET survey_goal = ?, survey_goal_other_text = ?
                  WHERE id = ? AND survey_goal IS NULL"
            )->execute([$goal, $goal_text, $row_id]);
        } catch (PDOException $e) {
            error_log('track-app-event survey goal failed: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true]);
    exit;
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

// Dedup: skip if we've already logged a first_run for this machine in this environment.
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
