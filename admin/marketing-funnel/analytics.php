<?php
/**
 * Marketing-funnel analytics data layer.
 *
 * Powers the Plausible-style dashboard on the Funnel tab: the channel donut,
 * the referrer / campaign bar lists, and the map / country / region /
 * city bar lists. Every breakdown carries both a visits count and attributed
 * revenue so the UI can draw the twin blue (visits) + orange (revenue) bars.
 *
 * Attribution model:
 *   - Visits are distinct visitors with a `landing` event in the period,
 *     grouped by the dimension recorded on that landing row.
 *   - Revenue is first-touch: each paying subscription's revenue is credited to
 *     the referer host captured on that visitor's earliest landing,
 *     and to the source_code / country recorded on the premium_signup event.
 *
 * All queries are environment-scoped and use plain GROUP BY (no window
 * functions) so they run on any MySQL 8 / MariaDB 10 build.
 */

// Shared host -> source map; the AI / social host lists for channel
// classification are derived from it so new entries there update both source
// attribution and the channel donut together.
require_once __DIR__ . '/../../referral_sources.php';

/**
 * SQL fragment that extracts the bare host from the referer URL stored in
 * event_data.$.referer: strips scheme, path, query, port and a leading "www.".
 * Yields NULL for a missing/empty/self referer so those roll up to Direct/None.
 */
const FUNNEL_REF_HOST_SQL =
    "NULLIF(TRIM(LEADING 'www.' FROM LOWER(" .
    "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(" .
    "JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.referer'))" .
    ", '://', -1), '/', 1), '?', 1), ':', 1))), '')";

/** Label used for visitors/customers with no usable referer. */
const FUNNEL_DIRECT_LABEL = 'Direct / None';

/** Category label per key, matching admin/referral-links "By category". */
const FUNNEL_CATEGORY_LABELS = [
    'paid'      => 'Paid ads',
    'website'   => 'My website (guides & articles)',
    'invgen'    => 'Invoice generator',
    'outreach'  => 'Outreach',
    'social'    => 'Social media',
    'youtube'   => 'YouTube',
    'ai'        => 'AI assistants',
    'directory' => 'Directories',
    'other'     => 'Other',
];

/**
 * Roll a source_code up into a referral category from its naming-prefix, the
 * same convention admin/referral-links uses. Returns null for untracked
 * (direct/organic) traffic that has no source_code.
 */
function funnel_category_key(?string $source_code): ?string
{
    if ($source_code === null || $source_code === '') {
        return null;
    }
    $code = strtolower($source_code);
    if (strncmp($code, 'google-ads-', 11) === 0 || strncmp($code, 'ads-', 4) === 0
        || strncmp($code, 'paid-', 5) === 0 || strncmp($code, 'bing-ads-', 9) === 0) {
        return 'paid';
    }
    if (strncmp($code, 'guide-', 6) === 0 || $code === 'guides-hub') {
        return 'website';
    }
    if (strncmp($code, 'invgen-', 7) === 0) {
        return 'invgen';
    }
    if (strncmp($code, 'outreach-', 9) === 0) {
        return 'outreach';
    }
    if (strncmp($code, 'social-', 7) === 0) {
        return 'social';
    }
    if (strncmp($code, 'youtube-', 8) === 0) {
        return 'youtube';
    }
    if (strncmp($code, 'ai-', 3) === 0) {
        return 'ai';
    }
    if (strncmp($code, 'dir-', 4) === 0) {
        return 'directory';
    }
    return 'other';
}

/**
 * True when $host equals one of $domains or is a subdomain of it.
 */
function funnel_host_matches(?string $host, array $domains): bool
{
    if ($host === null || $host === '') {
        return false;
    }
    foreach ($domains as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) {
            return true;
        }
    }
    return false;
}

/**
 * Classify a referer host (+ its resolved source_code) into a marketing
 * channel: Direct, Organic search, Organic social, AI, Newsletter, Referral.
 */
function funnel_classify_channel(?string $host, ?string $source_code): string
{
    $host = ($host === null || $host === '' || $host === 'null') ? null : strtolower($host);

    if ($host === null) {
        // No referer. A source_code that looks like an email blast still counts
        // as Newsletter; otherwise it's genuinely direct/untracked.
        if ($source_code !== null && (str_contains($source_code, 'email') || str_contains($source_code, 'newsletter'))) {
            return 'Newsletter';
        }
        return 'Direct';
    }

    if (str_contains($host, 'newsletter')) {
        return 'Newsletter';
    }

    [$ai, $social] = funnel_channel_host_lists();

    // AI assistants first so gemini.google.com / copilot.microsoft.com don't
    // fall through to the search or referral buckets.
    if (funnel_host_matches($host, $ai)) {
        return 'AI';
    }

    // Search engines. Google has many country TLDs, so match it by pattern.
    if (preg_match('/(^|\.)google\.[a-z.]+$/', $host)) {
        return 'Organic search';
    }
    $search = ['bing.com', 'duckduckgo.com', 'yahoo.com', 'ecosia.org', 'yandex.com',
               'yandex.ru', 'baidu.com', 'brave.com', 'startpage.com', 'qwant.com', 'search.marginalia.nu'];
    if (funnel_host_matches($host, $search)) {
        return 'Organic search';
    }

    if (funnel_host_matches($host, $social)) {
        return 'Organic social';
    }

    return 'Referral';
}

/**
 * AI + social host lists for channel classification, derived from the shared
 * get_auto_referral_sources() map (ai-* / social-* code prefixes) so a channel
 * added there is classified correctly here without a second edit.
 *
 * Deliberate deviations from the map:
 *   - duckduckgo.com is excluded from AI: the map files it under ai-duckduckgo
 *     for source attribution, but as a referer it's classified Organic search.
 *   - Extra hosts that appear as referers but aren't in the map (no utm/ref
 *     traffic expected from them) are appended per list.
 *
 * @return array{0: list<string>, 1: list<string>} [ai_hosts, social_hosts]
 */
function funnel_channel_host_lists(): array
{
    static $lists = null;
    if ($lists !== null) {
        return $lists;
    }
    $ai = ['openai.com'];
    $social = ['pinterest.com', 'mastodon.social', 'threads.net'];
    foreach (get_auto_referral_sources() as $host => $m) {
        if ($host === 'duckduckgo.com') {
            continue; // stays Organic search as a referer (see docblock)
        }
        if (strncmp($m['code'], 'ai-', 3) === 0) {
            $ai[] = $host;
        } elseif (strncmp($m['code'], 'social-', 7) === 0) {
            $social[] = $host;
        }
    }
    return $lists = [$ai, $social];
}

/**
 * Build the shared WHERE clause + bound params for landing-event breakdowns.
 */
function funnel_landing_scope(?string $period_start, ?string $source_filter): array
{
    $where  = "event_type = 'landing' AND js_confirmed = 1 AND environment = ? AND visitor_id IS NOT NULL";
    $params = [current_environment()];
    if ($period_start !== null) {
        $where .= ' AND created_at >= ?';
        $params[] = $period_start;
    }
    if ($source_filter !== null && $source_filter !== '') {
        $where .= ' AND source_code = ?';
        $params[] = $source_filter;
    }
    return [$where, $params];
}

/**
 * Distinct-visitor counts grouped by an arbitrary landing-row dimension.
 * Returns rows of ['k' => <value|null>, 'visitors' => int].
 */
function funnel_visits_by(string $expr, ?string $period_start, ?string $source_filter): array
{
    global $pdo;
    [$where, $params] = funnel_landing_scope($period_start, $source_filter);
    $sql = "SELECT $expr AS k, COUNT(DISTINCT visitor_id) AS visitors
              FROM referral_events
             WHERE $where
             GROUP BY k";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Paying subscriptions in the period with total revenue, plus the visitor,
 * source_code and country recorded on their premium_signup event.
 * Returns rows of subscription_id, visitor_id, source_code, country_code, amount.
 */
function funnel_paying_rows(?string $period_start, ?string $source_filter): array
{
    global $pdo;

    $pay_where = "status = 'completed'";
    $params    = [];
    if ($period_start !== null) {
        $pay_where .= ' AND created_at >= ?';
        $params[]   = $period_start;
    }

    $sql = "
        SELECT re.subscription_id,
               MAX(re.visitor_id)   AS visitor_id,
               MAX(re.source_code)  AS source_code,
               MAX(re.country_code) AS country_code,
               pay.amount           AS amount
          FROM referral_events re
          JOIN (
                SELECT subscription_id, SUM(amount) AS amount
                  FROM premium_subscription_payments
                 WHERE $pay_where
                 GROUP BY subscription_id
               ) pay ON pay.subscription_id = re.subscription_id
         WHERE re.event_type = 'premium_signup'
           AND re.environment = ?
           AND re.subscription_id IS NOT NULL";
    $params[] = current_environment();
    if ($source_filter !== null && $source_filter !== '') {
        $sql .= ' AND re.source_code = ?';
        $params[] = $source_filter;
    }
    $sql .= ' GROUP BY re.subscription_id, pay.amount';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * First-touch referer host for the given visitor ids.
 * Returns [visitor_id => ['host' => ?string]].
 */
function funnel_first_touch(array $visitor_ids): array
{
    global $pdo;
    $visitor_ids = array_values(array_unique(array_filter($visitor_ids, fn($v) => $v !== null && $v !== '')));
    if (empty($visitor_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($visitor_ids), '?'));
    $expr = FUNNEL_REF_HOST_SQL;
    $sql = "SELECT visitor_id, $expr AS host, page_url
              FROM referral_events
             WHERE event_type = 'landing' AND js_confirmed = 1 AND environment = ?
               AND visitor_id IN ($placeholders)
             ORDER BY created_at ASC, id ASC";
    $params = array_merge([current_environment()], $visitor_ids);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    while ($row = $stmt->fetch()) {
        $vid = $row['visitor_id'];
        if (!isset($out[$vid])) { // first row per visitor wins (earliest)
            $out[$vid] = [
                'host' => $row['host'],
                'page' => funnel_normalize_page($row['page_url']),
            ];
        }
    }
    return $out;
}

/**
 * Normalises a stored page_url into a bare path, so first-touch pages key the
 * same way the statistics-derived entry pages do.
 *
 * page_url is written from REQUEST_URI, so it already excludes the host but may
 * carry a query string (?source=..., utm params). The statistics entry-page
 * query strips the query string with SUBSTRING_INDEX, so this has to match or
 * the two datasets would never join.
 */
function funnel_normalize_page(?string $url): string
{
    if ($url === null || $url === '') {
        return '/';
    }
    $path = explode('?', $url, 2)[0];
    return $path === '' ? '/' : $path;
}

/**
 * First-touch entry pages, counted as distinct visitors, from the referral
 * funnel rather than the site-wide page_view stream.
 *
 * The `statistics` table cannot be used for this. track_page_view() records a
 * page *name* ("invgen_tool", "paid_lp_contractors"), not a URL, while
 * referral_events.page_url holds REQUEST_URI. The two never join except by
 * accident on the homepage, so attributing installs and payments to statistics
 * rows silently produces zeroes. Sourcing visits from the same table as the
 * attribution keeps all the columns on one definition of a visitor.
 */
function funnel_entry_page_visits(?string $period_start, ?string $source_filter): array
{
    global $pdo;

    $inner_where = "event_type = 'landing' AND js_confirmed = 1 AND environment = ?
                    AND visitor_id IS NOT NULL AND visitor_id <> ''";
    $params = [current_environment()];

    if ($period_start !== null) {
        $inner_where .= ' AND created_at >= ?';
        $params[] = $period_start;
    }
    if ($source_filter !== null && $source_filter !== '') {
        $inner_where .= ' AND source_code = ?';
        $params[] = $source_filter;
    }

    // MIN(id) picks each visitor's earliest landing, matching the first-touch
    // rule used for revenue. Written as a join rather than a window function so
    // it runs on MySQL 5.7 as well as 8.
    $sql = "SELECT SUBSTRING_INDEX(r.page_url, '?', 1) AS page, COUNT(*) AS visitors
              FROM referral_events r
              JOIN (
                    SELECT MIN(id) AS first_id
                      FROM referral_events
                     WHERE $inner_where
                     GROUP BY visitor_id
                   ) f ON f.first_id = r.id
             GROUP BY page
             ORDER BY visitors DESC, page ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[funnel_normalize_page($r['page'])] = (int)$r['visitors'];
    }
    return $out;
}

/**
 * Distinct visitor ids that reached a given funnel event in the period.
 *
 * Used to attribute installs and payments back to the referrer and entry page
 * that first brought the visitor in. app_first_run rows can carry a NULL
 * visitor_id when the install could not be matched to a web visit, and those
 * are excluded because there is nothing to attribute them to.
 */
function funnel_visitors_with_event(string $event_type, ?string $period_start, ?string $source_filter): array
{
    global $pdo;

    $sql = "SELECT DISTINCT visitor_id
              FROM referral_events
             WHERE event_type = ?
               AND environment = ?
               AND visitor_id IS NOT NULL
               AND visitor_id <> ''";
    $params = [$event_type, current_environment()];

    if ($period_start !== null) {
        $sql .= ' AND created_at >= ?';
        $params[] = $period_start;
    }
    if ($source_filter !== null && $source_filter !== '') {
        $sql .= ' AND source_code = ?';
        $params[] = $source_filter;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_column($stmt->fetchAll(), 'visitor_id');
}

/**
 * Merge a visits map and a revenue map keyed by the same dimension value into a
 * sorted list of ['label','key','visits','revenue'], visits desc then revenue.
 */
function funnel_merge_breakdown(
    array $visits_map,
    array $revenue_map,
    callable $labeler,
    array $free_map = [],
    array $paid_map = []
): array {
    $keys = array_unique(array_merge(
        array_keys($visits_map),
        array_keys($revenue_map),
        array_keys($free_map),
        array_keys($paid_map)
    ));
    $rows = [];
    foreach ($keys as $k) {
        $rows[] = [
            'key'     => $k,
            'label'   => $labeler((string)$k),
            'visits'  => (int)($visits_map[$k] ?? 0),
            'revenue' => (float)($revenue_map[$k] ?? 0),
            'free'    => (int)($free_map[$k] ?? 0),
            'paid'    => (int)($paid_map[$k] ?? 0),
        ];
    }
    usort($rows, function ($a, $b) {
        if ($b['visits'] !== $a['visits']) return $b['visits'] - $a['visits'];
        return $b['revenue'] <=> $a['revenue'];
    });
    return $rows;
}

/**
 * Per-stage top-N breakdown of an event dimension (country_code or source_code)
 * across every funnel stage, for the funnel hover tooltip.
 *
 * Returns [event_type => ['total' => int, 'rows' => [['label','visitors','pct'], ...]]].
 *
 * $column is pasted into the query, so it may be an expression such as
 * FUNNEL_OS_EXPR as well as a plain column name. It never carries user input.
 */
const FUNNEL_OS_EXPR = "CASE
        WHEN JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.platform')) = 'win'   THEN 'Windows'
        WHEN JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.platform')) = 'mac'   THEN 'macOS'
        WHEN JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.platform')) = 'linux' THEN 'Linux'
        WHEN user_agent IS NULL OR user_agent = '' THEN 'Unknown'
        WHEN user_agent LIKE '%Windows NT%'                     THEN 'Windows'
        WHEN user_agent LIKE '%Android%'                        THEN 'Android'
        WHEN user_agent REGEXP 'iPhone|iPad|iPod'               THEN 'iOS'
        WHEN user_agent REGEXP 'Macintosh|Mac OS X'             THEN 'macOS'
        WHEN user_agent REGEXP 'Linux|X11|Ubuntu|CrOS'          THEN 'Linux'
        ELSE 'Other'
    END";

function funnel_stage_dimension(string $column, ?string $period_start, ?string $source_filter, int $top = 3): array
{
    global $pdo;

    $where  = 'environment = ? AND js_confirmed = 1';
    $params = [current_environment()];
    if ($period_start !== null) {
        $where .= ' AND created_at >= ?';
        $params[] = $period_start;
    }
    if ($source_filter !== null && $source_filter !== '') {
        $where .= ' AND source_code = ?';
        $params[] = $source_filter;
    }

    $sql = "SELECT event_type, $column AS k, COUNT(DISTINCT visitor_id) AS visitors
              FROM referral_events
             WHERE $where
             GROUP BY event_type, k";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $by_stage = [];
    while ($row = $stmt->fetch()) {
        $by_stage[$row['event_type']][] = ['k' => $row['k'], 'visitors' => (int)$row['visitors']];
    }

    $out = [];
    foreach ($by_stage as $stage => $rows) {
        usort($rows, fn($a, $b) => $b['visitors'] - $a['visitors']);
        $total = array_sum(array_map(fn($r) => $r['visitors'], $rows));
        $picked = [];
        foreach (array_slice($rows, 0, $top) as $r) {
            $picked[] = [
                'k'        => $r['k'],
                'visitors' => $r['visitors'],
                'pct'      => $total > 0 ? round(($r['visitors'] / $total) * 100) : 0,
            ];
        }
        $out[$stage] = ['total' => $total, 'rows' => $picked];
    }
    return $out;
}

/**
 * Orchestrator: assemble every breakdown the Funnel dashboard needs.
 *
 * @return array{
 *   channels: list<array>, referrers: list<array>, campaigns: list<array>,
 *   countries: list<array>, regions: list<array>,
 *   cities: list<array>, total_visits:int, total_revenue:float,
 *   stage_countries: array, stage_sources: array
 * }
 */
function build_funnel_analytics(?string $period_start, ?string $source_filter, array $referral_links): array
{
    // ---- Visits (distinct visitors) per dimension ----
    $host_visits    = funnel_visits_by(FUNNEL_REF_HOST_SQL, $period_start, $source_filter);
    $source_visits  = funnel_visits_by('source_code',  $period_start, $source_filter);
    $country_visits = funnel_visits_by('country_code',  $period_start, $source_filter);
    $region_visits  = funnel_visits_by('region',        $period_start, $source_filter);
    $city_visits    = funnel_visits_by('city',          $period_start, $source_filter);

    // Referrer + channel visit maps.
    $ref_visits_map = [];
    $channel_visits = [];
    foreach ($host_visits as $r) {
        $host  = $r['k'];
        $label = ($host === null || $host === '' || $host === 'null') ? FUNNEL_DIRECT_LABEL : $host;
        $ref_visits_map[$label] = ($ref_visits_map[$label] ?? 0) + (int)$r['visitors'];
        $ch = funnel_classify_channel($host, null);
        $channel_visits[$ch] = ($channel_visits[$ch] ?? 0) + (int)$r['visitors'];
    }

    $mapize = function (array $rows, string $null_label): array {
        $m = [];
        foreach ($rows as $r) {
            $k = ($r['k'] === null || $r['k'] === '') ? $null_label : $r['k'];
            $m[$k] = ($m[$k] ?? 0) + (int)$r['visitors'];
        }
        return $m;
    };
    $country_visits_map = $mapize($country_visits, 'Unknown');
    $region_visits_map  = $mapize($region_visits,  'Unknown');
    $city_visits_map    = $mapize($city_visits,    'Unknown');

    // ---- Revenue (first-touch) per dimension ----
    $paying = funnel_paying_rows($period_start, $source_filter);
    $visitor_ids = array_map(fn($r) => $r['visitor_id'], $paying);
    $first_touch = funnel_first_touch($visitor_ids);

    $rev_by_ref = $rev_by_channel = $rev_by_category = [];
    $rev_by_country = [];
    $total_revenue = 0.0;
    foreach ($paying as $p) {
        $amt = (float)$p['amount'];
        $total_revenue += $amt;

        // Category rollup: only tracked sources contribute (direct excluded).
        $cat = funnel_category_key($p['source_code']);
        if ($cat !== null) {
            $rev_by_category[$cat] = ($rev_by_category[$cat] ?? 0) + $amt;
        }

        $cc = ($p['country_code'] === null || $p['country_code'] === '') ? 'Unknown' : $p['country_code'];
        $rev_by_country[$cc] = ($rev_by_country[$cc] ?? 0) + $amt;

        $ft   = $first_touch[$p['visitor_id']] ?? ['host' => null];
        $host = $ft['host'];
        $ref_label = ($host === null || $host === '' || $host === 'null') ? FUNNEL_DIRECT_LABEL : $host;
        $rev_by_ref[$ref_label] = ($rev_by_ref[$ref_label] ?? 0) + $amt;

        $ch = funnel_classify_channel($host, $p['source_code']);
        $rev_by_channel[$ch] = ($rev_by_channel[$ch] ?? 0) + $amt;

    }

    // ---- Free / paid user counts (first-touch) per referrer and entry page ----
    // Revenue alone hides which pages bring people who convert: a page can pull
    // plenty of visits and no installs, or few visits and several. Counting the
    // installs and payments a page introduced answers "which pages earn", which
    // visit counts on their own cannot.
    $free_by_ref = $paid_by_ref = [];
    $free_by_page = $paid_by_page = [];

    $ref_label_of = fn(?string $h) =>
        ($h === null || $h === '' || $h === 'null') ? FUNNEL_DIRECT_LABEL : $h;

    $install_visitors = funnel_visitors_with_event('app_first_run', $period_start, $source_filter);
    $install_touch    = funnel_first_touch($install_visitors);
    foreach ($install_touch as $ft) {
        $label = $ref_label_of($ft['host']);
        $free_by_ref[$label] = ($free_by_ref[$label] ?? 0) + 1;
        $page = $ft['page'];
        $free_by_page[$page] = ($free_by_page[$page] ?? 0) + 1;
    }

    // Paying visitors reuse the first-touch map already built above.
    foreach ($paying as $p) {
        $ft = $first_touch[$p['visitor_id']] ?? null;
        if ($ft === null) {
            continue; // no landing event recorded, nothing to attribute to
        }
        $label = $ref_label_of($ft['host']);
        $paid_by_ref[$label] = ($paid_by_ref[$label] ?? 0) + 1;
        $page = $ft['page'];
        $paid_by_page[$page] = ($paid_by_page[$page] ?? 0) + 1;
    }

    // ---- Category rollup (matches referral-links "By category") ----
    // Bucket tracked-source visits by category; direct/untracked is excluded.
    $category_visits = [];
    foreach ($source_visits as $r) {
        $cat = funnel_category_key($r['k']);
        if ($cat !== null) {
            $category_visits[$cat] = ($category_visits[$cat] ?? 0) + (int)$r['visitors'];
        }
    }

    // ---- Labelers ----
    $identity = fn(string $k) => $k;
    $category_labeler = fn(string $k) => FUNNEL_CATEGORY_LABELS[$k] ?? ucfirst($k);
    // Country keys stay ISO-2 codes (flag + map need them); only the label
    // becomes the readable country name.
    $country_labeler = fn(string $k) => $k === 'Unknown' ? 'Unknown' : (country_name($k) ?: $k);

    // ---- Merge into sorted breakdown lists ----
    $channels  = funnel_merge_breakdown($channel_visits,     $rev_by_channel, $identity);
    $referrers = funnel_merge_breakdown($ref_visits_map,     $rev_by_ref,     $identity, $free_by_ref, $paid_by_ref);
    $campaigns = funnel_merge_breakdown($category_visits,    $rev_by_category, $category_labeler);
    $countries = funnel_merge_breakdown($country_visits_map, $rev_by_country, $country_labeler);
    $regions   = funnel_merge_breakdown($region_visits_map,  [],              $identity);
    $cities    = funnel_merge_breakdown($city_visits_map,    [],              $identity);

    $total_visits = array_sum(array_map(fn($r) => $r['visits'], $referrers));

    return [
        'channels'        => $channels,
        'referrers'       => $referrers,
        'campaigns'       => $campaigns,
        'countries'       => $countries,
        'regions'         => $regions,
        'cities'          => $cities,
        'total_visits'    => $total_visits,
        'total_revenue'   => $total_revenue,
        // Entry pages, fully funnel-sourced: visits, installs and payments all
        // count distinct visitors whose first landing was on that path.
        'entry_pages'     => funnel_merge_breakdown(
            funnel_entry_page_visits($period_start, $source_filter),
            [],
            fn(string $k) => $k,
            $free_by_page,
            $paid_by_page
        ),
        'stage_countries' => funnel_stage_dimension('country_code', $period_start, $source_filter),
        'stage_sources'   => funnel_stage_dimension('source_code',  $period_start, $source_filter),
        'stage_os'        => funnel_stage_dimension(FUNNEL_OS_EXPR,  $period_start, $source_filter),
    ];
}

/**
 * Page breakdowns for the "Pages" analytics card: most-viewed pages, entry
 * pages, and exit pages. Source is the site-wide page_view stream in the
 * `statistics` table (event_type = 'page_view', event_data = the page).
 *
 * Notes / caveats, so the numbers are read honestly:
 *   - track_event() records each (page, ip) at most once per calendar day, so a
 *     "view" here is a distinct page-per-visitor-per-day, matching how the rest
 *     of the dashboard counts visitors rather than raw hits.
 *   - Entry / exit are derived per (ip_address, day) from insert order: the
 *     first page_view row (MIN(id)) is the entry, the last (MAX(id)) is the
 *     exit. A single-page day counts as both (a bounce). Because a page is only
 *     recorded on its FIRST view that day, "exit" is the last NEW page the
 *     visitor reached, not necessarily where they truly left if they backtracked
 *     to an already-seen page. It's an approximation at this granularity.
 *   - `statistics` has no environment or source columns, so these lists are
 *     whole-site and ignore the source pill; only the period window applies.
 *   - The query string is stripped (SUBSTRING_INDEX on '?') so the homepage,
 *     which stores the raw REQUEST_URI, collapses to one row instead of one per
 *     query-string variant.
 *
 * @return array{popular: list<array>, entry: list<array>, exit: list<array>}
 */
function funnel_page_breakdowns(?string $period_start): array
{
    global $pdo;

    $period_clause = $period_start !== null ? ' AND created_at >= ?' : '';
    $params = $period_start !== null ? [$period_start] : [];
    $base_where = "event_type = 'page_view' AND event_data IS NOT NULL AND event_data <> ''" . $period_clause;

    // Most popular: straight count of page views per page.
    $popular_sql = "SELECT SUBSTRING_INDEX(event_data, '?', 1) AS page, COUNT(*) AS visits
                      FROM statistics
                     WHERE $base_where
                     GROUP BY page
                     ORDER BY visits DESC, page ASC";

    // Entry / exit: the first / last page_view row per visitor per day, keyed by
    // MIN(id) / MAX(id) so same-second ties resolve deterministically by insert
    // order. Grouping the picked rows by page gives the entry/exit distribution.
    $edge_sql = fn(string $agg) =>
        "SELECT SUBSTRING_INDEX(s.event_data, '?', 1) AS page, COUNT(*) AS visits
           FROM statistics s
           JOIN (
                SELECT $agg(id) AS eid
                  FROM statistics
                 WHERE $base_where
                 GROUP BY ip_address, DATE(created_at)
           ) e ON e.eid = s.id
          GROUP BY page
          ORDER BY visits DESC, page ASC";

    $run = function (string $sql, array $sql_params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sql_params);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $page = (string)$r['page'];
            $rows[] = [
                'key'     => $page,
                'label'   => $page,
                'visits'  => (int)$r['visits'],
                'revenue' => 0.0, // funnel_render_bar_list reads this on every row
            ];
        }
        return $rows;
    };

    return [
        'popular' => $run($popular_sql,       $params),
        'entry'   => $run($edge_sql('MIN'),   $params),
        'exit'    => $run($edge_sql('MAX'),   $params),
    ];
}
