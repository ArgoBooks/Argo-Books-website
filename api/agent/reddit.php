<?php
/**
 * Reddit's part of the replies: finding threads where someone asks a question Argo Books
 * answers, and saving a reply to one. What every reply shares is in replies.php.
 *
 * Nothing here posts to Reddit. Its API is closed to new sign-ups, and software posting from
 * the owner's account would break its rules. The site reads Reddit's public RSS feeds instead,
 * which need no account.
 */

require_once __DIR__ . '/replies.php';

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

/**
 * Saves a reply to a Reddit thread for the owner to post, and returns its proposal id.
 */
function agent_propose_reddit_reply(PDO $pdo, ?int $runId, array $in): int
{
    $thread = agent_reddit_thread_url((string) ($in['thread_url'] ?? ''));
    $title = trim((string) ($in['thread_title'] ?? ''));
    if ($thread === null) {
        throw new AgentRefused('BAD_INPUT', 'thread_url is the address of a Reddit thread: https://www.reddit.com/r/NAME/comments/ID/...');
    }
    if ($title === '' || mb_strlen($title) > 300) {
        throw new AgentRefused('BAD_INPUT', "thread_title is the thread's title, as Reddit shows it.");
    }
    $reply = agent_reply_text($pdo, $in, 2500);

    $limit = agent_limit($pdo, 'replies_per_day');
    if (agent_replies_today($pdo, 'reddit') >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit Reddit replies is used up.", 429);
    }
    if (agent_replies_today($pdo, 'reddit', 'subreddit', $thread['subreddit']) >= AGENT_REPLIES_PER_SUBREDDIT) {
        throw new AgentRefused('DAILY_LIMIT', 'There are already ' . AGENT_REPLIES_PER_SUBREDDIT . " replies in r/{$thread['subreddit']} today. Find a thread somewhere else.", 429);
    }
    if (agent_reply_exists($pdo, $thread['id'])) {
        throw new AgentRefused('DUPLICATE', 'A reply was already drafted for that thread. One per thread, whatever happened to it.', 409);
    }

    return agent_save_proposal($pdo, $runId, 'reply', 'reddit', [
        'thread_url' => $thread['url'],
        'thread_id' => $thread['id'],
        'subreddit' => $thread['subreddit'],
        'thread_title' => mb_substr($title, 0, 300),
        'text' => $reply['text'],
        'why' => $reply['why'],
        'sources' => $reply['sources'],
        'experiment_key' => mb_substr(trim((string) ($in['experiment_key'] ?? '')), 0, 60),
    ], $reply['hash']);
}
