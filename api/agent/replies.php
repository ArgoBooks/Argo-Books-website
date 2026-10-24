<?php
/**
 * What Reddit and YouTube replies share. The agent finds someone asking a question Argo Books
 * answers and drafts a reply; the owner posts it by hand and marks it posted or skipped.
 *
 * The rules here are the ones that keep a reply from reading as spam on either site: no link,
 * never the same words twice, and one reply per question. Reddit's and YouTube's own parts are
 * in reddit.php and youtube.php.
 */

require_once __DIR__ . '/lib.php';

/** Refuses reply work while the owner has it switched off. */
function agent_require_replies_on(PDO $pdo): void
{
    if (!agent_on($pdo, 'replies_enabled')) {
        throw new AgentRefused('REPLIES_OFF', 'Replies are switched off for the agent.', 403);
    }
}

/** Whether a reply has already been drafted to a thread or comment, whatever became of it. */
function agent_reply_exists(PDO $pdo, string $threadId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'reply'
            AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.thread_id')) = ? LIMIT 1"
    );
    $stmt->execute([current_environment(), $threadId]);
    return (bool) $stmt->fetchColumn();
}

/** Whether text holds a link or a web address, which a reply must not. */
function agent_reply_has_link(string $text): bool
{
    return (bool) preg_match('~https?://|www\.|\b[a-z0-9-]+\.(?:com|ca|net|org|io|app|co|us|uk|dev|ai|me|info|biz)\b~i', $text);
}

/** How many replies on one platform were drafted today and not skipped. */
function agent_replies_today(PDO $pdo, string $platform, ?string $payloadKey = null, ?string $value = null): int
{
    $sql = "SELECT COUNT(*) FROM agent_proposals WHERE environment = ? AND kind = 'reply' AND platform = ?
               AND status != 'rejected' AND created_at >= CURDATE()";
    $params = [current_environment(), $platform];
    if ($payloadKey !== null) {
        $sql .= " AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.$payloadKey'))) = ?";
        $params[] = strtolower((string) $value);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Checks the parts every reply has: its text, why it was chosen, and its sources.
 *
 * @return array{text: string, why: string, sources: string[], hash: string}
 */
function agent_reply_text(PDO $pdo, array $in, int $maxLength): array
{
    $text = trim((string) ($in['text'] ?? ''));
    $why = trim((string) ($in['why'] ?? ''));

    if (mb_strlen($text) < 60 || mb_strlen($text) > $maxLength) {
        throw new AgentRefused('BAD_INPUT', "A reply is 60 to $maxLength characters. Short is better.");
    }
    if (agent_reply_has_link($text)) {
        throw new AgentRefused('BAD_INPUT', 'A reply carries no link and no web address of any kind. Name Argo Books instead; whoever wants it will look it up.');
    }
    if ($why === '') {
        throw new AgentRefused('BAD_INPUT', 'Say in "why" what the person asked and why Argo Books fits it.');
    }
    $sources = agent_sources($in);

    $hash = agent_text_hash($text);
    $seen = $pdo->prepare("SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'reply' AND text_hash = ? LIMIT 1");
    $seen->execute([current_environment(), $hash]);
    if ($seen->fetchColumn()) {
        throw new AgentRefused('DUPLICATE', 'That exact reply was written before. Every reply is written fresh for its question.', 409);
    }

    return ['text' => $text, 'why' => mb_substr($why, 0, 1000), 'sources' => $sources, 'hash' => $hash];
}

/**
 * Proposes a reply on Reddit or YouTube. It always waits for the owner, because he is the one
 * who posts it.
 *
 * @param callable|null $http stands in for agent_http in tests
 * @return array{proposal_id: int, status: string}
 */
function agent_propose_reply(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    agent_require_replies_on($pdo);
    $platform = (string) ($in['platform'] ?? 'reddit');

    $id = match ($platform) {
        'reddit' => agent_propose_reddit_reply($pdo, $runId, $in),
        'youtube' => agent_propose_youtube_reply($pdo, $runId, $in, $http),
        default => throw new AgentRefused('BAD_INPUT', 'platform is reddit or youtube.'),
    };
    return ['proposal_id' => $id, 'status' => 'waiting for the owner to post it'];
}
