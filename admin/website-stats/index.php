<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../country_names.php';
require_once __DIR__ . '/../date-range.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Set page variables for the header
$page_title = "Website Statistics";
$page_description = "View comprehensive analytics, user statistics, and performance metrics";

// --- Automatic bucketing. ------------------------------------------------------------------------

/**
 * Earliest record on this page, used as the floor for the "All Time" preset.
 */
function website_stats_all_time_start()
{
    global $pdo;

    $row = $pdo->query("
        SELECT LEAST(
            COALESCE((SELECT MIN(created_at) FROM statistics), NOW()),
            COALESCE((SELECT MIN(created_at) FROM community_users), NOW())
        ) AS earliest")->fetch();

    return !empty($row['earliest']) ? new DateTime($row['earliest']) : null;
}

/** Choose the bucket granularity from the range length (mirrors GetTimeBucket). */
function pick_time_bucket(DateTime $start, DateTime $end)
{
    $days = ($end->getTimestamp() - $start->getTimestamp()) / 86400;
    if ($days < 90)  return 'day';
    if ($days < 365) return 'week';
    return 'month';
}

/** SQL expression returning the canonical bucket key (a DATE string) for a row. */
function bucket_key_sql($bucket, $col = 'created_at')
{
    switch ($bucket) {
        case 'week':  // Sunday-start week (DAYOFWEEK: 1=Sun..7=Sat)
            return "DATE_SUB(DATE($col), INTERVAL (DAYOFWEEK($col) - 1) DAY)";
        case 'month':
            return "DATE_FORMAT($col, '%Y-%m-01')";
        case 'day':
        default:
            return "DATE($col)";
    }
}

/** Snap a DateTime down to the start of its bucket. */
function bucket_floor(DateTime $d, $bucket)
{
    $f = (clone $d)->setTime(0, 0, 0);
    if ($bucket === 'week') {
        $f->modify('-' . (int)$f->format('w') . ' days'); // w: 0=Sun..6=Sat
    } elseif ($bucket === 'month') {
        $f->modify('first day of this month');
    }
    return $f;
}

/** Ordered list of bucket keys ('Y-m-d') spanning [start, end]. */
function generate_buckets(DateTime $start, DateTime $end, $bucket)
{
    $cursor = bucket_floor($start, $bucket);
    $last   = bucket_floor($end, $bucket);
    $step   = $bucket === 'day' ? '+1 day' : ($bucket === 'week' ? '+7 days' : '+1 month');

    $keys = [];
    $guard = 0;
    while ($cursor <= $last && $guard++ < 5000) {
        $keys[] = $cursor->format('Y-m-d');
        $cursor = (clone $cursor)->modify($step);
    }
    return $keys;
}

/** Human label for a bucket key (mirrors GetBucketLabel). */
function bucket_label($key, $bucket)
{
    $d = DateTime::createFromFormat('Y-m-d', $key);
    if (!$d) return $key;
    return $bucket === 'month' ? $d->format('M Y') : $d->format('M d');
}

/** COUNT(*) per bucket over a date range, as [bucket_key => count]. */
function counts_by_bucket($table, DateTime $start, DateTime $end, $bucket, $where_extra = '')
{
    global $pdo;
    $key   = bucket_key_sql($bucket);
    $where = "created_at BETWEEN :start AND :end" . ($where_extra ? " AND $where_extra" : '');
    $sql   = "SELECT $key AS bkey, COUNT(*) AS count
              FROM $table
              WHERE $where
              GROUP BY bkey";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start' => $start->format('Y-m-d H:i:s'),
        ':end'   => $end->format('Y-m-d H:i:s'),
    ]);

    $map = [];
    while ($row = $stmt->fetch()) {
        $map[$row['bkey']] = (int)$row['count'];
    }
    return $map;
}

/**
 * Bounce rate = % of sessions with exactly one page view.
 * A session is page views from the same (ip, user_agent) with no gap > 30 min.
 * Both helpers rely on a shared session-building CTE.
 */
function bounce_sessions_cte()
{
    return "
        WITH pv AS (
            SELECT
                ip_address,
                user_agent,
                created_at,
                TIMESTAMPDIFF(MINUTE,
                    LAG(created_at) OVER (PARTITION BY ip_address, user_agent ORDER BY created_at),
                    created_at
                ) AS gap_min
            FROM statistics
            WHERE event_type = 'page_view'
              AND ip_address IS NOT NULL
        ),
        session_marks AS (
            SELECT
                ip_address,
                user_agent,
                created_at,
                SUM(CASE WHEN gap_min IS NULL OR gap_min > 30 THEN 1 ELSE 0 END)
                    OVER (PARTITION BY ip_address, user_agent ORDER BY created_at) AS session_id
            FROM pv
        ),
        sessions AS (
            SELECT
                ip_address,
                user_agent,
                session_id,
                MIN(created_at) AS session_start,
                MAX(created_at) AS session_end,
                COUNT(*) AS pageview_count
            FROM session_marks
            GROUP BY ip_address, user_agent, session_id
        )";
}

function get_bounce_rate_overall(DateTime $start = null, DateTime $end = null)
{
    global $pdo;
    $cte = bounce_sessions_cte();

    $where  = '';
    $params = [];
    if ($start && $end) {
        $where  = "WHERE session_start BETWEEN :start AND :end";
        $params = [
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        ];
    }

    $query = "
        $cte
        SELECT
            COUNT(*) AS total_sessions,
            SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) AS bounced_sessions
        FROM sessions
        $where";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch();
    $total = (int)($row['total_sessions'] ?? 0);
    $bounced = (int)($row['bounced_sessions'] ?? 0);

    return [
        'total_sessions' => $total,
        'bounced_sessions' => $bounced,
        'bounce_rate' => $total > 0 ? round($bounced / $total * 100, 1) : 0.0,
    ];
}

/**
 * Headline visitor metrics for the stat cards, scoped to [start, end] except
 * "live", which is always the last 5 minutes:
 *  - live visitors: distinct (ip, user_agent) with a page view in the last 5 minutes
 *  - unique visitors and total page views: page_view events in range
 *  - average session length in seconds (bounce sessions count as 0)
 */
function get_visitor_overview(DateTime $start, DateTime $end)
{
    global $pdo;

    $params = [
        ':start' => $start->format('Y-m-d H:i:s'),
        ':end'   => $end->format('Y-m-d H:i:s'),
    ];

    $live = (int) $pdo->query("
        SELECT COUNT(DISTINCT ip_address, user_agent) AS c
        FROM statistics
        WHERE event_type = 'page_view'
          AND ip_address IS NOT NULL
          AND created_at >= NOW() - INTERVAL 5 MINUTE
    ")->fetch()['c'];

    $totals_stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT ip_address) AS unique_visitors,
            COUNT(*) AS total_pageviews
        FROM statistics
        WHERE event_type = 'page_view'
          AND created_at BETWEEN :start AND :end
    ");
    $totals_stmt->execute($params);
    $totals = $totals_stmt->fetch();

    $cte = bounce_sessions_cte();
    $avg_stmt = $pdo->prepare("
        $cte
        SELECT AVG(TIMESTAMPDIFF(SECOND, session_start, session_end)) AS avg_seconds
        FROM sessions
        WHERE session_start BETWEEN :start AND :end
    ");
    $avg_stmt->execute($params);
    $avg = $avg_stmt->fetch();

    return [
        'live'             => $live,
        'unique_visitors'  => (int) ($totals['unique_visitors'] ?? 0),
        'total_pageviews'  => (int) ($totals['total_pageviews'] ?? 0),
        'avg_session_secs' => (int) round((float) ($avg['avg_seconds'] ?? 0)),
    ];
}

/** Bounce rate + session count per bucket over a date range, as [bucket_key => [...]]. */
function bounce_by_bucket(DateTime $start, DateTime $end, $bucket)
{
    global $pdo;
    $cte = bounce_sessions_cte();
    $key = bucket_key_sql($bucket, 'session_start');

    $query = "
        $cte
        SELECT
            $key AS bkey,
            COUNT(*) AS total_sessions,
            ROUND(SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS bounce_rate
        FROM sessions
        WHERE session_start BETWEEN :start AND :end
        GROUP BY bkey";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':start' => $start->format('Y-m-d H:i:s'),
        ':end'   => $end->format('Y-m-d H:i:s'),
    ]);

    $map = [];
    while ($row = $stmt->fetch()) {
        $map[$row['bkey']] = [
            'rate'     => (float)$row['bounce_rate'],
            'sessions' => (int)$row['total_sessions'],
        ];
    }
    return $map;
}

function get_community_post_views()
{
    global $pdo;
    $query = "
        SELECT
            SUM(views) as total_views,
            AVG(views) as avg_views_per_post,
            MAX(views) as most_viewed
        FROM community_posts";

    $stmt = $pdo->query($query);
    $data = $stmt->fetch();

    return $data;
}

// Function to get community activity by post type
function get_community_post_types()
{
    global $pdo;
    $query = "
        SELECT
            post_type,
            COUNT(*) as count,
            SUM(views) as total_views
        FROM community_posts
        GROUP BY post_type";

    $stmt = $pdo->query($query);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

// Function to get geographic distribution of users by page views
function get_user_countries($limit = 10)
{
    global $pdo;
    $query = "
        SELECT
            country_code,
            COUNT(DISTINCT ip_address) as count
        FROM statistics
        WHERE country_code IS NOT NULL AND country_code != ''
        GROUP BY country_code
        ORDER BY count DESC
        LIMIT ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

function get_downloads_by_country($limit = 10)
{
    global $pdo;
    $query = "
        SELECT
            country_code,
            COUNT(*) as download_count
        FROM statistics
        WHERE event_type IN ('download_win', 'download_mac', 'download_linux', 'download_avalonia') AND country_code IS NOT NULL AND country_code != ''
        GROUP BY country_code
        ORDER BY download_count DESC
        LIMIT ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

// Function to get browser/platform statistics
function get_user_agents()
{
    global $pdo;
    $query = "
        SELECT
            CASE
                WHEN user_agent LIKE '%Chrome%' THEN 'Chrome'
                WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                WHEN user_agent LIKE '%Safari%' THEN 'Safari'
                WHEN user_agent LIKE '%Edge%' THEN 'Edge'
                WHEN user_agent LIKE '%MSIE%' OR user_agent LIKE '%Trident%' THEN 'Internet Explorer'
                ELSE 'Other'
            END as browser,
            COUNT(*) as count
        FROM statistics
        WHERE user_agent IS NOT NULL
        GROUP BY browser
        ORDER BY count DESC";

    $stmt = $pdo->query($query);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

// Function to get conversion rate data
function get_conversion_data()
{
    global $pdo;

    // Get total downloads (from statistics table)
    $download_query = "SELECT COUNT(*) as count FROM statistics WHERE event_type IN ('download_win', 'download_mac', 'download_linux', 'download_avalonia')";
    $download_stmt = $pdo->query($download_query);
    $downloads = $download_stmt->fetch()['count'];

    // Get total registrations
    $reg_query = "SELECT COUNT(*) as count FROM community_users";
    $reg_stmt = $pdo->query($reg_query);
    $registrations = $reg_stmt->fetch()['count'];

    return [
        'downloads' => $downloads,
        'registrations' => $registrations,
    ];
}

// ---- Resolve the selected date range and derive the chart bucket ----
$presets = date_range_presets();
$selected_range = selected_date_range_preset();
$custom_start_raw = isset($_GET['start']) ? $_GET['start'] : null;
$custom_end_raw   = isset($_GET['end']) ? $_GET['end'] : null;

$all_time_start = $selected_range === 'All Time' ? website_stats_all_time_start() : null;
$range       = resolve_date_range($selected_range, $custom_start_raw, $custom_end_raw, $all_time_start);
$range_start = $range['start'];
$range_end   = $range['end'];
$bucket      = pick_time_bucket($range_start, $range_end);

// Continuous bucket axis across the whole range, so empty buckets still render.
$bucket_keys  = generate_buckets($range_start, $range_end, $bucket);
$chart_labels = array_map(function ($k) use ($bucket) {
    return bucket_label($k, $bucket);
}, $bucket_keys);

// Per-dataset counts, keyed by bucket, then aligned to the axis.
$downloads_map = counts_by_bucket('statistics', $range_start, $range_end, $bucket,
    "event_type IN ('download_win', 'download_mac', 'download_linux', 'download_avalonia')");
$registrations_map = counts_by_bucket('community_users', $range_start, $range_end, $bucket);
$page_views_map    = counts_by_bucket('statistics', $range_start, $range_end, $bucket, "event_type = 'page_view'");
$bounce_map        = bounce_by_bucket($range_start, $range_end, $bucket);

$downloads_data       = [];
$registrations_data   = [];
$page_views_data      = [];
$bounce_rate_data     = [];
$bounce_sessions_data = [];
foreach ($bucket_keys as $k) {
    $downloads_data[]     = $downloads_map[$k] ?? 0;
    $registrations_data[] = $registrations_map[$k] ?? 0;
    $page_views_data[]    = $page_views_map[$k] ?? 0;
    // Null bounce where no sessions in that bucket so the line shows a gap, not a misleading 0%.
    if (isset($bounce_map[$k])) {
        $bounce_rate_data[]     = $bounce_map[$k]['rate'];
        $bounce_sessions_data[] = $bounce_map[$k]['sessions'];
    } else {
        $bounce_rate_data[]     = null;
        $bounce_sessions_data[] = 0;
    }
}

// Non-time-series datasets (unaffected by the date range).
$post_views          = get_community_post_views();
$post_types          = get_community_post_types();
$user_countries      = get_user_countries();
$downloads_by_country = get_downloads_by_country();
$user_agents         = get_user_agents();
$conversion_data     = get_conversion_data();

// Range-scoped headline metrics for the stat cards.
$bounce_overall   = get_bounce_rate_overall($range_start, $range_end);
$visitor_overview = get_visitor_overview($range_start, $range_end);

// Pretty range string for the toolbar pill (e.g. "Sep 14, 2025 – Oct 14, 2025").
$range_display = format_date_range($range_start, $range_end);

// Format post views numbers
$total_post_views = isset($post_views['total_views']) ? number_format($post_views['total_views']) : 0;
$avg_post_views = isset($post_views['avg_views_per_post']) ? round($post_views['avg_views_per_post'], 1) : 0;
$most_viewed = isset($post_views['most_viewed']) ? number_format($post_views['most_viewed']) : 0;

// Prepare post type data for charts
$post_type_labels = [];
$post_type_counts = [];
$post_type_views = [];

foreach ($post_types as $type) {
    $post_type_labels[] = ucfirst($type['post_type']);
    $post_type_counts[] = (int)$type['count'];
    $post_type_views[] = (int)$type['total_views'];
}

// Prepare country data for charts
$country_labels = [];
$country_counts = [];

foreach ($user_countries as $country) {
    $country_labels[] = $country['country_code'];
    $country_counts[] = $country['count'];
}

// Prepare downloads by country data
$downloads_country_labels = [];
$downloads_country_counts = [];

foreach ($downloads_by_country as $country) {
    $downloads_country_labels[] = $country['country_code'];
    $downloads_country_counts[] = $country['download_count'];
}

// Prepare browser data for charts
$browser_labels = [];
$browser_counts = [];

foreach ($user_agents as $browser) {
    $browser_labels[] = $browser['browser'];
    $browser_counts[] = (int)$browser['count'];
}


// Prepare country data for charts
$country_labels = [];
$country_counts = [];

foreach ($user_countries as $country) {
    $country_labels[] = country_name($country['country_code']);
    $country_counts[] = $country['count'];
}

// Prepare downloads by country data
$downloads_country_labels = [];
$downloads_country_counts = [];

foreach ($downloads_by_country as $country) {
    $downloads_country_labels[] = country_name($country['country_code']);
    $downloads_country_counts[] = $country['download_count'];
}

include __DIR__ . '/../admin_header.php';
?>

<div class="container">
    <!-- Date range controls (the chart bucket is derived automatically from the range) -->
    <div class="control-bar">
        <form method="get" id="rangeForm" class="range-controls">
            <div class="control-group">
                <span class="control-label">Date Range:</span>
                <select name="range" id="rangePreset" class="control-select" onchange="onRangeChange()">
                    <?php foreach ($presets as $preset_option): ?>
                        <option value="<?php echo htmlspecialchars($preset_option); ?>"
                            <?php echo $preset_option === $selected_range ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($preset_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="range-display" title="Grouped by <?php echo htmlspecialchars($bucket); ?>">
                    <?php echo htmlspecialchars($range_display); ?>
                </span>
            </div>

            <div class="control-group range-custom" id="rangeCustom" style="display: <?php echo $selected_range === 'Custom Range' ? 'flex' : 'none'; ?>;">
                <input type="date" name="start" id="rangeStart" class="control-input" value="<?php echo htmlspecialchars($range_start->format('Y-m-d')); ?>" <?php echo $selected_range === 'Custom Range' ? '' : 'disabled'; ?>>
                <span>to</span>
                <input type="date" name="end" id="rangeEnd" class="control-input" value="<?php echo htmlspecialchars($range_end->format('Y-m-d')); ?>" <?php echo $selected_range === 'Custom Range' ? '' : 'disabled'; ?>>
                <button type="submit" class="control-pill">Apply</button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" id="statsGrid">
        <!-- Will be populated by JavaScript -->
    </div>

    <!-- Charts -->
    <div class="chart-row">
        <div class="chart-container">
            <h2>Downloads Over Time</h2>
            <canvas id="downloadsChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Page Views Over Time</h2>
            <canvas id="viewsChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Bounce Rate Over Time</h2>
            <canvas id="bounceRateChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Community Post Types</h2>
            <canvas id="postTypeChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Post Views by Type</h2>
            <canvas id="postViewsChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Top 10 User Countries (Page Views)</h2>
            <canvas id="countryChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Browser Distribution</h2>
            <canvas id="browserChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Downloads by Country</h2>
            <canvas id="downloadsCountryChart"></canvas>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart data
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const downloadsData = <?php echo json_encode($downloads_data); ?>;
        const registrationsData = <?php echo json_encode($registrations_data); ?>;
        const pageViewsData = <?php echo json_encode($page_views_data); ?>;
        const postTypeLabels = <?php echo json_encode($post_type_labels); ?>;
        const postTypeCounts = <?php echo json_encode($post_type_counts); ?>;
        const postTypeViews = <?php echo json_encode($post_type_views); ?>;
        const countryLabels = <?php echo json_encode($country_labels); ?>;
        const countryCounts = <?php echo json_encode($country_counts); ?>;
        const downloadsCountryLabels = <?php echo json_encode($downloads_country_labels); ?>;
        const downloadsCountryCounts = <?php echo json_encode($downloads_country_counts); ?>;
        const browserLabels = <?php echo json_encode($browser_labels); ?>;
        const browserCounts = <?php echo json_encode($browser_counts); ?>;
        const bounceRateData = <?php echo json_encode($bounce_rate_data); ?>;
        const bounceSessionsData = <?php echo json_encode($bounce_sessions_data); ?>;
        const bounceRateOverall = <?php echo json_encode($bounce_overall['bounce_rate']); ?>;
        const bounceSessionsTotal = <?php echo json_encode($bounce_overall['total_sessions']); ?>;
        const visitorOverview = <?php echo json_encode($visitor_overview); ?>;
        const conversionData = <?php echo json_encode([
                                    $conversion_data['downloads'],
                                    $conversion_data['registrations']
                                ]); ?>;

        generateStatistics();

        function generateStatistics() {
            const statsGrid = document.getElementById('statsGrid');

            function formatDuration(seconds) {
                seconds = Math.max(0, Math.round(seconds));
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return m > 0 ? `${m}m${s}s` : `${s}s`;
            }

            const stats = [{
                    title: 'Live Visitors',
                    value: visitorOverview.live.toLocaleString(),
                    live: true
                },
                {
                    title: 'Unique Visitors',
                    value: visitorOverview.unique_visitors.toLocaleString()
                },
                {
                    title: 'Total Pageviews',
                    value: visitorOverview.total_pageviews.toLocaleString()
                },
                {
                    title: 'Bounce Rate',
                    value: bounceSessionsTotal > 0 ? bounceRateOverall.toFixed(1) + '%' : '—',
                    info: 'The share of visits where someone looked at a single page and then left. Each visit counts as one session, and the same person returning after 30+ minutes of inactivity starts a fresh one. A high bounce rate is normal for pages people read and leave, like an article or landing page.'
                },
                {
                    title: 'Average Session',
                    value: bounceSessionsTotal > 0 ? formatDuration(visitorOverview.avg_session_secs) : '—'
                }
            ];

            statsGrid.innerHTML = stats.map(stat => `
                <div class="stat-card">
                    ${stat.info ? `<span class="info-tip" tabindex="0" aria-label="What does ${stat.title} mean?">
                        <span class="info-tip-icon" aria-hidden="true">i</span>
                        <span class="info-tip-tooltip" role="tooltip">${stat.info}</span>
                    </span>` : ''}
                    <h3>${stat.title}${stat.live ? ' <span class="live-dot"></span>' : ''}</h3>
                    <div class="value">${stat.value}</div>
                </div>
            `).join('');
        }

        // Downloads chart
        const ctxDownloads = document.getElementById('downloadsChart').getContext('2d');
        new Chart(ctxDownloads, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Downloads',
                    data: downloadsData,
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(37, 99, 235, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            padding: 10
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Downloads: ${context.raw}`;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Page Views chart
        const ctxViews = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctxViews, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Page Views',
                    data: pageViewsData,
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(245, 158, 11, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            padding: 10
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Page Views: ${context.raw}`;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Bounce Rate chart
        const ctxBounce = document.getElementById('bounceRateChart').getContext('2d');
        new Chart(ctxBounce, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Bounce Rate',
                    data: bounceRateData,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                    spanGaps: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        ticks: {
                            padding: 10
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const rate = context.raw;
                                const sessions = bounceSessionsData[context.dataIndex] || 0;
                                if (rate === null) {
                                    return 'No sessions';
                                }
                                return `Bounce: ${rate.toFixed(1)}% (${sessions.toLocaleString()} sessions)`;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Post Type chart
        const ctxPostType = document.getElementById('postTypeChart').getContext('2d');
        new Chart(ctxPostType, {
            type: 'pie',
            data: {
                labels: postTypeLabels,
                datasets: [{
                    data: postTypeCounts,
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(14, 165, 233, 0.8)'
                    ],
                    borderColor: [
                        'rgba(37, 99, 235, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(14, 165, 233, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = Number(context.raw) || 0;
                                const total = context.dataset.data.reduce((a, b) => Number(a) + Number(b), 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} posts (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Post Views by Type chart
        const ctxPostViews = document.getElementById('postViewsChart').getContext('2d');
        new Chart(ctxPostViews, {
            type: 'bar',
            data: {
                labels: postTypeLabels,
                datasets: [{
                    label: 'Views',
                    data: postTypeViews,
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(14, 165, 233, 0.7)'
                    ],
                    borderColor: [
                        'rgba(37, 99, 235, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(14, 165, 233, 1)'
                    ],
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = Number(context.raw) || 0;
                                const total = context.dataset.data.reduce((a, b) => Number(a) + Number(b), 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value.toLocaleString()} views (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Country chart (Page Views)
        const ctxCountry = document.getElementById('countryChart').getContext('2d');
        new Chart(ctxCountry, {
            type: 'bar',
            data: {
                labels: countryLabels,
                datasets: [{
                    label: 'Page Views',
                    data: countryCounts,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = Number(context.raw) || 0;
                                const total = context.dataset.data.reduce((a, b) => Number(a) + Number(b), 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} page views (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Downloads by Country chart
        const ctxDownloadsCountry = document.getElementById('downloadsCountryChart').getContext('2d');
        new Chart(ctxDownloadsCountry, {
            type: 'bar',
            data: {
                labels: downloadsCountryLabels,
                datasets: [{
                    label: 'Downloads',
                    data: downloadsCountryCounts,
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = Number(context.raw) || 0;
                                const total = context.dataset.data.reduce((a, b) => Number(a) + Number(b), 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} downloads (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Browser chart
        const ctxBrowser = document.getElementById('browserChart').getContext('2d');
        new Chart(ctxBrowser, {
            type: 'pie',
            data: {
                labels: browserLabels,
                datasets: [{
                    data: browserCounts,
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(14, 165, 233, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(107, 114, 128, 0.7)'
                    ],
                    borderColor: [
                        'rgba(37, 99, 235, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(14, 165, 233, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(107, 114, 128, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });




        // Preserve scroll position across the date-range reload.
        const rangeForm = document.getElementById('rangeForm');
        if (rangeForm) {
            rangeForm.addEventListener('submit', function() {
                sessionStorage.setItem('scrollPosition', window.scrollY);
            });
        }
    });

    // Show custom date inputs for "Custom Range"; for any preset, reload immediately.
    function onRangeChange() {
        const preset = document.getElementById('rangePreset').value;
        const custom = document.getElementById('rangeCustom');
        const start = document.getElementById('rangeStart');
        const end = document.getElementById('rangeEnd');

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
            sessionStorage.setItem('scrollPosition', window.scrollY);
            document.getElementById('rangeForm').submit();
        }
    }
</script>
<script src="../preserve-scroll.js" defer></script>
