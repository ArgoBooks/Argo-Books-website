<?php
/**
 * The marketing agent's side of the site: settings, runs, memory and reading.
 *
 * A scheduled agent outside the site reads numbers, proposes posts and emails, and records
 * what it did. Everything it can do goes through api/agent/, so the limits live here and not
 * in the agent's judgment: what it may read, how much it may do in a day, who it may never
 * email. How it works and why is in read-me/Marketing-agent.md.
 *
 * Each function takes its inputs and returns a result, so tests call them directly. What the
 * agent may do to the outside world is in actions.php.
 *
 * Tables: agent_settings, agent_runs, agent_actions, agent_journal, agent_notes,
 * agent_proposals. All are split by environment, like the rest of the shared database.
 */

require_once __DIR__ . '/../../referral_categories.php';
require_once __DIR__ . '/../../cron/lib/outreach_helpers.php';
require_once __DIR__ . '/../../admin/marketing-funnel/analytics.php';

/** A refusal the agent should read and act on, as opposed to a fault in the site. */
class AgentRefused extends RuntimeException
{
    public function __construct(public string $reason, string $message, public int $status = 422)
    {
        parent::__construct($message);
    }
}

// ─── Settings ───

/**
 * Everything starts off, and approval starts on. Switching a piece on is a decision made in
 * the admin page, never a default.
 */
const AGENT_DEFAULTS = [
    'enabled'          => '0',
    'posting_enabled'  => '0',
    'outreach_enabled' => '0',
    'approve_posts'    => '1',
    'approve_emails'   => '1',
    'runs_per_day'     => '2',
    'calls_per_run'    => '200',
    'posts_per_day'    => '1',
    'emails_per_day'   => '10',
    'links_per_day'    => '10',
    'research_per_day' => '40',
];

function agent_setting(PDO $pdo, string $key): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM agent_settings WHERE environment = ? AND setting_key = ?');
    $stmt->execute([current_environment(), $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? (AGENT_DEFAULTS[$key] ?? '') : (string) $value;
}

function agent_setting_set(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare(
        'INSERT INTO agent_settings (environment, setting_key, setting_value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([current_environment(), $key, $value]);
}

function agent_on(PDO $pdo, string $key): bool
{
    return agent_setting($pdo, $key) === '1';
}

function agent_limit(PDO $pdo, string $key): int
{
    return max(0, (int) agent_setting($pdo, $key));
}

// ─── Runs, and the record of what was done ───

/**
 * Opens a run. The number of runs a day is capped here because nothing on the scheduler's
 * side caps what a run costs: a schedule set wrong would otherwise repeat all day.
 */
function agent_start_run(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM agent_runs WHERE environment = ? AND started_at >= CURDATE()');
    $stmt->execute([current_environment()]);
    $limit = agent_limit($pdo, 'runs_per_day');
    if ((int) $stmt->fetchColumn() >= $limit) {
        throw new AgentRefused('RUN_LIMIT', "Already ran $limit time(s) today. Stop here; the next run is tomorrow.", 429);
    }

    $pdo->prepare('INSERT INTO agent_runs (environment) VALUES (?)')->execute([current_environment()]);
    return (int) $pdo->lastInsertId();
}

/** Counts a call against the run's allowance and refuses once it is used up. Returns what is left. */
function agent_use_call(PDO $pdo, int $runId): int
{
    $stmt = $pdo->prepare(
        'SELECT api_calls FROM agent_runs
          WHERE id = ? AND environment = ? AND finished_at IS NULL AND started_at >= NOW() - INTERVAL 6 HOUR'
    );
    $stmt->execute([$runId, current_environment()]);
    $calls = $stmt->fetchColumn();
    if ($calls === false) {
        throw new AgentRefused('NO_RUN', 'That run is not open. Call start_run first.', 409);
    }

    $limit = agent_limit($pdo, 'calls_per_run');
    if ((int) $calls >= $limit) {
        throw new AgentRefused('CALL_LIMIT', "This run has used its $limit calls. Write your journal entries and call finish_run.", 429);
    }

    $pdo->prepare('UPDATE agent_runs SET api_calls = api_calls + 1 WHERE id = ?')->execute([$runId]);
    return $limit - (int) $calls - 1;
}

/** The longest summary a run may end with. The owner reads one every day, so it stays short. */
const AGENT_SUMMARY_MAX = 800;

function agent_finish_run(PDO $pdo, int $runId, string $summary): void
{
    $summary = trim($summary);
    if (mb_strlen($summary) > AGENT_SUMMARY_MAX) {
        throw new AgentRefused('SUMMARY_TOO_LONG', 'Your summary is ' . mb_strlen($summary) . ' characters and the most is '
            . AGENT_SUMMARY_MAX . '. The owner reads it every morning. Keep what he has to do or know, put the rest in the '
            . 'journal, and call finish_run again.', 422);
    }
    $pdo->prepare('UPDATE agent_runs SET finished_at = NOW(), summary = ? WHERE id = ? AND environment = ?')
        ->execute([$summary, $runId, current_environment()]);
}

function agent_log(PDO $pdo, ?int $runId, string $action, string $status, array $detail): void
{
    $pdo->prepare('INSERT INTO agent_actions (environment, run_id, action, status, detail) VALUES (?, ?, ?, ?, ?)')
        ->execute([current_environment(), $runId, $action, $status,
                   mb_substr(json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 6000)]);
}

/** How many times an action went through today, for the daily caps. */
function agent_done_today(PDO $pdo, string $action): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM agent_actions
          WHERE environment = ? AND action = ? AND status = 'ok' AND created_at >= CURDATE()"
    );
    $stmt->execute([current_environment(), $action]);
    return (int) $stmt->fetchColumn();
}

function agent_require_room(PDO $pdo, string $action, string $limitKey, string $what): void
{
    $limit = agent_limit($pdo, $limitKey);
    if (agent_done_today($pdo, $action) >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit $what is used up.", 429);
    }
}

// ─── Memory: the journal ───

const AGENT_JOURNAL_KINDS = ['observation', 'experiment', 'result', 'decision', 'note'];

function agent_journal_add(PDO $pdo, int $runId, array $in): int
{
    $kind = (string) ($in['kind'] ?? '');
    $title = trim((string) ($in['title'] ?? ''));
    $body = trim((string) ($in['body'] ?? ''));
    $key = trim((string) ($in['experiment_key'] ?? ''));

    if (!in_array($kind, AGENT_JOURNAL_KINDS, true)) {
        throw new AgentRefused('BAD_INPUT', 'kind must be one of: ' . implode(', ', AGENT_JOURNAL_KINDS) . '.');
    }
    if ($title === '' || $body === '') {
        throw new AgentRefused('BAD_INPUT', 'A journal entry needs a title and a body.');
    }
    if ($key !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{1,58}$/', $key)) {
        throw new AgentRefused('BAD_INPUT', 'experiment_key is lowercase letters, digits and hyphens, up to 60 characters.');
    }

    $pdo->prepare(
        'INSERT INTO agent_journal (environment, run_id, kind, experiment_key, title, body) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([current_environment(), $runId, $kind, $key === '' ? null : $key,
                mb_substr($title, 0, 200), mb_substr($body, 0, 6000)]);
    return (int) $pdo->lastInsertId();
}

/**
 * The most recent entries, oldest first, so they read as a history. Bodies are cut short
 * here: the whole journal is in agent_journal for the times the detail is needed.
 */
function agent_journal_recent(PDO $pdo, int $limit = 40, int $bodyLength = 1200): array
{
    $stmt = $pdo->prepare(
        'SELECT id, created_at, kind, experiment_key, title, body FROM agent_journal
          WHERE environment = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
    );
    $stmt->execute([current_environment()]);
    $rows = array_reverse($stmt->fetchAll());
    foreach ($rows as &$row) {
        if (mb_strlen($row['body']) > $bodyLength) {
            $row['body'] = mb_substr($row['body'], 0, $bodyLength) . ' [cut; the full entry is in agent_journal]';
        }
    }
    return $rows;
}

// ─── Memory: notes ───

/** The one note the agent reads and cannot write. It is how the owner steers it. */
const AGENT_OWNER_NOTE = 'from-the-owner';

const AGENT_MAX_NOTES = 12;
const AGENT_NOTE_LENGTH = 6000;

/** @return array<int, array{name: string, body: string, updated_at: string}> */
function agent_notes(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT name, body, updated_at FROM agent_notes WHERE environment = ? ORDER BY name');
    $stmt->execute([current_environment()]);
    return $stmt->fetchAll();
}

/**
 * Saves a note, replacing the one of the same name. An empty body deletes it.
 *
 * @param bool $asOwner the admin page may write any note; the agent may not write the owner's
 */
function agent_note_save(PDO $pdo, string $name, string $body, bool $asOwner = false): void
{
    $name = strtolower(trim($name));
    $body = trim($body);

    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,78}$/', $name)) {
        throw new AgentRefused('BAD_INPUT', 'A note name is lowercase letters, digits and hyphens, up to 80 characters.');
    }
    if (!$asOwner && $name === AGENT_OWNER_NOTE) {
        throw new AgentRefused('NOT_ALLOWED', 'That note belongs to the owner. Read it; do not write it.', 403);
    }

    if ($body === '') {
        $pdo->prepare('DELETE FROM agent_notes WHERE environment = ? AND name = ?')->execute([current_environment(), $name]);
        return;
    }
    if (mb_strlen($body) > AGENT_NOTE_LENGTH) {
        throw new AgentRefused('BAD_INPUT', 'A note holds at most ' . AGENT_NOTE_LENGTH . ' characters. Cut it down to what still matters.');
    }

    $exists = $pdo->prepare('SELECT 1 FROM agent_notes WHERE environment = ? AND name = ?');
    $exists->execute([current_environment(), $name]);
    if (!$exists->fetchColumn() && count(agent_notes($pdo)) >= AGENT_MAX_NOTES) {
        throw new AgentRefused('BAD_INPUT', 'There are already ' . AGENT_MAX_NOTES . ' notes. Merge two or delete one before adding another.');
    }

    $pdo->prepare(
        'INSERT INTO agent_notes (environment, name, body) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE body = VALUES(body)'
    )->execute([current_environment(), $name, $body]);
}

// ─── Reading: SQL the agent writes itself ───

/**
 * The tables the agent may read. They hold visits, the install funnel, outreach and the
 * agent's own records. Nothing with customers' accounts, payments, licence keys or books is
 * here, and that is the point of the list.
 */
const AGENT_READABLE_TABLES = [
    'statistics', 'referral_links', 'referral_visits', 'referral_events', 'campaign_spend',
    'outreach_leads', 'outreach_activity_log', 'outreach_email_events', 'outreach_followups',
    'uninstall_feedback', 'cron_runs',
    'agent_runs', 'agent_actions', 'agent_journal', 'agent_notes', 'agent_proposals',
];

/** Columns never sent back, whichever table they sit in. Visitors' addresses are not the agent's business. */
const AGENT_HIDDEN_COLUMNS = ['ip_address', 'ip', 'license_key', 'unsubscribe_token', 'subscription_id', 'user_id'];

const AGENT_SQL_MAX_ROWS = 300;

/**
 * Says why a query may not run, or null when it may.
 *
 * The read-only database user is what makes writing impossible. This check is the second
 * line: it keeps the query to one plain SELECT over the listed tables. It works on the words
 * in the query, not a parse of it: any word that is the name of a table in the database must
 * be on the list. A single SELECT has no way to reach a table without writing its name.
 *
 * @param string[] $allTables every table in the database, lower case
 */
function agent_sql_problem(string $sql, array $allTables): ?string
{
    $sql = trim($sql);
    if ($sql === '' || strlen($sql) > 4000) {
        return 'The query is empty or longer than 4000 characters.';
    }
    if (preg_match('~/\*|--|#~', $sql)) {
        return 'Comments are not allowed in a query.';
    }

    // Text inside quotes is data, not names. Backticked names stay.
    $bare = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.|\"\")*\"/s", "''", $sql);
    $bare = strtolower(rtrim(rtrim($bare), ';'));

    if (str_contains($bare, ';')) {
        return 'One statement at a time.';
    }
    if (!preg_match('/^\s*(select|with)\b/', $bare)) {
        return 'Only SELECT queries are allowed.';
    }
    if (preg_match('/\b(into\s+(outfile|dumpfile)|for\s+update|lock\s+in\s+share\s+mode|load_file|sleep|benchmark|get_lock)\b/', $bare)) {
        return 'That query uses something that is not allowed.';
    }
    if (preg_match('/\b(information_schema|performance_schema)\b|\b(mysql|sys)\s*\./', $bare)) {
        return 'Only the listed tables can be read.';
    }

    preg_match_all('/[a-z_][a-z0-9_$]*/', $bare, $words);
    $words = array_unique($words[0]);
    foreach (array_intersect($words, AGENT_HIDDEN_COLUMNS) as $hidden) {
        return "The column $hidden is not available.";
    }
    foreach (array_intersect($words, $allTables) as $table) {
        if (!in_array($table, AGENT_READABLE_TABLES, true)) {
            return "The table $table is not available. Readable tables: " . implode(', ', AGENT_READABLE_TABLES) . '.';
        }
    }
    return null;
}

/**
 * The read-only connection. There is deliberately no fallback to the site's own connection:
 * without a user that can only read, the agent gets no SQL at all.
 */
function agent_readonly_pdo(): ?PDO
{
    $user = $_ENV['AGENT_DB_USER'] ?? '';
    if ($user === '') {
        return null;
    }
    $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? '') . ';charset=utf8mb4';
    return new PDO($dsn, $user, $_ENV['AGENT_DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** @return string[] */
function agent_all_tables(PDO $pdo): array
{
    return array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Runs a query the agent wrote.
 *
 * @param PDO $reader the read-only connection
 * @return array{columns: string[], rows: array[], truncated: bool}
 */
function agent_run_sql(PDO $reader, string $sql): array
{
    $problem = agent_sql_problem($sql, agent_all_tables($reader));
    if ($problem !== null) {
        throw new AgentRefused('SQL_REFUSED', $problem);
    }

    try {
        // MySQL and MariaDB each have their own setting for this and reject the other's.
        try { $reader->exec('SET SESSION MAX_EXECUTION_TIME = 8000'); } catch (PDOException) {}
        try { $reader->exec('SET SESSION max_statement_time = 8'); } catch (PDOException) {}

        $stmt = $reader->query(rtrim(trim($sql), ';'));
        $rows = [];
        $truncated = false;
        while (($row = $stmt->fetch()) !== false) {
            if (count($rows) >= AGENT_SQL_MAX_ROWS) {
                $truncated = true;
                break;
            }
            // SELECT * names no column, so the hidden ones are taken out of the answer too.
            $rows[] = array_diff_key($row, array_flip(AGENT_HIDDEN_COLUMNS));
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        throw new AgentRefused('SQL_ERROR', 'The database rejected the query: ' . $e->getMessage());
    }

    return ['columns' => array_keys($rows[0] ?? []), 'rows' => $rows, 'truncated' => $truncated];
}

/** The readable tables and their columns, so the agent can write queries without guessing. */
function agent_schema(PDO $pdo): array
{
    $out = [];
    $existing = agent_all_tables($pdo);
    foreach (AGENT_READABLE_TABLES as $table) {
        if (!in_array($table, $existing, true)) {
            continue;
        }
        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $col) {
            if (!in_array(strtolower($col['Field']), AGENT_HIDDEN_COLUMNS, true)) {
                $columns[] = $col['Field'] . ' ' . $col['Type'];
            }
        }
        $out[$table] = $columns;
    }
    return $out;
}

// ─── Reading: the standing numbers ───

/**
 * The figures every run starts from, so a run does not spend its calls rebuilding them. Each
 * block is fetched on its own: one table that is missing or slow must not hide the rest.
 */
function agent_overview(PDO $pdo): array
{
    $env = current_environment();
    $firstRun = "FROM referral_events WHERE environment = ? AND event_type = 'app_first_run' AND created_at >= NOW() - INTERVAL 90 DAY";
    $blocks = [
        'site_events_30d' => ["SELECT event_type, SUM(created_at >= NOW() - INTERVAL 7 DAY) AS last_7d, COUNT(*) AS last_30d
                                 FROM statistics WHERE created_at >= NOW() - INTERVAL 30 DAY
                                GROUP BY event_type ORDER BY last_30d DESC LIMIT 25", []],
        'where_new_users_say_they_came_from_90d' => ["SELECT source_survey_answer AS answer, COUNT(*) AS people $firstRun
                                       AND source_survey_answer IS NOT NULL GROUP BY source_survey_answer ORDER BY people DESC", [$env]],
        'what_new_users_came_to_do_90d' => ["SELECT survey_goal AS goal, COUNT(*) AS people $firstRun
                                       AND survey_goal IS NOT NULL GROUP BY survey_goal ORDER BY people DESC", [$env]],
        'why_people_left_90d' => ["SELECT exit_survey_answer AS answer, COUNT(*) AS people $firstRun
                                       AND exit_survey_answer IS NOT NULL GROUP BY exit_survey_answer ORDER BY people DESC", [$env]],
        'uninstall_reasons_90d' => ["SELECT reason, COUNT(*) AS people FROM uninstall_feedback
                                      WHERE environment = ? AND created_at >= NOW() - INTERVAL 90 DAY
                                      GROUP BY reason ORDER BY people DESC", [$env]],
        'outreach_leads_by_source' => ["SELECT source, status, COUNT(*) AS leads FROM outreach_leads
                                         GROUP BY source, status ORDER BY source, leads DESC", []],
        'outreach_emails_30d' => ["SELECT COUNT(*) AS first_emails_sent FROM outreach_leads
                                    WHERE sent_at >= NOW() - INTERVAL 30 DAY", []],
        // A count only. Who the subscribers are is not the agent's to read.
        'paying_subscribers' => ["SELECT COUNT(*) AS active FROM premium_subscriptions
                                   WHERE environment = ? AND status = 'active' AND amount > 0", [$env]],
    ];

    // The funnel comes from the functions behind the admin Funnel page, so the agent sees the
    // figures the owner sees: people, not rows.
    $out = [
        'how_the_funnel_is_counted' => 'Distinct people, with bots left out, exactly as on the admin Funnel page. '
            . 'landing and downloads_page count visitors whose page view was confirmed by the browser. '
            . 'download_click counts only those visitors. app_first_run counts distinct installs.',
    ];
    foreach (['funnel_all_traffic_7d' => '-7 days', 'funnel_all_traffic_30d' => '-30 days'] as $name => $since) {
        try {
            $out[$name] = get_funnel_stage_counts(date('Y-m-d 00:00:00', strtotime($since)), null);
        } catch (PDOException $e) {
            $out[$name] = ['unavailable' => $e->getMessage()];
        }
    }
    try {
        // Every referral link, including the agent's own, with anything that happened on it.
        $out['funnel_by_link_30d'] = array_values(array_filter(
            get_funnel_per_source(date('Y-m-d 00:00:00', strtotime('-30 days')), $env),
            fn ($row) => $row['landings'] || $row['dl_clicks'] || $row['first_runs'] || $row['signups'] || $row['paying']
        ));
    } catch (PDOException $e) {
        $out['funnel_by_link_30d'] = ['unavailable' => $e->getMessage()];
    }

    foreach ($blocks as $name => [$sql, $params]) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $out[$name] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $out[$name] = ['unavailable' => $e->getMessage()];
        }
    }
    return $out;
}

// ─── The files it works from ───

/** The standing instructions, kept in the repo so they are edited in one place. */
function agent_playbook(): string
{
    return (string) @file_get_contents(__DIR__ . '/playbook.md');
}

/** The statements about Argo Books that have been checked and may be repeated. */
function agent_facts(): string
{
    return (string) @file_get_contents(__DIR__ . '/facts.md');
}

/**
 * The plans, prices and monthly limits as the site itself shows them right now. They are set
 * in .env, so this is the only place the agent can get a figure that is true today.
 */
function agent_live_plans(): array
{
    require_once __DIR__ . '/../../config/pricing.php';
    $pricing = get_pricing_config();
    return [
        'currency' => $pricing['currency'],
        'premium_monthly_price' => $pricing['premium_monthly_price'],
        'premium_yearly_price' => $pricing['premium_yearly_price'],
        'plans' => get_plan_features(),
    ];
}

/**
 * Every public page on the site, from the same list the sitemap is built from. The agent
 * sends people to pages that exist; it does not make new ones.
 *
 * @return string[]
 */
function agent_site_pages(): array
{
    require_once __DIR__ . '/../../sitemap_urls.php';
    return array_map(fn ($url) => $url['loc'], sitemap_build_urls());
}

/** Only the site's own pages may be linked or posted. */
function agent_is_site_url(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    return ($parts['scheme'] ?? '') === 'https' && ($host === 'argorobots.com' || $host === 'www.argorobots.com');
}
