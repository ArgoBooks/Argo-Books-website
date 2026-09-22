<?php
/**
 * Shared interpretation of a single telemetry event.
 *
 * Two things read the same telemetry files and must agree about what an event
 * means: user-activity-tab.php, which renders the on-screen timeline, and
 * download-user.php, which exports it as CSV. Keeping these functions here is
 * what stops the download from drifting away from the screen it came from.
 *
 * Pulled out of user-activity-tab.php, which cannot be required from anywhere:
 * it is a partial that renders HTML on include, and admin/.htaccess denies it
 * over HTTP.
 */

if (!function_exists('ua_unwrap_event')) {
    /**
     * Returns the event payload itself. The compact upload format nests the real
     * event one level down under 'event'; the flat format is already the payload.
     * Every reader has to normalize this before touching a field, or the flat
     * readers silently see nothing on a nested file.
     */
    function ua_unwrap_event(array $ev): array
    {
        if (isset($ev['event']) && is_array($ev['event'])) {
            return $ev['event'];
        }
        return $ev;
    }
}

if (!function_exists('ua_is_warning')) {
    /**
     * True when an Error-dataType event is really a warning: an expected, handled
     * condition rather than a failure. The app stamps this from its own LogLevel.
     * Events uploaded before the field existed have no severity and stay errors,
     * which is deliberate: we don't guess severity from the error code.
     */
    function ua_is_warning(array $ev): bool
    {
        return strcasecmp((string)($ev['severity'] ?? ''), 'Warning') === 0;
    }
}

if (!function_exists('ua_describe_event')) {
    /**
     * Returns [type, text] for a single telemetry event. $type drives the colour
     * on screen and lands in the CSV's event_type column.
     */
    function ua_describe_event(array $ev): array
    {
        switch ($ev['dataType'] ?? '') {
            case 'Session':
                if (($ev['action'] ?? '') === 'SessionStart') {
                    return ['session', 'Session started'];
                }
                $dur = (int)($ev['durationSeconds'] ?? 0);
                $human = $dur >= 60 ? round($dur / 60, 1) . ' min' : $dur . 's';
                // Only an explicit false is unclean. Ends uploaded before the flag existed
                // have no value at all and must not be shown as if they'd been force-quit.
                // The duration on an unclean end is accurate to the app's heartbeat interval,
                // not to the moment it died.
                if (array_key_exists('clean', $ev) && $ev['clean'] === false) {
                    return ['unclean', "Session ended unexpectedly ({$human})"];
                }
                return ['session', "Session ended ({$human})"];

            case 'FeatureUsage':
                $name = $ev['featureName'] ?? 'Unknown';
                // The app sends the enum name. This one lands among real user actions in the
                // timeline, where "SampleCompanyOpened" reads as a leaked identifier.
                if ($name === 'SampleCompanyOpened') $name = 'Opened sample company';
                // Without the context the timeline shows "ImportFailed" and no reason.
                $bits = [];
                if (!empty($ev['context']))    $bits[] = (string)$ev['context'];
                if (!empty($ev['durationMs'])) $bits[] = (int)$ev['durationMs'] . ' ms';
                $extra = $bits ? ' (' . implode(', ', $bits) . ')' : '';
                return ['feature', $name . $extra];

            case 'CompanyScale':
                // Only the non-zero counts: a file with three expenses and nothing else
                // should read as that, not as nine zeroes.
                $fields = [
                    'expenses' => 'expenses', 'revenues' => 'revenues', 'invoices' => 'invoices',
                    'payments' => 'payments', 'customers' => 'customers', 'suppliers' => 'suppliers',
                    'products' => 'products', 'categories' => 'categories', 'receipts' => 'receipts',
                    'employees' => 'employees', 'bankLines' => 'bank lines',
                ];
                $parts = [];
                foreach ($fields as $key => $label) {
                    if (!empty($ev[$key])) $parts[] = (int)$ev[$key] . ' ' . $label;
                }
                return ['scale', 'File contents: ' . ($parts ? implode(', ', $parts) : 'empty')];

            case 'PageView':
                $page = $ev['pageName'] ?? 'Unknown';
                $bits = [];
                // Both, because the gap between them is the tell: a long visit with little
                // active time is someone who walked away, not someone stuck on the screen.
                if (isset($ev['activeSeconds']))   $bits[] = (int)$ev['activeSeconds'] . 's active';
                if (isset($ev['durationSeconds'])) $bits[] = (int)$ev['durationSeconds'] . 's open';
                $suffix = $bits ? ' (' . implode(', ', $bits) . ')' : '';
                return ['page', "Page: {$page}{$suffix}"];

            case 'Export':
                $type = $ev['exportType'] ?? 'Unknown';
                $bits = [];
                if (!empty($ev['fileSize']))   $bits[] = number_format((int)$ev['fileSize']) . ' bytes';
                if (!empty($ev['durationMs'])) $bits[] = (int)$ev['durationMs'] . ' ms';
                $suffix = $bits ? ' (' . implode(', ', $bits) . ')' : '';
                return ['export', "Export: {$type}{$suffix}"];

            case 'ApiUsage':
                $api = $ev['apiName'] ?? 'Unknown';
                $ok  = array_key_exists('success', $ev) ? ($ev['success'] ? 'ok' : 'FAILED') : '';
                $bits = [];
                if ($ok !== '')                $bits[] = $ok;
                if (!empty($ev['durationMs'])) $bits[] = (int)$ev['durationMs'] . ' ms';
                $suffix = $bits ? ' (' . implode(', ', $bits) . ')' : '';
                return ['api', "API: {$api}{$suffix}"];

            case 'CompanyProfile':
                $name = $ev['companyName'] ?? '(unnamed)';
                $bits = array_filter([
                    $ev['industry'] ?? null,
                    $ev['businessType'] ?? null,
                    $ev['country'] ?? null,
                    $ev['currency'] ?? null,
                    $ev['language'] ?? null,
                ]);
                $suffix = $bits ? ' — ' . implode(', ', $bits) : '';
                // The sample company is never reported: the app stopped sending its profile
                // because the details are the demo file's and identical on every install.
                return ['company', "Company: {$name}{$suffix}"];

            case 'Startup':
                $bits = [];
                // All four phases, so a slow launch says which stage was slow rather than
                // only that it was. The app records them and the filter keeps them.
                if (isset($ev['toFirstPaintMs']))      $bits[] = 'blank screen ' . (int)$ev['toFirstPaintMs'] . ' ms';
                if (isset($ev['toServicesReadyMs']))   $bits[] = 'services ' . (int)$ev['toServicesReadyMs'] . ' ms';
                if (isset($ev['toViewModelsReadyMs'])) $bits[] = 'view models ' . (int)$ev['toViewModelsReadyMs'] . ' ms';
                if (isset($ev['toReadyMs']))           $bits[] = 'ready ' . (int)$ev['toReadyMs'] . ' ms';
                if (array_key_exists('coldStart', $ev)) {
                    $bits[] = $ev['coldStart'] ? 'cold' : 'warm';
                }
                return ['startup', 'Launch' . ($bits ? ': ' . implode(', ', $bits) : '')];

            case 'Error':
                $parts = [];
                if (!empty($ev['errorCategory'])) $parts[] = $ev['errorCategory'];
                if (!empty($ev['errorCode']))     $parts[] = 'code=' . $ev['errorCode'];
                if (!empty($ev['methodName']))    $parts[] = $ev['methodName'] . '()';
                if (!empty($ev['sourceFile'])) {
                    $loc = $ev['sourceFile'];
                    if (!empty($ev['lineNumber'])) $loc .= ':' . $ev['lineNumber'];
                    $parts[] = $loc;
                }
                // The message carries the detail a warning needs to be actionable; error
                // rows already say enough via category + code + location.
                if (ua_is_warning($ev)) {
                    if (!empty($ev['message'])) $parts[] = $ev['message'];
                    return ['warning', 'Warning: ' . ($parts ? implode(' · ', $parts) : 'unknown')];
                }
                return ['error', 'Error: ' . ($parts ? implode(' · ', $parts) : 'unknown')];

            default:
                return ['other', $ev['dataType'] ?? 'Unknown'];
        }
    }
}

if (!function_exists('ua_merge_timeline')) {
    /**
     * Turns the raw event stream into the lines the timeline shows: folds the
     * "action" event and the "state" event the app sends for the same moment into
     * one row, and says what actually happened to the company on each remaining
     * profile row.
     *
     * Creating a company emits FeatureUsage/CompanyCreated followed by a
     * CompanyProfile a few seconds later, once the details are filled in. The
     * profile line already carries everything the feature line said plus the
     * industry, country and currency, so the feature line is dropped and its verb
     * folded into the profile line's wording.
     *
     * A CompanyProfile with no feature event beside it is the app re-reporting the
     * profile on the company-open path. The app records one profile per company per
     * session and re-records it when the name, country, currency or language change,
     * so a repeat with different details is an edit and a first sighting is an open.
     * Saying which is the whole point: a bare "Company: aslevo" row reads as nothing
     * having happened.
     *
     * Deliberately NOT applied to download-user.php. The wording of any single event
     * still comes from ua_describe_event(), which both share, so the two cannot drift
     * about what an event means. This is a presentation pass on top of that: the CSV
     * keeps one row per event because it is the raw record, and collapsing rows there
     * would blank the feature_name column on the row that survived.
     *
     * Also folds each EmptyStateShown into the page visit it describes.
     *
     * @param array $timeline Rows of ['ts' => int, 'type' => string, 'text' => string]
     * @return array The same rows, merged, original order preserved.
     */
    function ua_merge_timeline(array $timeline): array
    {
        // Wide enough for the gap between creating a company and finishing its
        // details, tight enough that a later profile edit in the same session is
        // not mistaken for the creation itself.
        $window = 60;

        $companyIdx = [];
        foreach ($timeline as $i => $row) {
            if (($row['type'] ?? '') === 'company') $companyIdx[$i] = true;
        }

        $drop    = [];
        $claimed = [];   // company row index => true once a CompanyCreated has taken it
        foreach ($timeline as $i => $row) {
            if (($row['type'] ?? '') !== 'feature') continue;
            if (($row['text'] ?? '') !== 'CompanyCreated') continue;

            $bestJ = null;
            $bestD = null;
            foreach (array_keys($companyIdx) as $j) {
                if (isset($claimed[$j])) continue;
                $d = abs((int)($timeline[$j]['ts'] ?? 0) - (int)($row['ts'] ?? 0));
                if ($d <= $window && ($bestD === null || $d < $bestD)) {
                    $bestD = $d;
                    $bestJ = $j;
                }
            }
            // No profile row to fold into: the feature name is a raw enum, so it is
            // rewritten in place rather than left on screen as "CompanyCreated".
            if ($bestJ === null) {
                $timeline[$i]['text'] = 'Created company';
                continue;
            }
            $claimed[$bestJ] = true;
            $drop[$i] = true;
        }

        // Oldest first, so "first sighting of this company" means what it says. The
        // rows arrive grouped by upload file, which is not chronological once a user
        // has more than one.
        $order = array_keys($companyIdx);
        usort($order, function ($a, $b) use ($timeline) {
            return ((int)($timeline[$a]['ts'] ?? 0) <=> (int)($timeline[$b]['ts'] ?? 0)) ?: ($a <=> $b);
        });

        $lastDetails = [];   // company name => the detail string last seen for it
        foreach ($order as $j) {
            $rest    = preg_replace('/^Company:\s*/', '', (string)($timeline[$j]['text'] ?? ''));
            $parts   = explode(' — ', $rest, 2);
            $name    = $parts[0];
            $details = $parts[1] ?? '';

            if (isset($claimed[$j])) {
                $timeline[$j]['text'] = 'Created company: ' . $rest;
            } elseif (array_key_exists($name, $lastDetails) && $lastDetails[$name] !== $details) {
                $timeline[$j]['text'] = 'Updated company details: ' . $rest;
            } else {
                // Also covers a language-only change, which re-fires the profile without
                // altering anything shown here. Rare, and reading it as another open is
                // the harmless way to be wrong.
                $timeline[$j]['text'] = 'Opened company: ' . $rest;
            }
            $lastDetails[$name] = $details;
        }

        // An empty state describes one page visit, so it belongs in that visit's row. The app
        // reports it on open and the visit on close, so the match is the next row of that name.
        $pagesByName = [];
        foreach ($timeline as $i => $row) {
            if (($row['type'] ?? '') !== 'page') continue;
            if (preg_match('/^Page:\s*(\S+?)(?:\s*\(|$)/', (string)($row['text'] ?? ''), $m)) {
                $pagesByName[$m[1]][] = $i;
            }
        }
        foreach ($pagesByName as &$indices) {
            usort($indices, fn($a, $b) => ((int)$timeline[$a]['ts'] <=> (int)$timeline[$b]['ts']) ?: ($a <=> $b));
        }
        unset($indices);

        $emptyClaimed = [];
        foreach ($timeline as $i => $row) {
            if (($row['type'] ?? '') !== 'feature') continue;
            if (!preg_match('/^EmptyStateShown(?:\s*\((.+)\))?$/', (string)($row['text'] ?? ''), $m)) continue;

            $page = $m[1] ?? '';
            if ($page === '') {
                $timeline[$i]['text'] = 'Empty page shown';
                continue;
            }

            $ts = (int)($row['ts'] ?? 0);
            $target = null;
            foreach ($pagesByName[$page] ?? [] as $j) {
                if (isset($emptyClaimed[$j])) continue;
                if ((int)$timeline[$j]['ts'] >= $ts) { $target = $j; break; }
                $target ??= $j;
            }
            if ($target === null) {
                $timeline[$i]['text'] = "Empty page shown: {$page}";
                continue;
            }

            $text = (string)$timeline[$target]['text'];
            $timeline[$target]['text'] = str_ends_with($text, ')')
                ? substr($text, 0, -1) . ', empty)'
                : $text . ' (empty)';
            $emptyClaimed[$target] = true;
            $drop[$i] = true;
        }

        if (!$drop) return $timeline;
        return array_values(array_diff_key($timeline, $drop));
    }
}
