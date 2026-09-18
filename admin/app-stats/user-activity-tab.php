<?php
// User Activity tab body, included by admin/app-stats/index.php inside the
// #user-activity tab. Admin auth + page chrome are handled by the host page.
//
// Telemetry lives in JSON files, NOT the database. New uploads land in
// data-logs/telemetry/; the legacy data-logs/ root is still read during the
// transition. Files are named argo_data_{tier}_{date}_{rand}.json and each is
// tagged at the top level with {tier, authId} by api/data/upload.php. This tab
// groups every file by user, shows what each user did, and lets you delete a
// user's files (e.g. your own test installs) one card at a time.

// Direct-access guard. This partial is only valid when included by its parent
// page (app-stats/index.php), which starts the session and verifies the admin
// login. Requested directly, no session is started so $_SESSION is empty and we
// fail closed. (An admin/.htaccess also denies *-tab.php as defense in depth.)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../founder_identity.php';      // is_founder_auth_id()
require_once __DIR__ . '/../../country_names.php';         // country_name()
require_once __DIR__ . '/../../telemetry_environment.php'; // is_other_environment_auth_id()
// ua_describe_event() / ua_is_warning() / ua_unwrap_event(). Shared with
// download-user.php so the CSV export says the same thing this timeline does.
require_once __DIR__ . '/user-activity-events.php';
require_once __DIR__ . '/telemetry-dedupe.php';       // telemetry_is_duplicate_event()

if (!function_exists('ua_set_language')) {
    // Newest wins: files are read in name order, not event order. Language comes from
    // the company profile and from switching it in settings, whichever is later.
    function ua_set_language(array &$u, $language, $ts): void {
        if (!is_string($language) || $language === '') return;
        $ts = $ts === false ? 0 : (int)$ts;
        if ($u['languageTs'] !== null && $ts < $u['languageTs']) return;
        $u['language']   = $language;
        $u['languageTs'] = $ts;
    }
}

// Tier and date range come from the page-level control bar, so this tab shows
// the same slice as the charts. Defaulted here so the partial still renders if
// it's ever included without them.
$ua_tierFilter   = $tierFilter ?? 'all';
$ua_rangeStartTs = $rangeStartTs ?? null;
$ua_rangeEndTs   = $rangeEndTs ?? PHP_INT_MAX;

$ua_dataDir   = __DIR__ . '/../data-logs/telemetry/';
$ua_legacyDir = __DIR__ . '/../data-logs/';

// Collect files, de-duping by basename so a file in both dirs counts once.
$ua_files = [];
$ua_seen  = [];
foreach ([$ua_dataDir, $ua_legacyDir] as $dir) {
    if (!is_dir($dir)) continue;
    foreach (glob($dir . '*.json') ?: [] as $f) {
        $name = basename($f);
        if (!isset($ua_seen[$name])) {
            $ua_seen[$name] = true;
            $ua_files[$name] = $f; // map basename -> full path
        }
    }
}

// ---- Delete action: removes the exact files posted from a card. -------------
// Matching by filename (not authId) so legacy files with no authId can be
// deleted too. Each posted name is basename()'d and must already be in the
// collected $ua_files map, so nothing outside data-logs/ can be touched.
$ua_flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_files'])) {
    $label   = (string)($_POST['del_label'] ?? '');
    $deleted = 0;
    foreach ((array)$_POST['del_files'] as $reqName) {
        $base = basename((string)$reqName);
        if (isset($ua_files[$base]) && @unlink($ua_files[$base])) {
            $deleted++;
            unset($ua_files[$base]);
        }
    }
    $ua_flash = "Deleted {$deleted} file(s) for " . htmlspecialchars($label) . ".";
}

// ---- Aggregate per authId ---------------------------------------------------
$ua_users = []; // authId => aggregate

// This tab reads the files in its own pass, so it needs its own seen-set rather
// than sharing index.php's. See telemetry-dedupe.php for why duplicates exist.
$ua_seenEventIds = [];

// Surfaced under the filters rather than silently swallowed. Once the client-side
// locking fix has rolled out, this should trend to zero for recent uploads; if it
// doesn't, devices are still losing their "uploaded" flags somewhere.
$ua_duplicatesCollapsed = 0;

// Installs whose premium came from another environment's subscription, counted so
// the page can say they were left out rather than silently dropping them.
$ua_otherEnvUsers = [];
// Name the environment rather than saying "another" one: on the live site what gets
// hidden is sandbox, and on dev it is production.
$ua_otherEnvLabel = current_environment() === 'production' ? 'sandbox' : 'production';

foreach ($ua_files as $name => $path) {
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') continue;
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['events']) || !is_array($d['events'])) continue;

    $tier   = $d['tier'] ?? 'premium';
    $authId = $d['authId'] ?? '(no authId)';
    $geo    = $d['geoLocation'] ?? [];

    // This tab is the ONE place the founder's own installs are visible. Every other
    // read site (app-stats charts and KPIs, crashes, marketing funnel) skips them, so
    // they never reach a real-user number. Here they render badged and are left out of
    // the header tally instead.
    $isFounder = is_founder_auth_id($authId);

    // Sandbox is the one thing it does not show. The upload endpoint authenticates a
    // license without checking which environment its subscription belongs to, so a test
    // redemption lands here looking like a customer. An id with no subscription in this
    // environment at all is the same story: either sandbox, or an account since deleted.
    if (is_other_environment_auth_id($authId)) {
        $ua_otherEnvUsers[$authId] = true;
        continue;
    }

    // Page-level tier filter.
    if ($ua_tierFilter !== 'all' && $tier !== $ua_tierFilter) {
        continue;
    }

    if (!isset($ua_users[$authId])) {
        $ua_users[$authId] = [
            'tier'      => $tier,
            'authId'    => $authId,
            'isFounder' => $isFounder,
            'platforms' => [],
            'versions'  => [],
            'country'   => country_name($geo['country'] ?? ''),
            'region'    => $geo['region'] ?? '',
            'timezone'  => $geo['timezone'] ?? '',
            'language'  => null,
            'languageTs'=> null, // when 'language' was set, so the newest wins across files
            'first'     => null,
            'last'      => null,
            'sessions'  => 0,
            'unclean'   => 0,     // sessions that ended without a clean shutdown
            'events'    => 0,
            'errors'    => 0,
            'warnings'  => 0,    // severity=Warning events, counted apart from errors
            'company'   => null, // most recent non-sample CompanyProfile, shown on the card
            'timeline'  => [],   // every event: ['ts','type','text']
            'files'     => [],
        ];
    }
    $u =& $ua_users[$authId];
    $u['files'][] = $name;
    if (!empty($d['platform']))   $u['platforms'][$d['platform']] = true;
    if (!empty($d['appVersion'])) $u['versions'][$d['appVersion']] = true;
    if (empty($u['country'])  && !empty($geo['country']))  $u['country']  = country_name($geo['country']);
    if (empty($u['region'])   && !empty($geo['region']))   $u['region']   = $geo['region'];
    if (empty($u['timezone']) && !empty($geo['timezone'])) $u['timezone'] = $geo['timezone'];

    foreach ($d['events'] as $ev) {
        // A re-uploaded event is the same action, not a second one. This is what
        // stops a single tutorial skip rendering as three at the same timestamp.
        if (telemetry_is_duplicate_event($ev, $authId, $ua_seenEventIds)) {
            $ua_duplicatesCollapsed++;
            continue;
        }

        // Normalize the compact format's nesting before reading any field. The dedupe
        // check above already does this internally; everything below it needs it too.
        $ev = ua_unwrap_event($ev);

        $ts = isset($ev['timestamp']) ? strtotime($ev['timestamp']) : false;

        // Page-level date range. An event we can't date is kept, matching how
        // the crash tab treats undated reports.
        if ($ts !== false) {
            if ($ts > $ua_rangeEndTs || ($ua_rangeStartTs !== null && $ts < $ua_rangeStartTs)) {
                continue;
            }
        }

        $u['events']++;
        if ($ts !== false) {
            if ($u['first'] === null || $ts < $u['first']) $u['first'] = $ts;
            if ($u['last']  === null || $ts > $u['last'])  $u['last']  = $ts;
        }

        switch ($ev['dataType'] ?? '') {
            case 'Session':
                if (($ev['action'] ?? '') === 'SessionStart') {
                    $u['sessions']++;
                } elseif (array_key_exists('clean', $ev) && $ev['clean'] === false) {
                    $u['unclean']++;
                }
                break;
            case 'Error':
                if (ua_is_warning($ev)) {
                    $u['warnings']++;
                } else {
                    $u['errors']++;
                }
                break;
            case 'CompanyProfile':
                $u['company'] = $ev;
                ua_set_language($u, $ev['language'] ?? null, $ts);
                break;
            case 'FeatureUsage':
                if (($ev['featureName'] ?? '') === 'LanguageChanged') {
                    ua_set_language($u, $ev['context'] ?? null, $ts);
                }
                break;
        }

        [$evType, $evText] = ua_describe_event($ev);
        $u['timeline'][] = ['ts' => ($ts !== false ? $ts : 0), 'type' => $evType, 'text' => $evText];
    }
    unset($u);
}

// A user whose files hold no events inside the selected range isn't part of this
// view. Their file list is still complete on the card, because "Delete this
// user" removes every file they uploaded, not just the ones in range.
$ua_users = array_filter($ua_users, function ($u) { return $u['events'] > 0; });

// What a premium install is running on: the subscription key behind it, and whether
// that key was redeemed rather than paid for. api/data/upload.php stamps a premium
// authId as 'subscription:<subscription_id>', and redeem_premium_key() records the
// subscription with payment_method = 'free_key'. That column is what separates a
// promo or reseller key from someone who actually paid: both end up with a row in
// premium_subscription_keys, because paid checkout auto-creates one too, so the key
// table alone cannot tell them apart.
$ua_subInfo = [];   // subscription_id => ['key' =>, 'batch' =>, 'isFreeKey' =>]
$ua_subIds  = [];
foreach ($ua_users as $ua_authId => $_ua_ignored) {
    if (strncmp($ua_authId, 'subscription:', 13) === 0) {
        $ua_subIds[] = substr($ua_authId, 13);
    }
}
if ($ua_subIds && isset($pdo)) {
    try {
        $ua_ph = implode(',', array_fill(0, count($ua_subIds), '?'));
        // Ordered by key id so that if a subscription ever carries more than one key
        // row, the newest is the one left on the card: that is the key the install is
        // actually running on.
        $ua_stmt = $pdo->prepare("
            SELECT s.subscription_id, s.payment_method, k.subscription_key, k.batch_label
            FROM premium_subscriptions s
            LEFT JOIN premium_subscription_keys k ON k.subscription_id = s.subscription_id
            WHERE s.subscription_id IN ($ua_ph)
              AND s.environment = ?
            ORDER BY k.id ASC
        ");
        $ua_stmt->execute(array_merge($ua_subIds, [current_environment()]));
        foreach ($ua_stmt->fetchAll(PDO::FETCH_ASSOC) as $ua_row) {
            $ua_subInfo[$ua_row['subscription_id']] = [
                'key'       => $ua_row['subscription_key'] ?? null,
                'batch'     => $ua_row['batch_label'] ?? null,
                'isFreeKey' => ($ua_row['payment_method'] ?? '') === 'free_key',
            ];
        }
    } catch (PDOException $e) {
        // Neither the key nor the badge is worth failing the page over. Without them
        // these users render as plain premium, which is what happened before this existed.
        error_log('user-activity subscription lookup failed: ' . $e->getMessage());
    }
}
foreach ($ua_users as $ua_authId => &$ua_u) {
    $ua_sid  = strncmp($ua_authId, 'subscription:', 13) === 0 ? substr($ua_authId, 13) : null;
    $ua_info = $ua_sid !== null ? ($ua_subInfo[$ua_sid] ?? null) : null;
    $ua_u['isKeyUser']  = $ua_info !== null && $ua_info['isFreeKey'];
    $ua_u['keyBatch']   = $ua_u['isKeyUser'] ? $ua_info['batch'] : null;
    $ua_u['licenseKey'] = $ua_info !== null ? $ua_info['key'] : null;
}
unset($ua_u);

// Where a free install came from. api/track-app-event.php stores the same device
// hash on the install's app_first_run row that upload.php files telemetry under.
// Installs reported before that field existed carry no hash and show nothing.
$ua_deviceHashes = [];
foreach ($ua_users as $ua_authId => $_ua_ignored) {
    if (strncmp($ua_authId, 'device:', 7) === 0) {
        $ua_deviceHashes[] = substr($ua_authId, 7);
    }
}
$ua_referrals = [];   // device hash => ['link' =>, 'linkCode' =>, 'survey' =>]
if ($ua_deviceHashes && isset($pdo)) {
    try {
        require_once __DIR__ . '/../../config/survey_options.php';
        require_once __DIR__ . '/../../referral_categories.php';
        $ua_surveyLabels = [];
        foreach (get_survey_options() ?? [] as $ua_opt) {
            $ua_surveyLabels[$ua_opt['key']] = $ua_opt['label'];
        }

        $ua_ph = implode(',', array_fill(0, count($ua_deviceHashes), '?'));
        $ua_stmt = $pdo->prepare("
            SELECT JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.device_hash')) AS device_hash,
                   e.source_code, l.name AS source_name, l.category AS source_category,
                   e.source_survey_answer, e.source_survey_other_text
            FROM referral_events e
            LEFT JOIN referral_links l ON l.source_code = e.source_code
            WHERE e.event_type = 'app_first_run'
              AND e.environment = ?
              AND JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.device_hash')) IN ($ua_ph)
            ORDER BY e.created_at ASC
        ");
        $ua_stmt->execute(array_merge([current_environment()], $ua_deviceHashes));
        foreach ($ua_stmt->fetchAll(PDO::FETCH_ASSOC) as $ua_row) {
            // A reinstall adds a second row. The first answer found for each part is
            // kept, so a later bare reinstall can't blank out a known source.
            $ua_ref =& $ua_referrals[$ua_row['device_hash']];
            $ua_ref ??= ['link' => null, 'linkCode' => null, 'survey' => null];
            if ($ua_ref['link'] === null && !empty($ua_row['source_code'])) {
                // A source code with no referral_links row has no category to show.
                $ua_ref['link']     = $ua_row['source_name'] !== null
                    ? referral_display_name($ua_row['source_category'], $ua_row['source_name'], $ua_row['source_code'])
                    : $ua_row['source_code'];
                $ua_ref['linkCode'] = $ua_row['source_code'];
            }
            if ($ua_ref['survey'] === null && !empty($ua_row['source_survey_answer'])) {
                $ua_answer = $ua_row['source_survey_answer'];
                $ua_ref['survey'] = $ua_surveyLabels[$ua_answer] ?? $ua_answer;
                if (!empty($ua_row['source_survey_other_text'])) {
                    $ua_ref['survey'] .= ': ' . $ua_row['source_survey_other_text'];
                }
            }
            unset($ua_ref);
        }
    } catch (PDOException $e) {
        error_log('user-activity referral lookup failed: ' . $e->getMessage());
    }
}

// Sort users: free first (what you care about), then most-recent activity.
uasort($ua_users, function ($a, $b) {
    if ($a['tier'] !== $b['tier']) return $a['tier'] === 'free' ? -1 : 1;
    return ($b['last'] ?? 0) <=> ($a['last'] ?? 0);
});

// Delete posts back to this tab with the current filters intact, so the page
// doesn't snap back to the default range after removing a user's files.
$ua_action = '?tab=user-activity&tier=' . urlencode($ua_tierFilter);
if (isset($selectedRange)) {
    $ua_action .= '&range=' . urlencode($selectedRange);
    if ($selectedRange === 'Custom Range' && isset($rangeStart, $rangeEnd)) {
        $ua_action .= '&start=' . urlencode($rangeStart->format('Y-m-d'))
                    . '&end=' . urlencode($rangeEnd->format('Y-m-d'));
    }
}

if (!function_exists('ua_fmt')) {
    // Printed as UTC and relabelled to the reader's timezone by the script in
    // admin_header.php. Telemetry timestamps arrive as UTC (Z-suffixed).
    function ua_fmt($ts, $withSeconds = false) {
        if (!$ts) return '—';
        return '<time data-epoch="' . (int)$ts . '"'
            . ($withSeconds ? ' data-epoch-seconds="1"' : '') . '>'
            . gmdate($withSeconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i', $ts) . ' UTC</time>';
    }
}
?>
<style>
.ua-intro { color:var(--black); margin-bottom:1rem; }
.ua-flash { background:#ecfdf5; border:1px solid #6ee7b7; color:#065f46; padding:10px 14px; border-radius:8px; margin-bottom:1rem; }
.ua-card { border:1px solid #e5e7eb; border-radius:10px; padding:1rem 1.25rem; margin-bottom:1rem; background:#fff; }
.ua-card h3 { margin:0 0 .25rem; font-family:monospace; font-size:1rem; word-break:break-all; }
.ua-badge { display:inline-block; font-size:.7rem; font-weight:700; text-transform:uppercase; padding:2px 8px; border-radius:999px; margin-left:.5rem; vertical-align:middle; }
.ua-badge.free { background:#dbeafe; color:#1e40af; }
.ua-badge.premium { background:#fef3c7; color:#92400e; }
/* Premium via a redeemed key rather than a payment. Distinct from the premium
   badge because these users pay nothing recurring and are worth reading
   separately when judging what Premium usage actually costs. */
.ua-badge.keyuser { background:#dcfce7; color:#166534; }
/* The founder's own install. Deliberately loud so it can never be mistaken for a
   real user while reading the list. */
.ua-badge.founder { background:#7c3aed; color:#fff; }
.ua-meta { color:var(--black); font-size:.85rem; margin:.4rem 0; }
.ua-meta span { display:inline-block; margin-right:1.25rem; }
.ua-row { font-size:.85rem; margin:.25rem 0; }
.ua-row b { color:var(--black); }
.ua-key { font-family:monospace; font-size:.8rem; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:5px; padding:1px 6px; user-select:all; }
/* Download sits left of Delete. Flex rather than the old bare float, because the
   delete form is block-level and would otherwise push the link onto its own line. */
.ua-del { float:right; display:flex; gap:8px; align-items:center; }
.ua-del button { background:#ef4444; color:#fff; border:0; border-radius:6px; padding:6px 12px; font-size:.8rem; cursor:pointer; }
.ua-del button:hover { background:#dc2626; }
/* Secondary to Delete: this one is safe to click, so it reads as a quiet action. */
.ua-dl { background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:6px; padding:6px 12px; font-size:.8rem; text-decoration:none; white-space:nowrap; }
.ua-dl:hover { background:#e5e7eb; color:#111827; }
.ua-events { margin-top:.6rem; }
.ua-events > summary { cursor:pointer; font-size:.85rem; font-weight:600; color:#2563eb; }
.ua-timeline { margin-top:.5rem; max-height:340px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:8px; }
.ua-evt { display:flex; gap:.75rem; padding:5px 12px; font-size:.78rem; border-bottom:1px solid #f3f4f6; }
.ua-evt:last-child { border-bottom:0; }
.ua-evt-ts { color:var(--black); font-family:monospace; white-space:nowrap; }
.ua-evt-text { color:var(--black); }
.ua-evt.error  .ua-evt-text { color:#b91c1c; font-family:monospace; }
.ua-evt.api    .ua-evt-text { color:#1d4ed8; }
.ua-evt.export .ua-evt-text { color:#0369a1; }
.ua-evt.feature .ua-evt-text { color:#047857; }
.ua-evt.session .ua-evt-text { color:var(--black); }
/* Force-quit / OS restart / power loss. Red like an error because it's worth
   noticing, but prose rather than the error rows' monospace: there's no code here. */
.ua-evt.unclean .ua-evt-text { color:#b91c1c; font-weight:600; }
.ua-unclean, .ua-err { color:#b91c1c; font-weight:700; }
/* Warnings are expected, handled conditions. Amber and prose, so they read as
   "worth knowing" rather than sitting in the error rows' red monospace. */
.ua-evt.warning .ua-evt-text { color:#b45309; }
.ua-warn { color:#b45309; font-weight:700; }
/* Who the user is, rather than what they did. Bold and near-black so it stands out
   from the activity around it: it's usually the only row on a card worth reading first. */
.ua-evt.company .ua-evt-text { color:#6d28d9; font-weight:600; }
/* Launch timings. Deliberately muted: one per session, and only interesting in bulk. */
.ua-evt.startup .ua-evt-text { color:#6b7280; font-family:monospace; }
/* How much is in the file. Its own type rather than 'company': that one is claimed by the
   CompanyCreated merge, which would otherwise fold the creation into a scale row. */
.ua-evt.scale .ua-evt-text { color:#0f766e; font-family:monospace; }
.ua-business { color:#6d28d9; font-weight:700; }
[data-theme="dark"] .ua-card { background:var(--gray-800); border-color:var(--gray-700); }
[data-theme="dark"] .ua-card h3, [data-theme="dark"] .ua-meta, [data-theme="dark"] .ua-row, [data-theme="dark"] .ua-row b, [data-theme="dark"] .ua-evt-text { color:var(--white); }
[data-theme="dark"] .ua-timeline { border-color:var(--gray-700); }
[data-theme="dark"] .ua-evt { border-bottom-color:var(--gray-700); }
[data-theme="dark"] .ua-evt-ts { color:var(--gray-400); }
[data-theme="dark"] .ua-evt.error  .ua-evt-text { color:#f87171; }
[data-theme="dark"] .ua-evt.api    .ua-evt-text { color:#93c5fd; }
[data-theme="dark"] .ua-evt.export .ua-evt-text { color:#38bdf8; }
[data-theme="dark"] .ua-evt.feature .ua-evt-text { color:#34d399; }
[data-theme="dark"] .ua-evt.session .ua-evt-text { color:var(--white); }
[data-theme="dark"] .ua-evt.unclean .ua-evt-text, [data-theme="dark"] .ua-unclean, [data-theme="dark"] .ua-err { color:#f87171; }
[data-theme="dark"] .ua-evt.warning .ua-evt-text, [data-theme="dark"] .ua-warn { color:#fbbf24; }
[data-theme="dark"] .ua-dl { background:var(--gray-700); border-color:var(--gray-600); color:var(--white); }
[data-theme="dark"] .ua-dl:hover { background:var(--gray-600); color:var(--white); }
[data-theme="dark"] .ua-evt.company .ua-evt-text { color:#c4b5fd; }
[data-theme="dark"] .ua-evt.startup .ua-evt-text { color:#9ca3af; }
[data-theme="dark"] .ua-evt.scale .ua-evt-text { color:#5eead4; }
[data-theme="dark"] .ua-key { background:var(--gray-700); border-color:var(--gray-600); color:var(--white); }
[data-theme="dark"] .ua-evt.company .ua-evt-text, [data-theme="dark"] .ua-business { color:#c4b5fd; }

/* Filter controls. Pagination itself is the shared admin TablePaginator. */
.ua-controls { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:1rem; }
.ua-dupes { font-size:.78rem; color:#b45309; }
[data-theme="dark"] .ua-dupes { color:#fbbf24; }
.ua-input { padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:.85rem; background:#fff; color:var(--admin-text); }
.ua-input:focus { outline:none; border-color:#3b82f6; }
#ua-search { flex:1; min-width:220px; }
.ua-count { font-size:.8rem; color:#6b7280; margin-left:auto; white-space:nowrap; }
.ua-noresults { color:#6b7280; font-size:.9rem; padding:1rem 0; }
.ua-table { width:100%; border-collapse:collapse; }
.ua-table td.ua-td { padding:0; border:0; background:transparent; }
/* The rows only exist so the shared paginator can page the cards. They aren't
   selectable table rows, so the global admin row-hover highlight is cancelled. */
.ua-table tbody tr, .ua-table tbody tr:hover,
[data-theme="dark"] .ua-table tbody tr, [data-theme="dark"] .ua-table tbody tr:hover { background:transparent !important; transition:none; }
[data-theme="dark"] .ua-input { background:var(--gray-800); border-color:var(--gray-700); color:var(--white); }
[data-theme="dark"] .ua-count, [data-theme="dark"] .ua-noresults { color:var(--gray-400); }
</style>

<h2 class="section-title">User Activity</h2>

<?php if ($ua_flash): ?><div class="ua-flash"><?= $ua_flash ?></div><?php endif; ?>

<?php if (!$ua_users): ?>
    <?php if ($ua_files && isset($rangeDisplay)): ?>
        <p>
            No <?= $ua_tierFilter !== 'all' ? htmlspecialchars($ua_tierFilter) . '-tier ' : '' ?>user
            activity in <?= htmlspecialchars($rangeDisplay) ?>. Widen the date range<?= $ua_tierFilter !== 'all' ? ' or switch tier' : '' ?> above.
        </p>
    <?php else: ?>
        <p>No telemetry files found.</p>
    <?php endif; ?>
    <?php if ($ua_otherEnvUsers): ?>
        <?php // Otherwise an empty page looks like nothing was uploaded, when in fact
              // every install that did upload belongs to another environment. ?>
        <p><?= count($ua_otherEnvUsers) ?> <?= htmlspecialchars($ua_otherEnvLabel) ?> install<?= count($ua_otherEnvUsers) === 1 ? '' : 's' ?> hidden: no subscription for them in this environment.</p>
    <?php endif; ?>
<?php else: ?>
    <!-- Search only. Tier and date range are set once in the page's control bar. -->
    <div class="ua-controls" id="ua-controls">
        <input type="text" id="ua-search" class="ua-input" placeholder="Search id, country, region, version&hellip;">
        <span class="ua-count" id="ua-count"></span>
        <!-- Same scope as the per-user button, for everybody at once: every event
             ever sent, ignoring this page's range and tier. Grouped by user with an
             auth_id column, which the single-user file does not carry. -->
        <a class="ua-dl" href="download-user.php?all=1"
           title="Every event from every user, in one file. Ignores the filters above.">Download all users (CSV)</a>
        <?php if ($ua_otherEnvUsers): ?>
            <span class="ua-dupes" title="Premium installs whose subscription is not in this environment: a sandbox test redemption, or a subscription since deleted. Hidden here and everywhere else on this page.">
                <?= count($ua_otherEnvUsers) ?> <?= htmlspecialchars($ua_otherEnvLabel) ?> install<?= count($ua_otherEnvUsers) === 1 ? '' : 's' ?> hidden
            </span>
        <?php endif; ?>
        <?php if ($ua_duplicatesCollapsed > 0): ?>
            <span class="ua-dupes" title="The same event uploaded more than once and collapsed by dataId. Should trend to zero as the client-side locking fix rolls out.">
                <?= number_format($ua_duplicatesCollapsed) ?> duplicate event<?= $ua_duplicatesCollapsed === 1 ? '' : 's' ?> collapsed
            </span>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
    <table class="ua-table" data-paginate="25" data-paginate-noun="users">
        <tbody>
    <?php foreach ($ua_users as $u):
        // Newest-first timeline for display.
        $timeline = $u['timeline'];
        $timeline = ua_merge_timeline($timeline);
        usort($timeline, fn($a, $b) => $b['ts'] <=> $a['ts']);
        $ua_ref = strncmp($u['authId'], 'device:', 7) === 0
            ? ($ua_referrals[substr($u['authId'], 7)] ?? null)
            : null;
        // Searchable haystack + filter keys for the client-side filters.
        $ua_haystack = strtolower(trim(
            $u['authId'] . ' ' . $u['country'] . ' ' . $u['region'] . ' ' . ($u['language'] ?? '') . ' ' .
            implode(' ', array_keys($u['versions'])) . ' ' .
            implode(' ', array_keys($u['platforms'])) .
            // So "me" / "you" / "founder" all find your own card.
            ($u['isFounder'] ? ' founder you me' : '') .
            // And so a promo cohort can be pulled up by name, e.g. "stacksocial".
            (!empty($u['isKeyUser']) ? ' freekey free key promo redeemed ' . ($u['keyBatch'] ?? '') : '') .
            // Pasting a key from a support email should land on the install using it.
            ' ' . ($u['licenseKey'] ?? '') .
            // So "youtube" pulls up everyone a video brought in.
            ' ' . ($ua_ref['link'] ?? '') . ' ' . ($ua_ref['linkCode'] ?? '') . ' ' . ($ua_ref['survey'] ?? '')
        ));
    ?>
    <tr class="ua-user"<?= $u['isFounder'] ? ' data-founder="1"' : '' ?> data-search="<?= htmlspecialchars($ua_haystack) ?>">
      <td class="ua-td">
        <div class="ua-card">
        <div class="ua-del">
            <!-- Plain GET link, so the browser downloads it natively with no JS. Exports
                 every event this user ever sent, ignoring the page's range and tier the
                 same way the Delete button beside it does. -->
            <a class="ua-dl" href="download-user.php?authId=<?= urlencode($u['authId']) ?>"
               title="Every event this user has ever sent, ignoring the filters above.">Download CSV</a>
            <form method="post" action="<?= htmlspecialchars($ua_action) ?>"
                  onsubmit="return confirm('Delete ALL <?= count($u['files']) ?> file(s) for this user? This cannot be undone.');">
                <input type="hidden" name="del_label" value="<?= htmlspecialchars($u['authId']) ?>">
                <?php foreach ($u['files'] as $fn): ?>
                    <input type="hidden" name="del_files[]" value="<?= htmlspecialchars($fn) ?>">
                <?php endforeach; ?>
                <button type="submit">Delete this user</button>
            </form>
        </div>
        <h3><?= htmlspecialchars($u['authId']) ?><span class="ua-badge <?= $u['tier'] ?>"><?= htmlspecialchars($u['tier']) ?></span><?php if (!empty($u['isKeyUser'])): ?><span class="ua-badge keyuser" title="Premium from a redeemed key, not a paid subscription<?= $u['keyBatch'] ? ' — batch: ' . htmlspecialchars($u['keyBatch']) : '' ?>."><?= $u['keyBatch'] ? htmlspecialchars($u['keyBatch']) : 'Free key' ?></span><?php endif; ?><?php if ($u['isFounder']): ?><span class="ua-badge founder" title="Your own install. Counted nowhere else on the site.">You</span><?php endif; ?></h3>
        <div class="ua-meta">
            <span><b>Platform:</b> <?= htmlspecialchars(implode(', ', array_keys($u['platforms'])) ?: '—') ?></span>
            <span><b>Country:</b> <?= htmlspecialchars($u['country'] ?: '—') ?></span>
            <span><b>Region:</b> <?= htmlspecialchars($u['region'] ?: '—') ?></span>
            <span><b>Timezone:</b> <?= htmlspecialchars($u['timezone'] ?: '—') ?></span>
            <span><b>Language:</b> <?= htmlspecialchars($u['language'] ?? '—') ?></span>
            <span><b>Version:</b> <?= htmlspecialchars(implode(', ', array_keys($u['versions'])) ?: '—') ?></span>
        </div>
        <div class="ua-meta">
            <span><b>First seen:</b> <?= ua_fmt($u['first']) ?></span>
            <span><b>Last seen:</b> <?= ua_fmt($u['last']) ?></span>
            <span><b>Sessions:</b> <?= $u['sessions'] ?></span>
            <?php if ($u['unclean'] > 0): ?>
                <span><b>Unclean exits:</b> <span class="ua-unclean"><?= $u['unclean'] ?></span></span>
            <?php endif; ?>
            <span><b>Total events:</b> <?= $u['events'] ?></span>
            <span><b>Errors:</b> <?php if ($u['errors'] > 0): ?><span class="ua-err"><?= $u['errors'] ?></span><?php else: ?>0<?php endif; ?></span>
            <?php if ($u['warnings'] > 0): ?>
                <span><b>Warnings:</b> <span class="ua-warn"><?= $u['warnings'] ?></span></span>
            <?php endif; ?>
        </div>
        <?php if ($ua_ref !== null && ($ua_ref['link'] !== null || $ua_ref['survey'] !== null)): ?>
            <div class="ua-row"><b>Referral:</b>
                <?php if ($ua_ref['link'] !== null): ?>
                    <span title="Referral link <?= htmlspecialchars($ua_ref['linkCode']) ?>"><?= htmlspecialchars($ua_ref['link']) ?></span>
                <?php endif; ?>
                <?php if ($ua_ref['link'] !== null && $ua_ref['survey'] !== null): ?> &middot; <?php endif; ?>
                <?php if ($ua_ref['survey'] !== null): ?>
                    <span>said "<?= htmlspecialchars($ua_ref['survey']) ?>"</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($u['licenseKey'])): ?>
            <?php // The key this install's premium is running on. Support material, so it
                  // renders whole rather than masked: the page is already behind admin 2FA. ?>
            <div class="ua-row"><b>License key:</b> <span class="ua-key"><?= htmlspecialchars($u['licenseKey']) ?></span></div>
        <?php endif; ?>
        <?php if ($u['company'] !== null): ?>
            <?php
            $c = $u['company'];
            // Escaped per item, then joined with the raw separator: escaping the joined
            // string would turn the separator entity into visible "&middot;" text.
            $companyBits = array_map(
                'htmlspecialchars',
                array_filter([
                    $c['industry'] ?? null,
                    $c['businessType'] ?? null,
                    $c['country'] ?? null,
                    $c['currency'] ?? null,
                ])
            );
            ?>
            <div class="ua-row">
                <b>Business:</b>
                <span class="ua-business"><?= htmlspecialchars($c['companyName'] ?? '(unnamed)') ?></span><?php
                    if ($companyBits): ?> &middot; <?= implode(' &middot; ', $companyBits) ?><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($timeline): ?>
        <details class="ua-events">
            <summary>Show all <?= count($timeline) ?> events</summary>
            <div class="ua-timeline">
                <?php foreach ($timeline as $t): ?>
                    <div class="ua-evt <?= $t['type'] ?>">
                        <span class="ua-evt-ts"><?= ua_fmt($t['ts'], true) ?></span>
                        <span class="ua-evt-text"><?= htmlspecialchars($t['text']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- /.table-responsive -->

    <p class="ua-noresults" id="ua-noresults" style="display:none;">No users match your search.</p>

    <script>
    // Text search only; tier and date range are applied server-side from the
    // page's control bar. Pagination is the shared admin TablePaginator, which we
    // re-run via reset() after hiding non-matching rows. Rows we hide use
    // display:none WITHOUT the .pg-hidden class, so the paginator excludes them
    // from its counts and pages (see admin/pagination.js _rows()).
    (function () {
        var table = document.querySelector('.ua-table');
        if (!table) return;
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr.ua-user'));
        var search = document.getElementById('ua-search');
        var countEl = document.getElementById('ua-count');
        var noRes = document.getElementById('ua-noresults');
        var t;

        function apply() {
            var q = (search.value || '').trim().toLowerCase();
            var visible = 0;
            var mine = 0;

            rows.forEach(function (tr) {
                var ok = !q || (tr.dataset.search || '').indexOf(q) !== -1;
                if (ok) {
                    tr.style.display = '';
                    // The founder's own installs render here but are not real users, so
                    // they are tallied separately rather than inflating the count.
                    if (tr.dataset.founder) { mine++; } else { visible++; }
                } else {
                    tr.style.display = 'none';
                    tr.classList.remove('pg-hidden');
                }
            });

            countEl.textContent = visible + (visible === 1 ? ' user' : ' users') +
                (mine ? ' + you' : '');
            // Founder rows count here: searching "me" matches only your own card, and
            // that is still a result even though it adds nothing to the user tally.
            noRes.style.display = (visible + mine) === 0 ? '' : 'none';
            if (table._paginator) table._paginator.reset();
        }

        search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 150); });
        apply();
    })();
    </script>
<?php endif; ?>
