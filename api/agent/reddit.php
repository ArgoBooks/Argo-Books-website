<?php
/**
 * Reddit replies: the agent finds people asking a question Argo Books answers and drafts a
 * reply, which waits on the admin page for the owner to post by hand.
 *
 * Nothing here posts to Reddit. Its API is closed to new sign-ups, and software posting from
 * the owner's account would break its rules. The site reads Reddit's public RSS feeds instead,
 * which need no account. A reply never carries a link: Reddit's spam filter watches young
 * accounts that repeat one, and the product's name is enough for someone who wants to find it.
 */

require_once __DIR__ . '/lib.php';

const AGENT_REDDIT_USER_AGENT = 'Mozilla/5.0 (compatible; ArgoBooksAgent/1.0; +https://argorobots.com)';

/** The most replies in one subreddit a day, so the account does not look like it works one community. */
const AGENT_REPLIES_PER_SUBREDDIT = 2;

/**
 * The subreddit and id of a Reddit thread address, with the address in one canonical form.
 *
 * @return array{subreddit: string, id: string, url: string}|null null when it is not a thread
 */
function agent_reddit_thread_url(string $url): ?array
{
    if (!preg_match('~^https?://(?:www\.|old\.|new\.)?reddit\.com/r/([A-Za-z0-9_]{2,21})/comments/([A-Za-z0-9]{3,12})(?:[/?#].*)?$~', trim($url), $m)) {
        return null;
    }
    $id = strtolower($m[2]);
    return ['subreddit' => $m[1], 'id' => $id, 'url' => "https://www.reddit.com/r/{$m[1]}/comments/$id/"];
}

/**
 * Reads one Reddit RSS feed and returns its entries in the order Reddit gives them.
 *
 * @return array<int, array{title: string, url: string, author: string, posted: string, text: string}>
 */
function agent_reddit_feed(string $url, callable $http): array
{
    [$status, , $body] = $http('GET', $url, ['User-Agent: ' . AGENT_REDDIT_USER_AGENT, 'Accept: application/atom+xml']);
    if ($status === 429) {
        throw new AgentRefused('REDDIT_BUSY', 'Reddit is limiting how fast it answers. Wait a minute before the next Reddit request.', 429);
    }
    if ($status === 403) {
        throw new AgentRefused('REDDIT_BLOCKED', "Reddit refused the site's request. Fetch this feed yourself instead: $url", 502);
    }
    if ($status === 404) {
        throw new AgentRefused('NOT_FOUND', 'Reddit has no such subreddit or thread.', 404);
    }
    if ($status !== 200) {
        throw new AgentRefused('REDDIT_FAILED', "Reddit answered $status. Try again later.", 502);
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_use_internal_errors($previous);
    if ($xml === false) {
        throw new AgentRefused('REDDIT_FAILED', 'Reddit sent something that is not a feed. Try again later.', 502);
    }

    $entries = [];
    foreach ($xml->entry as $entry) {
        $html = html_entity_decode((string) $entry->content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', $html))));
        // Reddit ends every post in a feed with a "submitted by /u/name [link] [comments]" line that is not part of what was written.
        $text = preg_replace('~\s*submitted by\s+/u/\S+\s*\[link\]\s*\[comments\]$~i', '', $text);
        $entries[] = [
            'title' => trim((string) $entry->title),
            'url' => (string) ($entry->link['href'] ?? ''),
            'author' => preg_replace('~^/?u/~', '', trim((string) $entry->author->name)),
            'posted' => (string) ($entry->published ?: $entry->updated),
            'text' => $text,
        ];
    }
    return $entries;
}

/** Refuses Reddit work while the owner has it switched off. */
function agent_require_replies_on(PDO $pdo): void
{
    if (!agent_on($pdo, 'replies_enabled')) {
        throw new AgentRefused('REPLIES_OFF', 'Reddit replies are switched off for the agent.', 403);
    }
}

/** Whether a reply has already been drafted for a thread, whatever became of it. */
function agent_reply_exists(PDO $pdo, string $threadId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'reply'
            AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.thread_id')) = ? LIMIT 1"
    );
    $stmt->execute([current_environment(), $threadId]);
    return (bool) $stmt->fetchColumn();
}

/** Runs one Reddit read, counting it against the day's allowance and recording it. */
function agent_reddit_read(PDO $pdo, ?int $runId, string $url, array $detail, ?callable $http): array
{
    agent_require_replies_on($pdo);
    agent_require_room($pdo, 'reddit_read', 'reddit_reads_per_day', 'Reddit reads');
    try {
        $entries = agent_reddit_feed($url, $http ?? 'agent_http');
    } catch (AgentRefused $e) {
        agent_log($pdo, $runId, 'reddit_read', 'failed', $detail + ['reason' => $e->reason]);
        throw $e;
    }
    agent_log($pdo, $runId, 'reddit_read', 'ok', $detail + ['entries' => count($entries)]);
    return $entries;
}

/**
 * Searches one subreddit for recent threads. Reddit's search is loose, so these are leads to
 * read with agent_reddit_thread, not questions known to fit.
 */
function agent_reddit_search(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    $subreddit = preg_replace('~^/?r/~i', '', trim((string) ($in['subreddit'] ?? '')));
    $query = trim((string) ($in['query'] ?? ''));
    $period = (string) ($in['period'] ?? 'week');

    if (!preg_match('/^[A-Za-z0-9_]{2,21}$/', $subreddit)) {
        throw new AgentRefused('BAD_INPUT', 'subreddit is a subreddit name such as "smallbusiness", without "r/".');
    }
    if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
        throw new AgentRefused('BAD_INPUT', 'query is 2 to 100 characters.');
    }
    if (!in_array($period, ['day', 'week', 'month'], true)) {
        throw new AgentRefused('BAD_INPUT', 'period is day, week or month.');
    }

    $url = "https://www.reddit.com/r/$subreddit/search.rss?" . http_build_query(['q' => $query, 'restrict_sr' => 1, 'sort' => 'new', 't' => $period]);
    $entries = agent_reddit_read($pdo, $runId, $url, ['subreddit' => $subreddit, 'query' => $query], $http);

    $threads = [];
    foreach ($entries as $entry) {
        $thread = agent_reddit_thread_url($entry['url']);
        if ($thread === null) {
            continue;
        }
        $threads[] = [
            'title' => $entry['title'],
            'url' => $thread['url'],
            'author' => $entry['author'],
            'posted' => $entry['posted'],
            'opening' => mb_substr($entry['text'], 0, 300),
            'already_drafted' => agent_reply_exists($pdo, $thread['id']),
        ];
    }
    return ['subreddit' => $subreddit, 'threads' => $threads];
}

/** Reads one thread: the question and the replies it already has. */
function agent_reddit_thread(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    $thread = agent_reddit_thread_url((string) ($in['url'] ?? ''));
    if ($thread === null) {
        throw new AgentRefused('BAD_INPUT', 'url is the address of a Reddit thread: https://www.reddit.com/r/NAME/comments/ID/...');
    }

    $entries = agent_reddit_read($pdo, $runId, $thread['url'] . '.rss', ['thread' => $thread['url']], $http);
    if (!$entries) {
        throw new AgentRefused('NOT_FOUND', 'That thread has nothing in it Reddit will show. Leave it.', 404);
    }

    $post = array_shift($entries);
    return [
        'url' => $thread['url'],
        'subreddit' => $thread['subreddit'],
        'title' => $post['title'],
        'author' => $post['author'],
        'posted' => $post['posted'],
        'question' => mb_substr($post['text'], 0, 4000),
        'replies' => array_map(fn ($e) => [
            'author' => $e['author'],
            'posted' => $e['posted'],
            'text' => mb_substr($e['text'], 0, 600),
        ], array_slice($entries, 0, 60)),
        'already_drafted' => agent_reply_exists($pdo, $thread['id']),
    ];
}

/** Whether text holds a link or a web address, which a reply must not. */
function agent_reply_has_link(string $text): bool
{
    return (bool) preg_match('~https?://|www\.|\b[a-z0-9-]+\.(?:com|ca|net|org|io|app|co|us|uk|dev|ai|me|info|biz)\b~i', $text);
}

/**
 * Proposes a reply to a Reddit thread. It always waits for the owner, because he is the one
 * who posts it.
 *
 * @return array{proposal_id: int, status: string}
 */
function agent_propose_reply(PDO $pdo, ?int $runId, array $in): array
{
    agent_require_replies_on($pdo);

    $thread = agent_reddit_thread_url((string) ($in['thread_url'] ?? ''));
    $title = trim((string) ($in['thread_title'] ?? ''));
    $text = trim((string) ($in['text'] ?? ''));
    $why = trim((string) ($in['why'] ?? ''));

    if ($thread === null) {
        throw new AgentRefused('BAD_INPUT', 'thread_url is the address of a Reddit thread: https://www.reddit.com/r/NAME/comments/ID/...');
    }
    if ($title === '' || mb_strlen($title) > 300) {
        throw new AgentRefused('BAD_INPUT', "thread_title is the thread's title, as Reddit shows it.");
    }
    if (mb_strlen($text) < 60 || mb_strlen($text) > 2500) {
        throw new AgentRefused('BAD_INPUT', 'A reply is 60 to 2500 characters. Short is better.');
    }
    if (agent_reply_has_link($text)) {
        throw new AgentRefused('BAD_INPUT', 'A reply carries no link and no web address of any kind. Name Argo Books instead; whoever wants it will look it up.');
    }
    if ($why === '') {
        throw new AgentRefused('BAD_INPUT', 'Say in "why" what the person asked and why Argo Books fits it.');
    }
    $sources = agent_sources($in);

    $limit = agent_limit($pdo, 'replies_per_day');
    if (agent_proposed_today($pdo, 'reply') >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit Reddit replies is used up.", 429);
    }
    $inSubreddit = $pdo->prepare(
        "SELECT COUNT(*) FROM agent_proposals WHERE environment = ? AND kind = 'reply' AND status != 'rejected'
            AND created_at >= CURDATE() AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.subreddit'))) = ?"
    );
    $inSubreddit->execute([current_environment(), strtolower($thread['subreddit'])]);
    if ((int) $inSubreddit->fetchColumn() >= AGENT_REPLIES_PER_SUBREDDIT) {
        throw new AgentRefused('DAILY_LIMIT', 'There are already ' . AGENT_REPLIES_PER_SUBREDDIT . " replies in r/{$thread['subreddit']} today. Find a thread somewhere else.", 429);
    }
    if (agent_reply_exists($pdo, $thread['id'])) {
        throw new AgentRefused('DUPLICATE', 'A reply was already drafted for that thread. One per thread, whatever happened to it.', 409);
    }

    $hash = agent_text_hash($text);
    $seen = $pdo->prepare("SELECT 1 FROM agent_proposals WHERE environment = ? AND kind = 'reply' AND text_hash = ? LIMIT 1");
    $seen->execute([current_environment(), $hash]);
    if ($seen->fetchColumn()) {
        throw new AgentRefused('DUPLICATE', 'That exact reply was written before. Every reply is written fresh for its thread.', 409);
    }

    $id = agent_save_proposal($pdo, $runId, 'reply', 'reddit', [
        'thread_url' => $thread['url'],
        'thread_id' => $thread['id'],
        'subreddit' => $thread['subreddit'],
        'thread_title' => mb_substr($title, 0, 300),
        'text' => $text,
        'why' => mb_substr($why, 0, 1000),
        'sources' => $sources,
        'experiment_key' => mb_substr(trim((string) ($in['experiment_key'] ?? '')), 0, 60),
    ], $hash);

    return ['proposal_id' => $id, 'status' => 'waiting for the owner to post it'];
}
