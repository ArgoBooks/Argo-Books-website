<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../founder_identity.php';      // is_founder_auth_id()
require_once __DIR__ . '/../../country_names.php';         // country_name()
require_once __DIR__ . '/../../telemetry_environment.php'; // is_other_environment_auth_id()
require_once __DIR__ . '/../date-range.php';
require_once __DIR__ . '/telemetry-dedupe.php'; // telemetry_is_duplicate_event()

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Set page variables for header
$page_title = "Argo Books Statistics";
$page_description = "View and analyze anonymous user data with geo-location insights from the Argo Books application";

// Telemetry files now live in data-logs/telemetry/. The legacy data-logs/ root
// is still read during the transition so files not yet moved keep showing.
$dataDir = '../data-logs/telemetry/';
$legacyDataDir = '../data-logs/';
$errorMessage = '';

// Tier filter: 'all' (default), 'free', 'premium'
$tierFilter = $_GET['tier'] ?? 'all';
if (!in_array($tierFilter, ['all', 'free', 'premium'], true)) {
    $tierFilter = 'all';
}

// Date range (shared presets: admin/date-range.php). It scopes the charts and
// detail tables only. The Active Users KPI cards below are fixed-window metrics
// by definition (DAU / WAU / MAU are always measured against today), so they are
// computed from every event regardless of what is selected here.
//
// "All Time" can't resolve a floor up front the way the SQL pages do, since the
// earliest telemetry event isn't known until the files are parsed. It's treated
// as "no lower bound" during the parse and the display date is backfilled from
// the oldest event afterwards.
$presets = date_range_presets();
$selectedRange = selected_date_range_preset();
$customStartRaw = $_GET['start'] ?? null;
$customEndRaw   = $_GET['end'] ?? null;

$range      = resolve_date_range($selectedRange, $customStartRaw, $customEndRaw);
$rangeStart = $range['start'];
$rangeEnd   = $range['end'];

$rangeStartTs = $selectedRange === 'All Time' ? null : $rangeStart->getTimestamp();
$rangeEndTs   = $rangeEnd->getTimestamp();
$earliestEventTs = null;

$aggregatedData = [
    'dataPoints' => [
        'Export' => [],
        'Gemini' => [],
        'OpenExchangeRates' => [],
        'OpenExchangeRatesBatch' => [],
        'ReceiptScanning' => [],
        'Session' => [],
        'Error' => [],
        'Warning' => [],
        'FeatureUsage' => []
    ],
    'geoLocationEnabled' => false,
    'tierFilter' => $tierFilter,
    // Per-tier user counts computed from ALL files (independent of $tierFilter)
    'tierStats' => [
        'free' => ['files' => 0, 'mauUsers' => 0, 'totalUsers' => 0],
        'premium' => ['files' => 0, 'mauUsers' => 0, 'totalUsers' => 0],
    ],
    // Active Users KPI cards. Respect the tier filter, ignore the date range.
    'fixedKpis' => ['totalUsers' => 0, 'dau' => 0, 'mau' => 0, 'newUsers' => 0],
    // User key => first event ever, as a timestamp. Lets the New vs Returning chart call
    // someone new only on their real first day, not on the first day of the chosen range.
    'userFirstSeen' => [],
    // Selected range, so the charts can build their axes from it instead of "today".
    'range' => ['preset' => $selectedRange, 'start' => null, 'end' => null],
];
$fileInfo = [];

// Helper function to normalize event data
// $sessionMeta contains top-level fields from the compact format (including tier + authId)
function normalizeEvent($event, $sessionMeta = []) {
    $normalized = [
        'timestamp' => $event['timestamp'] ?? date('Y-m-d H:i:s'),
        'appVersion' => $event['appVersion'] ?? $sessionMeta['appVersion'] ?? 'Unknown',
        'platform' => $event['platform'] ?? $sessionMeta['platform'] ?? 'Unknown',
        'userAgent' => $event['userAgent'] ?? $sessionMeta['userAgent'] ?? '',
        'dataType' => $event['dataType'] ?? 'Unknown',
        'tier' => $sessionMeta['tier'] ?? 'premium',
        'authId' => $sessionMeta['authId'] ?? '',
    ];

    // Geo-location: prefer event-level, fall back to session-level
    $geo = $event['geoLocation'] ?? $sessionMeta['geoLocation'] ?? null;
    if (isset($geo) && is_array($geo)) {
        // The geolocation service returns a full name for some countries and a bare ISO
        // code for others, so the same place could appear twice: once as "United States"
        // and once as "US", splitting a chart bucket and missing its colour. country_name()
        // maps a code to its name and returns anything else untouched, so it is safe to run
        // over values that are already spelled out.
        $normalized['country'] = country_name($geo['country'] ?? '') ?: 'Unknown';
        $normalized['region'] = $geo['region'] ?? '';
        $normalized['timezone'] = $geo['timezone'] ?? '';
        $normalized['hashedIP'] = $geo['hashedIp'] ?? '';
    }

    // Fallback user identifier for free-tier events (no hashedIp); use authId as a stable per-device key.
    if (empty($normalized['hashedIP']) && !empty($normalized['authId'])) {
        $normalized['hashedIP'] = $normalized['authId'];
    }

    return $normalized;
}

// Helper function to categorize and transform events
// $sessionMeta: top-level fields from compact format (appVersion, platform, userAgent, geoLocation)
function processEvent($event, $sourceFile, $sessionMeta = []) {
    // Handle nested event structure (wrapper has dataType, actual data is in event property)
    if (isset($event['event']) && is_array($event['event'])) {
        $event = $event['event'];
    }

    $dataType = $event['dataType'] ?? null;
    if (!$dataType) {
        return null;
    }

    $normalized = normalizeEvent($event, $sessionMeta);
    $normalized['source_file'] = $sourceFile;

    switch ($dataType) {
        case 'Session':
            $normalized['action'] = $event['action'] ?? 'Unknown';
            $normalized['duration'] = $event['durationSeconds'] ?? 0;
            // Null on SessionStart and on ends uploaded by builds before the flag
            // existed, so only an explicit false counts as an unclean exit.
            $normalized['clean'] = $event['clean'] ?? null;
            $normalized['activeSeconds'] = $event['activeSeconds'] ?? null;
            $normalized['lastPage'] = $event['lastPage'] ?? null;
            return ['category' => 'Session', 'data' => $normalized];

        case 'Export':
            $normalized['ExportType'] = $event['exportType'] ?? 'Unknown';
            $normalized['DurationMS'] = $event['durationMs'] ?? 0;
            $normalized['FileSize'] = $event['fileSize'] ?? null;
            return ['category' => 'Export', 'data' => $normalized];

        case 'ApiUsage':
            $apiName = $event['apiName'] ?? 'Unknown';
            $normalized['DurationMS'] = $event['durationMs'] ?? 0;
            $normalized['Success'] = $event['success'] ?? true;

            switch ($apiName) {
                case 'Gemini':
                    return ['category' => 'Gemini', 'data' => $normalized];

                case 'OpenExchangeRates':
                    return ['category' => 'OpenExchangeRates', 'data' => $normalized];

                // Kept in its own bucket so the bulk call and the per-date repair that follows it
                // can be told apart. Under one name a failing bulk request looks like ordinary
                // traffic, which is exactly how it went unnoticed.
                case 'OpenExchangeRatesBatch':
                    return ['category' => 'OpenExchangeRatesBatch', 'data' => $normalized];

                case 'ReceiptScanProxy':
                    return ['category' => 'ReceiptScanning', 'data' => $normalized];

                default:
                    return null;
            }

        case 'Error':
            $normalized['ErrorCategory'] = $event['errorCategory'] ?? 'Unknown';
            $normalized['ErrorCode'] = $event['errorCode'] ?? '';
            $normalized['Message'] = $event['message'] ?? '';
            $normalized['SourceFile'] = $event['sourceFile'] ?? '';
            $normalized['LineNumber'] = $event['lineNumber'] ?? null;
            $normalized['MethodName'] = $event['methodName'] ?? '';
            $normalized['Context'] = $event['context'] ?? '';
            // The app stamps severity from its own LogLevel. Warnings are expected,
            // handled conditions, so they get their own bucket and never reach the
            // Errors tab's charts or details table. Events uploaded before the field
            // existed carry no severity and stay errors; we don't infer it from the
            // error code. Either way the event still counts toward DAU and tier users,
            // because that accounting happens before this bucket is used.
            $severity = strcasecmp((string)($event['severity'] ?? ''), 'Warning') === 0
                ? 'Warning'
                : 'Error';
            return ['category' => $severity, 'data' => $normalized];

        case 'Startup':
            // ToReadyMs already contains ToFirstPaintMs rather than continuing from
            // it, so the two are nested, not additive. Null on either means the app
            // never reached that milestone (or predates the field), and null is kept
            // rather than coerced to 0 so a missing measurement cannot pull an
            // average down as though the launch were instant.
            $normalized['ToFirstPaintMs']      = isset($event['toFirstPaintMs']) ? (int)$event['toFirstPaintMs'] : null;
            $normalized['ToServicesReadyMs']   = isset($event['toServicesReadyMs']) ? (int)$event['toServicesReadyMs'] : null;
            $normalized['ToViewModelsReadyMs'] = isset($event['toViewModelsReadyMs']) ? (int)$event['toViewModelsReadyMs'] : null;
            $normalized['ToReadyMs']           = isset($event['toReadyMs']) ? (int)$event['toReadyMs'] : null;
            $normalized['ColdStart']      = !empty($event['coldStart']);
            return ['category' => 'Startup', 'data' => $normalized];

        case 'PageView':
            $normalized['PageName'] = $event['pageName'] ?? 'Unknown';
            $normalized['ActiveSeconds'] = (int)($event['activeSeconds'] ?? 0);
            $normalized['DurationSeconds'] = (int)($event['durationSeconds'] ?? 0);
            return ['category' => 'PageView', 'data' => $normalized];

        case 'FeatureUsage':
            $normalized['FeatureName'] = $event['featureName'] ?? 'Unknown';
            $normalized['Context'] = $event['context'] ?? '';
            if (isset($event['durationMs'])) {
                $normalized['DurationMs'] = $event['durationMs'];
            }
            return ['category' => 'FeatureUsage', 'data' => $normalized];

        default:
            return null;
    }
}

$dataDirs = array_values(array_filter([$dataDir, $legacyDataDir], 'is_dir'));
if (empty($dataDirs)) {
    $errorMessage = "Directory 'data-logs/' does not exist.";
} else {
    // Read from both the new telemetry/ folder and the legacy root, de-duping by
    // filename so a file present in both places is only counted once.
    $dataFiles = [];
    $seenNames = [];
    foreach ($dataDirs as $dir) {
        foreach (glob($dir . '*.json') ?: [] as $f) {
            $name = basename($f);
            if (!isset($seenNames[$name])) {
                $seenNames[$name] = true;
                $dataFiles[] = $f;
            }
        }
    }
    if (count($dataFiles) === 0) {
        $errorMessage = "No anonymous data files found.";
    } else {
        // Sort files by modification time (newest first) for file info display
        usort($dataFiles, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $totalFiles = count($dataFiles);
        $processedFiles = 0;
        $failedFiles = 0;

        // Shared across every file: the same event can appear in several of them when
        // an upload was retried or its "uploaded" flag was lost. See telemetry-dedupe.php.
        $seenEventIds = [];

        // Track per-tier unique users (from ALL files, regardless of $tierFilter)
        $tierUsers = [
            'free' => ['all' => [], 'mau' => []],
            'premium' => ['all' => [], 'mau' => []],
        ];
        $mauThreshold = time() - 30 * 86400;

        // First- and last-seen per user for the Active Users KPI cards: tier-filtered like
        // the rest of the page, but never date-range filtered, so the cards keep measuring
        // against today no matter which range is selected.
        $kpiLastSeen  = [];
        $kpiFirstSeen = [];
        $dauThreshold = strtotime('today');

        // Process all JSON files and aggregate the data
        foreach ($dataFiles as $file) {
            $jsonDataRaw = file_get_contents($file);
            if ($jsonDataRaw === false || trim($jsonDataRaw) === '') {
                $failedFiles++;
                continue;
            }

            $fileData = json_decode($jsonDataRaw, true);
            if ($fileData === null) {
                $failedFiles++;
                continue;
            }

            $sourceFile = basename($file);

            // Upload payload with events array
            // Compact format: session metadata at top level, events only have unique fields
            if (isset($fileData['events']) && is_array($fileData['events'])) {
                // Files without an explicit tier (legacy uploads) are treated as premium
                $fileTier = $fileData['tier'] ?? 'premium';
                if (!in_array($fileTier, ['free', 'premium'], true)) {
                    $fileTier = 'premium';
                }
                $fileAuthId = $fileData['authId'] ?? '';

                // Never let the founder's own installs count toward app stats. This one
                // skip covers the whole file, so it keeps them out of tier counts, DAU,
                // geo, versions, features, usage, API and errors in a single place. The
                // User Activity tab reads the same files separately and does show them.
                if (is_founder_auth_id($fileAuthId)) {
                    continue;
                }

                // Nor an install whose premium came from another environment. The upload
                // endpoint authenticates a license without checking which environment its
                // subscription belongs to, so a sandbox test redemption would otherwise sit
                // in production's charts as a real premium user.
                if (is_other_environment_auth_id($fileAuthId)) {
                    continue;
                }

                // Always count toward per-tier stats, even if the file is filtered out below
                $aggregatedData['tierStats'][$fileTier]['files']++;

                // Extract session-level metadata from the top level
                $sessionMeta = [
                    'appVersion' => $fileData['appVersion'] ?? null,
                    'platform' => $fileData['platform'] ?? null,
                    'userAgent' => $fileData['userAgent'] ?? null,
                    'geoLocation' => $fileData['geoLocation'] ?? null,
                    'tier' => $fileTier,
                    'authId' => $fileAuthId,
                ];

                // Skip files that don't match the active tier filter (UI-only filter; tier stats above are unaffected)
                $includeFile = $tierFilter === 'all' || $tierFilter === $fileTier;

                foreach ($fileData['events'] as $event) {
                    // Collapse re-uploads before any accounting: a duplicate must not
                    // reach tier stats or DAU either, or one launch reads as several
                    // users' worth of activity.
                    if (telemetry_is_duplicate_event($event, $fileAuthId, $seenEventIds)) {
                        continue;
                    }

                    $result = processEvent($event, $sourceFile, $sessionMeta);
                    if ($result === null) {
                        continue;
                    }

                    $data = $result['data'];

                    // Tier-stats accounting uses ALL events (not filter-gated)
                    $userKey = !empty($data['hashedIP']) ? $data['hashedIP'] : ($fileAuthId ?: null);
                    if ($userKey !== null) {
                        $tierUsers[$fileTier]['all'][$userKey] = true;
                        $eventTime = strtotime($data['timestamp'] ?? '1970-01-01');
                        if ($eventTime !== false && $eventTime >= $mauThreshold) {
                            $tierUsers[$fileTier]['mau'][$userKey] = true;
                        }
                    }

                    // Per-category dataPoints respect the tier filter
                    if (!$includeFile) {
                        continue;
                    }

                    // An event with no readable timestamp can't be placed on any axis.
                    $eventTs = strtotime($data['timestamp'] ?? '');
                    if ($eventTs === false) {
                        continue;
                    }

                    if ($earliestEventTs === null || $eventTs < $earliestEventTs) {
                        $earliestEventTs = $eventTs;
                    }

                    // KPI cards: filled before the date-range gate below, so they stay
                    // range-independent. Keyed like main.js (events with a hashedIP).
                    if (!empty($data['hashedIP'])) {
                        $kpiKey = $data['hashedIP'];
                        if (!isset($kpiLastSeen[$kpiKey]) || $eventTs > $kpiLastSeen[$kpiKey]) {
                            $kpiLastSeen[$kpiKey] = $eventTs;
                        }
                        if (!isset($kpiFirstSeen[$kpiKey]) || $eventTs < $kpiFirstSeen[$kpiKey]) {
                            $kpiFirstSeen[$kpiKey] = $eventTs;
                        }
                    }

                    // Charts and detail tables respect the selected date range.
                    if ($eventTs > $rangeEndTs || ($rangeStartTs !== null && $eventTs < $rangeStartTs)) {
                        continue;
                    }

                    $category = $result['category'];
                    if (!isset($aggregatedData['dataPoints'][$category])) {
                        $aggregatedData['dataPoints'][$category] = [];
                    }
                    $aggregatedData['dataPoints'][$category][] = $data;

                    if (!empty($data['country']) && $data['country'] !== 'Unknown') {
                        $aggregatedData['geoLocationEnabled'] = true;
                    }
                }
                $processedFiles++;
            }
            // Unknown format
            else {
                $failedFiles++;
            }
        }

        // Finalize per-tier user counts
        foreach (['free', 'premium'] as $t) {
            $aggregatedData['tierStats'][$t]['totalUsers'] = count($tierUsers[$t]['all']);
            $aggregatedData['tierStats'][$t]['mauUsers'] = count($tierUsers[$t]['mau']);
        }

        // Finalize the fixed-window Active Users KPIs
        $aggregatedData['fixedKpis']['totalUsers'] = count($kpiLastSeen);
        foreach ($kpiLastSeen as $lastSeen) {
            if ($lastSeen >= $dauThreshold) $aggregatedData['fixedKpis']['dau']++;
            if ($lastSeen >= $mauThreshold) $aggregatedData['fixedKpis']['mau']++;
        }
        foreach ($kpiFirstSeen as $firstSeen) {
            if ($firstSeen >= $mauThreshold) $aggregatedData['fixedKpis']['newUsers']++;
        }
        $aggregatedData['userFirstSeen'] = $kpiFirstSeen;

        // "All Time" had no lower bound during the parse; show the real oldest event.
        if ($selectedRange === 'All Time' && $earliestEventTs !== null) {
            $rangeStart = (new DateTime())->setTimestamp($earliestEventTs)->setTime(0, 0, 0);
        }

        // Store file processing information
        $fileInfo = [
            'total_files' => $totalFiles,
            'processed_files' => $processedFiles,
            'failed_files' => $failedFiles,
            'latest_file' => $totalFiles > 0 ? basename($dataFiles[0]) : '',
            'latest_modified' => $totalFiles > 0 ? filemtime($dataFiles[0]) : 0,
            'oldest_file' => $totalFiles > 0 ? basename(end($dataFiles)) : '',
            'oldest_modified' => $totalFiles > 0 ? filemtime(end($dataFiles)) : 0
        ];

        // Sort all aggregated data by timestamp for chronological analysis
        foreach ($aggregatedData['dataPoints'] as $category => &$dataPoints) {
            usort($dataPoints, function ($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '1970-01-01');
                $timeB = strtotime($b['timestamp'] ?? '1970-01-01');
                return $timeA - $timeB;
            });
        }
        unset($dataPoints); // break reference

        if ($processedFiles === 0) {
            $errorMessage = "No valid JSON data found in any files.";
        }
    }
}

$rangeDisplay = format_date_range($rangeStart, $rangeEnd);
$aggregatedData['range']['start'] = $rangeStart->format('Y-m-d');
$aggregatedData['range']['end']   = $rangeEnd->format('Y-m-d');

// Convert aggregated data to JSON for JavaScript. Escape HTML-meaningful characters
// (<, >, &, ', ") as \u00xx so a telemetry string containing "</script>" cannot break
// out of the inline <script> context where this is emitted.
$jsonData = json_encode(
    $aggregatedData,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
);

// Include the shared header
include __DIR__ . '/../admin_header.php';
?>

<style>
.tabs-container {
    margin-bottom: 2rem;
}

/* Tab styles are shared across admin pages: see admin/common-style.css */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    text-align: center;
}

.stat-card h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--black);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-card .value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--black);
    margin: 0.5rem 0;
}

.stat-card .subtext {
    font-size: 0.75rem;
    color: var(--black);
    margin: 0;
}

.chart-section {
    margin-bottom: 3rem;
}

.section-title {
    text-align: center;
    margin-bottom: 1.5rem;
    color: var(--black);
    font-size: 1.5rem;
    font-weight: 600;
}

/* Groups a set of stat cards under a named heading inside a tab that already
   has a .section-title, so Usage can hold more than one topic. */
.section-subtitle {
    margin: 0.5rem 0 1rem;
    color: var(--gray-700);
    font-size: 1.05rem;
    font-weight: 600;
}

[data-theme="dark"] .section-subtitle {
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .section-tabs {
        gap: 2px;
    }

    .section-tab {
        padding: 10px 14px;
        font-size: 13px;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

.error-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.error-details-table th,
.error-details-table td {
    padding: 10px 14px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.error-details-table th {
    background: #f9fafb;
    font-weight: 600;
    color: var(--black);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    position: sticky;
    top: 0;
}

.error-details-table tr:hover {
    background: #f9fafb;
}

.error-details-table td.error-code {
    font-family: monospace;
    font-weight: 600;
    color: #ef4444;
}

.error-details-table td.error-source {
    font-family: monospace;
    font-size: 0.8rem;
    color: var(--black);
}

.error-details-wrapper {
    max-height: 500px;
    overflow-y: auto;
    border-radius: 8px;
    margin-top: 1rem;
}

/* Tier filter pills use the shared .control-bar / .control-pill component
   (admin/common-style.css). No page-specific styles needed. */
</style>

<div class="container">
    <?php
    $hasAnyData = !$errorMessage && ($fileInfo['total_files'] ?? 0) > 0;
    $currentViewHasData = !empty($aggregatedData['dataPoints']) && (
        count($aggregatedData['dataPoints']['Export']) > 0 ||
        count($aggregatedData['dataPoints']['Gemini']) > 0 ||
        count($aggregatedData['dataPoints']['OpenExchangeRates']) > 0 ||
        count($aggregatedData['dataPoints']['OpenExchangeRatesBatch']) > 0 ||
        count($aggregatedData['dataPoints']['ReceiptScanning']) > 0 ||
        count($aggregatedData['dataPoints']['Session']) > 0 ||
        count($aggregatedData['dataPoints']['Error']) > 0 ||
        count($aggregatedData['dataPoints']['Warning']) > 0 ||
        count($aggregatedData['dataPoints']['FeatureUsage']) > 0
    );
    ?>

    <?php if ($hasAnyData): ?>
        <div class="control-bar">
            <div class="control-group">
                <span class="control-label">Tier:</span>
                <div class="control-pills">
                    <?php
                        $tierLabels = [
                            'all' => 'All',
                            'free' => 'Free',
                            'premium' => 'Premium',
                        ];
                        // Preserve the current tab across a tier switch (section-tabs.js keeps
                        // it in ?tab=). Whitelist against the real tabs to avoid reflecting
                        // arbitrary input into the href.
                        $validTabs = ['active-users', 'user-activity', 'geographic', 'versions', 'features', 'usage', 'api', 'errors', 'crashes'];
                        $currentTab = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : '';
                        foreach ($tierLabels as $tierKey => $label):
                            $isActive = $tierFilter === $tierKey;
                            $pillHref = '?tier=' . urlencode($tierKey);
                            if ($currentTab !== '') $pillHref .= '&tab=' . urlencode($currentTab);
                            // Carry the date range across a tier switch, the same way the tab is carried.
                            $pillHref .= '&range=' . urlencode($selectedRange);
                            if ($selectedRange === 'Custom Range') {
                                $pillHref .= '&start=' . urlencode($rangeStart->format('Y-m-d'))
                                           . '&end=' . urlencode($rangeEnd->format('Y-m-d'));
                            }
                    ?>
                        <a href="<?= htmlspecialchars($pillHref) ?>"
                           class="control-pill <?= $isActive ? 'active' : '' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Date range: scopes the charts and detail tables. The Active Users KPI
                 cards are fixed-window metrics and deliberately ignore it. -->
            <form method="get" id="rangeForm" class="range-controls">
                <input type="hidden" name="tier" value="<?= htmlspecialchars($tierFilter) ?>">
                <input type="hidden" name="tab" id="rangeTabInput" value="<?= htmlspecialchars($currentTab) ?>">
                <div class="control-group">
                    <span class="control-label">Date Range:</span>
                    <select name="range" id="rangePreset" class="control-select" onchange="onRangeChange()">
                        <?php foreach ($presets as $presetOption): ?>
                            <option value="<?= htmlspecialchars($presetOption) ?>" <?= $presetOption === $selectedRange ? 'selected' : '' ?>>
                                <?= htmlspecialchars($presetOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="range-display"><?= htmlspecialchars($rangeDisplay) ?></span>
                    <span class="info-tip" tabindex="0" role="button" aria-label="What does the date range affect?" aria-describedby="range-tip">
                        <span class="info-tip-icon" aria-hidden="true">i</span>
                        <span class="info-tip-tooltip" id="range-tip" role="tooltip">Scopes the charts and detail tables. The Active Users cards (today, this week, this month) always measure against today, so they don't follow this range.</span>
                    </span>
                </div>

                <div class="control-group range-custom" id="rangeCustom" style="display: <?= $selectedRange === 'Custom Range' ? 'flex' : 'none' ?>;">
                    <input type="date" name="start" id="rangeStart" class="control-input" value="<?= htmlspecialchars($rangeStart->format('Y-m-d')) ?>" <?= $selectedRange === 'Custom Range' ? '' : 'disabled' ?>>
                    <span>to</span>
                    <input type="date" name="end" id="rangeEnd" class="control-input" value="<?= htmlspecialchars($rangeEnd->format('Y-m-d')) ?>" <?= $selectedRange === 'Custom Range' ? '' : 'disabled' ?>>
                    <button type="submit" class="control-pill">Apply</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="no-data">
            <h3>No Data Available</h3>
            <p><?= htmlspecialchars($errorMessage) ?></p>
            <p><small>Make sure the data directory exists and contains valid JSON files at: <code>admin/data-logs/telemetry/</code></small></p>
        </div>
    <?php elseif (!$currentViewHasData): ?>
        <div class="no-data">
            <h3>No Data Available</h3>
            <?php if ($hasAnyData): ?>
                <p>
                    No <?= $tierFilter !== 'all' ? htmlspecialchars($tierFilter) . '-tier ' : '' ?>data
                    for <?= htmlspecialchars($rangeDisplay) ?>. Widen the date range<?= $tierFilter !== 'all' ? ' or switch tier' : '' ?> above.
                </p>
            <?php elseif ($tierFilter !== 'all'): ?>
                <p>No <?= htmlspecialchars($tierFilter) ?>-tier data has been collected yet. Switch to a different tier above.</p>
            <?php else: ?>
                <p>No anonymous data has been collected yet. Data will appear here once users start using the application and uploading their analytics.</p>
                <p><small>Data files are automatically uploaded to: <code>admin/data-logs/telemetry/</code></small></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="tabs-container">
            <div class="section-tabs">
                <button class="section-tab active" data-tab="active-users">Active Users</button>
                <button class="section-tab" data-tab="user-activity">User Activity</button>
                <?php if ($aggregatedData['geoLocationEnabled']): ?>
                <button class="section-tab" data-tab="geographic">Geographic</button>
                <?php endif; ?>
                <button class="section-tab" data-tab="versions">Versions</button>
                <button class="section-tab" data-tab="features">Features</button>
                <button class="section-tab" data-tab="pages">Pages</button>
                <button class="section-tab" data-tab="usage">Usage</button>
                <button class="section-tab" data-tab="api">API Usage</button>
                <button class="section-tab" data-tab="errors">Errors</button>
                <button class="section-tab" data-tab="crashes">Crashes</button>
            </div>

            <!-- Active Users Tab -->
            <div id="active-users" class="tab-content active">
                <h2 class="section-title">Active Users</h2>

                <div class="stats-grid" id="activeUsersKpiGrid">
                    <div class="stat-card">
                        <h3>Total Unique Users</h3>
                        <div class="value" id="kpiTotalUsers">—</div>
                        <p class="subtext">All time</p>
                    </div>
                    <div class="stat-card">
                        <h3>Active Today</h3>
                        <div class="value" id="kpiDAU">—</div>
                        <p class="subtext">Daily Active Users</p>
                    </div>
                    <div class="stat-card">
                        <h3>Active This Month</h3>
                        <div class="value" id="kpiMAU">—</div>
                        <p class="subtext">
                            <?= number_format($aggregatedData['tierStats']['free']['mauUsers']) ?> free
                            · <?= number_format($aggregatedData['tierStats']['premium']['mauUsers']) ?> premium
                            · last 30 days
                        </p>
                    </div>
                    <div class="stat-card">
                        <h3>New Users</h3>
                        <div class="value" id="kpiNewUsers">—</div>
                        <p class="subtext">First seen in the last 30 days</p>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container" style="flex: 1;">
                        <h2>Daily Active Users</h2>
                        <canvas id="dauChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Active Users by Platform</h2>
                        <canvas id="platformBreakdownChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>New vs Returning Users</h2>
                        <canvas id="newVsReturningChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Peak Usage Hours</h2>
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Avg Session Duration (minutes)</h2>
                        <canvas id="avgSessionDurationChart"></canvas>
                    </div>
                </div>

                <h2 class="section-title" style="margin-top: 2rem;">User Details</h2>
                <div class="error-details-wrapper">
                    <table class="error-details-table" id="activeUsersTable" data-paginate="25" data-paginate-noun="users">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>First Seen</th>
                                <th>Last Seen</th>
                                <th>Sessions</th>
                                <th>Platform</th>
                                <th>Country</th>
                                <th>App Version</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- User Activity Tab -->
            <div id="user-activity" class="tab-content">
                <?php include __DIR__ . '/user-activity-tab.php'; ?>
            </div>

            <!-- Geographic Tab -->
            <?php if ($aggregatedData['geoLocationEnabled']): ?>
            <div id="geographic" class="tab-content">
                <h2 class="section-title">Geographic Analytics</h2>
                
                <div class="chart-row">
                    <div class="chart-container">
                        <h2>User Distribution by Country</h2>
                        <canvas id="countryDistributionChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Performance by Country</h2>
                        <canvas id="performanceByCountryChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Error Rates by Country</h2>
                        <canvas id="errorRatesByCountryChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Session Duration by Region</h2>
                        <canvas id="sessionDurationByRegionChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Timezone Distribution</h2>
                        <canvas id="timezoneChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Versions Tab -->
            <div id="versions" class="tab-content">
                <h2 class="section-title">Version Analytics</h2>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Version Distribution</h2>
                        <canvas id="versionDistributionChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Version Usage Over Time</h2>
                        <canvas id="versionTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Session Duration by Version</h2>
                        <canvas id="versionSessionChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Error Rate by Version</h2>
                        <canvas id="versionErrorChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Features Tab -->
            <div id="features" class="tab-content">
                <h2 class="section-title">Feature Usage Analytics</h2>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Most Used Features</h2>
                        <canvas id="featureUsageChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Feature Usage Over Time</h2>
                        <canvas id="featureTimelineChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Usage Tab -->
            <div id="pages" class="tab-content">
                <h2 class="section-title">Pages</h2>

                <p style="color: var(--admin-text); margin-bottom: 1.5rem;">
                    Where time goes inside a session. <strong>Active</strong> excludes gaps longer
                    than five minutes, so a window left open counts for nothing; <strong>open</strong>
                    is wall clock. A page whose open time is far above its active time is where
                    people walk away. A page with a high active time is where they get stuck.
                </p>

                <div class="error-details-wrapper">
                    <table class="error-details-table" id="pageViewsTable" data-paginate="25" data-paginate-noun="pages">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Visits</th>
                                <th>Median active</th>
                                <th>Median open</th>
                                <th>Total active</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div id="usage" class="tab-content">
                <h2 class="section-title">Usage Analytics</h2>

                <h3 class="section-subtitle">Startup Performance</h3>
                <div class="stats-grid" id="startupKpiGrid">
                    <div class="stat-card">
                        <h3>Time to Loading Screen (median)</h3>
                        <div class="value" id="kpiFirstPaintP50">—</div>
                        <p class="subtext">Cold launches, nothing on screen yet</p>
                    </div>
                    <div class="stat-card">
                        <h3>Time to Loading Screen (90th pct)</h3>
                        <div class="value" id="kpiFirstPaintP90">—</div>
                        <p class="subtext">1 in 10 waits at least this long</p>
                    </div>
                    <div class="stat-card">
                        <h3>Time to Ready (median)</h3>
                        <div class="value" id="kpiReadyP50">—</div>
                        <p class="subtext">Cold launches, to usable window</p>
                    </div>
                    <div class="stat-card">
                        <h3>Time to Ready (90th pct)</h3>
                        <div class="value" id="kpiReadyP90">—</div>
                        <p class="subtext" id="kpiStartupSample">—</p>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Cold Launch Time to Ready</h2>
                        <canvas id="startupDistributionChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Startup: Cold vs Warm (median)</h2>
                        <canvas id="startupColdWarmChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Average Session Duration (minutes)</h2>
                        <canvas id="sessionDurationChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Export Types Distribution</h2>
                        <canvas id="exportTypesGrid"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Average Duration by Export Type</h2>
                        <canvas id="exportDurationByTypeChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Average File Size by Export Type</h2>
                        <canvas id="exportFileSizeByTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- API Usage Tab -->
            <div id="api" class="tab-content">
                <h2 class="section-title">API Usage</h2>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Gemini Success Rate</h2>
                        <canvas id="geminiChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Gemini Response Time</h2>
                        <canvas id="geminiResponseTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Currency Conversion Response Time</h2>
                        <canvas id="exchangeRatesChart"></canvas>
                    </div>
                </div>

                <h2 class="section-title" style="margin-top: 2rem;">Receipt Scanning</h2>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Receipt Scans Overview</h2>
                        <canvas id="receiptScanOverviewChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Receipt Scan Success Rate</h2>
                        <canvas id="receiptScanSuccessChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Receipt Scan Duration</h2>
                        <canvas id="receiptScanDurationChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Scans Per Day Trend</h2>
                        <canvas id="receiptScanTrendChart"></canvas>
                    </div>
                </div>

                <h2 class="section-title" style="margin-top: 2rem;">AI Spreadsheet Importer</h2>

                <div class="stats-grid" id="aiImportStatsGrid">
                    <!-- Will be populated by JavaScript -->
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>AI Import Type Breakdown</h2>
                        <canvas id="aiImportOverviewChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>AI Imports Per Day Trend</h2>
                        <canvas id="aiImportTrendChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Import Type Over Time</h2>
                        <canvas id="aiImportTypeTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>AI Import Duration Over Time</h2>
                        <canvas id="aiImportDurationChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>AI Import Duration by Type</h2>
                        <canvas id="aiImportDurationByTypeChart"></canvas>
                    </div>
                </div>
            </div>

              <!-- Errors Tab -->
            <div id="errors" class="tab-content">
                <h2 class="section-title">Error Analysis</h2>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Errors by Category</h2>
                        <canvas id="errorCategoryChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h2>Errors by Code</h2>
                        <canvas id="errorCodeChart"></canvas>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-container">
                        <h2>Errors by Category Over Time</h2>
                        <canvas id="errorCategoryTimelineChart"></canvas>
                    </div>
                </div>

                <h2 class="section-title" style="margin-top: 2rem;">Error Details</h2>
                <div class="error-details-wrapper" id="errorDetailsTableWrapper">
                    <p style="text-align: center; color: var(--black);">No error data available</p>
                </div>
            </div>

            <!-- Crashes Tab -->
            <div id="crashes" class="tab-content">
                <?php include __DIR__ . '/crashes-tab.php'; ?>
            </div>

        </div>
    <?php endif; ?>
</div>

<?php if (!$errorMessage && $currentViewHasData): ?>
<script>
// Tab switching is handled centrally by admin/section-tabs.js
// Pass PHP data to JavaScript
window.dashboardData = <?= $jsonData ?>;
</script>
<!-- Only loaded when the tabs above were rendered: every chart generator in
     main.js assumes its canvas exists, so running it against the empty-state
     page (no data for the selected range or tier) would throw. -->
<script src="main.js?v=<?= filemtime(__DIR__ . '/main.js') ?>"></script>
<?php endif; ?>

<script>
// Preserve scroll position when switching tier filter (shared admin pattern; see CLAUDE.md)
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.control-bar a.control-pill').forEach(function (link) {
        link.addEventListener('click', function () {
            sessionStorage.setItem('scrollPosition', window.scrollY);
            // The href is rendered server-side at load, before the user clicks a
            // tab (section-tabs.js only updates the URL, not these hrefs). Rewrite
            // it here so the tier switch keeps the currently-active tab.
            try {
                var dest = new URL(link.href, window.location.origin);
                var activeBtn = document.querySelector('.section-tab.active[data-tab]');
                var currentTab = activeBtn
                    ? activeBtn.dataset.tab
                    : new URLSearchParams(window.location.search).get('tab');
                if (currentTab) {
                    dest.searchParams.set('tab', currentTab);
                    link.href = dest.toString();
                }
            } catch (err) { /* URL API missing; fall back to server-rendered href */ }
        });
    });
});

// Date range control. Mirrors admin/website-stats: presets submit immediately,
// "Custom Range" reveals the date inputs and waits for Apply.
function onRangeChange() {
    var preset = document.getElementById('rangePreset').value;
    var custom = document.getElementById('rangeCustom');
    var start = document.getElementById('rangeStart');
    var end = document.getElementById('rangeEnd');

    if (preset === 'Custom Range') {
        custom.style.display = 'flex';
        start.disabled = false;
        end.disabled = false;
        // Wait for the user to pick dates and press Apply.
    } else {
        custom.style.display = 'none';
        // Disable so stale custom dates aren't appended to the URL.
        start.disabled = true;
        end.disabled = true;
        submitRangeForm();
    }
}

// The hidden tab field is rendered server-side at load; section-tabs.js only
// updates the URL, so refresh it here to keep the active tab across a reload.
function submitRangeForm() {
    var activeBtn = document.querySelector('.section-tab.active[data-tab]');
    var currentTab = activeBtn
        ? activeBtn.dataset.tab
        : new URLSearchParams(window.location.search).get('tab');
    document.getElementById('rangeTabInput').value = currentTab || '';
    sessionStorage.setItem('scrollPosition', window.scrollY);
    document.getElementById('rangeForm').submit();
}

document.addEventListener('DOMContentLoaded', function () {
    var rangeForm = document.getElementById('rangeForm');
    if (rangeForm) {
        // Apply button (custom range) goes through the same tab-preserving path.
        rangeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitRangeForm();
        });
    }
});
</script>
<script src="../preserve-scroll.js" defer></script>
