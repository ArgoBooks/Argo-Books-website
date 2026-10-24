<?php
/**
 * YouTube's part of the replies: finding comments under videos about accounting software
 * where someone asks for what Argo Books does, and saving a reply to one. What every reply
 * shares is in replies.php.
 *
 * Reading goes through the YouTube Data API with a key in YOUTUBE_API_KEY. The key is free and
 * comes with a daily allowance: a search costs 100 units of it and reading comments 1, out of
 * 10,000 a day. Nothing here posts to YouTube; the owner posts each reply himself.
 */

require_once __DIR__ . '/replies.php';

const AGENT_YOUTUBE_API = 'https://www.googleapis.com/youtube/v3/';

/** The most replies under one video a day, so the comments do not fill up with Argo Books. */
const AGENT_YOUTUBE_REPLIES_PER_VIDEO = 2;

/** The address that opens a video with one comment highlighted at the top. */
function agent_youtube_comment_url(string $videoId, string $commentId): string
{
    return 'https://www.youtube.com/watch?' . http_build_query(['v' => $videoId, 'lc' => $commentId]);
}

/** Calls the YouTube Data API and returns its answer, or refuses with what the agent should do. */
function agent_youtube_get(string $path, array $params, callable $http): array
{
    $key = $_ENV['YOUTUBE_API_KEY'] ?? '';
    if ($key === '') {
        throw new AgentRefused('NOT_CONFIGURED', 'YouTube is not set up: there is no YOUTUBE_API_KEY. Leave YouTube today and say so in your summary.', 503);
    }

    [$status, , $body] = $http('GET', AGENT_YOUTUBE_API . $path . '?' . http_build_query($params + ['key' => $key]));
    $data = agent_json($body);
    if ($status === 200) {
        return $data;
    }

    $reason = (string) ($data['error']['errors'][0]['reason'] ?? '');
    $message = mb_substr((string) ($data['error']['message'] ?? ''), 0, 200);
    throw match (true) {
        in_array($reason, ['quotaExceeded', 'rateLimitExceeded', 'dailyLimitExceeded'], true)
            => new AgentRefused('YOUTUBE_QUOTA', "YouTube's daily allowance for this site is used up. Leave YouTube until tomorrow.", 429),
        $reason === 'commentsDisabled' => new AgentRefused('NOT_FOUND', 'Comments are turned off on that video. Leave it.', 404),
        $status === 404 || in_array($reason, ['videoNotFound', 'commentNotFound', 'notFound'], true)
            => new AgentRefused('NOT_FOUND', 'YouTube has no such video or comment.', 404),
        $status === 400 || $status === 403
            => new AgentRefused('NOT_CONFIGURED', "YouTube refused the site's key ($message). The owner has to fix it; leave YouTube today and say so in your summary.", 503),
        default => new AgentRefused('YOUTUBE_FAILED', "YouTube answered $status. Try again later.", 502),
    };
}

/** Runs one YouTube read, counting it against the day's allowance and recording it. */
function agent_youtube_read(PDO $pdo, ?int $runId, string $path, array $params, array $detail, ?callable $http): array
{
    agent_require_replies_on($pdo);
    agent_require_room($pdo, 'youtube_read', 'youtube_reads_per_day', 'YouTube reads');
    try {
        $data = agent_youtube_get($path, $params, $http ?? 'agent_http');
    } catch (AgentRefused $e) {
        agent_log($pdo, $runId, 'youtube_read', 'failed', $detail + ['reason' => $e->reason]);
        throw $e;
    }
    agent_log($pdo, $runId, 'youtube_read', 'ok', $detail + ['items' => count($data['items'] ?? [])]);
    return $data;
}

/** Text from YouTube's search, which comes with HTML entities in it. */
function agent_youtube_text(mixed $text): string
{
    return trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * Searches YouTube for videos, with how many views and comments each has, so the agent can go
 * where people are commenting.
 */
function agent_youtube_search(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    $query = trim((string) ($in['query'] ?? ''));
    $period = (string) ($in['period'] ?? 'year');
    $order = (string) ($in['order'] ?? 'relevance');
    $since = ['week' => '-7 days', 'month' => '-1 month', 'year' => '-1 year', 'any' => null];

    if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
        throw new AgentRefused('BAD_INPUT', 'query is 2 to 100 characters.');
    }
    if (!array_key_exists($period, $since)) {
        throw new AgentRefused('BAD_INPUT', 'period is week, month, year or any.');
    }
    if (!in_array($order, ['relevance', 'date', 'viewCount'], true)) {
        throw new AgentRefused('BAD_INPUT', 'order is relevance, date or viewCount.');
    }

    $params = ['part' => 'snippet', 'type' => 'video', 'q' => $query, 'order' => $order, 'maxResults' => 15, 'relevanceLanguage' => 'en'];
    if ($since[$period] !== null) {
        $params['publishedAfter'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($since[$period]));
    }
    $found = agent_youtube_read($pdo, $runId, 'search', $params, ['query' => $query], $http);

    $ids = array_values(array_filter(array_map(fn ($item) => (string) ($item['id']['videoId'] ?? ''), $found['items'] ?? [])));
    if (!$ids) {
        return ['videos' => []];
    }
    $stats = [];
    foreach (agent_youtube_get('videos', ['part' => 'statistics', 'id' => implode(',', $ids)], $http ?? 'agent_http')['items'] ?? [] as $item) {
        $stats[$item['id']] = $item['statistics'] ?? [];
    }

    $videos = [];
    foreach ($found['items'] as $item) {
        $id = (string) ($item['id']['videoId'] ?? '');
        if ($id === '') {
            continue;
        }
        $videos[] = [
            'video_id' => $id,
            'title' => agent_youtube_text($item['snippet']['title'] ?? ''),
            'channel' => agent_youtube_text($item['snippet']['channelTitle'] ?? ''),
            'published' => (string) ($item['snippet']['publishedAt'] ?? ''),
            'views' => (int) ($stats[$id]['viewCount'] ?? 0),
            'comments' => (int) ($stats[$id]['commentCount'] ?? 0),
        ];
    }
    return ['videos' => $videos];
}

/** Reads a video's comments, newest first by default, with the replies each already has. */
function agent_youtube_comments(PDO $pdo, ?int $runId, array $in, ?callable $http = null): array
{
    $videoId = trim((string) ($in['video_id'] ?? ''));
    $order = (string) ($in['order'] ?? 'time');
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
        throw new AgentRefused('BAD_INPUT', 'video_id is the 11-character id of a YouTube video.');
    }
    if (!in_array($order, ['time', 'relevance'], true)) {
        throw new AgentRefused('BAD_INPUT', 'order is time or relevance.');
    }

    $data = agent_youtube_read($pdo, $runId, 'commentThreads', [
        'part' => 'snippet,replies', 'videoId' => $videoId, 'order' => $order, 'maxResults' => 100, 'textFormat' => 'plainText',
    ], ['video_id' => $videoId], $http);

    $comments = [];
    foreach ($data['items'] ?? [] as $item) {
        $top = $item['snippet']['topLevelComment']['snippet'] ?? [];
        $comments[] = [
            'comment_id' => (string) $item['id'],
            'author' => (string) ($top['authorDisplayName'] ?? ''),
            'text' => mb_substr((string) ($top['textOriginal'] ?? $top['textDisplay'] ?? ''), 0, 1000),
            'posted' => (string) ($top['publishedAt'] ?? ''),
            'likes' => (int) ($top['likeCount'] ?? 0),
            'replies' => array_map(fn ($reply) => [
                'author' => (string) ($reply['snippet']['authorDisplayName'] ?? ''),
                'text' => mb_substr((string) ($reply['snippet']['textOriginal'] ?? $reply['snippet']['textDisplay'] ?? ''), 0, 300),
            ], $item['replies']['comments'] ?? []),
            'already_drafted' => agent_reply_exists($pdo, (string) $item['id']),
        ];
    }
    return ['video_id' => $videoId, 'comments' => $comments];
}

/**
 * Saves a reply to a YouTube comment for the owner to post, and returns its proposal id. The
 * comment and its video are read from YouTube here, so the owner sees what is really there.
 */
function agent_propose_youtube_reply(PDO $pdo, ?int $runId, array $in, ?callable $http = null): int
{
    $commentId = trim((string) ($in['comment_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{10,60}$/', $commentId)) {
        throw new AgentRefused('BAD_INPUT', 'comment_id is the id of a top-level YouTube comment, as youtube_comments gives it.');
    }
    $reply = agent_reply_text($pdo, $in, 1500);

    $limit = agent_limit($pdo, 'youtube_replies_per_day');
    if (agent_replies_today($pdo, 'youtube') >= $limit) {
        throw new AgentRefused('DAILY_LIMIT', "Today's limit of $limit YouTube replies is used up.", 429);
    }
    if (agent_reply_exists($pdo, $commentId)) {
        throw new AgentRefused('DUPLICATE', 'A reply was already drafted for that comment. One per comment, whatever happened to it.', 409);
    }

    $http ??= 'agent_http';
    $thread = agent_youtube_get('commentThreads', ['part' => 'snippet', 'id' => $commentId, 'textFormat' => 'plainText'], $http)['items'][0] ?? null;
    if ($thread === null) {
        throw new AgentRefused('NOT_FOUND', 'That comment is not on YouTube any more. Leave it.', 404);
    }
    $videoId = (string) ($thread['snippet']['videoId'] ?? '');
    $comment = $thread['snippet']['topLevelComment']['snippet'] ?? [];

    if (agent_replies_today($pdo, 'youtube', 'video_id', $videoId) >= AGENT_YOUTUBE_REPLIES_PER_VIDEO) {
        throw new AgentRefused('DAILY_LIMIT', 'There are already ' . AGENT_YOUTUBE_REPLIES_PER_VIDEO . ' replies under that video today. Find a comment somewhere else.', 429);
    }
    $video = agent_youtube_get('videos', ['part' => 'snippet', 'id' => $videoId], $http)['items'][0]['snippet'] ?? [];

    return agent_save_proposal($pdo, $runId, 'reply', 'youtube', [
        'thread_url' => agent_youtube_comment_url($videoId, $commentId),
        'thread_id' => $commentId,
        'video_id' => $videoId,
        'thread_title' => mb_substr(agent_youtube_text($video['title'] ?? ''), 0, 300),
        'channel' => agent_youtube_text($video['channelTitle'] ?? ''),
        'comment_author' => (string) ($comment['authorDisplayName'] ?? ''),
        'comment_text' => mb_substr((string) ($comment['textOriginal'] ?? $comment['textDisplay'] ?? ''), 0, 2000),
        'comment_posted' => (string) ($comment['publishedAt'] ?? ''),
        'text' => $reply['text'],
        'why' => $reply['why'],
        'sources' => $reply['sources'],
        'experiment_key' => mb_substr(trim((string) ($in['experiment_key'] ?? '')), 0, 60),
    ], $reply['hash']);
}
