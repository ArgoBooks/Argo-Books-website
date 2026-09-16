<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../country_names.php';
// Needed for svg_icon() when an AJAX save renders table rows without the header.
require_once __DIR__ . '/../../resources/icons.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Set page variables for the header
$page_title = "Referral Link Tracking";
$page_description = "Create and manage referral links to track ad/sponsor performance";

// Generate a per-session CSRF token used by every form on this page.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Send a JSON reply and stop. Used by the create / update / delete handlers
 * when the form was submitted with fetch() instead of a full page post.
 */
function referral_json_response(array $payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// The management-table forms post through fetch() and set ajax=1. Those requests
// get JSON back (message + freshly rendered table rows) instead of a redirect,
// so adding or editing a link never reloads the page.
$is_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['ajax'] ?? '') === '1');
$saved_message = '';
$saved_category = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check: every state-mutating POST must include the session token.
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!is_string($posted_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
        http_response_code(403);
        if ($is_ajax) {
            referral_json_response([
                'success' => false,
                'message' => 'Your session has expired. Reload the page and try again.',
            ]);
        }
        die('Invalid CSRF token. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    if (in_array($action, ['create', 'update', 'delete'], true)) {
        try {
            if ($action === 'create') {
                $source_code = trim($_POST['source_code']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $target_url = trim($_POST['target_url']);

                $stmt = $pdo->prepare('INSERT INTO referral_links (source_code, name, description, target_url) VALUES (?, ?, ?, ?)');
                $stmt->execute([$source_code, $name, $description, $target_url]);

                $saved_message = 'Referral link created successfully!';
                $saved_category = referral_category_key($source_code);
            } elseif ($action === 'update') {
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $target_url = trim($_POST['target_url']);

                $stmt = $pdo->prepare('UPDATE referral_links SET name = ?, description = ?, target_url = ? WHERE id = ?');
                $stmt->execute([$name, $description, $target_url, $id]);

                $saved_message = 'Referral link updated successfully!';
                $saved_category = referral_category_key(trim($_POST['source_code'] ?? ''));
            } else {
                $id = (int)$_POST['id'];

                $stmt = $pdo->prepare('DELETE FROM referral_links WHERE id = ?');
                $stmt->execute([$id]);

                $saved_message = 'Referral link deleted successfully!';
            }
        } catch (PDOException $e) {
            if (!$is_ajax) {
                throw $e;
            }
            // 23000 is the duplicate-key state; the only realistic collision here
            // is a source code that already exists.
            $message = $e->getCode() === '23000'
                ? 'That source code is already in use. Pick a different one.'
                : 'The referral link could not be saved. Please try again.';
            referral_json_response(['success' => false, 'message' => $message]);
        }

        if (!$is_ajax) {
            $_SESSION['success_message'] = $saved_message;
            header('Location: index.php');
            exit;
        }
    } elseif ($is_ajax) {
        referral_json_response(['success' => false, 'message' => 'Unknown action.']);
    }
}

function get_referral_links()
{
    global $pdo;
    $query = "
        SELECT
            rl.*,
            COUNT(DISTINCT rv.id) as total_visits,
            COALESCE(MAX(inst.installs), 0) as installs,
            SUM(CASE WHEN rv.converted = 1 THEN 1 ELSE 0 END) as conversions
        FROM referral_links rl
        LEFT JOIN referral_visits rv ON rl.source_code = rv.source_code
        LEFT JOIN (
            SELECT source_code, COUNT(*) AS installs
            FROM referral_events
            WHERE event_type = 'app_first_run' AND environment = ?
            GROUP BY source_code
        ) inst ON inst.source_code = rl.source_code
        GROUP BY rl.id
        ORDER BY total_visits DESC, rl.created_at DESC";

    // referral_events is shared with sandbox, so only this environment's installs count.
    $stmt = $pdo->prepare($query);
    $stmt->execute([current_environment()]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

// Function to get referral visits by source
function get_visits_by_source($limit = 10)
{
    global $pdo;
    $query = "
        SELECT
            rv.source_code,
            COUNT(*) as visit_count,
            SUM(CASE WHEN rv.converted = 1 THEN 1 ELSE 0 END) as conversions,
            COUNT(DISTINCT rv.ip_address) as unique_visitors
        FROM referral_visits rv
        INNER JOIN referral_links rl ON rl.source_code = rv.source_code
        GROUP BY rv.source_code
        ORDER BY visit_count DESC
        LIMIT ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

function get_visits_over_time($period = 'day', $limit = 30, $source_code = null)
{
    global $pdo;

    $sql_period = '';
    $display_format = '';
    switch ($period) {
        case 'day':
            $sql_period = 'DATE(visited_at)';
            $display_format = 'DATE(visited_at)';
            break;
        case 'week':
            $sql_period = 'YEARWEEK(visited_at)';
            $display_format = 'CONCAT("Week ", WEEK(visited_at), ", ", YEAR(visited_at))';
            break;
        case 'month':
            $sql_period = 'DATE_FORMAT(visited_at, "%Y-%m")';
            $display_format = 'DATE_FORMAT(visited_at, "%b %Y")';
            break;
    }

    if ($source_code) {
        $query = "
            SELECT
                $sql_period as period,
                $display_format as display_period,
                COUNT(*) as count,
                SUM(CASE WHEN rv.converted = 1 THEN 1 ELSE 0 END) as conversions
            FROM referral_visits rv
            INNER JOIN referral_links rl ON rl.source_code = rv.source_code
            WHERE rv.source_code = ?
            GROUP BY period, display_period
            ORDER BY period DESC
            LIMIT ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$source_code, $limit]);
    } else {
        $query = "
            SELECT
                $sql_period as period,
                $display_format as display_period,
                COUNT(*) as count,
                SUM(CASE WHEN rv.converted = 1 THEN 1 ELSE 0 END) as conversions
            FROM referral_visits rv
            INNER JOIN referral_links rl ON rl.source_code = rv.source_code
            GROUP BY period, display_period
            ORDER BY period DESC
            LIMIT ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$limit]);
    }

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

// Function to get geographic distribution
function get_referral_countries($limit = 10)
{
    global $pdo;
    $query = "
        SELECT
            rv.country_code,
            COUNT(*) as visit_count,
            SUM(CASE WHEN rv.converted = 1 THEN 1 ELSE 0 END) as conversions
        FROM referral_visits rv
        INNER JOIN referral_links rl ON rl.source_code = rv.source_code
        WHERE rv.country_code IS NOT NULL AND rv.country_code != ''
        GROUP BY rv.country_code
        ORDER BY visit_count DESC
        LIMIT ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = $row;
    }

    return $data;
}

/**
 * Map a source_code to a category from its naming-convention prefix. No DB
 * column needed: auto-registered sources (guide-*, social-*, ai-*, ...) sort
 * themselves into the right bucket, so the list never needs hand-filing.
 */
function referral_category_key($source_code)
{
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
 * Render the grouped <tbody> blocks of the management table (the <thead> stays
 * put). Shared by the initial page render and the JSON reply an AJAX save gets
 * back, so both go through exactly the same markup.
 */
function render_referral_table_bodies()
{
    global $category_order, $category_labels, $grouped_links, $category_visits, $category_installs, $category_conversions;

    ob_start();
    foreach ($category_order as $ckey) {
        $clinks = $grouped_links[$ckey] ?? [];
        if (empty($clinks)) {
            continue;
        }
        $cvisits = $category_visits[$ckey];
        $cinst   = $category_installs[$ckey];
        $cconv   = $category_conversions[$ckey];
        $crate   = $cvisits > 0 ? round(($cconv / $cvisits) * 100, 1) : 0;
        ?>
        <tbody class="category-group collapsed" data-cat="<?php echo htmlspecialchars($ckey); ?>">
            <tr class="category-header" role="button" tabindex="0" aria-expanded="false">
                <td colspan="9">
                    <span class="cat-caret" aria-hidden="true">&#9656;</span>
                    <span class="cat-name"><?php echo htmlspecialchars($category_labels[$ckey]); ?></span>
                    <span class="cat-meta"><?php echo count($clinks); ?> source<?php echo count($clinks) === 1 ? '' : 's'; ?>
                        &middot; <?php echo number_format($cvisits); ?> visits
                        &middot; <?php echo number_format($cinst); ?> installs
                        &middot; <?php echo number_format($cconv); ?> conversions
                        &middot; <?php echo $crate; ?>%</span>
                </td>
            </tr>
            <?php foreach ($clinks as $link): ?>
                <?php $conv_rate = $link['total_visits'] > 0 ? round(($link['conversions'] / $link['total_visits']) * 100, 1) : 0; ?>
                <tr class="source-row">
                    <td><code><?php echo htmlspecialchars($link['source_code']); ?></code></td>
                    <td><?php echo htmlspecialchars($link['name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($link['description'], 0, 50)) . (strlen($link['description']) > 50 ? '...' : ''); ?></td>
                    <td><a href="<?php echo htmlspecialchars($link['target_url']); ?>" target="_blank" class="link-preview"><?php echo htmlspecialchars(substr($link['target_url'], 0, 30)) . (strlen($link['target_url']) > 30 ? '...' : ''); ?></a></td>
                    <td><?php echo number_format($link['total_visits']); ?></td>
                    <td><?php echo number_format($link['installs']); ?></td>
                    <td><?php echo number_format($link['conversions']); ?></td>
                    <td><?php echo $conv_rate; ?>%</td>
                    <td class="actions-cell">
                        <button onclick="editLink(<?php echo htmlspecialchars(json_encode($link)); ?>)" class="btn-icon" title="Edit" aria-label="Edit"><?php echo svg_icon('pencil', 16); ?></button>
                        <button onclick="deleteLink(<?php echo $link['id']; ?>)" class="btn-icon" title="Delete" aria-label="Delete"><?php echo svg_icon('trash', 16); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <?php
    }

    return ob_get_clean();
}

/**
 * Rows behind the table's Download button, in the table's own order. Sent back with
 * every AJAX save as well, so a link created or renamed since the page loaded is in it.
 */
function referral_download_payload(): string
{
    global $category_order, $category_labels, $grouped_links;

    $rows = [];
    foreach ($category_order as $ckey) {
        foreach ($grouped_links[$ckey] ?? [] as $link) {
            $visits = (int)$link['total_visits'];
            $conversions = (int)$link['conversions'];
            $rows[] = [
                $category_labels[$ckey],
                $link['source_code'],
                $link['name'],
                $link['description'],
                $link['target_url'],
                $visits,
                (int)$link['installs'],
                $conversions,
                $visits > 0 ? round($conversions / $visits * 100, 1) : 0,
            ];
        }
    }

    return json_encode([
        'filename' => 'referral-links',
        'datasets' => [[
            'title' => 'Referral links',
            'columns' => ['Category', 'Source code', 'Name', 'Description', 'Target URL', 'Visits', 'Installs', 'Conversions', 'Rate %'],
            'rows' => $rows,
        ]],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Get statistics
$referral_links = get_referral_links();

// Group-by toggle: 'source' (per-link, default) or 'category' (rolled up).
$group_mode = (($_GET['group'] ?? 'source') === 'category') ? 'category' : 'source';

$category_labels = [
    'paid'    => 'Paid ads',
    'website' => 'My website (guides & articles)',
    'invgen'  => 'Invoice generator',
    'outreach' => 'Outreach',
    'social'  => 'Social media',
    'youtube' => 'YouTube',
    'ai'        => 'AI assistants',
    'directory' => 'Directories (launch & SaaS sites)',
    'other'     => 'Other',
];
$category_order = ['paid', 'website', 'invgen', 'outreach', 'social', 'youtube', 'ai', 'directory', 'other'];

// Bucket every referral link by category and tally per-category subtotals.
$grouped_links = [];
$category_visits = [];
$category_installs = [];
$category_conversions = [];
foreach ($category_order as $k) {
    $category_visits[$k] = 0;
    $category_installs[$k] = 0;
    $category_conversions[$k] = 0;
}
foreach ($referral_links as $link) {
    $k = referral_category_key($link['source_code']);
    $grouped_links[$k][] = $link;
    $category_visits[$k] += (int)$link['total_visits'];
    $category_installs[$k] += (int)$link['installs'];
    $category_conversions[$k] += (int)$link['conversions'];
}

// An AJAX save is done here: hand back the rebuilt table and skip the chart
// queries entirely, since the page keeps the charts it already drew.
if ($is_ajax) {
    referral_json_response([
        'success' => true,
        'message' => $saved_message,
        'table_html' => render_referral_table_bodies(),
        'download' => referral_download_payload(),
        'expand' => $saved_category,
    ]);
}

$visits_by_source = get_visits_by_source(15);
$period = isset($_GET['period']) ? $_GET['period'] : 'day';
$allowed_periods = ['day', 'week', 'month'];
if (!in_array($period, $allowed_periods)) {
    $period = 'day';
}

$visits_over_time = get_visits_over_time($period, 30);
$referral_countries = get_referral_countries();

// Prepare data for charts
$source_labels = [];
$source_visit_counts = [];
$source_conversion_counts = [];

foreach ($visits_by_source as $item) {
    $source_labels[] = $item['source_code'];
    $source_visit_counts[] = (int)$item['visit_count'];
    $source_conversion_counts[] = (int)$item['conversions'];
}

// The three "by source" breakdown charts roll up to the 6 categories when the
// Group-by toggle is set to category (6 clean bars instead of 40 noisy ones).
$breakdown_noun = 'Source';
if ($group_mode === 'category') {
    $breakdown_noun = 'Category';
    $source_labels = [];
    $source_visit_counts = [];
    $source_conversion_counts = [];
    foreach ($category_order as $k) {
        if (empty($grouped_links[$k])) {
            continue;
        }
        $source_labels[] = $category_labels[$k];
        $source_visit_counts[] = $category_visits[$k];
        $source_conversion_counts[] = $category_conversions[$k];
    }
}

// Prepare time series data
$time_labels = [];
$time_visit_counts = [];
$time_conversion_counts = [];

$visits_over_time = array_reverse($visits_over_time);
foreach ($visits_over_time as $item) {
    $time_labels[] = isset($item['display_period']) ? $item['display_period'] : $item['period'];
    $time_visit_counts[] = (int)$item['count'];
    $time_conversion_counts[] = (int)$item['conversions'];
}

// Prepare country data
$country_labels = [];
$country_visit_counts = [];
$country_conversion_counts = [];

foreach ($referral_countries as $country) {
    $country_labels[] = country_name($country['country_code']);
    $country_visit_counts[] = (int)$country['visit_count'];
    $country_conversion_counts[] = (int)$country['conversions'];
}

// Calculate total stats across ALL sources (every category), so the cards read
// the same in both group modes rather than reflecting only the charted top-N.
$total_visits = array_sum($category_visits);
$total_conversions = array_sum($category_conversions);
$conversion_rate = $total_visits > 0 ? round(($total_conversions / $total_visits) * 100, 1) : 0;

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">

<div class="container">
    <?php
    // One message box for both the redirect flash (a non-JS post) and the
    // messages JavaScript writes after an AJAX save.
    $flash_message = $_SESSION['success_message'] ?? '';
    unset($_SESSION['success_message']);
    ?>
    <div id="pageMessage" class="success-message"<?php echo $flash_message === '' ? ' style="display: none;"' : ''; ?>><?php echo htmlspecialchars($flash_message); ?></div>

    <!-- Controls: group-by toggle + time period selector, combined into one bar -->
    <div class="control-bar">
        <div class="control-group">
            <span class="control-label">Group by:</span>
            <div class="control-pills">
                <a href="?group=source&amp;period=<?php echo htmlspecialchars($period); ?>"
                   class="control-pill <?php echo $group_mode === 'source' ? 'active' : ''; ?>">By source</a>
                <a href="?group=category&amp;period=<?php echo htmlspecialchars($period); ?>"
                   class="control-pill <?php echo $group_mode === 'category' ? 'active' : ''; ?>">By category</a>
            </div>
        </div>
        <div class="control-group">
            <span class="control-label">Time period:</span>
            <div class="control-pills">
                <?php
                $periods = [
                    'day' => 'Daily',
                    'week' => 'Weekly',
                    'month' => 'Monthly'
                ];

                foreach ($periods as $periodKey => $periodName) {
                    $activeClass = ($period === $periodKey) ? 'active' : '';
                    echo "<a href=\"?period={$periodKey}&amp;group={$group_mode}\" class=\"control-pill {$activeClass}\">{$periodName}</a>";
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Visits</h3>
            <div class="value"><?php echo number_format($total_visits); ?></div>
            <div class="subtext">from referral sources</div>
        </div>
        <div class="stat-card">
            <h3>Total Conversions</h3>
            <div class="value"><?php echo number_format($total_conversions); ?></div>
            <div class="subtext">license purchases</div>
        </div>
        <div class="stat-card">
            <h3>Conversion Rate</h3>
            <div class="value"><?php echo $conversion_rate; ?>%</div>
            <div class="subtext">visit to purchase</div>
        </div>
        <div class="stat-card">
            <h3>Active Sources</h3>
            <div class="value"><?php echo count($visits_by_source); ?></div>
            <div class="subtext">referral sources</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-row">
        <div class="chart-container">
            <h2>Visits by <?php echo $breakdown_noun; ?></h2>
            <canvas id="sourceVisitsChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Conversions by <?php echo $breakdown_noun; ?></h2>
            <canvas id="sourceConversionsChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Visits Over Time</h2>
            <canvas id="visitsTimeChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Conversions Over Time</h2>
            <canvas id="conversionsTimeChart"></canvas>
        </div>
    </div>

    <div class="chart-row">
        <div class="chart-container">
            <h2>Top Countries</h2>
            <canvas id="countriesChart"></canvas>
        </div>
        <div class="chart-container">
            <h2>Conversion Rate by <?php echo $breakdown_noun; ?></h2>
            <canvas id="conversionRateChart"></canvas>
        </div>
    </div>

    <!-- Referral Links Management -->
    <div class="table-container">
        <div class="table-header-actions">
            <h2>Manage Referral Links</h2>
            <button type="button" id="referralDownloadBtn" class="bd-download-btn" title="Download this table as CSV"
                    data-download="<?php echo htmlspecialchars(referral_download_payload(), ENT_QUOTES); ?>">Download</button>
            <button id="createLinkBtn" class="btn btn-blue">Create New Link</button>
        </div>

        <div class="table-responsive">
            <table class="referral-table">
                <thead>
                    <tr>
                        <th>Source Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Target URL</th>
                        <th>Visits</th>
                        <th>Installs</th>
                        <th>Conversions</th>
                        <th>Rate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <?php echo render_referral_table_bodies(); ?>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="linkModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Create Referral Link</h2>
        <div id="modalError" class="error-message" style="display: none;"></div>

        <form id="linkForm" method="POST">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="linkId">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="source_code">Source Code *</label>
                <input type="text" name="source_code" id="source_code" required pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, hyphens, and underscores allowed">
                <small>Used in URL: ?source=CODE (alphanumeric, hyphens, underscores only)</small>
            </div>

            <div class="form-group">
                <label for="name">Display Name *</label>
                <input type="text" name="name" id="name" required>
                <small>A friendly name for this referral source</small>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3"></textarea>
                <small>Optional notes about this referral source</small>
            </div>

            <div class="form-group">
                <label for="target_url">Target URL *</label>
                <input type="url" name="target_url" id="target_url" required>
                <small>The page users will land on (usually your homepage)</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-blue">Save</button>
                <button type="button" class="btn btn-gray" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    const sourceLabels = <?php echo json_encode($source_labels); ?>;
    const sourceVisitCounts = <?php echo json_encode($source_visit_counts); ?>;
    const sourceConversionCounts = <?php echo json_encode($source_conversion_counts); ?>;
    const timeLabels = <?php echo json_encode($time_labels); ?>;
    const timeVisitCounts = <?php echo json_encode($time_visit_counts); ?>;
    const timeConversionCounts = <?php echo json_encode($time_conversion_counts); ?>;
    const countryLabels = <?php echo json_encode($country_labels); ?>;
    const countryVisitCounts = <?php echo json_encode($country_visit_counts); ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Visits by Source Chart
        const ctxSourceVisits = document.getElementById('sourceVisitsChart').getContext('2d');
        new Chart(ctxSourceVisits, {
            type: 'bar',
            data: {
                labels: sourceLabels,
                datasets: [{
                    label: 'Visits',
                    data: sourceVisitCounts,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
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
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Visits: ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });

        // Conversions by Source Chart
        const ctxSourceConversions = document.getElementById('sourceConversionsChart').getContext('2d');
        new Chart(ctxSourceConversions, {
            type: 'bar',
            data: {
                labels: sourceLabels,
                datasets: [{
                    label: 'Conversions',
                    data: sourceConversionCounts,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
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
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Conversions: ${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });

        // Visits Over Time Chart
        const ctxVisitsTime = document.getElementById('visitsTimeChart').getContext('2d');
        new Chart(ctxVisitsTime, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Visits',
                    data: timeVisitCounts,
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
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        // Conversions Over Time Chart
        const ctxConversionsTime = document.getElementById('conversionsTimeChart').getContext('2d');
        new Chart(ctxConversionsTime, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Conversions',
                    data: timeConversionCounts,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(16, 185, 129, 1)'
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
                        position: 'top'
                    }
                }
            }
        });

        // Countries Chart
        const ctxCountries = document.getElementById('countriesChart').getContext('2d');
        new Chart(ctxCountries, {
            type: 'bar',
            data: {
                labels: countryLabels,
                datasets: [{
                    label: 'Visits',
                    data: countryVisitCounts,
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
                        display: false
                    }
                }
            }
        });

        // Conversion Rate by Source Chart
        const conversionRates = sourceLabels.map((label, index) => {
            const visits = sourceVisitCounts[index];
            const conversions = sourceConversionCounts[index];
            return visits > 0 ? (conversions / visits) * 100 : 0;
        });

        const ctxConversionRate = document.getElementById('conversionRateChart').getContext('2d');
        new Chart(ctxConversionRate, {
            type: 'bar',
            data: {
                labels: sourceLabels,
                datasets: [{
                    label: 'Conversion Rate (%)',
                    data: conversionRates,
                    backgroundColor: 'rgba(14, 165, 233, 0.7)',
                    borderColor: 'rgba(14, 165, 233, 1)',
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
                            callback: function(value) {
                                return value.toFixed(1) + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Conversion Rate: ${context.raw.toFixed(2)}%`;
                            }
                        }
                    }
                }
            }
        });
    });

    // Modal Functions
    function openModal() {
        document.getElementById('modalError').style.display = 'none';
        document.getElementById('linkModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('linkModal').style.display = 'none';
        document.getElementById('linkForm').reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('modalTitle').textContent = 'Create Referral Link';
        document.getElementById('source_code').removeAttribute('readonly');
        document.getElementById('modalError').style.display = 'none';
    }

    function editLink(link) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('linkId').value = link.id;
        document.getElementById('source_code').value = link.source_code;
        document.getElementById('source_code').setAttribute('readonly', 'readonly');
        document.getElementById('name').value = link.name;
        document.getElementById('description').value = link.description;
        document.getElementById('target_url').value = link.target_url;
        document.getElementById('modalTitle').textContent = 'Edit Referral Link';
        openModal();
    }

    function deleteLink(id) {
        if (!confirm('Are you sure you want to delete this referral link? This will not delete visit history.')) {
            return;
        }
        const data = new FormData();
        data.append('csrf_token', csrfToken);
        data.append('action', 'delete');
        data.append('id', id);
        postLinkChange(data);
    }

    // Send a create / update / delete to the server and refresh just the table.
    // Nothing here reloads the page, so the charts and scroll position survive.
    async function postLinkChange(formData, onError) {
        formData.append('ajax', '1');

        let result;
        try {
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            result = await response.json();
        } catch (err) {
            result = { success: false, message: 'Could not reach the server. Please try again.' };
        }

        if (!result.success) {
            if (onError) {
                onError(result.message);
            } else {
                showPageMessage(result.message, true);
            }
            return false;
        }

        refreshTableBodies(result.table_html, result.expand);
        if (result.download) {
            document.getElementById('referralDownloadBtn').setAttribute('data-download', result.download);
        }
        showPageMessage(result.message, false);
        return true;
    }

    let pageMessageTimer = null;

    function showPageMessage(text, isError) {
        const box = document.getElementById('pageMessage');
        box.textContent = text;
        box.className = isError ? 'error-message' : 'success-message';
        box.style.display = 'block';

        clearTimeout(pageMessageTimer);
        if (!isError) {
            pageMessageTimer = setTimeout(function() {
                box.style.display = 'none';
            }, 5000);
        }
    }

    // Swap in the server-rendered category groups, keeping whichever sections
    // the user had open and expanding the one the saved link belongs to.
    function refreshTableBodies(html, expandCategory) {
        const table = document.querySelector('.referral-table');
        if (!table || typeof html !== 'string') {
            return;
        }

        const stayOpen = new Set();
        table.querySelectorAll('.category-group:not(.collapsed)').forEach(function(group) {
            stayOpen.add(group.dataset.cat);
        });
        if (expandCategory) {
            stayOpen.add(expandCategory);
        }

        table.querySelectorAll('tbody').forEach(function(body) {
            body.remove();
        });
        table.insertAdjacentHTML('beforeend', html);

        table.querySelectorAll('.category-group').forEach(function(group) {
            if (stayOpen.has(group.dataset.cat)) {
                group.classList.remove('collapsed');
                group.querySelector('.category-header').setAttribute('aria-expanded', 'true');
            }
        });
        bindCategoryHeaders();
    }

    const createLinkBtnEl = document.getElementById('createLinkBtn');
    if (createLinkBtnEl) {
        createLinkBtnEl.addEventListener('click', openModal);
    }

    // Save without leaving the page; errors stay in the modal so the typed
    // values are still there to correct.
    document.getElementById('linkForm').addEventListener('submit', async function(event) {
        event.preventDefault();

        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const modalError = document.getElementById('modalError');

        modalError.style.display = 'none';
        submitBtn.disabled = true;

        const saved = await postLinkChange(new FormData(form), function(message) {
            modalError.textContent = message;
            modalError.style.display = 'block';
        });

        submitBtn.disabled = false;
        if (saved) {
            closeModal();
        }
    });

    // Close modal when clicking outside (only if mousedown also started on backdrop)
    let modalMouseDownTarget = null;
    window.addEventListener('mousedown', function(event) {
        modalMouseDownTarget = event.target;
    });
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('linkModal');
        if (modal && event.target === modal && modalMouseDownTarget === modal) {
            closeModal();
        }
    });



    // Save scroll position when clicking period or group-by links so the page
    // reload doesn't jump back to the top.
    document.querySelectorAll('a[href^="?period="], a[href^="?group="]').forEach(link => {
        link.addEventListener('click', function() {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        });
    });

    // Collapse / expand referral category sections in the management table.
    // Re-run after an AJAX save replaces the rows.
    function bindCategoryHeaders() {
        document.querySelectorAll('.category-header').forEach(header => {
            const toggle = () => {
                const group = header.closest('.category-group');
                const collapsed = group.classList.toggle('collapsed');
                header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            };
            header.addEventListener('click', toggle);
            header.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
        });
    }

    bindCategoryHeaders();
</script>
<script>window.ADMIN_PRESERVE_SCROLL = ['a[href^="?period="]', 'a[href^="?group="]'];</script>
<script src="../preserve-scroll.js" defer></script>
<script>
    // Download button: the same client-side CSV as the marketing funnel's cards, built from
    // the payload already in the page, so there is no extra admin endpoint to secure.
    (function () {
        const escapeCell = value => {
            const s = String(value ?? '');
            return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        };

        const btn = document.getElementById('referralDownloadBtn');
        if (!btn) return;
        btn.addEventListener('click', () => {
            let payload;
            try {
                payload = JSON.parse(btn.getAttribute('data-download'));
            } catch (err) {
                return;
            }

            const lines = [];
            (payload.datasets || []).forEach((ds, i) => {
                if (i > 0) lines.push('');
                lines.push(escapeCell(ds.title));
                lines.push((ds.columns || []).map(escapeCell).join(','));
                (ds.rows || []).forEach(row => lines.push(row.map(escapeCell).join(',')));
            });

            // BOM so Excel reads UTF-8 rather than the system codepage.
            const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (payload.filename || 'referral-links') + '-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    })();
</script>
