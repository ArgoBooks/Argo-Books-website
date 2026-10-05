<?php
/**
 * The marketing agent's page: what is waiting for approval, the switches and limits, the
 * platform sign-ins, and the record of what the agent has done and concluded.
 *
 * The rules themselves are in api/agent/. This page only shows them and takes decisions.
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/../../api/agent/actions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const AGENT_SWITCHES = ['enabled', 'posting_enabled', 'outreach_enabled', 'approve_posts', 'approve_emails'];
const AGENT_LIMIT_KEYS = ['runs_per_day', 'calls_per_run', 'posts_per_day', 'emails_per_day', 'links_per_day', 'research_per_day'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['agent_message'] = ['error', 'Your session expired. Reload the page and try again.'];
        header('Location: index.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $message = ['success', 'Saved.'];
    try {
        if ($action === 'switch' && in_array($_POST['key'] ?? '', AGENT_SWITCHES, true)) {
            agent_setting_set($pdo, $_POST['key'], ($_POST['value'] ?? '') === '1' ? '1' : '0');
        } elseif ($action === 'limits') {
            foreach (AGENT_LIMIT_KEYS as $key) {
                if (isset($_POST[$key]) && ctype_digit((string) $_POST[$key])) {
                    agent_setting_set($pdo, $key, (string) min(1000, (int) $_POST[$key]));
                }
            }
        } elseif ($action === 'decide') {
            // Publishing to Threads waits between its two steps, so give the request room.
            @set_time_limit(120);
            $status = agent_decide(
                $pdo,
                (int) ($_POST['id'] ?? 0),
                ($_POST['decision'] ?? '') === 'approve',
                array_intersect_key($_POST, array_flip(['text', 'subject', 'body'])),
                (string) ($_POST['note'] ?? '')
            );
            $message = match ($status) {
                'done' => ['success', 'Approved and carried out.'],
                'rejected' => ['success', 'Rejected. The agent will be told why on its next run.'],
                default => ['error', 'Approved, but it could not be carried out. The reason is in the list below.'],
            };
        } elseif ($action === 'note') {
            agent_note_save($pdo, (string) ($_POST['name'] ?? ''), (string) ($_POST['body'] ?? ''), true);
        } elseif ($action === 'disconnect' && in_array($_POST['platform'] ?? '', ['linkedin', 'threads'], true)) {
            agent_forget_connection($pdo, $_POST['platform']);
            $message = ['success', ucfirst($_POST['platform']) . ' disconnected.'];
        }
    } catch (AgentRefused $e) {
        $message = ['error', $e->getMessage()];
    }

    $_SESSION['agent_message'] = $message;
    header('Location: index.php' . (isset($_POST['anchor']) ? '#' . preg_replace('/[^a-z-]/', '', (string) $_POST['anchor']) : ''));
    exit;
}

$env = current_environment();
$fetch = function (string $sql, array $params = []) use ($pdo) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$pending = $fetch("SELECT * FROM agent_proposals WHERE environment = ? AND status = 'pending' ORDER BY id", [$env]);
$decided = $fetch("SELECT * FROM agent_proposals WHERE environment = ? AND status != 'pending' ORDER BY id DESC LIMIT 25", [$env]);
$runs = $fetch('SELECT * FROM agent_runs WHERE environment = ? ORDER BY id DESC LIMIT 14', [$env]);
$actions = $fetch('SELECT * FROM agent_actions WHERE environment = ? ORDER BY id DESC LIMIT 60', [$env]);
$journal = $fetch('SELECT * FROM agent_journal WHERE environment = ? ORDER BY id DESC LIMIT 40', [$env]);
$notes = agent_notes($pdo);
$connections = agent_connections($pdo);

$ownerNote = '';
foreach ($notes as $i => $note) {
    if ($note['name'] === AGENT_OWNER_NOTE) {
        $ownerNote = $note['body'];
        unset($notes[$i]);
    }
}

$configured = [
    'Token for the agent (AGENT_API_TOKEN in .env, 32 characters or more)' => strlen($_ENV['AGENT_API_TOKEN'] ?? '') >= 32,
    'Read-only database user (AGENT_DB_USER in .env), for SQL' => ($_ENV['AGENT_DB_USER'] ?? '') !== '',
    'Gemini key (GEMINI_API_KEY in .env), for research' => ($_ENV['GEMINI_API_KEY'] ?? '') !== '',
    'Playbook (api/agent/playbook.md)' => trim(agent_playbook()) !== '',
    'Facts file (api/agent/facts.md)' => trim(agent_facts()) !== '',
];

$page_title = 'Marketing Agent';
$page_description = 'Approve what the agent wants to send, and see what it has done.';
include __DIR__ . '/../admin_header.php';

$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$csrf = '<input type="hidden" name="csrf_token" value="' . $h($_SESSION['csrf_token']) . '">';

/** One on/off pair of buttons for a setting. */
$toggle = function (string $key, string $onTitle, string $onDesc, string $offTitle, string $offDesc) use ($pdo, $csrf, $h) {
    $on = agent_on($pdo, $key);
    $button = fn (string $value, bool $active, string $title, string $desc) =>
        '<form method="POST" style="display:contents;">' . $csrf
        . '<input type="hidden" name="action" value="switch"><input type="hidden" name="key" value="' . $h($key) . '">'
        . '<input type="hidden" name="value" value="' . $value . '"><input type="hidden" name="anchor" value="switches">'
        . '<button type="submit" class="segmented-option ' . ($active ? 'active' : '') . '">'
        . '<span class="segmented-title">' . $h($title) . '</span><span class="segmented-desc">' . $h($desc) . '</span></button></form>';
    return '<div class="segmented-toggle" style="margin-bottom:12px;">' . $button('1', $on, $onTitle, $onDesc) . $button('0', !$on, $offTitle, $offDesc) . '</div>';
};
?>

<link rel="stylesheet" href="../outreach/style.css">
<style>
    .agent-card { border: 1px solid var(--gray-border); border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; }
    .agent-card textarea, .agent-note textarea { width: 100%; min-height: 110px; font: inherit; padding: 8px; border: 1px solid var(--gray-border); border-radius: 6px; background: transparent; color: inherit; }
    .agent-card input[type=text] { width: 100%; padding: 8px; border: 1px solid var(--gray-border); border-radius: 6px; background: transparent; color: inherit; }
    .agent-meta { font-size: 12px; opacity: .75; margin: 6px 0; }
    .agent-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 10px; }
    .agent-pre { white-space: pre-wrap; margin: 4px 0 0; }
    .agent-ok { color: var(--green-600); } .agent-bad { color: var(--red); }
</style>

<?php if (!empty($_SESSION['agent_message'])): [$type, $text] = $_SESSION['agent_message']; unset($_SESSION['agent_message']); ?>
    <div class="panel"><div class="panel-content <?= $type === 'error' ? 'agent-bad' : 'agent-ok' ?>"><?= $h($text) ?></div></div>
<?php endif; ?>

<div class="panel" id="waiting">
    <div class="panel-header"><h2>Waiting for you (<?= count($pending) ?>)</h2></div>
    <div class="panel-content">
        <?php if (!$pending): ?>
            <p class="hint" style="margin:0;">Nothing is waiting. Posts and emails the agent writes appear here while approval is on.</p>
        <?php endif; ?>
        <?php foreach ($pending as $row): $p = json_decode((string) $row['payload'], true) ?: []; ?>
            <form method="POST" class="agent-card">
                <?= $csrf ?>
                <input type="hidden" name="action" value="decide">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="anchor" value="waiting">
                <?php if ($row['kind'] === 'post'): ?>
                    <strong>Post on <?= $h(ucfirst((string) $row['platform'])) ?></strong>
                    <div class="agent-meta"><?= $h($p['about'] ?? '') ?> &middot; link: <?= $h(($p['link'] ?? '') ?: 'none') ?> &middot; limit <?= (int) (AGENT_PLATFORM_LIMITS[$row['platform']] ?? 0) ?> characters</div>
                    <textarea name="text"><?= $h($p['text'] ?? '') ?></textarea>
                <?php else: ?>
                    <strong>Email to <?= $h($p['business_name'] ?? '') ?></strong> &lt;<?= $h($p['email'] ?? '') ?>&gt;
                    <div class="agent-meta">
                        <?= $h(trim(($p['category'] ?? '') . ' ' . ($p['city'] ?? '') . ' ' . ($p['country'] ?? ''))) ?>
                        &middot; address read from <a href="<?= $h($p['found_on'] ?? '') ?>" target="_blank" rel="noopener noreferrer"><?= $h($p['found_on'] ?? '') ?></a>
                    </div>
                    <div class="agent-meta">Why this business: <?= $h($p['why'] ?? '') ?></div>
                    <input type="text" name="subject" value="<?= $h($p['subject'] ?? '') ?>">
                    <textarea name="body" style="margin-top:8px; min-height:180px;"><?= $h($p['body'] ?? '') ?></textarea>
                <?php endif; ?>
                <div class="agent-meta">Where its claims come from: <?= $h(implode(' | ', $p['sources'] ?? [])) ?></div>
                <div class="agent-row">
                    <input type="text" name="note" placeholder="A line for the agent: why you changed or rejected it (optional)" style="flex:1; min-width:240px;">
                    <button type="submit" name="decision" value="approve" class="btn btn-blue">Approve</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-neutral">Reject</button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel" id="switches">
    <div class="panel-header"><h2>Switches</h2></div>
    <div class="panel-content">
        <?= $toggle('enabled', 'Agent on', 'It can start a run and read.', 'Agent off', 'Every request is refused. Nothing runs.') ?>
        <?= $toggle('posting_enabled', 'Posting on', 'It may write posts for the connected platforms.', 'Posting off', 'It cannot propose or publish a post.') ?>
        <?= $toggle('outreach_enabled', 'Outreach on', 'It may find businesses and write them emails.', 'Outreach off', 'It cannot propose an email or write a follow-up.') ?>
        <?= $toggle('approve_posts', 'Posts wait for approval', 'Each post sits above until you approve it.', 'Posts go out automatically', 'A post is published as soon as the agent writes it.') ?>
        <?= $toggle('approve_emails', 'Emails wait for approval', 'Each email sits above until you approve it. Follow-ups wait in Outreach, Follow-ups.', 'Emails go out automatically', 'An email is queued to send as soon as the agent writes it.') ?>
    </div>
</div>

<div class="panel" id="setup">
    <div class="panel-header"><h2>Setup</h2></div>
    <div class="panel-content">
        <?php foreach ($configured as $what => $ok): ?>
            <div><span class="<?= $ok ? 'agent-ok' : 'agent-bad' ?>"><?= $ok ? 'Ready' : 'Missing' ?></span> &middot; <?= $h($what) ?></div>
        <?php endforeach; ?>
        <h3 style="margin:16px 0 6px;">Platforms</h3>
        <?php foreach ($connections as $platform => $c): ?>
            <div class="agent-row" style="margin-top:4px;">
                <span class="<?= $c['connected'] ? 'agent-ok' : 'agent-bad' ?>"><?= $c['connected'] ? 'Connected' : 'Not connected' ?></span>
                <strong><?= $h(ucfirst($platform)) ?></strong>
                <span class="agent-meta" style="margin:0;">
                    <?= $h($c['detail']) ?><?= $c['expires_at'] ? ', sign-in good until ' . $h(date('F j, Y', $c['expires_at'])) : '' ?>
                </span>
                <?php if ($platform !== 'bluesky'): ?>
                    <a class="btn btn-small btn-blue" href="connect.php?platform=<?= $h($platform) ?>"><?= $c['expires_at'] ? 'Connect again' : 'Connect' ?></a>
                    <?php if ($c['expires_at']): ?>
                        <form method="POST" style="display:inline;"><?= $csrf ?>
                            <input type="hidden" name="action" value="disconnect"><input type="hidden" name="platform" value="<?= $h($platform) ?>">
                            <input type="hidden" name="anchor" value="setup">
                            <button type="submit" class="btn btn-small btn-neutral">Disconnect</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel" id="limits">
    <div class="panel-header"><h2>Limits</h2></div>
    <div class="panel-content">
        <form method="POST" class="agent-row" style="margin-top:0;">
            <?= $csrf ?><input type="hidden" name="action" value="limits"><input type="hidden" name="anchor" value="limits">
            <?php foreach ([
                'runs_per_day' => 'Runs a day', 'calls_per_run' => 'Requests a run', 'posts_per_day' => 'Posts a day, each platform',
                'emails_per_day' => 'New outreach emails a day', 'links_per_day' => 'New links a day', 'research_per_day' => 'Research questions a day',
            ] as $key => $label): ?>
                <div class="form-group" style="margin:0;">
                    <label for="lim-<?= $h($key) ?>"><?= $h($label) ?></label>
                    <input type="number" min="0" max="1000" id="lim-<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= agent_limit($pdo, $key) ?>" style="width:120px;">
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-blue">Save</button>
        </form>
    </div>
</div>

<div class="panel agent-note" id="notes">
    <div class="panel-header"><h2>Notes</h2></div>
    <div class="panel-content">
        <form method="POST">
            <?= $csrf ?><input type="hidden" name="action" value="note"><input type="hidden" name="name" value="<?= $h(AGENT_OWNER_NOTE) ?>">
            <input type="hidden" name="anchor" value="notes">
            <strong>Your note to the agent</strong>
            <p class="hint" style="margin:4px 0 8px;">It reads this at the start of every run and cannot change it. Use it to steer: who to go after, what to stop doing, what you liked.</p>
            <textarea name="body"><?= $h($ownerNote) ?></textarea>
            <div class="agent-row"><button type="submit" class="btn btn-blue">Save</button></div>
        </form>
        <h3 style="margin:18px 0 6px;">The agent's own notes (<?= count($notes) ?>)</h3>
        <p class="hint" style="margin:0 0 8px;">What it has decided is worth remembering. Correct anything that is wrong. Emptying a note and saving deletes it.</p>
        <?php foreach ($notes as $note): ?>
            <form method="POST" style="margin-bottom:14px;">
                <?= $csrf ?><input type="hidden" name="action" value="note"><input type="hidden" name="name" value="<?= $h($note['name']) ?>">
                <input type="hidden" name="anchor" value="notes">
                <strong><?= $h($note['name']) ?></strong> <span class="agent-meta">updated <?= $h($note['updated_at']) ?></span>
                <textarea name="body"><?= $h($note['body']) ?></textarea>
                <div class="agent-row"><button type="submit" class="btn btn-small btn-blue">Save</button></div>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel" id="runs">
    <div class="panel-header"><h2>Recent runs</h2></div>
    <div class="panel-content">
        <?php if (!$runs): ?><p class="hint" style="margin:0;">It has not run yet.</p><?php endif; ?>
        <?php foreach ($runs as $run): ?>
            <div class="agent-card">
                <strong><?= $h($run['started_at']) ?></strong>
                <span class="agent-meta">
                    <?= $run['finished_at'] ? 'finished' : 'did not finish' ?> &middot; <?= (int) $run['api_calls'] ?> requests
                    &middot; <?= $run['emailed_at'] ? 'update emailed' : 'no update emailed' ?>
                </span>
                <p class="agent-pre"><?= $h($run['summary'] ?: 'No summary.') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel" id="decided">
    <div class="panel-header"><h2>Posts and emails already decided</h2></div>
    <div class="panel-content">
        <div class="leads-table-wrapper"><table class="data-table">
            <thead><tr><th>When</th><th>What</th><th>Outcome</th><th>Text</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($decided as $row): $p = json_decode((string) $row['payload'], true) ?: []; ?>
                <tr>
                    <td><?= $h($row['decided_at'] ?? $row['created_at']) ?></td>
                    <td><?= $row['kind'] === 'post' ? 'Post, ' . $h($row['platform']) : 'Email to ' . $h($p['business_name'] ?? '') ?></td>
                    <td class="<?= $row['status'] === 'done' ? 'agent-ok' : ($row['status'] === 'failed' ? 'agent-bad' : '') ?>">
                        <?= $h($row['status']) ?><?= $row['edited'] ? ', you edited it' : '' ?><?= $row['owner_note'] ? ': ' . $h($row['owner_note']) : '' ?>
                    </td>
                    <td><?= $h(mb_substr((string) ($p['text'] ?? $p['subject'] ?? ''), 0, 140)) ?></td>
                    <td class="agent-meta"><?= $h(mb_substr((string) $row['result'], 0, 200)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

<div class="panel" id="journal">
    <div class="panel-header"><h2>Journal</h2></div>
    <div class="panel-content">
        <?php if (!$journal): ?><p class="hint" style="margin:0;">Empty so far.</p><?php endif; ?>
        <?php foreach ($journal as $entry): ?>
            <div class="agent-card">
                <strong><?= $h($entry['title']) ?></strong>
                <span class="agent-meta"><?= $h($entry['kind']) ?><?= $entry['experiment_key'] ? ' &middot; ' . $h($entry['experiment_key']) : '' ?> &middot; <?= $h($entry['created_at']) ?></span>
                <p class="agent-pre"><?= $h($entry['body']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel" id="actions">
    <div class="panel-header"><h2>The site's record of what it did</h2></div>
    <div class="panel-content">
        <div class="leads-table-wrapper"><table class="data-table">
            <thead><tr><th>When</th><th>Run</th><th>Action</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($actions as $action): ?>
                <tr>
                    <td><?= $h($action['created_at']) ?></td>
                    <td><?= $h($action['run_id']) ?></td>
                    <td><?= $h($action['action']) ?></td>
                    <td class="<?= $action['status'] === 'ok' ? 'agent-ok' : 'agent-bad' ?>"><?= $h($action['status']) ?></td>
                    <td class="agent-meta"><?= $h(mb_substr((string) $action['detail'], 0, 260)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>

        </main>
    </div>
</body>

</html>
