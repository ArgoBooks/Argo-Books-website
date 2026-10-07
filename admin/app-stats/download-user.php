<?php
/**
 * CSV export of telemetry history, for one user or for everybody.
 *
 * ?authId=...  one user, reached from the "Download CSV" button on their card.
 * ?all=1       every user in one file, reached from the button above the list.
 *
 * Both deliberately ignore the page's date range and tier filter: the buttons mean
 * "everything ever sent", the same scope the Delete button operates on, so the file
 * you get never depends on page state.
 *
 * The all-users export adds auth_id and is_founder columns and is grouped by user,
 * newest user first, each user's own events newest first. The single-user export
 * keeps exactly the columns it always had, so anything already reading those files
 * still works.
 *
 * is_founder is a flag rather than an exclusion, because this tab is the one place
 * the founder's own installs are deliberately visible. Dropping those rows here
 * would make the file disagree with the screen it came from; a column lets a total
 * be filtered without the export having decided for you.
 *
 * Interpretation of each event is shared with the tab via user-activity-events.php
 * rather than duplicated, so the CSV always says what the screen says.
 */

require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/telemetry-dedupe.php';        // telemetry_is_duplicate_event()
require_once __DIR__ . '/telemetry-time.php';          // telemetry_ts_seconds()
require_once __DIR__ . '/user-activity-events.php';    // ua_describe_event() etc.
require_once __DIR__ . '/../../founder_identity.php';  // is_founder_auth_id()
require_once __DIR__ . '/../../country_names.php';     // country_name()

// Same guard as the parent page. This endpoint is a normal admin page as far as
// .htaccess is concerned (that only denies *-tab.php), so it must check for itself.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

// admin_session.php records every GET as the page to return to after re-login. A file download is not a page: landing on it post-login would hand the admin a CSV instead of a dashboard.
$_SESSION['admin_return_to'] = '/admin/app-stats/?tab=user-activity';

$allMode = (string)($_GET['all'] ?? '') === '1';
$authId  = (string)($_GET['authId'] ?? '');
if (!$allMode && $authId === '') {
    http_response_code(400);
    exit('Missing authId');
}

// Collect candidate files exactly as the tab does: new location first, legacy root
// during the transition, de-duped by basename so a file present in both counts once.
$dirs  = [__DIR__ . '/../data-logs/telemetry/', __DIR__ . '/../data-logs/'];
$files = [];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob($dir . '*.json') ?: [] as $f) {
        $name = basename($f);
        if (!isset($files[$name])) {
            $files[$name] = $f;
        }
    }
}
ksort($files);

/**
 * Renders a tri-state telemetry flag: 'true', 'false', or empty for "not recorded".
 *
 * These fields are nullable in the app (SessionEvent.Clean is bool?), and a null
 * serializes into the uploaded JSON as a present key holding null. A truthiness
 * check would collapse that into 'false' and assert something the data never said:
 * a session whose shutdown was never recorded would read as a force-quit. Only an
 * actual boolean produces a word here, which matches ua_describe_event(), where
 * only a strict === false says "ended unexpectedly".
 */
/**
 * The non-zero counts on a CompanyScale event as one readable cell. Zeroes are dropped so a
 * file holding three expenses reads as that rather than as a row of noughts, and an entirely
 * empty file reads as "empty" rather than as a blank cell that looks like missing data.
 */
function ua_csv_scale(array $ev): string
{
    if (($ev['dataType'] ?? '') !== 'CompanyScale') {
        return '';
    }

    $fields = [
        'expenses' => 'expenses', 'revenues' => 'revenues', 'invoices' => 'invoices',
        'payments' => 'payments', 'customers' => 'customers', 'suppliers' => 'suppliers',
        'products' => 'products', 'categories' => 'categories', 'receipts' => 'receipts',
        'employees' => 'employees', 'bankLines' => 'bank lines',
    ];
    $parts = [];
    foreach ($fields as $key => $label) {
        if (!empty($ev[$key])) {
            $parts[] = (int)$ev[$key] . ' ' . $label;
        }
    }

    return $parts ? implode(', ', $parts) : 'empty';
}

function ua_csv_bool(array $ev, string $key): string
{
    if (!array_key_exists($key, $ev) || !is_bool($ev[$key])) {
        return '';
    }
    return $ev[$key] ? 'true' : 'false';
}

/**
 * Neutralizes a value that a spreadsheet would treat as a formula.
 *
 * Error messages now travel in telemetry and originate from uploaded files, so a
 * message could begin with =, +, - or @ and execute on open. Prefixing with a
 * single quote is the standard defence and is stripped back out by the spreadsheet
 * on display. Leading whitespace is trimmed first, because Excel looks past it.
 */
function ua_csv_safe($value): string
{
    if ($value === null || $value === false) return '';
    if ($value === true) return 'true';
    $s = (string)$value;
    if ($s !== '' && preg_match('/^[\s]*[=+\-@]/', $s)) {
        return "'" . $s;
    }
    return $s;
}

// ---- Row building -------------------------------------------------------------

/**
 * Every row one user ever sent, unsorted.
 *
 * Held in memory per user rather than for the whole site. One person's history is
 * small, so the all-users export stays flat in memory no matter how many people
 * are on it: their rows are built, written and released before the next user
 * starts. Building the whole site's history at once would not survive a few
 * hundred installs on shared hosting.
 *
 * $seen is threaded through every call so a re-uploaded event is collapsed once
 * across the entire export, exactly as it is on the tab.
 */
function ua_rows_for_user(array $files, string $authId, array &$seen, bool &$matchedFile): array
{
    $rows = [];
    $isFounder = is_founder_auth_id($authId) ? 'true' : 'false';

    foreach ($files as $name => $path) {
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') continue;
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['events']) || !is_array($d['events'])) continue;

        // The parameter is only ever compared against file contents. It never touches a
        // path, so no value of authId can reach a file outside data-logs/.
        if ((string)($d['authId'] ?? '') !== $authId) continue;
        $matchedFile = true;

        $geo = $d['geoLocation'] ?? [];
        $fileMeta = [
            'tier'        => $d['tier'] ?? 'premium',
            'app_version' => $d['appVersion'] ?? '',
            'platform'    => $d['platform'] ?? '',
            'country'     => country_name($geo['country'] ?? ''),
            'region'      => $geo['region'] ?? '',
            'timezone'    => $geo['timezone'] ?? '',
        ];

        foreach ($d['events'] as $ev) {
            // A re-uploaded event is the same action, not a second one. Matches the tab,
            // so the CSV row count agrees with the "Show all N events" figure.
            if (telemetry_is_duplicate_event($ev, $authId, $seen)) {
                continue;
            }
            $ev = ua_unwrap_event($ev);

            [$type, $text] = ua_describe_event($ev);
            $ts = telemetry_ts_seconds($ev['timestamp'] ?? null);

            $rows[] = [
                'sort'             => $ts === false ? 0 : $ts,
                'auth_id'          => $authId,
                'is_founder'       => $isFounder,
                'timestamp_utc'    => $ts === false ? '' : telemetry_ts_text($ts),
                'event_type'       => $type,
                'description'      => $text,
                'severity'         => $ev['severity'] ?? '',
                'tier'             => $fileMeta['tier'],
                'app_version'      => $fileMeta['app_version'],
                'platform'         => $fileMeta['platform'],
                'country'          => $fileMeta['country'],
                'region'           => $fileMeta['region'],
                'timezone'         => $fileMeta['timezone'],
                'feature_name'     => $ev['featureName'] ?? '',
                // Several events carry their whole meaning here: which stage an import died
                // at, why a scan came back empty, which page rendered with nothing on it.
                'feature_context'  => $ev['context'] ?? '',
                'export_type'      => $ev['exportType'] ?? '',
                'api_name'         => $ev['apiName'] ?? '',
                'error_category'   => $ev['errorCategory'] ?? '',
                'error_code'       => $ev['errorCode'] ?? '',
                'message'          => $ev['message'] ?? '',
                'source_file'      => $ev['sourceFile'] ?? '',
                'line_number'      => $ev['lineNumber'] ?? '',
                'method_name'      => $ev['methodName'] ?? '',
                'duration_ms'      => $ev['durationMs'] ?? '',
                'duration_seconds' => $ev['durationSeconds'] ?? '',
                'active_seconds'   => $ev['activeSeconds'] ?? '',
                // Session ends carry the page quit from; page views carry the page visited.
                'last_page'        => $ev['lastPage'] ?? $ev['pageName'] ?? '',
                // CompanyScale counts. Flattened into one column rather than eleven, which
                // would sit empty on every other row in the file.
                'file_contents'    => ua_csv_scale($ev),
                'clean'            => ua_csv_bool($ev, 'clean'),
                'file_size'        => $ev['fileSize'] ?? '',
                'success'          => ua_csv_bool($ev, 'success'),
                'telemetry_file'   => $name,
            ];
        }
    }

    return $rows;
}

// --- Work out who is being exported ---
// One cheap pass to learn each file's authId, so the all-users export can group by person.

$seenIds     = [];
$matchedFile = false;

if ($allMode) {
    $filesByAuth = [];   // authId => [basename => path]
    $newestByAuth = [];  // authId => newest mtime, used only for ordering

    foreach ($files as $name => $path) {
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') continue;
        $d = json_decode($raw, true);
        if (!is_array($d)) continue;

        $owner = (string)($d['authId'] ?? '');
        if ($owner === '') continue;

        $filesByAuth[$owner][$name] = $path;
        $mtime = @filemtime($path) ?: 0;
        if (!isset($newestByAuth[$owner]) || $mtime > $newestByAuth[$owner]) {
            $newestByAuth[$owner] = $mtime;
        }
    }

    // Most recently active first, which is the order the tab presents people in.
    arsort($newestByAuth);
    $exportOrder = array_keys($newestByAuth);
    $matchedFile = $exportOrder !== [];
} else {
    $exportOrder = [$authId];
    $filesByAuth = [$authId => $files];
}

// Nobody to export is a bad request, not an empty spreadsheet. A zero-byte CSV looks
// like the user genuinely did nothing, which is worse than an error.
if (!$allMode) {
    // Built up front so an authId nobody uploaded under still 404s before any
    // headers go out. The rows are kept and written below rather than rebuilt.
    $probe = ua_rows_for_user($files, $authId, $seenIds, $matchedFile);
}
if (!$matchedFile) {
    http_response_code(404);
    exit($allMode ? 'No telemetry found' : 'No telemetry found for that user');
}

// ---- Emit ---------------------------------------------------------------------
if ($allMode) {
    $filename = 'argo-activity-all-users-' . gmdate('Y-m-d') . '.csv';
} else {
    // authId contains a colon ('device:...' / 'subscription:...'), which is illegal in a
    // Windows filename, and is long enough to be unwieldy. Keep it recognisable instead.
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $authId);
    $slug = trim((string)$slug, '-');
    if (strlen($slug) > 40) {
        $slug = substr($slug, 0, 40);
    }
    $filename = 'argo-activity-' . ($slug !== '' ? $slug : 'user') . '-' . gmdate('Y-m-d') . '.csv';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel reads accented company names and the '·' separator correctly
// instead of showing mojibake.
fwrite($out, "\xEF\xBB\xBF");

// auth_id and is_founder only in the all-users file. Adding them to the single-user export would repeat two constants on every row and change a format that already has readers.
$columns = array_merge(
    $allMode ? ['auth_id', 'is_founder'] : [],
    [
        'timestamp_utc', 'event_type', 'description', 'severity', 'tier', 'app_version',
        'platform', 'country', 'region', 'timezone', 'feature_name', 'feature_context',
        'export_type',
        'api_name', 'error_category', 'error_code', 'message', 'source_file',
        'line_number', 'method_name', 'duration_ms', 'duration_seconds', 'active_seconds',
        'last_page', 'clean', 'file_contents',
        'file_size', 'success', 'telemetry_file',
    ]
);
fputcsv($out, $columns);

// One user at a time: build, sort, write, release. The single-user path reuses the
// rows already gathered by the 404 probe rather than reading everything twice.
foreach ($exportOrder as $owner) {
    $rows = ($allMode || !isset($probe))
        ? ua_rows_for_user($filesByAuth[$owner] ?? [], $owner, $seenIds, $matchedFile)
        : $probe;

    // Newest first, matching the on-screen timeline. Undated events sort to the bottom.
    usort($rows, fn($a, $b) => $b['sort'] <=> $a['sort']);

    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = ua_csv_safe($row[$col] ?? '');
        }
        fputcsv($out, $line);
    }

    unset($rows);
}
fclose($out);
