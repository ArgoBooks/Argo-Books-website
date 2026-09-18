<?php
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../country_names.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/../../referral_categories.php';

/**
 * ISO 3166-1 alpha-2 code -> flag emoji (regional indicator symbols).
 * Returns an empty string for non-two-letter / unknown codes.
 */
function funnel_flag_emoji(?string $code): string
{
    $code = strtoupper((string)$code);
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return '';
    }
    $a = 0x1F1E6 + (ord($code[0]) - ord('A'));
    $b = 0x1F1E6 + (ord($code[1]) - ord('A'));
    return mb_convert_encoding('&#' . $a . ';&#' . $b . ';', 'UTF-8', 'HTML-ENTITIES');
}

/**
 * Render a Plausible-style breakdown list: each row is tinted by a blue visits
 * bar with an orange revenue underline, the label on the left and the visit
 * count (plus revenue) on the right. Rows beyond $limit collapse into "Other".
 *
 * @param list<array{key:string,label:string,visits:int,revenue:float}> $rows
 * @param array{revenue?:bool,flag?:bool,icon?:bool,limit?:int,empty?:string} $opts
 */
function funnel_render_bar_list(array $rows, array $opts = []): string
{
    $show_revenue = $opts['revenue'] ?? true;
    $with_flag    = $opts['flag']    ?? false;
    $with_icon    = $opts['icon']    ?? false;
    $limit        = $opts['limit']   ?? 9;
    $empty_msg    = $opts['empty']   ?? 'No data for this period yet.';

    if (empty($rows)) {
        return '<div class="bd-empty">' . htmlspecialchars($empty_msg) . '</div>';
    }

    $shown = array_slice($rows, 0, $limit);
    $rest  = array_slice($rows, $limit);
    if (!empty($rest)) {
        $shown[] = [
            'key'     => '__other__',
            'label'   => 'Other',
            'visits'  => array_sum(array_map(fn($r) => (int)$r['visits'], $rest)),
            'revenue' => array_sum(array_map(fn($r) => (float)$r['revenue'], $rest)),
        ];
    }

    $max_v = max(1, max(array_map(fn($r) => (int)$r['visits'], $shown)));
    $max_r = max(array_map(fn($r) => (float)$r['revenue'], $shown));

    // Muted globe icon (data URI, CSP-safe) for referrer/campaign rows.
    $globe = 'data:image/svg+xml;utf8,' . rawurlencode(
        "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='#9098a3' stroke-width='1.6'>"
        . "<circle cx='12' cy='12' r='9'/><path d='M3 12h18'/><path d='M12 3c2.6 2.7 2.6 15.3 0 18M12 3c-2.6 2.7-2.6 15.3 0 18'/></svg>");

    $html = '<ul class="bd-list">';
    foreach ($shown as $r) {
        $v   = (int)$r['visits'];
        $rev = (float)$r['revenue'];
        $vw  = round(($v / $max_v) * 100, 2);
        $rw  = ($max_r > 0) ? round(($rev / $max_r) * 100, 2) : 0;

        $flag = ($with_flag && $r['key'] !== '__other__' && $r['key'] !== 'Unknown')
            ? funnel_flag_emoji($r['key']) : '';

        $tip = $r['label'] . ' — ' . number_format($v) . ' visit' . ($v === 1 ? '' : 's');
        if ($show_revenue) {
            $tip .= ' · $' . number_format($rev, 2) . ' revenue';
        }

        $icon = '';
        if ($with_flag) {
            $icon = '<span class="bd-flag">' . ($flag !== '' ? $flag : '&#127987;&#65039;') . '</span>';
        } elseif ($with_icon && $r['key'] !== '__other__') {
            $icon = '<img class="bd-icon" src="' . htmlspecialchars($globe) . '" alt="">';
        }

        $html .= '<li class="bd-row" data-tip="' . htmlspecialchars($tip) . '">';
        $html .= '<span class="bd-bar bd-bar-visits" style="width:' . $vw . '%"></span>';
        if ($show_revenue) {
            $html .= '<span class="bd-bar bd-bar-revenue" style="width:' . $rw . '%"></span>';
        }
        $html .= $icon;
        $html .= '<span class="bd-label">' . htmlspecialchars($r['label']) . '</span>';
        $html .= '<span class="bd-value">' . funnel_kfmt($v) . '</span></li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Full-list "See all details" modal, matching the Referrer modal. Rendered once
 * per breakdown that wants a searchable overflow list (referrer / country /
 * region / city). $rows are the same rows fed to funnel_render_bar_list. Returns
 * '' when there's nothing to show, so callers can echo unconditionally.
 */
function funnel_render_details_modal(
    string $id,
    string $title,
    array $rows,
    bool $show_revenue = true,
    string $name_heading = 'Source'
): string {
    if (empty($rows)) {
        return '';
    }
    // Only breakdowns that carry first-touch attribution (referrer, entry page)
    // have the free/paid counts. Detecting them rather than taking another
    // parameter keeps the country/region/city call sites unchanged.
    $show_users = false;
    foreach ($rows as $r) {
        if (!empty($r['free']) || !empty($r['paid'])) {
            $show_users = true;
            break;
        }
    }

    $h  = '<div id="' . htmlspecialchars($id) . '" class="modal bd-details-modal" style="display:none;">';
    $h .= '<div class="modal-content">';
    $h .= '<span class="modal-close bd-details-close">&times;</span>';
    $h .= '<h2>' . htmlspecialchars($title) . '</h2>';
    $h .= '<input type="text" class="bd-details-search" placeholder="Search&hellip;" autocomplete="off" spellcheck="false">';
    $h .= '<div class="bd-details-list' . ($show_users ? ' bd-details-list-users' : '') . '">';

    if ($show_users) {
        $h .= '<div class="bd-details-row bd-details-head">';
        $h .= '<span class="bd-details-name">' . htmlspecialchars($name_heading) . '</span>';
        $h .= '<span class="bd-details-visits">Visits</span>';
        $h .= '<span class="bd-details-free">Free users</span>';
        $h .= '<span class="bd-details-paid">Paid users</span>';
        if ($show_revenue) {
            $h .= '<span class="bd-details-revenue">Revenue</span>';
        }
        $h .= '</div>';
    }

    foreach ($rows as $r) {
        $h .= '<div class="bd-details-row" data-name="' . htmlspecialchars(strtolower($r['label'])) . '">';
        $h .= '<span class="bd-details-name">' . htmlspecialchars($r['label']) . '</span>';
        $h .= '<span class="bd-details-visits">' . number_format((int)$r['visits']) . '</span>';
        if ($show_users) {
            $h .= '<span class="bd-details-free">' . number_format((int)($r['free'] ?? 0)) . '</span>';
            $h .= '<span class="bd-details-paid">' . number_format((int)($r['paid'] ?? 0)) . '</span>';
        }
        if ($show_revenue) {
            $h .= '<span class="bd-details-revenue">$' . number_format((float)$r['revenue'], 0) . '</span>';
        }
        $h .= '</div>';
    }
    $h .= '</div></div></div>';
    return $h;
}

/**
 * "See all details" trigger button, targeting a modal rendered by
 * funnel_render_details_modal(). Returns '' when the list is empty.
 */
function funnel_render_details_btn(string $target_id, array $rows): string
{
    if (empty($rows)) {
        return '';
    }
    return '<button type="button" class="bd-details-btn" data-details-target="'
        . htmlspecialchars($target_id) . '">See all details</button>';
}

/**
 * Turns a breakdown row list into a labelled dataset for the CSV export.
 */
function funnel_export_dataset(string $title, array $rows, bool $revenue = false, bool $users = false): array
{
    $cols = ['Name', 'Visits'];
    if ($users)   { $cols[] = 'Free users'; $cols[] = 'Paid users'; }
    if ($revenue) { $cols[] = 'Revenue'; }

    $out = [];
    foreach ($rows as $r) {
        $line = [(string)$r['label'], (int)$r['visits']];
        if ($users) {
            $line[] = (int)($r['free'] ?? 0);
            $line[] = (int)($r['paid'] ?? 0);
        }
        if ($revenue) {
            $line[] = round((float)($r['revenue'] ?? 0), 2);
        }
        $out[] = $line;
    }
    return ['title' => $title, 'columns' => $cols, 'rows' => $out];
}

/**
 * "Download" button for an analytics card. Carries every tab's rows in a data
 * attribute so the export runs client-side, with no extra endpoint to secure.
 */
function funnel_render_download_btn(string $filename, array $datasets): string
{
    $payload = json_encode(
        ['filename' => $filename, 'datasets' => $datasets],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    return '<button type="button" class="bd-download-btn" title="Download every tab in this card as CSV"'
        . ' data-download="' . htmlspecialchars($payload, ENT_QUOTES) . '">Download</button>';
}

/**
 * Short explanatory line shown under a tab's content.
 */
function funnel_render_panel_desc(string $text): string
{
    return '<p class="bd-panel-desc">' . htmlspecialchars($text) . '</p>';
}

/**
 * Compact visitor-count formatting: 1900 -> "1.9k", 782 -> "782".
 */
function funnel_kfmt(int $n): string
{
    if (abs($n) >= 1000) {
        $k = $n / 1000;
        return (fmod($k, 1.0) === 0.0 ? number_format($k, 0) : number_format($k, 1)) . 'k';
    }
    return number_format($n);
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Marketing Funnel";
$page_description = "Conversion funnel and ad-spend tracking for referral campaigns";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// POST handlers: ad-spend create/update + delete only.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!is_string($posted_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
        http_response_code(403);
        die('Invalid CSRF token. Reload the page and try again.');
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'spend_save') {
            $source_code = trim($_POST['spend_source_code'] ?? '');
            $period_month = trim($_POST['spend_period'] ?? '');
            $amount = (float)($_POST['spend_amount'] ?? 0);
            $currency = trim($_POST['spend_currency'] ?? 'CAD');
            $notes = trim($_POST['spend_notes'] ?? '');

            $period_start = null;
            if (preg_match('/^(\d{4})-(\d{2})$/', $period_month, $m)) {
                $period_start = $m[1] . '-' . $m[2] . '-01';
            }
            if ($source_code !== '' && $period_start !== null && $amount >= 0) {
                $stmt = $pdo->prepare(
                    'INSERT INTO campaign_spend (source_code, period_start, amount, currency, notes)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount), currency = VALUES(currency), notes = VALUES(notes)'
                );
                $stmt->execute([$source_code, $period_start, $amount, $currency, $notes]);
                $_SESSION['success_message'] = 'Ad spend saved.';
            } else {
                $_SESSION['success_message'] = 'Could not save ad spend, check the source, month, and amount.';
            }
            header('Location: index.php?tab=spend');
            exit;
        } elseif ($_POST['action'] === 'spend_delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM campaign_spend WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['success_message'] = 'Ad spend deleted.';
            header('Location: index.php?tab=spend');
            exit;
        }
    }
}

/**
 * Active referral links, used to populate the Source filter pills and the
 * Source <select> in the ad-spend modal. The funnel + comparison-table data
 * is queried separately by get_funnel_per_source().
 */
function get_active_referral_links(): array
{
    global $pdo;
    $stmt = $pdo->query(
        'SELECT id, source_code, name, category, is_active
           FROM referral_links
          WHERE is_active = 1
          ORDER BY category ASC, name ASC'
    );
    return $stmt->fetchAll();
}

/**
 * SQL fragment: TRUE when a referral_events row's visitor also has a
 * JS-confirmed page view. Used to bot-filter download_click, which is a
 * server-side file-request event with no JS beacon, so js_confirmed can't
 * filter it directly. The check is deliberately not period-scoped: a real
 * user's landing may predate the selected period. Bots fetching the installer
 * URL directly mint a fresh cookieless visitor_id per request and never
 * produce a confirmed page view, so this drops them.
 *
 * $alias is how the outer query refers to referral_events (table name or alias).
 * Shared by the all-traffic funnel and the per-source table so the bot rule
 * can never drift between the two.
 */
function funnel_confirmed_visitor_sql(string $alias): string
{
    return "EXISTS (
        SELECT 1 FROM referral_events pv
         WHERE pv.visitor_id = {$alias}.visitor_id
           AND pv.event_type IN ('landing', 'downloads_page')
           AND pv.js_confirmed = 1
           AND pv.environment = {$alias}.environment)";
}

/**
 * SQL fragment: dedupe key for app_first_run rows. Rows can have
 * visitor_id = NULL (install token missing or outside the 14-day attribution
 * window); COUNT(DISTINCT visitor_id) would silently drop those real installs,
 * so fall back to the per-machine UUID the desktop reporter stamps into
 * event_data, then the row id.
 */
function funnel_first_run_key_sql(): string
{
    return "COALESCE(visitor_id,
        JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.machine_uuid')),
        CONCAT('row-', id))";
}

/**
 * Returns the start datetime for the active period filter. Periods:
 *   '30d' (default) | '90d' | 'all'
 */
function funnel_period_start(string $period): ?string
{
    switch ($period) {
        case '90d': return date('Y-m-d 00:00:00', strtotime('-90 days'));
        case 'all': return null;
        case '30d':
        default:    return date('Y-m-d 00:00:00', strtotime('-30 days'));
    }
}

/**
 * Count funnel-stage totals for the given period + source filter.
 *
 * Top-of-funnel stages (landing through premium_signup) count distinct
 * visitors so a user firing the same event twice doesn't double-count.
 *
 * download_click only counts visitors who also have a JS-confirmed page view,
 * since bots that fetch the installer URL directly bypass the js_confirmed
 * filter (see the inline comment on $confirmed_visitor_exists).
 *
 * app_first_run counts unattributed installs too (visitor_id NULL) by falling
 * back to the machine_uuid recorded in event_data.
 *
 * premium_paid and premium_churned count distinct subscription_id instead:
 * they're subscription-keyed events that can fire for visitors we never
 * resolved (webhook context), and premium_paid is restricted to the initial
 * payment so renewals/retries don't inflate the "paid" stage above signups.
 */
function get_funnel_stage_counts(?string $period_start, ?string $source_code): array
{
    global $pdo;

    // js_confirmed = 1 filters bots out of the page-view stages (landing,
    // downloads_page); non-page-view stages are inserted already-confirmed.
    $where_clauses = ['environment = ?', 'js_confirmed = 1'];
    $params = [current_environment()];

    $confirmed_visitor_exists = funnel_confirmed_visitor_sql('referral_events');
    $first_run_key = funnel_first_run_key_sql();

    if ($period_start !== null) {
        $where_clauses[] = 'created_at >= ?';
        $params[] = $period_start;
    }
    if ($source_code !== null && $source_code !== '') {
        $where_clauses[] = 'source_code = ?';
        $params[] = $source_code;
    }
    $where = implode(' AND ', $where_clauses);

    $sql = "
        SELECT
          COUNT(DISTINCT CASE WHEN event_type='landing'        THEN visitor_id END) AS landing,
          COUNT(DISTINCT CASE WHEN event_type='downloads_page' THEN visitor_id END) AS downloads_page,
          COUNT(DISTINCT CASE WHEN event_type='download_click'
                               AND $confirmed_visitor_exists
                              THEN visitor_id END) AS download_click,
          COUNT(DISTINCT CASE WHEN event_type='app_first_run' THEN $first_run_key END) AS app_first_run,
          COUNT(DISTINCT CASE WHEN event_type='premium_signup' THEN visitor_id END) AS premium_signup,
          COUNT(DISTINCT CASE WHEN event_type='premium_paid'
                                AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.payment_type')) = 'initial'
                               THEN subscription_id END) AS premium_paid,
          COUNT(DISTINCT CASE WHEN event_type='premium_churned' THEN subscription_id END) AS premium_churned
        FROM referral_events
        WHERE $where";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'landing'         => (int)($row['landing'] ?? 0),
        'downloads_page'  => (int)($row['downloads_page'] ?? 0),
        'download_click'  => (int)($row['download_click'] ?? 0),
        'app_first_run'   => (int)($row['app_first_run'] ?? 0),
        'premium_signup'  => (int)($row['premium_signup'] ?? 0),
        'premium_paid'    => (int)($row['premium_paid'] ?? 0),
        'premium_churned' => (int)($row['premium_churned'] ?? 0),
    ];
}

/**
 * Per-source totals for the comparison table. Joins event counts, ad spend,
 * and premium subscription payment revenue all in a single result set.
 */
function get_funnel_per_source(?string $period_start, string $environment): array
{
    global $pdo;

    // Same bot-filter + first-run dedupe rules as get_funnel_stage_counts(),
    // via the shared fragment helpers ('re' is this query's table alias).
    $confirmed_click = funnel_confirmed_visitor_sql('re');
    $first_run_key   = funnel_first_run_key_sql();

    $params = [$environment];
    $event_period_clause = '';
    if ($period_start !== null) {
        $event_period_clause = ' AND re.created_at >= ?';
        $params[] = $period_start;
    }

    $spend_period_clause = '';
    $params_spend = [];
    if ($period_start !== null) {
        $spend_period_clause = 'WHERE period_start >= ?';
        $params_spend[] = date('Y-m-01', strtotime($period_start));
    }

    // Revenue must scope to the same period as events + spend, otherwise the
    // funnel mixes period-scoped events with all-time revenue and inflates LTV.
    $rev_period_clause = '';
    $params_rev_extra = [];
    if ($period_start !== null) {
        $rev_period_clause = ' AND p.created_at >= ?';
        $params_rev_extra[] = $period_start;
    }

    $sql = "
        SELECT
          rl.source_code,
          rl.name,
          rl.category,
          COALESCE(ev.landings,    0)      AS landings,
          COALESCE(ev.dl_pages,    0)      AS dl_pages,
          COALESCE(ev.dl_clicks,   0)      AS dl_clicks,
          COALESCE(ev.first_runs,  0)      AS first_runs,
          COALESCE(ev.signups,     0)      AS signups,
          COALESCE(ev.paying,      0)      AS paying,
          COALESCE(ev.churned,     0)      AS churned,
          COALESCE(sp.total_spend, 0)      AS spend,
          COALESCE(rv.total_revenue, 0)    AS revenue
        FROM referral_links rl
        LEFT JOIN (
            SELECT
              source_code,
              COUNT(DISTINCT CASE WHEN event_type='landing'        THEN visitor_id END) AS landings,
              COUNT(DISTINCT CASE WHEN event_type='downloads_page' THEN visitor_id END) AS dl_pages,
              COUNT(DISTINCT CASE WHEN event_type='download_click'
                                   AND {$confirmed_click}
                                  THEN visitor_id END) AS dl_clicks,
              COUNT(DISTINCT CASE WHEN event_type='app_first_run'
                                  THEN {$first_run_key} END) AS first_runs,
              COUNT(DISTINCT CASE WHEN event_type='premium_signup' THEN visitor_id END) AS signups,
              COUNT(DISTINCT CASE WHEN event_type='premium_paid'
                                   AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.payment_type')) = 'initial'
                                  THEN subscription_id END) AS paying,
              COUNT(DISTINCT CASE WHEN event_type='premium_churned' THEN subscription_id END) AS churned
            FROM referral_events re
            WHERE re.environment = ? AND re.js_confirmed = 1 $event_period_clause
            GROUP BY source_code
        ) ev ON ev.source_code = rl.source_code
        LEFT JOIN (
            SELECT source_code, SUM(amount) AS total_spend
              FROM campaign_spend
              $spend_period_clause
            GROUP BY source_code
        ) sp ON sp.source_code = rl.source_code
        LEFT JOIN (
            SELECT re2.source_code, SUM(p.amount) AS total_revenue
              FROM referral_events re2
              JOIN premium_subscription_payments p
                ON p.subscription_id = re2.subscription_id
               AND p.status = 'completed'
             WHERE re2.event_type = 'premium_signup'
               AND re2.environment = ?
               $rev_period_clause
             GROUP BY re2.source_code
        ) rv ON rv.source_code = rl.source_code
        WHERE rl.is_active = 1
        ORDER BY landings DESC, rl.created_at DESC";

    $bind = array_merge($params, $params_spend, [$environment], $params_rev_extra);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    return $stmt->fetchAll();
}

/**
 * Counts of survey answers among unattributed (visitor_id IS NULL) app_first_run
 * rows. Returns ordered list of [answer => count] plus an "unanswered" count for
 * the same period. Used by the source-survey breakdown section on the funnel tab.
 */
function get_unattributed_survey_breakdown(?string $period_start, string $environment): array
{
    global $pdo;

    $answer_where = ['environment = ?', "event_type = 'app_first_run'", 'visitor_id IS NULL', 'source_survey_answer IS NOT NULL'];
    $answer_params = [$environment];
    if ($period_start !== null) {
        $answer_where[] = 'source_survey_answered_at >= ?';
        $answer_params[] = $period_start;
    }
    $answer_sql = "
        SELECT source_survey_answer AS answer, COUNT(*) AS count
          FROM referral_events
         WHERE " . implode(' AND ', $answer_where) . "
         GROUP BY source_survey_answer";
    $stmt = $pdo->prepare($answer_sql);
    $stmt->execute($answer_params);
    $by_answer = [];
    while ($row = $stmt->fetch()) {
        $by_answer[(string)$row['answer']] = (int)$row['count'];
    }

    $unanswered_where = ['environment = ?', "event_type = 'app_first_run'", 'visitor_id IS NULL', 'source_survey_answer IS NULL'];
    $unanswered_params = [$environment];
    if ($period_start !== null) {
        $unanswered_where[] = 'created_at >= ?';
        $unanswered_params[] = $period_start;
    }
    $unanswered_sql = "
        SELECT COUNT(*) AS count
          FROM referral_events
         WHERE " . implode(' AND ', $unanswered_where);
    $stmt = $pdo->prepare($unanswered_sql);
    $stmt->execute($unanswered_params);
    $unanswered = (int)($stmt->fetchColumn() ?: 0);

    // Freeform "Other" answers. Group case-insensitively so "youtube channel"
    // and "Youtube channel" collapse, but show one representative casing.
    $other_where = ['environment = ?', "event_type = 'app_first_run'", 'visitor_id IS NULL', "source_survey_answer = 'other'", 'source_survey_other_text IS NOT NULL', "source_survey_other_text <> ''"];
    $other_params = [$environment];
    if ($period_start !== null) {
        $other_where[] = 'source_survey_answered_at >= ?';
        $other_params[] = $period_start;
    }
    $other_sql = "
        SELECT MIN(source_survey_other_text) AS text, COUNT(*) AS count
          FROM referral_events
         WHERE " . implode(' AND ', $other_where) . "
         GROUP BY LOWER(TRIM(source_survey_other_text))
         ORDER BY count DESC, text ASC
         LIMIT 100";
    $stmt = $pdo->prepare($other_sql);
    $stmt->execute($other_params);
    $other_texts = [];
    while ($row = $stmt->fetch()) {
        $other_texts[] = ['text' => (string)$row['text'], 'count' => (int)$row['count']];
    }

    return [
        'by_answer'   => $by_answer,
        'unanswered'  => $unanswered,
        'other_texts' => $other_texts,
    ];
}

function get_campaign_spend_rows(): array
{
    global $pdo;
    $stmt = $pdo->query(
        'SELECT id, source_code, period_start, amount, currency, notes, updated_at
           FROM campaign_spend
          ORDER BY period_start DESC, source_code ASC'
    );
    return $stmt->fetchAll();
}

/**
 * Anonymous in-app activation + early retention, read from the desktop app's
 * telemetry uploads (admin/data-logs/telemetry/). Powers the info popover on the
 * "App first run" funnel stage: of users whose app sent telemetry, how many did a
 * real bookkeeping action and how many came back on a later day.
 *
 * Keyed by the app's anonymous per-device / per-subscription id, so it can't be
 * joined to individual website funnel visitors: it's a separate aggregate view of
 * "what happens after install." Telemetry carries no environment or source, so
 * these counts are all-environment and all-source.
 *
 * Scoped to the same period as the funnel via $period_start (only events on/after
 * it count; null = all time). Note this counts ACTIVE app users in the window
 * (new + returning), which is a different population from the "App first run"
 * install count above, so the two intentionally won't match.
 *
 * @param ?string $period_start 'Y-m-d H:i:s' lower bound, or null for all time.
 * @return array{seen:int, activated:int, returned:int, onboarded:int, skipped:int, activated_pct:float, returned_pct:float, onboarded_pct:float, skipped_pct:float, has_data:bool}
 */
function get_app_activation_stats(?string $period_start = null): array
{
    require_once __DIR__ . '/../../founder_identity.php'; // is_founder_auth_id()

    // "Activation" = the user did a real bookkeeping action (got value from the
    // app), as opposed to setup/scaffolding (creating a customer, supplier,
    // product, or rentable item), cosmetic changes (theme/language), or backups.
    // DataImported covers both bank-statement and spreadsheet imports: the app
    // tags which kind in a field it doesn't send to the server, so they can't be
    // told apart here, but either one counts as a real action.
    $activationFeatures = [
        'InvoiceCreated', 'ExpenseCreated', 'RevenueCreated', 'PaymentRecorded',
        'ReceiptScanned', 'DataImported', 'ReportGenerated',
        'PurchaseOrderCreated', 'StockAdjusted', 'RentalRecordCreated',
    ];
    $dirs = [__DIR__ . '/../data-logs/telemetry/', __DIR__ . '/../data-logs/'];
    $period_ts = ($period_start !== null) ? strtotime($period_start) : null;

    $seenFiles = [];
    $users = [];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '*.json') ?: [] as $file) {
            $name = basename($file);
            if (isset($seenFiles[$name])) {
                continue; // same file in both dirs: count once
            }
            $seenFiles[$name] = true;

            $raw = @file_get_contents($file);
            if ($raw === false || trim($raw) === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['events']) || !is_array($data['events'])) {
                continue;
            }

            $authId = (string)($data['authId'] ?? '');
            if ($authId === '' || is_founder_auth_id($authId)) {
                continue; // unattributable or the founder's own machine
            }

            foreach ($data['events'] as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                $ts = isset($ev['timestamp']) ? strtotime((string)$ev['timestamp']) : false;
                if ($ts === false) {
                    continue;
                }
                // Scope to the funnel's period: skip events before the window.
                // A user only counts if they have at least one in-window event.
                if ($period_ts !== null && $ts < $period_ts) {
                    continue;
                }
                if (!isset($users[$authId])) {
                    $users[$authId] = ['days' => [], 'activated' => false, 'onboarded' => false, 'skipped' => false];
                }
                $users[$authId]['days'][gmdate('Y-m-d', $ts)] = true;
                if (($ev['dataType'] ?? '') === 'FeatureUsage') {
                    $fn = $ev['featureName'] ?? '';
                    if (in_array($fn, $activationFeatures, true)) {
                        $users[$authId]['activated'] = true;
                    } elseif ($fn === 'OnboardingCompleted') {
                        $users[$authId]['onboarded'] = true;
                    } elseif ($fn === 'OnboardingSkipped') {
                        $users[$authId]['skipped'] = true;
                    }
                }
            }
        }
    }

    $seen = count($users);
    $activated = 0;
    $returned = 0;
    $onboarded = 0;
    $skipped = 0;
    foreach ($users as $u) {
        if ($u['activated']) {
            $activated++;
        }
        if (count($u['days']) >= 2) {
            $returned++;
        }
        if ($u['onboarded']) {
            $onboarded++;
        }
        if ($u['skipped']) {
            $skipped++;
        }
    }

    return [
        'seen'          => $seen,
        'activated'     => $activated,
        'returned'      => $returned,
        'onboarded'     => $onboarded,
        'skipped'       => $skipped,
        'activated_pct' => $seen > 0 ? round($activated / $seen * 100, 1) : 0.0,
        'returned_pct'  => $seen > 0 ? round($returned / $seen * 100, 1) : 0.0,
        'onboarded_pct' => $seen > 0 ? round($onboarded / $seen * 100, 1) : 0.0,
        'skipped_pct'   => $seen > 0 ? round($skipped / $seen * 100, 1) : 0.0,
        'has_data'      => $seen > 0,
    ];
}

/**
 * Invoice-generator tool metrics, all derived from the statistics table.
 *
 * Event shape (see api/invoice-generator/track.php and statistics.php):
 *   - Page views into the tool ecosystem are written by track_page_view()
 *     with event_type='page_view' and event_data='invgen_tool' or
 *     'invgen_niche_<slug>'.
 *   - Explicit events (downloads, CTA clicks, etc.) are written by
 *     track_event() with event_type='invgen_*' and event_data describing
 *     the variant (template name, placement, slug, etc.).
 *
 * Note: statistics has no environment column, so the tool metrics are not
 * environment-scoped. statistics.track_event() also dedupes by IP per day,
 * which means counts are unique-visitor-per-day rather than raw events.
 */

/**
 * Run one tool-metric query with the optional period filter spliced in.
 * $sql must contain a {PERIOD} placeholder inside its WHERE clause; it becomes
 * " AND created_at >= ?" when a period is set and '' for all-time.
 */
function tool_stat_rows(string $sql, ?string $period_start): array
{
    global $pdo;
    $sql = str_replace('{PERIOD}', $period_start !== null ? ' AND created_at >= ?' : '', $sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($period_start !== null ? [$period_start] : []);
    return $stmt->fetchAll();
}

function get_tool_sessions_per_day(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT DATE(created_at) AS day, COUNT(*) AS sessions
           FROM statistics
          WHERE event_type = 'page_view'
            AND event_data LIKE 'invgen\\_%'{PERIOD}
       GROUP BY day
       ORDER BY day ASC",
        $period_start
    );
}

function get_tool_downloads_per_day(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT DATE(created_at) AS day,
                SUM(event_type = 'invgen_pdf_downloaded')  AS pdf,
                SUM(event_type = 'invgen_docx_downloaded') AS docx
           FROM statistics
          WHERE event_type IN ('invgen_pdf_downloaded','invgen_docx_downloaded'){PERIOD}
       GROUP BY day
       ORDER BY day ASC",
        $period_start
    );
}

function get_cta_ctr_by_placement(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT event_data AS placement, COUNT(*) AS clicks
           FROM statistics
          WHERE event_type = 'invgen_cta_clicked'{PERIOD}
       GROUP BY event_data
       ORDER BY clicks DESC",
        $period_start
    );
}

function get_niche_traffic(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT REPLACE(event_data, 'invgen_niche_', '') AS niche, COUNT(*) AS views
           FROM statistics
          WHERE event_type = 'page_view'
            AND event_data LIKE 'invgen\\_niche\\_%'{PERIOD}
       GROUP BY event_data
       ORDER BY views DESC",
        $period_start
    );
}

/**
 * Phase B template library metrics.
 *
 * Page views into /invoice-template/{slug}/ are written by track_page_view()
 * with event_type='page_view' and event_data='invgen_template_<slug>'. CTA
 * clicks and direct downloads are written by track_event() with event_type
 * 'invgen_template_cta_clicked' or 'invgen_template_download' and event_data
 * '<style>|<format>'.
 */
function get_template_page_views(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT REPLACE(event_data, 'invgen_template_', '') AS slug, COUNT(*) AS views
           FROM statistics
          WHERE event_type = 'page_view'
            AND event_data LIKE 'invgen\\_template\\_%'{PERIOD}
       GROUP BY event_data
       ORDER BY views DESC",
        $period_start
    );
}

function get_template_cta_clicks(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT event_data AS style_format, COUNT(*) AS clicks
           FROM statistics
          WHERE event_type = 'invgen_template_cta_clicked'{PERIOD}
       GROUP BY event_data
       ORDER BY clicks DESC",
        $period_start
    );
}

function get_template_downloads(?string $period_start): array
{
    return tool_stat_rows(
        "SELECT event_data AS style_format, COUNT(*) AS downloads
           FROM statistics
          WHERE event_type = 'invgen_template_download'{PERIOD}
       GROUP BY event_data
       ORDER BY downloads DESC",
        $period_start
    );
}

// Resolve which tab is active. Default to funnel.
$allowed_tabs = ['funnel', 'spend', 'tools'];
$current_tab  = $_GET['tab'] ?? 'funnel';
if (!in_array($current_tab, $allowed_tabs, true)) {
    $current_tab = 'funnel';
}

$funnel_period_key = $_GET['funnel_period'] ?? '30d';
if (!in_array($funnel_period_key, ['30d', '90d', 'all'], true)) {
    $funnel_period_key = '30d';
}
$funnel_period_start_dt = funnel_period_start($funnel_period_key);

$funnel_source_filter = $_GET['source'] ?? '';
if ($funnel_source_filter !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $funnel_source_filter)) {
    $funnel_source_filter = '';
}

$referral_links = get_active_referral_links();

include __DIR__ . '/../admin_header.php';
?>

<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="vendor/jsvectormap.min.css">

<div class="container">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message">
            <?php
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="section-tabs">
        <button class="section-tab <?php echo $current_tab === 'funnel' ? 'active' : ''; ?>" data-tab="funnel">Funnel</button>
        <button class="section-tab <?php echo $current_tab === 'spend'  ? 'active' : ''; ?>" data-tab="spend">Spend</button>
        <button class="section-tab <?php echo $current_tab === 'tools'  ? 'active' : ''; ?>" data-tab="tools">Tools</button>
    </div>

    <div id="funnel" class="tab-content <?php echo $current_tab === 'funnel' ? 'active' : ''; ?>">
    <?php
        $funnel_counts = get_funnel_stage_counts($funnel_period_start_dt, $funnel_source_filter ?: null);
        $per_source = get_funnel_per_source($funnel_period_start_dt, current_environment());

        // Source-survey breakdown: unattributed installs only (visitor_id IS NULL),
        // so it doesn't matter whether a specific source pill is selected. It
        // always reflects "users we couldn't attribute by token".
        $survey_breakdown = get_unattributed_survey_breakdown($funnel_period_start_dt, current_environment());

        // Plausible-style breakdowns for the channel donut, the referrer /
        // campaign bar lists, and the map / country / region / city
        // lists. Each row carries visits + attributed revenue.
        $analytics = build_funnel_analytics($funnel_period_start_dt, $funnel_source_filter ?: null, $referral_links);

        $total_spend = 0.0;
        $total_revenue = 0.0;
        foreach ($per_source as $row) {
            if ($funnel_source_filter !== '' && $row['source_code'] !== $funnel_source_filter) {
                continue;
            }
            $total_spend   += (float)$row['spend'];
            $total_revenue += (float)$row['revenue'];
        }

        $total_paying = $funnel_counts['premium_paid'];
        $cac = $total_paying > 0 ? $total_spend / $total_paying : null;
        $ltv = $total_paying > 0 ? $total_revenue / $total_paying : null;
        $ltv_cac = ($cac !== null && $cac > 0 && $ltv !== null) ? $ltv / $cac : null;

        $ratio_class = 'ratio-organic';
        $ratio_suffix = 'free';
        if ($total_spend > 0) {
            if ($ltv_cac === null) {
                $ratio_class = 'ratio-losing';
                $ratio_suffix = 'no customers';
            } elseif ($ltv_cac < 1.0) {
                $ratio_class = 'ratio-losing';
                $ratio_suffix = 'losing money';
            } elseif ($ltv_cac < 3.0) {
                $ratio_class = 'ratio-marginal';
                $ratio_suffix = 'marginal';
            } else {
                $ratio_class = 'ratio-profitable';
                $ratio_suffix = 'profitable';
            }
        }

        $stage_defs = [
            ['key' => 'landing',         'label' => 'Landing'],
            ['key' => 'downloads_page',  'label' => 'Downloads page'],
            ['key' => 'download_click',  'label' => 'Download click'],
            ['key' => 'app_first_run',   'label' => 'App first run'],
            ['key' => 'premium_signup',  'label' => 'Premium signup'],
            ['key' => 'premium_paid',    'label' => 'Premium paid'],
        ];

        // Per-stage top sources / countries for the funnel hover tooltip.
        $stage_countries = $analytics['stage_countries'];
        $stage_sources   = $analytics['stage_sources'];
        $name_by_source_code = [];
        foreach ($referral_links as $rl) {
            $name_by_source_code[$rl['source_code']] = referral_display_name($rl['category'], $rl['name'], $rl['source_code']);
        }
        $fmt_stage_rows = function (array $bucket, bool $is_country) use ($name_by_source_code): array {
            $out = [];
            foreach (($bucket['rows'] ?? []) as $r) {
                if ($is_country) {
                    $label = ($r['k'] === null || $r['k'] === '') ? 'Unknown' : (country_name($r['k']) ?: $r['k']);
                    $flag  = ($r['k'] === null || $r['k'] === '') ? '' : funnel_flag_emoji($r['k']);
                } else {
                    $label = ($r['k'] === null || $r['k'] === '') ? FUNNEL_DIRECT_LABEL : ($name_by_source_code[$r['k']] ?? $r['k']);
                    $flag  = '';
                }
                $out[] = ['label' => $label, 'flag' => $flag, 'pct' => $r['pct']];
            }
            return $out;
        };

        $top_count = max(1, $funnel_counts[$stage_defs[0]['key']]);
        $funnel_stages = [];
        $prev_count = null;
        foreach ($stage_defs as $sd) {
            $c = (int)$funnel_counts[$sd['key']];
            $pct_of_top = $top_count > 0 ? round(($c / $top_count) * 100, 1) : 0;
            $retained = ($prev_count !== null && $prev_count > 0) ? round(($c / $prev_count) * 100, 1) : null;
            $lost     = ($retained !== null) ? round(100 - $retained, 1) : null;
            $funnel_stages[] = [
                'key' => $sd['key'], 'label' => $sd['label'], 'count' => $c,
                'pct_of_top' => $pct_of_top, 'retained' => $retained, 'lost' => $lost,
                'dropoff'    => ($prev_count !== null) ? ($prev_count - $c) : null,
                'step_value' => $c > 0 ? round($total_revenue / $c, 2) : 0,
                'top_countries' => $fmt_stage_rows($stage_countries[$sd['key']] ?? [], true),
                'top_sources'   => $fmt_stage_rows($stage_sources[$sd['key']] ?? [], false),
            ];
            $prev_count = $c;
        }

        // Anonymous in-app activation/retention for the "App first run" info
        // popover, scoped to the same period as the funnel.
        $app_activation = get_app_activation_stats($funnel_period_start_dt);

        $biggest_drop_index = null;
        $biggest_drop_pct = 101;
        foreach ($funnel_stages as $i => $s) {
            if ($s['retained'] !== null && $s['retained'] < $biggest_drop_pct) {
                $biggest_drop_pct = $s['retained'];
                $biggest_drop_index = $i;
            }
        }
    ?>
    <div class="control-bar">
        <div class="control-group">
            <span class="control-label">Period:</span>
            <div class="control-pills">
                <?php foreach (['30d' => 'Last 30 days', '90d' => 'Last 90 days', 'all' => 'All time'] as $pkey => $plabel):
                    $href_params = ['tab' => 'funnel', 'funnel_period' => $pkey];
                    if ($funnel_source_filter !== '') $href_params['source'] = $funnel_source_filter;
                    $href = 'index.php?' . http_build_query($href_params);
                ?>
                    <a href="<?php echo htmlspecialchars($href); ?>"
                       class="control-pill <?php echo $funnel_period_key === $pkey ? 'active' : ''; ?>">
                        <?php echo $plabel; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="control-group control-spacer">
        <span class="control-label">Source:</span>
        <div class="source-combobox" id="sourceCombobox" data-period="<?php echo htmlspecialchars($funnel_period_key); ?>">
            <input type="text" class="source-combobox-input" id="sourceComboboxInput"
                   autocomplete="off" spellcheck="false" placeholder="Search sources&hellip;"
                   value="<?php echo $funnel_source_filter === '' ? 'All traffic' : htmlspecialchars($funnel_source_filter); ?>"
                   aria-label="Filter funnel by source" role="combobox" aria-expanded="false">
            <span class="source-combobox-caret" aria-hidden="true">&#9662;</span>
            <ul class="source-combobox-list" id="sourceComboboxList" role="listbox">
                <li class="source-combobox-option<?php echo $funnel_source_filter === '' ? ' active' : ''; ?>"
                    data-source="" role="option">All traffic</li>
                <?php
                    $sorted_links = $referral_links;
                    usort($sorted_links, fn($a, $b) => strcmp($a['source_code'], $b['source_code']));
                    foreach ($sorted_links as $rl):
                ?>
                    <li class="source-combobox-option<?php echo $funnel_source_filter === $rl['source_code'] ? ' active' : ''; ?>"
                        data-source="<?php echo htmlspecialchars($rl['source_code']); ?>" role="option"><?php echo htmlspecialchars($rl['source_code']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card hero">
            <h3>Ad Spend</h3>
            <div class="value">$<?php echo number_format($total_spend, 2); ?></div>
            <div class="subtext"><?php echo $funnel_period_key === 'all' ? 'all time' : 'in selected period'; ?></div>
        </div>
        <div class="stat-card hero">
            <h3>Paying Customers</h3>
            <div class="value"><?php echo number_format($total_paying); ?></div>
            <div class="subtext">initial Premium payments</div>
        </div>
        <div class="stat-card hero">
            <h3>Customer Acquisition Cost</h3>
            <div class="value"><?php echo $cac !== null ? '$' . number_format($cac, 2) : '—'; ?></div>
            <div class="subtext">spend &divide; paying customers</div>
        </div>
        <div class="stat-card hero <?php echo $ratio_class; ?>">
            <h3>Lifetime Value : Customer Acquisition Cost</h3>
            <div class="value">
                <?php
                    if ($total_spend == 0 && $total_paying > 0) {
                        echo '∞';
                    } elseif ($ltv_cac !== null) {
                        echo number_format($ltv_cac, 2) . '×';
                    } else {
                        echo '—';
                    }
                ?>
                <span class="suffix"><?php echo htmlspecialchars($ratio_suffix); ?></span>
            </div>
            <div class="subtext">
                LTV: <?php echo $ltv !== null ? '$' . number_format($ltv, 2) : '—'; ?>
            </div>
        </div>
    </div>

    <?php
        // Date-range label for the funnel header, e.g. "Jun 15 → Jul 15".
        $range_start_label = $funnel_period_start_dt !== null
            ? date('M j', strtotime($funnel_period_start_dt))
            : 'All time';
        $range_end_label = date('M j');
        $range_label = $funnel_period_start_dt !== null
            ? ($range_start_label . ' → ' . $range_end_label)
            : 'All time';
    ?>
    <div class="funnel-card">
        <div class="funnel-card-head">
            <h2>
                Conversion Funnel
                <?php if ($funnel_source_filter !== ''): ?>
                    <span class="source-tag"><?php echo htmlspecialchars($funnel_source_filter); ?></span>
                <?php else: ?>
                    <span class="source-tag">all traffic</span>
                <?php endif; ?>
            </h2>
            <div class="funnel-conv">
                <span class="funnel-conv-range"><?php echo htmlspecialchars($range_label); ?></span>
            </div>
        </div>
        <div class="funnel-stream" id="funnelStream"
             data-biggest="<?php echo $biggest_drop_index === null ? -1 : (int)$biggest_drop_index; ?>"></div>
        <div class="funnel-tip" id="funnelTip" role="tooltip" aria-hidden="true"></div>
    </div>

    <?php
        // Server-rendered breakdown lists (dual blue/orange bars). The Channel
        // donut and the world map are drawn client-side from the JSON below.
        $bd_ref      = funnel_render_bar_list($analytics['referrers'], ['revenue' => true,  'limit' => 9, 'icon' => true]);
        $bd_campaign = funnel_render_bar_list($analytics['campaigns'], ['revenue' => true,  'limit' => 9, 'icon' => true, 'empty' => 'No tracked source categories in this period yet.']);
        $bd_country  = funnel_render_bar_list($analytics['countries'], ['revenue' => true,  'limit' => 9, 'flag' => true]);
        $bd_region   = funnel_render_bar_list($analytics['regions'],   ['revenue' => false, 'limit' => 9, 'empty' => 'No region data yet. Collecting going forward.']);
        $bd_city     = funnel_render_bar_list($analytics['cities'],    ['revenue' => false, 'limit' => 9, 'empty' => 'No city data yet. Collecting going forward.']);

        // Page breakdowns (popular / entry / exit) come from the site-wide
        // page_view stream, not the referral funnel, so they ignore the source
        // pill and only honor the period window.
        $pages       = funnel_page_breakdowns($funnel_period_start_dt);

        // Entry pages come from the referral funnel, not the page_view stream.
        // track_page_view() stores a page *name* ("invgen_tool"), so those rows
        // cannot be joined to the funnel and would show zero installs against
        // every page. Sourcing all three columns from referral_events keeps
        // visits, installs and payments on one definition of a visitor.
        $entry_rows = $analytics['entry_pages'] ?? [];

        $bd_popular  = funnel_render_bar_list($pages['popular'], ['revenue' => false, 'limit' => 9, 'empty' => 'No page views in this period yet.']);
        $bd_entry    = funnel_render_bar_list($entry_rows,       ['revenue' => false, 'limit' => 9, 'empty' => 'No entry pages in this period yet.']);
        $bd_exit     = funnel_render_bar_list($pages['exit'],    ['revenue' => false, 'limit' => 9, 'empty' => 'No exit pages in this period yet.']);

        $channel_total_visits = array_sum(array_map(fn($r) => (int)$r['visits'], $analytics['channels']));
    ?>
    <div class="analytics-row">
        <!-- Traffic sources: channel / referrer / campaign -->
        <div class="analytics-card">
            <div class="bd-tabs" role="tablist">
                <button class="bd-tab active" data-bd="channel">Channel</button>
                <button class="bd-tab" data-bd="referrer">Referrer</button>
                <button class="bd-tab" data-bd="campaign">Category</button>
                <?php echo funnel_render_download_btn('traffic-sources', [
                    funnel_export_dataset('Channel',  $analytics['channels'],  true),
                    funnel_export_dataset('Referrer', $analytics['referrers'], true, true),
                    funnel_export_dataset('Category', $analytics['campaigns'], true),
                ]); ?>
            </div>

            <div class="bd-panel active" data-bd-panel="channel">
                <?php echo funnel_render_panel_desc('Visits grouped into broad acquisition channels, worked out from the referring site and the tracked source code.'); ?>
                <?php if ($channel_total_visits > 0): ?>
                    <div class="donut-wrap">
                        <div class="donut-canvas">
                            <canvas id="channelDonut"></canvas>
                            <div class="donut-center">
                                <span class="dc-num"><?php echo funnel_kfmt($channel_total_visits); ?></span>
                                <span class="dc-lbl">visitors</span>
                            </div>
                        </div>
                        <ul class="donut-legend" id="channelLegend"></ul>
                    </div>
                <?php else: ?>
                    <div class="bd-empty">No traffic in this period yet.</div>
                <?php endif; ?>
            </div>

            <div class="bd-panel" data-bd-panel="referrer">
                <?php echo funnel_render_panel_desc('The site each visitor arrived from, with the installs and payments they went on to produce. "Direct / None" means no referrer was sent, which includes bookmarks, typed URLs and most clicks from outside a browser.'); ?>
                <?php echo $bd_ref; ?>
                <?php echo funnel_render_details_btn('referrerDetailsModal', $analytics['referrers']); ?>
            </div>

            <div class="bd-panel" data-bd-panel="campaign">
                <?php echo funnel_render_panel_desc('Visits grouped by the category of the tracked referral link they used. Untracked and direct traffic is excluded.'); ?>
                <?php echo $bd_campaign; ?>
            </div>
        </div>

        <!-- Geography: map / country / region / city -->
        <div class="analytics-card">
            <div class="bd-tabs" role="tablist">
                <button class="bd-tab active" data-bd="map">Map</button>
                <button class="bd-tab" data-bd="country">Country</button>
                <button class="bd-tab" data-bd="region">Region</button>
                <button class="bd-tab" data-bd="city">City</button>
                <?php echo funnel_render_download_btn('geography', [
                    funnel_export_dataset('Country', $analytics['countries'], true),
                    funnel_export_dataset('Region',  $analytics['regions']),
                    funnel_export_dataset('City',    $analytics['cities']),
                ]); ?>
            </div>

            <div class="bd-panel active" data-bd-panel="map">
                <?php echo funnel_render_panel_desc('Visitor counts by country, shaded darker for more visits. Hover a country for its total.'); ?>
                <div class="world-map" id="worldMap"></div>
                <div class="map-legend">
                    <span class="map-legend-label">Fewer</span>
                    <span class="map-legend-gradient"></span>
                    <span class="map-legend-label">More visits</span>
                </div>
            </div>
            <div class="bd-panel" data-bd-panel="country">
                <?php echo funnel_render_panel_desc('Visitors and attributed revenue by country, resolved from IP address. Useful for spotting spend reaching places you do not sell to.'); ?>
                <?php echo $bd_country; ?>
                <?php echo funnel_render_details_btn('countryDetailsModal', $analytics['countries']); ?>
            </div>
            <div class="bd-panel" data-bd-panel="region">
                <?php echo funnel_render_panel_desc('Province or state, resolved from IP address. Best-effort, and often unknown.'); ?>
                <?php echo $bd_region; ?>
                <?php echo funnel_render_details_btn('regionDetailsModal', $analytics['regions']); ?>
            </div>
            <div class="bd-panel" data-bd-panel="city">
                <?php echo funnel_render_panel_desc('City, resolved from IP address. Best-effort, and often unknown.'); ?>
                <?php echo $bd_city; ?>
                <?php echo funnel_render_details_btn('cityDetailsModal', $analytics['cities']); ?>
            </div>
        </div>
    </div>

    <!-- Pages: most popular / entry / exit (site-wide page_view stream) -->
    <div class="analytics-row analytics-row-full">
        <div class="analytics-card">
            <div class="bd-tabs" role="tablist">
                <button class="bd-tab active" data-bd="popular">Popular pages</button>
                <button class="bd-tab" data-bd="entry">Entry page</button>
                <button class="bd-tab" data-bd="exit">Exit page</button>
                <?php echo funnel_render_download_btn('pages', [
                    funnel_export_dataset('Popular pages', $pages['popular']),
                    funnel_export_dataset('Entry pages',   $entry_rows, false, true),
                    funnel_export_dataset('Exit pages',    $pages['exit']),
                ]); ?>
            </div>

            <div class="bd-panel active" data-bd-panel="popular">
                <?php echo funnel_render_panel_desc('Every page view in the period, counted once per visitor per day. Shows what gets read, not what converts.'); ?>
                <?php echo $bd_popular; ?>
                <?php echo funnel_render_details_btn('popularPagesDetailsModal', $pages['popular']); ?>
            </div>
            <div class="bd-panel" data-bd-panel="entry">
                <?php echo funnel_render_panel_desc('The first page each visitor landed on, with the installs and payments that visitor later produced. This is the tab that shows which pages earn, rather than which get read.'); ?>
                <?php echo $bd_entry; ?>
                <?php echo funnel_render_details_btn('entryPagesDetailsModal', $entry_rows); ?>
            </div>
            <div class="bd-panel" data-bd-panel="exit">
                <?php echo funnel_render_panel_desc('The last page a visitor saw before leaving. A high count is normal for pages that end a journey, such as the downloads page.'); ?>
                <?php echo $bd_exit; ?>
                <?php echo funnel_render_details_btn('exitPagesDetailsModal', $pages['exit']); ?>
            </div>
        </div>
    </div>

    <!-- Full breakdown lists (opened from each tab's "See all details") -->
    <?php
        echo funnel_render_details_modal('referrerDetailsModal', 'Referrer', $analytics['referrers'], true);
        echo funnel_render_details_modal('countryDetailsModal',  'Country',  $analytics['countries'], true);
        echo funnel_render_details_modal('regionDetailsModal',   'Region',   $analytics['regions'],   false);
        echo funnel_render_details_modal('cityDetailsModal',     'City',     $analytics['cities'],    false);
        echo funnel_render_details_modal('popularPagesDetailsModal', 'Most popular pages', $pages['popular'], false, 'Page');
        echo funnel_render_details_modal('entryPagesDetailsModal',   'Entry pages',        $entry_rows,       false, 'Page');
        echo funnel_render_details_modal('exitPagesDetailsModal',    'Exit pages',         $pages['exit'],    false, 'Page');
    ?>

        <?php
            $survey_by_answer = $survey_breakdown['by_answer'];
            $survey_unanswered = (int)$survey_breakdown['unanswered'];
            $survey_total = array_sum($survey_by_answer) + $survey_unanswered;
            $survey_show = $survey_total > 0;
            $survey_option_order = ['google','bing','youtube','reddit','friend','email','other'];
            $survey_option_labels = [
                'google'  => 'Google',
                'bing'    => 'Bing',
                'youtube' => 'YouTube',
                'reddit'  => 'Reddit',
                'friend'  => 'A friend',
                'email'   => 'Email',
                'other'   => 'Other',
            ];

            // Build chart arrays (skip zero-count buckets so the doughnut isn't
            // cluttered with empty legend entries). Unanswered is included as a
            // gray slice at the end so the totals still add up to the unattributed
            // install volume.
            $survey_chart_rows = [];
            foreach ($survey_option_order as $opt) {
                $c = (int)($survey_by_answer[$opt] ?? 0);
                if ($c > 0) {
                    $survey_chart_rows[] = ['label' => $survey_option_labels[$opt], 'count' => $c];
                }
            }
            if ($survey_unanswered > 0) {
                $survey_chart_rows[] = ['label' => 'Unanswered', 'count' => $survey_unanswered];
            }
            $survey_chart_labels = array_map(fn($r) => $r['label'], $survey_chart_rows);
            $survey_chart_counts = array_map(fn($r) => $r['count'], $survey_chart_rows);
        ?>
        <?php if ($survey_show): ?>
            <div class="landing-breakdown">
                <h3>Unattributed install sources (survey)</h3>
                <p class="muted-note">
                    In-app survey answers from users whose install we couldn't attribute by token
                    (no referral cookie at install time).
                </p>
                <div class="landing-breakdown-body">
                    <div class="landing-breakdown-chart">
                        <canvas id="surveySourcesChart"></canvas>
                    </div>
                    <ul class="landing-breakdown-list">
                        <?php foreach ($survey_chart_rows as $i => $row):
                            $pct = $survey_total > 0 ? round(($row['count'] / $survey_total) * 100, 1) : 0;
                        ?>
                            <li>
                                <span class="swatch" data-survey-swatch-idx="<?php echo $i; ?>"></span>
                                <span class="lbl"><?php echo htmlspecialchars($row['label']); ?></span>
                                <span class="pct"><?php echo $pct; ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php $other_texts = $survey_breakdown['other_texts'] ?? []; ?>
                <?php if (!empty($other_texts)): ?>
                    <details class="survey-other-details" open>
                        <summary>What did "Other" respondents say? (<?php echo count($other_texts); ?> unique)</summary>
                        <ul class="survey-other-list">
                            <?php foreach ($other_texts as $row): ?>
                                <li>
                                    <span class="lbl"><?php echo htmlspecialchars($row['text']); ?></span>
                                    <span class="count">×<?php echo number_format($row['count']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
            // Users by source. get_funnel_per_source() has always returned first_runs
            // alongside everything else, but nothing rendered it: the rows were used only
            // to total spend and revenue. Answering "where did the people who actually ran
            // the app come from" meant clicking each source pill in turn and writing the
            // numbers down.
            //
            // Sorted by installs, not by landings as the query is, because a source with
            // 800 visitors and no installs tells you less than one with four visitors and
            // three installs.
            // A source nobody has ever arrived through is a dead link, not a result. 41 of
            // 168 are in that state, and they pad the table and the export without ever
            // saying anything. Dropped outright rather than left to the toggle, which is
            // about sources that brought visitors but no users: a different question.
            //
            // The install and paying guards are belt and braces. Attribution should not be
            // able to record either without a landing, but if it ever did, that row would
            // be worth seeing rather than silently discarded.
            $install_rows = array_values(array_filter($per_source, static function ($r) {
                return (int)$r['landings'] > 0
                    || (int)$r['first_runs'] > 0
                    || (int)$r['paying'] > 0;
            }));
            // Columns read left to right as the funnel runs, landings through to paying.
            // The sort deliberately does not follow that: rows are ordered by the far end,
            // paying then installs, so the sources that produced someone sit at the top
            // rather than the ones that merely produced traffic. Paying is all zeroes today,
            // in which case this falls through to installs and nothing looks different.
            usort($install_rows, static function ($a, $b) {
                return ((int)$b['paying'] <=> (int)$a['paying'])
                    ?: ((int)$b['first_runs'] <=> (int)$a['first_runs'])
                    ?: ((int)$b['dl_clicks'] <=> (int)$a['dl_clicks']);
            });

            $sources_with_installs = 0;
            $attributed_installs = 0;
            $install_export = [];
            foreach ($install_rows as $r) {
                if ((int)$r['first_runs'] > 0 || (int)$r['paying'] > 0) {
                    $sources_with_installs++;
                    $attributed_installs += (int)$r['first_runs'];
                }
                // Every source, including the ones the on-screen toggle hides. A CSV is
                // for slicing elsewhere, and an export that silently mirrors a checkbox
                // is the kind of thing you only notice after trusting a total.
                $install_export[] = [
                    referral_display_name($r['category'], $r['name'], $r['source_code']),
                    (string)$r['source_code'],
                    (int)$r['landings'],
                    (int)$r['dl_clicks'],
                    (int)$r['first_runs'],
                    (int)$r['paying'],
                ];
            }
        ?>
        <div class="table-container funnel-users-card">
            <div class="installs-head">
                <h3 style="margin:0;">Users by source</h3>
                <?php echo funnel_render_download_btn('users-by-source', [[
                    'title'   => 'Users by source',
                    'columns' => ['Source', 'Source code', 'Landings', 'Download clicks', 'Installs', 'Paying'],
                    'rows'    => $install_export,
                ]]); ?>
            </div>
            <?php echo funnel_render_panel_desc(
                'Sources that produced a real user: someone who ran the app, and whoever went'
                . ' on to pay. Only counts people whose referral token survived the 14 day'
                . ' attribution window, so the survey above covers the rest.'
            ); ?>

            <?php if (empty($install_rows)): ?>
                <div class="empty-state"><p>No active referral sources yet.</p></div>
            <?php else: ?>
                <label class="funnel-installs-toggle">
                    <input type="checkbox" id="funnelHideZeroUsers" checked>
                    Hide sources with no users
                    <span class="subtext">
                        (<?php echo number_format($sources_with_installs); ?> of
                        <?php echo number_format(count($install_rows)); ?> sources with traffic,
                        <?php echo number_format($attributed_installs); ?> attributed
                        install<?php echo $attributed_installs === 1 ? '' : 's'; ?>)
                    </span>
                </label>

                <div class="table-responsive">
                    <table id="funnelUsersTable">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Landings</th>
                                <th>Download clicks</th>
                                <th>Installs</th>
                                <th>Paying</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($install_rows as $row): ?>
                                <?php
                                    $runs = (int)$row['first_runs'];
                                    // Hidden only when a source produced neither. A payment
                                    // without an attributed install is unlikely but possible,
                                    // and hiding that row would bury the best thing on the page.
                                    $no_users = $runs === 0 && (int)$row['paying'] === 0;
                                ?>
                                <tr<?php echo $no_users ? ' data-no-installs="1"' : ''; ?>>
                                    <td>
                                        <?php echo htmlspecialchars(referral_display_name($row['category'], $row['name'], $row['source_code'])); ?>
                                        <span class="subtext"><?php echo htmlspecialchars($row['source_code']); ?></span>
                                    </td>
                                    <td><?php echo number_format((int)$row['landings']); ?></td>
                                    <td><?php echo number_format((int)$row['dl_clicks']); ?></td>
                                    <td><strong><?php echo number_format($runs); ?></strong></td>
                                    <td><strong><?php echo number_format((int)$row['paying']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div><!-- /#funnel -->

    <div id="spend" class="tab-content <?php echo $current_tab === 'spend' ? 'active' : ''; ?>">
    <?php $spend_rows = get_campaign_spend_rows(); ?>
    <div class="table-container">
        <div class="spend-actions">
            <h2 style="margin:0;">Ad Spend</h2>
            <button id="addSpendBtn" class="btn btn-blue">Add ad spend</button>
        </div>
        <p class="subtext" style="margin-top:0;">
            Enter what you actually paid each ad platform per month. The Funnel tab uses this to compute
            Customer Acquisition Cost (CAC) and the Lifetime Value : CAC ratio.
        </p>

        <?php if (empty($spend_rows)): ?>
            <div class="empty-state">
                <p>No ad spend recorded yet.</p>
                <p>Add a month of spend to start seeing CAC and LTV:CAC on the Funnel tab.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Month</th>
                            <th>Amount</th>
                            <th>Currency</th>
                            <th>Notes</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spend_rows as $sr): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($sr['source_code']); ?></code></td>
                                <td><?php echo htmlspecialchars(date('F Y', strtotime($sr['period_start']))); ?></td>
                                <td>$<?php echo number_format((float)$sr['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($sr['currency']); ?></td>
                                <td><?php echo htmlspecialchars(substr($sr['notes'] ?? '', 0, 60)); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($sr['updated_at']))); ?></td>
                                <td class="action-buttons">
                                    <button class="btn-small btn-blue"
                                            onclick='editSpend(<?php echo json_encode($sr); ?>)'>Edit</button>
                                    <button class="btn-small btn-red"
                                            onclick="deleteSpend(<?php echo (int)$sr['id']; ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    </div><!-- /#spend -->

    <div id="tools" class="tab-content <?php echo $current_tab === 'tools' ? 'active' : ''; ?>">
    <?php
        $tool_sessions    = get_tool_sessions_per_day($funnel_period_start_dt);
        $tool_downloads   = get_tool_downloads_per_day($funnel_period_start_dt);
        $tool_cta_clicks  = get_cta_ctr_by_placement($funnel_period_start_dt);
        $tool_niche_views = get_niche_traffic($funnel_period_start_dt);

        $total_tool_sessions = 0;
        foreach ($tool_sessions as $r) { $total_tool_sessions += (int)$r['sessions']; }
        $total_tool_pdf = 0; $total_tool_docx = 0;
        foreach ($tool_downloads as $r) {
            $total_tool_pdf  += (int)$r['pdf'];
            $total_tool_docx += (int)$r['docx'];
        }
        $total_tool_downloads = $total_tool_pdf + $total_tool_docx;
        $total_tool_clicks = 0;
        foreach ($tool_cta_clicks as $r) { $total_tool_clicks += (int)$r['clicks']; }

        $download_rate = $total_tool_sessions > 0
            ? round(($total_tool_downloads / $total_tool_sessions) * 100, 1)
            : null;
        $cta_rate = $total_tool_sessions > 0
            ? round(($total_tool_clicks / $total_tool_sessions) * 100, 1)
            : null;
    ?>
    <div class="control-bar">
        <div class="control-group">
            <span class="control-label">Period:</span>
            <div class="control-pills">
                <?php foreach (['30d' => 'Last 30 days', '90d' => 'Last 90 days', 'all' => 'All time'] as $pkey => $plabel):
                    $href = 'index.php?' . http_build_query(['tab' => 'tools', 'funnel_period' => $pkey]);
                ?>
                    <a href="<?php echo htmlspecialchars($href); ?>"
                       class="control-pill <?php echo $funnel_period_key === $pkey ? 'active' : ''; ?>">
                        <?php echo $plabel; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Tool sessions</h3>
            <div class="value"><?php echo number_format($total_tool_sessions); ?></div>
            <div class="subtext">unique visitors per day across invoice-generator pages</div>
        </div>
        <div class="stat-card">
            <h3>Downloads</h3>
            <div class="value"><?php echo number_format($total_tool_downloads); ?></div>
            <div class="subtext">
                PDF <?php echo number_format($total_tool_pdf); ?>
                &middot; DOCX <?php echo number_format($total_tool_docx); ?>
            </div>
        </div>
        <div class="stat-card">
            <h3>Download rate</h3>
            <div class="value"><?php echo $download_rate !== null ? $download_rate . '%' : '—'; ?></div>
            <div class="subtext">downloads &divide; sessions</div>
        </div>
        <div class="stat-card">
            <h3>CTA click rate</h3>
            <div class="value"><?php echo $cta_rate !== null ? $cta_rate . '%' : '—'; ?></div>
            <div class="subtext"><?php echo number_format($total_tool_clicks); ?> clicks on Argo Books pitches</div>
        </div>
    </div>

    <div class="chart-container">
        <h3>Sessions per day</h3>
        <?php if (empty($tool_sessions)): ?>
            <div class="empty-state">
                <p>No invoice-generator traffic recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25" class="tool-day-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tool_sessions as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['day']); ?></td>
                                <td><?php echo number_format((int)$r['sessions']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="chart-container">
        <h3>Downloads per day (PDF vs DOCX)</h3>
        <?php if (empty($tool_downloads)): ?>
            <div class="empty-state">
                <p>No downloads recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25" class="tool-day-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>PDF</th>
                            <th>DOCX</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tool_downloads as $r):
                            $pdf  = (int)$r['pdf'];
                            $docx = (int)$r['docx'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['day']); ?></td>
                                <td><?php echo number_format($pdf); ?></td>
                                <td><?php echo number_format($docx); ?></td>
                                <td><?php echo number_format($pdf + $docx); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <h2>CTA click-through by placement</h2>
        <p class="subtext" style="margin-top:0;">
            Where invoice-generator visitors click through to argorobots.com. Percent is share of total CTA clicks
            in this period, which is a useful ranking but not a true click-through rate (clicks per impression).
        </p>
        <?php if (empty($tool_cta_clicks)): ?>
            <div class="empty-state">
                <p>No CTA clicks recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Placement</th>
                            <th>Clicks</th>
                            <th>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tool_cta_clicks as $r):
                            $clicks = (int)$r['clicks'];
                            $share = $total_tool_clicks > 0
                                ? round(($clicks / $total_tool_clicks) * 100, 1)
                                : 0;
                        ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($r['placement'] !== '' ? $r['placement'] : '(unspecified)'); ?></code></td>
                                <td><?php echo number_format($clicks); ?></td>
                                <td><?php echo $share; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <h2>Niche traffic</h2>
        <p class="subtext" style="margin-top:0;">
            Page views on each niche landing page (e.g. /niches/photographer-invoice). One row per niche per visitor per day.
        </p>
        <?php if (empty($tool_niche_views)): ?>
            <div class="empty-state">
                <p>No niche-page views recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Niche</th>
                            <th>Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tool_niche_views as $r): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($r['niche']); ?></code></td>
                                <td><?php echo number_format((int)$r['views']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
        $template_views     = get_template_page_views($funnel_period_start_dt);
        $template_cta       = get_template_cta_clicks($funnel_period_start_dt);
        $template_downloads = get_template_downloads($funnel_period_start_dt);
    ?>

    <h2 style="margin-top:32px;">Template library</h2>

    <div class="table-container">
        <h3>Template page views</h3>
        <p class="subtext" style="margin-top:0;">
            Page views on /invoice-template/{slug}/. Slug `hub` is the index page; `pdf` / `word` / `excel` / `google-docs` / `google-sheets` are format-generic pages; `{style}-{format}` are style-format pages.
        </p>
        <?php if (empty($template_views)): ?>
            <div class="empty-state">
                <p>No template page views recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Slug</th>
                            <th>Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($template_views as $r): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($r['slug']); ?></code></td>
                                <td><?php echo number_format((int)$r['views']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <h3>Customize-and-download CTA clicks</h3>
        <p class="subtext" style="margin-top:0;">
            Clicks on the &quot;Customize and download PDF/Word&quot; CTA on a /invoice-template/{style}-{pdf|word}/ page. event_data is &quot;{style}|{format}&quot;.
        </p>
        <?php if (empty($template_cta)): ?>
            <div class="empty-state">
                <p>No template CTA clicks recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Style and format</th>
                            <th>Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($template_cta as $r): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($r['style_format']); ?></code></td>
                                <td><?php echo number_format((int)$r['clicks']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <h3>Direct downloads (Excel and Google copies)</h3>
        <p class="subtext" style="margin-top:0;">
            Excel .xlsx downloads plus &quot;Make a copy in Google Docs/Sheets&quot; clicks. event_data is &quot;{style}|{format}&quot;.
        </p>
        <?php if (empty($template_downloads)): ?>
            <div class="empty-state">
                <p>No template downloads recorded for this period yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table data-paginate="25">
                    <thead>
                        <tr>
                            <th>Style and format</th>
                            <th>Downloads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($template_downloads as $r): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($r['style_format']); ?></code></td>
                                <td><?php echo number_format((int)$r['downloads']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    </div><!-- /#tools -->
</div>

<!-- Ad spend modal -->
<div id="spendModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeSpendModal()">&times;</span>
        <h2 id="spendModalTitle">Add ad spend</h2>
        <form id="spendForm" method="POST" action="index.php">
            <input type="hidden" name="action" value="spend_save">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="spend_source_code">Source *</label>
                <select name="spend_source_code" id="spend_source_code" required
                        style="width:100%; padding:10px 12px; border:1px solid var(--gray-input-border); border-radius:6px; font-size:14px;">
                    <option value="">-- choose a source --</option>
                    <?php foreach ($referral_links as $rl): ?>
                        <option value="<?php echo htmlspecialchars($rl['source_code']); ?>">
                            <?php echo htmlspecialchars(referral_display_name($rl['category'], $rl['name'], $rl['source_code']) . ' (' . $rl['source_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Pick a referral link the spend should be attributed to.</small>
            </div>
            <div class="form-group">
                <label for="spend_period">Month *</label>
                <input type="month" name="spend_period" id="spend_period" required>
                <small>Spend is tracked one calendar month at a time.</small>
            </div>
            <div class="spend-form-row">
                <div class="form-group spend-amount-group">
                    <label for="spend_amount">Amount *</label>
                    <input type="number" name="spend_amount" id="spend_amount" step="0.01" min="0" required>
                    <small>Total ad-platform spend for the chosen source + month.</small>
                </div>
                <div class="form-group spend-currency-group">
                    <label for="spend_currency">Currency</label>
                    <select name="spend_currency" id="spend_currency">
                        <option value="CAD" selected>CAD - Canadian Dollar</option>
                        <option value="USD">USD - US Dollar</option>
                        <option value="EUR">EUR - Euro</option>
                        <option value="GBP">GBP - British Pound</option>
                        <option value="AUD">AUD - Australian Dollar</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="spend_notes">Notes</label>
                <textarea name="spend_notes" id="spend_notes" rows="4"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-blue">Save</button>
                <button type="button" class="btn btn-gray" onclick="closeSpendModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- World map for the geography card (choropleth by visits). Self-hosted so it
     loads under the admin CSP, which only allows scripts from 'self' + cdnjs. -->
<script src="vendor/jsvectormap.min.js"></script>
<script src="vendor/world.min.js"></script>

<script>
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    // Spend modal open/close + edit + delete
    function openSpendModal() {
        const m = document.getElementById('spendModal');
        if (m) m.style.display = 'block';
    }
    window.closeSpendModal = function() {
        const m = document.getElementById('spendModal');
        if (!m) return;
        m.style.display = 'none';
        document.getElementById('spendForm').reset();
        document.getElementById('spend_source_code').removeAttribute('disabled');
        document.getElementById('spendModalTitle').textContent = 'Add ad spend';
    };
    window.editSpend = function(row) {
        document.getElementById('spend_source_code').value = row.source_code;
        // Disabled so the user can't repoint an existing row to a different source
        // (the UNIQUE key on source_code+period_start would then collide).
        document.getElementById('spend_source_code').setAttribute('disabled', 'disabled');
        const periodStr = (row.period_start || '').slice(0, 7); // YYYY-MM
        document.getElementById('spend_period').value = periodStr;
        document.getElementById('spend_amount').value = row.amount;
        document.getElementById('spend_currency').value = row.currency;
        document.getElementById('spend_notes').value = row.notes || '';
        document.getElementById('spendModalTitle').textContent = 'Edit ad spend';
        openSpendModal();
    };
    window.deleteSpend = function(id) {
        if (!confirm('Delete this ad-spend entry?')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'index.php?tab=spend';
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = csrfToken;
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'spend_delete';
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(tokenInput);
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    };
    // Re-enable the source dropdown on submit so its value is included in POST
    const spendFormEl = document.getElementById('spendForm');
    if (spendFormEl) {
        spendFormEl.addEventListener('submit', function() {
            document.getElementById('spend_source_code').removeAttribute('disabled');
        });
    }
    const addSpendBtnEl = document.getElementById('addSpendBtn');
    if (addSpendBtnEl) {
        addSpendBtnEl.addEventListener('click', openSpendModal);
    }

    // Close modal when clicking outside (only if mousedown also started on backdrop)
    let modalMouseDownTarget = null;
    window.addEventListener('mousedown', function(event) {
        modalMouseDownTarget = event.target;
    });
    window.addEventListener('click', function(event) {
        const sModal = document.getElementById('spendModal');
        if (sModal && event.target === sModal && modalMouseDownTarget === sModal) {
            closeSpendModal();
        }
    });

    // Searchable source filter (combobox). Type to filter the source list,
    // click or Enter to apply; applying reloads the funnel for that source and
    // preserves scroll the same way the pills do.
    (function () {
        const box = document.getElementById('sourceCombobox');
        if (!box) return;
        const input = document.getElementById('sourceComboboxInput');
        const list  = document.getElementById('sourceComboboxList');
        const period = box.getAttribute('data-period') || '30d';
        const options = Array.from(list.querySelectorAll('.source-combobox-option'));
        const currentLabel = input.value; // restored if the user closes without choosing
        let highlighted = -1;

        function go(source) {
            sessionStorage.setItem('scrollPosition', window.scrollY);
            const params = new URLSearchParams();
            params.set('tab', 'funnel');
            params.set('funnel_period', period);
            if (source) params.set('source', source);
            window.location = 'index.php?' + params.toString();
        }

        function open()  { box.classList.add('open');  input.setAttribute('aria-expanded', 'true'); }
        function close() {
            box.classList.remove('open');
            input.setAttribute('aria-expanded', 'false');
            input.value = currentLabel;
            highlighted = -1;
        }
        function visible() { return options.filter(o => o.style.display !== 'none'); }

        function filter() {
            const q = input.value.trim().toLowerCase();
            // While the field still shows the current selection, list everything.
            const showAll = (q === '' || q === currentLabel.toLowerCase());
            options.forEach(o => {
                o.style.display = (showAll || o.textContent.toLowerCase().includes(q)) ? '' : 'none';
                o.classList.remove('highlighted');
            });
            highlighted = -1;
        }

        function setHighlight(i) {
            const vis = visible();
            vis.forEach(o => o.classList.remove('highlighted'));
            if (!vis.length) { highlighted = -1; return; }
            highlighted = (i + vis.length) % vis.length;
            vis[highlighted].classList.add('highlighted');
            vis[highlighted].scrollIntoView({ block: 'nearest' });
        }

        input.addEventListener('focus', () => { input.select(); open(); filter(); });
        input.addEventListener('input', () => { open(); filter(); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown')      { e.preventDefault(); open(); setHighlight(highlighted + 1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); setHighlight(highlighted - 1); }
            else if (e.key === 'Enter')     {
                e.preventDefault();
                const vis = visible();
                const choice = highlighted >= 0 ? vis[highlighted] : vis[0];
                if (choice) go(choice.getAttribute('data-source'));
            } else if (e.key === 'Escape')  { close(); input.blur(); }
        });
        // mousedown (not click) so it fires before the input's blur closes the list.
        list.addEventListener('mousedown', (e) => {
            const opt = e.target.closest('.source-combobox-option');
            if (opt) { e.preventDefault(); go(opt.getAttribute('data-source')); }
        });
        box.querySelector('.source-combobox-caret').addEventListener('mousedown', (e) => {
            e.preventDefault();
            if (box.classList.contains('open')) { close(); } else { input.focus(); }
        });
        document.addEventListener('click', (e) => { if (!box.contains(e.target)) close(); });
    })();

    // Source-survey breakdown doughnut (unattributed installs only).
    (function () {
        const canvas = document.getElementById('surveySourcesChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const labels = <?php echo json_encode($survey_chart_labels ?? [], JSON_UNESCAPED_SLASHES); ?>;
        const counts = <?php echo json_encode($survey_chart_counts ?? []); ?>;
        if (!labels.length) return;

        // Same palette as the landing-pages chart so themes stay consistent.
        // Unanswered always lands on the gray slot (last in the palette).
        const palette = [
            'rgba(59, 130, 246, 0.85)',   // blue
            'rgba(30, 64, 175, 0.85)',    // navy
            'rgba(16, 185, 129, 0.85)',   // emerald
            'rgba(245, 158, 11, 0.85)',   // amber
            'rgba(239, 68, 68, 0.85)',    // red
            'rgba(14, 165, 233, 0.85)',   // sky
            'rgba(147, 197, 253, 0.85)',  // light blue
            'rgba(107, 114, 128, 0.85)',  // gray
        ];
        const GRAY = palette[palette.length - 1];
        const colors = labels.map((lbl, i) => lbl === 'Unanswered' ? GRAY : palette[i % (palette.length - 1)]);

        document.querySelectorAll('.landing-breakdown-list .swatch[data-survey-swatch-idx]').forEach(el => {
            const idx = parseInt(el.getAttribute('data-survey-swatch-idx'), 10);
            if (!Number.isNaN(idx) && colors[idx]) {
                el.style.backgroundColor = colors[idx];
            }
        });

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: counts, backgroundColor: colors, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    })();

    // ---------------------------------------------------------------------
    // Plausible-style dashboard: streamgraph funnel, channel donut, world map,
    // dual-bar breakdown tooltips, inner tab switching, referrer details modal.
    // ---------------------------------------------------------------------
    <?php
        // Country map values keyed by uppercase ISO-2, for the choropleth.
        $map_values = [];
        foreach ($analytics['countries'] as $mr) {
            if (!preg_match('/^[A-Za-z]{2}$/', (string)$mr['key'])) continue;
            $map_values[strtoupper($mr['key'])] = [
                'visits'  => (int)$mr['visits'],
                'revenue' => round((float)$mr['revenue'], 2),
            ];
        }
        $channels_js = array_map(fn($r) => [
            'label'   => $r['label'],
            'visits'  => (int)$r['visits'],
            'revenue' => round((float)$r['revenue'], 2),
        ], $analytics['channels']);
    ?>
    (function () {
        const FUNNEL      = <?php echo json_encode($funnel_stages, JSON_UNESCAPED_SLASHES); ?>;
        const APP_ACTIVATION = <?php echo json_encode($app_activation, JSON_UNESCAPED_SLASHES); ?>;
        const CHANNELS    = <?php echo json_encode($channels_js, JSON_UNESCAPED_SLASHES); ?>;
        const MAP_VALUES  = <?php echo json_encode($map_values ?: new stdClass(), JSON_UNESCAPED_SLASHES); ?>;

        const nf = new Intl.NumberFormat('en-US');
        const money = v => '$' + nf.format(Math.round(v));
        const escapeHtml = s => String(s).replace(/[&<>"']/g, c => (
            {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const fmtCount = n => {
            n = Number(n) || 0;
            if (Math.abs(n) >= 1000) {
                const k = n / 1000;
                return (k % 1 === 0 ? k.toFixed(0) : k.toFixed(1)) + 'k';
            }
            return nf.format(n);
        };
        const isDark = () =>
            (document.documentElement.getAttribute('data-theme') === 'dark') ||
            (document.body.getAttribute('data-theme') === 'dark');

        // ----- Inner tab switching (sources + geo cards) -----
        document.querySelectorAll('.bd-tabs').forEach(tabs => {
            const card = tabs.closest('.analytics-card');
            tabs.addEventListener('click', e => {
                const btn = e.target.closest('.bd-tab');
                if (!btn) return;
                const key = btn.getAttribute('data-bd');
                tabs.querySelectorAll('.bd-tab').forEach(b => b.classList.toggle('active', b === btn));
                card.querySelectorAll('.bd-panel').forEach(p =>
                    p.classList.toggle('active', p.getAttribute('data-bd-panel') === key));
                if (key === 'map' && window.__argoMap && typeof window.__argoMap.updateSize === 'function') {
                    window.__argoMap.updateSize();
                }
            });
        });

        // ----- Shared tooltip for the dual-bar breakdown lists -----
        const barTip = document.createElement('div');
        barTip.className = 'bd-tip';
        document.body.appendChild(barTip);
        document.querySelectorAll('.bd-list').forEach(list => {
            list.addEventListener('mousemove', e => {
                const row = e.target.closest('.bd-row');
                if (!row) { barTip.classList.remove('show'); return; }
                barTip.textContent = row.getAttribute('data-tip') || '';
                barTip.classList.add('show');
                barTip.style.left = (e.clientX + 14) + 'px';
                barTip.style.top  = (e.clientY + 16) + 'px';
            });
            list.addEventListener('mouseleave', () => barTip.classList.remove('show'));
        });

        // ----- Streamgraph funnel -----
        (function renderFunnel() {
            const host = document.getElementById('funnelStream');
            if (!host || !FUNNEL.length) return;
            const W = 1000, H = 300, cy = H / 2, pad = 18;
            const n = FUNNEL.length;
            const colW = W / n;
            const maxCount = Math.max(1, ...FUNNEL.map(s => s.count));
            const halfMax = cy - pad;
            // Section centres (labels + waist points sit here); the shape flows
            // flat from the left edge, through each centre, off the right edge.
            const cxs = FUNNEL.map((s, i) => (i + 0.5) * colW);
            const hs = FUNNEL.map(s => Math.max(6, Math.sqrt(s.count / maxCount) * halfMax));
            const px = [0, ...cxs, W];
            const ph = [hs[0], ...hs, hs[n - 1]];

            // Per-section blue ramp, dark blue -> light blue (matches Funnel.png).
            const c0 = [44, 70, 140], c1 = [176, 209, 246];
            const hx2 = v => ('0' + Math.max(0, Math.min(255, Math.round(v))).toString(16)).slice(-2);
            const sectionColor = i => {
                const t = n === 1 ? 0 : i / (n - 1);
                return '#' + hx2(c0[0] + (c1[0] - c0[0]) * t) + hx2(c0[1] + (c1[1] - c0[1]) * t) + hx2(c0[2] + (c1[2] - c0[2]) * t);
            };

            const edge = sign => {
                let d = `M ${px[0].toFixed(1)} ${(cy + sign * ph[0]).toFixed(1)}`;
                for (let i = 0; i < px.length - 1; i++) {
                    const x0 = px[i], x1 = px[i + 1], y0 = cy + sign * ph[i], y1 = cy + sign * ph[i + 1], dx = (x1 - x0) / 2;
                    d += ` C ${(x0 + dx).toFixed(1)} ${y0.toFixed(1)}, ${(x1 - dx).toFixed(1)} ${y1.toFixed(1)}, ${x1.toFixed(1)} ${y1.toFixed(1)}`;
                }
                return d;
            };
            let bottom = `L ${px[px.length - 1].toFixed(1)} ${(cy + ph[ph.length - 1]).toFixed(1)}`;
            for (let i = px.length - 1; i > 0; i--) {
                const x0 = px[i], x1 = px[i - 1], y0 = cy + ph[i], y1 = cy + ph[i - 1], dx = (x0 - x1) / 2;
                bottom += ` C ${(x0 - dx).toFixed(1)} ${y0.toFixed(1)}, ${(x1 + dx).toFixed(1)} ${y1.toFixed(1)}, ${x1.toFixed(1)} ${y1.toFixed(1)}`;
            }
            const pathD = edge(-1) + ' ' + bottom + ' Z';

            // Each section: the full shape clipped to its column, own colour.
            let defs = '<defs>', sections = '', dividers = '', segs = '';
            for (let i = 0; i < n; i++) {
                defs += `<clipPath id="fseg${i}"><rect x="${(i * colW).toFixed(1)}" y="0" width="${colW.toFixed(1)}" height="${H}"/></clipPath>`;
                sections += `<path d="${pathD}" fill="${sectionColor(i)}" clip-path="url(#fseg${i})"/>`;
                if (i > 0) dividers += `<line x1="${(i * colW).toFixed(1)}" y1="0" x2="${(i * colW).toFixed(1)}" y2="${H}" class="funnel-divider" vector-effect="non-scaling-stroke"/>`;
                segs += `<rect class="funnel-seg" data-i="${i}" x="${(i * colW).toFixed(1)}" y="0" width="${colW.toFixed(1)}" height="${H}"></rect>`;
            }
            defs += '</defs>';

            const svg =
                `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" class="funnel-svg">` +
                defs + dividers +
                `<g class="funnel-body">${sections}<path d="${pathD}" fill="none" class="funnel-border" vector-effect="non-scaling-stroke"/></g>` +
                segs + `</svg>`;

            let pills = '<div class="funnel-pills">';
            for (let i = 0; i < n - 1; i++) {
                const midX = ((i + 1) * colW) / W * 100;
                // Drop-off is undefined when the previous stage had 0 (nothing to
                // drop from) — show a dash rather than a null percentage.
                const lost = FUNNEL[i + 1].lost;
                const lostLabel = (lost === null || lost === undefined) ? '&ndash;' : `-${lost}%`;
                pills += `<span class="funnel-pill" style="left:${midX}%">${lostLabel} <span class="arw">&rarr;</span></span>`;
            }
            pills += '</div>';

            // Labels centred under each section. The "App first run" label always
            // gets an info button opening the anonymous in-app activation popover.
            let labels = '<div class="funnel-labels">';
            FUNNEL.forEach((s, i) => {
                const left = ((i + 0.5) * colW) / W * 100;
                const info = (s.key === 'app_first_run')
                    ? ` <button type="button" class="fl-info" aria-label="After install: activation and retention"></button>`
                    : '';
                labels += `<div class="funnel-lbl" style="left:${left.toFixed(2)}%;transform:translateX(-50%);text-align:center"><span class="fl-count">${fmtCount(s.count)}</span><span class="fl-name">${escapeHtml(s.label)}${info}</span></div>`;
            });
            labels += '</div>';

            host.innerHTML = svg + pills + labels;

            // ----- "After install" activation popover on the App-first-run stage -----
            const actBtn = host.querySelector('.fl-info');
            if (actBtn) {
                const a = APP_ACTIVATION || {};
                const pop = document.createElement('div');
                pop.className = 'funnel-activation-pop';
                pop.hidden = true;
                pop.innerHTML = a.has_data
                    ? (`<div class="fap-title">After install &middot; anonymous app data</div>` +
                       `<div class="fap-row"><span>Active app users</span><b>${fmtCount(a.seen)}</b></div>` +
                       `<div class="fap-row"><span>Finished setup tutorial</span><b>${fmtCount(a.onboarded)} &middot; ${a.onboarded_pct}%</b></div>` +
                       `<div class="fap-row"><span>Skipped setup tutorial</span><b>${fmtCount(a.skipped)} &middot; ${a.skipped_pct}%</b></div>` +
                       `<div class="fap-row"><span>Did a bookkeeping action</span><b>${fmtCount(a.activated)} &middot; ${a.activated_pct}%</b></div>` +
                       `<div class="fap-row"><span>Came back another day</span><b>${fmtCount(a.returned)} &middot; ${a.returned_pct}%</b></div>` +
                       `<div class="fap-note">Active app users in this period (new and returning), so it won't match the install count above. Anonymous in-app usage, not tied to individual visitors. Activated = a real bookkeeping action: invoice, expense, revenue, payment, receipt scan, bank/spreadsheet import, report, purchase order, stock change, or rental record. Setup-tutorial figures need app 2.0.11+.</div>`)
                    : (`<div class="fap-title">After install &middot; anonymous app data</div>` +
                       `<div class="fap-note">No in-app usage data for this period yet. Once people run the app and it uploads anonymous telemetry, this shows how many finished setup, did a bookkeeping action (invoice, receipt, or expense), and came back another day.</div>`);
                document.body.appendChild(pop);

                const closePop = () => { pop.hidden = true; };
                actBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (pop.hidden) {
                        const r = actBtn.getBoundingClientRect();
                        pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 300)) + 'px';
                        pop.style.top = (r.bottom + window.scrollY + 8) + 'px';
                        pop.hidden = false;
                    } else {
                        closePop();
                    }
                });
                document.addEventListener('click', (e) => {
                    if (e.target !== actBtn && !pop.contains(e.target)) closePop();
                });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closePop(); });
            }

            const tip = document.getElementById('funnelTip');
            const showTip = (e, i) => {
                const s = FUNNEL[i], prev = i > 0 ? FUNNEL[i - 1] : null;
                let h = '';
                if (prev) {
                    h += `<div class="ft-line"><span>${escapeHtml(prev.label)}</span><b>${fmtCount(prev.count)}</b></div>`;
                    h += `<div class="ft-line ft-drop"><span>Dropoff</span><b>-${fmtCount(s.dropoff)}</b></div>`;
                }
                h += `<div class="ft-line ft-cur"><span>${escapeHtml(s.label)}</span><b>${fmtCount(s.count)}</b></div>`;
                h += `<div class="ft-sep"></div>`;
                h += `<div class="ft-line"><span>Conversion</span><b>${s.retained === null ? '100%' : s.retained + '%'}</b></div>`;
                h += `<div class="ft-line ft-sub"><span>from start</span><b>${s.pct_of_top}%</b></div>`;
                h += `<div class="ft-line"><span>Step value</span><b>${money(s.step_value)}/visitor</b></div>`;
                if ((s.top_sources && s.top_sources.length) || (s.top_countries && s.top_countries.length)) {
                    const col = (title, rows) => `<div class="ft-col"><div class="ft-h">${title}</div>` +
                        rows.map(r => `<div class="ft-row"><span>${r.flag ? r.flag + ' ' : ''}${escapeHtml(r.label)}</span><em>${r.pct}%</em></div>`).join('') + `</div>`;
                    h += `<div class="ft-cols">` + col('Top sources', s.top_sources || []) + col('Top countries', s.top_countries || []) + `</div>`;
                }
                tip.innerHTML = h;
                tip.setAttribute('aria-hidden', 'false');
                tip.classList.add('show');
                let x = e.clientX + 16;
                if (x + 250 > window.innerWidth) x = e.clientX - 250;
                tip.style.left = Math.max(4, x) + 'px';
                tip.style.top = Math.max(4, e.clientY + 14) + 'px';
            };
            host.querySelectorAll('.funnel-seg').forEach(seg => {
                const i = parseInt(seg.getAttribute('data-i'), 10);
                seg.addEventListener('mouseenter', () => host.classList.add('hovering'));
                seg.addEventListener('mousemove', e => showTip(e, i));
                seg.addEventListener('mouseleave', () => {
                    host.classList.remove('hovering');
                    tip.classList.remove('show');
                    tip.setAttribute('aria-hidden', 'true');
                });
            });
        })();

        // ----- Channel donut -----
        (function renderChannelDonut() {
            const canvas = document.getElementById('channelDonut');
            if (!canvas || typeof Chart === 'undefined' || !CHANNELS.length) return;
            const palette = {
                'Referral': '#3f63e8', 'Direct': '#8aa0e8', 'Organic search': '#2740b5',
                'Organic social': '#6f8fe6', 'Newsletter': '#c2cffb', 'AI': '#9db4f5'
            };
            const colors = CHANNELS.map(c => palette[c.label] || '#8aa0e8');
            const total = CHANNELS.reduce((a, c) => a + c.visits, 0);
            const donutBorder = isDark() ? '#1e232b' : '#ffffff';
            new Chart(canvas, {
                type: 'doughnut',
                data: { labels: CHANNELS.map(c => c.label), datasets: [{ data: CHANNELS.map(c => c.visits), backgroundColor: colors, borderWidth: 2, borderColor: donutBorder }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => {
                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            const rev = CHANNELS[ctx.dataIndex] ? CHANNELS[ctx.dataIndex].revenue : 0;
                            let l = ctx.label + ': ' + nf.format(ctx.parsed) + ' (' + pct + '%)';
                            if (rev > 0) l += ' · ' + money(rev);
                            return l;
                        } } }
                    }
                }
            });
            const legend = document.getElementById('channelLegend');
            if (legend) {
                legend.innerHTML = CHANNELS.map((c, i) => {
                    const pct = total > 0 ? Math.round(c.visits / total * 100) : 0;
                    return `<li><span class="dot" style="background:${colors[i]}"></span>` +
                           `<span class="dl">${escapeHtml(c.label)}</span>` +
                           `<span class="dv">${nf.format(c.visits)} &middot; ${pct}%</span></li>`;
                }).join('');
            }
        })();

        // ----- World map choropleth -----
        (function renderMap() {
            const el = document.getElementById('worldMap');
            if (!el || typeof jsVectorMap === 'undefined') {
                if (el) el.innerHTML = '<div class="bd-empty">Map unavailable.</div>';
                return;
            }
            const dark = isDark();
            // Use jsvectormap's top-level `visualizeData` (a linear colour scale)
            // rather than the ordinal `series.scale`, and sqrt-spread the counts
            // so low-traffic countries still read as blue. visualizeData updates
            // each region's stored style, so hovering keeps the country's colour.
            const codes = Object.keys(MAP_VALUES);
            const maxV = Math.max(1, ...codes.map(c => MAP_VALUES[c].visits));
            const vals = {};
            codes.forEach(k => { vals[k] = Math.round(Math.sqrt(MAP_VALUES[k].visits / maxV) * 1000); });
            const scale = dark ? ['#4d7ac9', '#a9d3f7'] : ['#9db4f5', '#2740b5'];
            try {
                window.__argoMap = new jsVectorMap({
                    selector: '#worldMap',
                    map: 'world',
                    zoomButtons: true,
                    zoomOnScroll: false,
                    backgroundColor: 'transparent',
                    regionStyle: {
                        initial: { fill: dark ? '#2a2f38' : '#e6ebf3', stroke: dark ? '#13161c' : '#ffffff', strokeWidth: 0.4 },
                        hover: { fillOpacity: 0.78 }
                    },
                    visualizeData: { scale: scale, values: vals },
                    onRegionTooltipShow(event, tooltip, code) {
                        const d = MAP_VALUES[code];
                        let html = '<b>' + (tooltip.text() || code) + '</b>';
                        if (d) {
                            html += '<br>' + nf.format(d.visits) + ' visit' + (d.visits === 1 ? '' : 's');
                            if (d.revenue > 0) html += ' &middot; ' + money(d.revenue);
                        } else {
                            html += '<br>No visits';
                        }
                        tooltip.text(html, true);
                    }
                });
            } catch (err) {
                el.innerHTML = '<div class="bd-empty">Map unavailable.</div>';
            }
        })();

        // ----- "See all details" modals (referrer / country / region / city) -----
        (function breakdownDetails() {
            const close = modal => { modal.style.display = 'none'; };

            document.querySelectorAll('.bd-details-modal').forEach(modal => {
                const search = modal.querySelector('.bd-details-search');
                const rows = Array.from(modal.querySelectorAll('.bd-details-row'));
                const filter = () => {
                    const q = (search.value || '').trim().toLowerCase();
                    rows.forEach(r => { r.style.display = (!q || r.getAttribute('data-name').includes(q)) ? '' : 'none'; });
                };
                if (search) search.addEventListener('input', filter);
                modal.querySelectorAll('.bd-details-close').forEach(x => x.addEventListener('click', () => close(modal)));
                modal.addEventListener('mousedown', e => { if (e.target === modal) close(modal); });
            });

            document.querySelectorAll('.bd-details-btn[data-details-target]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = document.getElementById(btn.getAttribute('data-details-target'));
                    if (!modal) return;
                    modal.style.display = 'block';
                    const search = modal.querySelector('.bd-details-search');
                    if (search) { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); }
                });
            });

            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                document.querySelectorAll('.bd-details-modal').forEach(m => {
                    if (m.style.display === 'block') close(m);
                });
            });
        })();

        // Card "Download" buttons. Every tab in the card is written into one CSV,
        // separated by a blank line and its own header row, which Excel and Sheets
        // both open cleanly. Done client-side from the payload already in the DOM
        // so there is no extra admin endpoint to authenticate.
        (function () {
            const escapeCell = value => {
                const s = String(value ?? '');
                return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            };

            document.querySelectorAll('.bd-download-btn[data-download]').forEach(btn => {
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
                    const stamp = new Date().toISOString().slice(0, 10);
                    a.href = url;
                    a.download = (payload.filename || 'analytics') + '-' + stamp + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            });
        })();
    })();
</script>
<script>
    // Hide sources that produced no installs. On by default: the point of the table is
    // which sources produced users, and a wall of zeroes buries the two or three that did.
    (function () {
        const toggle = document.getElementById('funnelHideZeroUsers');
        const table = document.getElementById('funnelUsersTable');
        if (!toggle || !table) return;

        function apply() {
            table.querySelectorAll('tr[data-no-installs]').forEach(function (row) {
                row.style.display = toggle.checked ? 'none' : '';
            });
        }

        toggle.addEventListener('change', apply);
        apply();
    })();
</script>
<script>window.ADMIN_PRESERVE_SCROLL = ['.control-pill'];</script>
<script src="../preserve-scroll.js" defer></script>
