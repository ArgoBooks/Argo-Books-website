<?php
/**
 * The marketing agent's endpoint.
 *
 *   POST /api/agent/?action=<name>     Authorization: Bearer <AGENT_API_TOKEN>
 *   body: JSON
 *
 * A run starts with start_run, which returns everything the agent needs, and ends with
 * finish_run, which emails the owner. Every other action names the run it belongs to in
 * "run_id" and counts against that run's allowance.
 *
 * Actions: start_run, sql, numbers, research, journal_add, note_save, link_create,
 * propose_post, propose_email, followup_write, finish_run.
 *
 * Answers are JSON. A refusal the agent should act on comes back as
 * {"ok": false, "reason": "CODE", "message": "..."} with a 4xx status. The rules are in
 * lib.php and actions.php; this file only checks who is calling and routes.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../email_sender.php';
require_once __DIR__ . '/actions.php';

function agent_answer(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Who is calling ───

$token = $_ENV['AGENT_API_TOKEN'] ?? '';
if (strlen($token) < 32) {
    // No token, or one too short to be a secret, means the endpoint does not exist yet.
    agent_answer(['ok' => false, 'reason' => 'NOT_CONFIGURED', 'message' => 'The agent endpoint is not set up.'], 503);
}

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$given = preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $m) ? $m[1] : '';
if (!hash_equals($token, $given)) {
    error_log('agent: request refused, bad or missing token, from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    usleep(500000);
    agent_answer(['ok' => false, 'reason' => 'UNAUTHORIZED', 'message' => 'Missing or wrong token.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agent_answer(['ok' => false, 'reason' => 'BAD_METHOD', 'message' => 'Use POST.'], 405);
}

global $pdo;

if (!agent_on($pdo, 'enabled')) {
    agent_answer(['ok' => false, 'reason' => 'AGENT_OFF', 'message' => 'The agent is switched off. Stop here.'], 403);
}

$action = (string) ($_GET['action'] ?? '');
$in = json_decode((string) file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];

// ─── Route ───

try {
    if ($action === 'start_run') {
        $runId = agent_start_run($pdo);
        agent_answer(['ok' => true] + agent_brief($pdo, $runId));
    }

    $runId = (int) ($in['run_id'] ?? 0);
    $left = agent_use_call($pdo, $runId);

    $result = match ($action) {
        'sql' => (function () use ($in) {
            $reader = agent_readonly_pdo();
            if ($reader === null) {
                throw new AgentRefused('NOT_CONFIGURED', 'SQL is not set up: there is no read-only database user. Work from the numbers you were given.', 503);
            }
            return agent_run_sql($reader, (string) ($in['query'] ?? ''));
        })(),
        'numbers'        => ['numbers' => agent_overview($pdo)],
        'research'       => agent_research($pdo, $runId, (string) ($in['question'] ?? '')),
        'journal_add'    => ['journal_id' => agent_journal_add($pdo, $runId, $in)],
        'note_save'      => (function () use ($pdo, $in) {
            agent_note_save($pdo, (string) ($in['name'] ?? ''), (string) ($in['body'] ?? ''));
            return ['saved' => true];
        })(),
        'link_create'    => agent_create_link($pdo, $runId, $in),
        'propose_post'   => agent_propose_post($pdo, $runId, $in),
        'propose_email'  => agent_propose_email($pdo, $runId, $in),
        'followup_write' => ['status' => agent_write_followup($pdo, $runId, $in)],
        'finish_run'     => (function () use ($pdo, $runId, $in) {
            agent_finish_run($pdo, $runId, (string) ($in['summary'] ?? ''));
            return ['finished' => true, 'update_emailed' => agent_send_update($pdo, $runId)];
        })(),
        default => throw new AgentRefused('UNKNOWN_ACTION', "There is no action called \"$action\".", 404),
    };

    agent_answer(['ok' => true, 'calls_left' => $left] + $result);
} catch (AgentRefused $e) {
    agent_answer(['ok' => false, 'reason' => $e->reason, 'message' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('agent: ' . $action . ' failed: ' . $e->getMessage());
    agent_answer(['ok' => false, 'reason' => 'SITE_ERROR', 'message' => 'The site hit a problem with that. Do not retry more than once.'], 500);
}
