<?php
/**
 * The social platforms the agent can post to: Bluesky and LinkedIn.
 *
 * Each platform has the same three pieces: whether it is connected, how to post, and how to
 * read back how a post did. X is not here. It is the plan's last stage, to be added only once
 * the free platforms are bringing installs.
 *
 * Every function that talks to a platform takes the HTTP function as an argument, so tests
 * can stand in for the network. Credentials never leave the server: the agent asks for a
 * post and the site makes it.
 */

/** Longest text each platform takes, link included where the link goes in the text. */
const AGENT_PLATFORM_LIMITS = ['bluesky' => 300, 'linkedin' => 2800];

/**
 * Sends one HTTP request and returns [status, headers, body].
 *
 * @param array<int, string> $headers
 * @return array{0: int, 1: array<string, string>, 2: string}
 */
function agent_http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    $responseHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $responseHeaders, $response === false ? '' : (string) $response];
}

/** A platform call that did not work, with the platform's own answer for the record. */
class AgentPlatformFailed extends RuntimeException
{
}

function agent_json(string $body): array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

// ─── Connections ───

/**
 * Which platforms can be posted to right now, and when each sign-in runs out.
 *
 * @return array<string, array{connected: bool, expires_at: ?int, detail: string}>
 */
function agent_connections(PDO $pdo): array
{
    $out = [];

    $handle = $_ENV['BLUESKY_HANDLE'] ?? '';
    $out['bluesky'] = [
        'connected' => $handle !== '' && ($_ENV['BLUESKY_APP_PASSWORD'] ?? '') !== '',
        'expires_at' => null,
        'detail' => $handle !== '' ? $handle : 'BLUESKY_HANDLE and BLUESKY_APP_PASSWORD are not set in .env',
    ];

    $expires = (int) agent_setting($pdo, 'linkedin_expires_at');
    $has = agent_setting($pdo, 'linkedin_token') !== '' && agent_setting($pdo, 'linkedin_account') !== '';
    $out['linkedin'] = [
        'connected' => $has && $expires > time(),
        'expires_at' => $has ? $expires : null,
        'detail' => !$has ? 'LinkedIn is not connected' : ($expires > time() ? 'connected' : 'the LinkedIn sign-in has expired'),
    ];
    return $out;
}

function agent_store_connection(PDO $pdo, string $platform, string $token, string $account, int $expiresAt): void
{
    agent_setting_set($pdo, $platform . '_token', portal_encrypt($token));
    agent_setting_set($pdo, $platform . '_account', $account);
    agent_setting_set($pdo, $platform . '_expires_at', (string) $expiresAt);
}

function agent_forget_connection(PDO $pdo, string $platform): void
{
    foreach (['_token', '_account', '_expires_at'] as $suffix) {
        agent_setting_set($pdo, $platform . $suffix, '');
    }
}

/** @return array{token: string, account: string} */
function agent_connection(PDO $pdo, string $platform): array
{
    if (!(agent_connections($pdo)[$platform]['connected'] ?? false)) {
        throw new AgentPlatformFailed(ucfirst($platform) . ' is not connected, or its sign-in has expired.');
    }
    return ['token' => portal_decrypt(agent_setting($pdo, $platform . '_token')),
            'account' => agent_setting($pdo, $platform . '_account')];
}

// ─── Posting ───

/**
 * Publishes one post and returns the platform's id for it.
 *
 * @param string $link a page on the site, or '' for none
 */
function agent_publish(PDO $pdo, string $platform, string $text, string $link, callable $http): string
{
    return match ($platform) {
        'bluesky'  => agent_publish_bluesky($text, $link, $http),
        'linkedin' => agent_publish_linkedin($pdo, $text, $link, $http),
        default    => throw new AgentPlatformFailed("There is no platform called $platform."),
    };
}

/** @return array{jwt: string, did: string} */
function agent_bluesky_session(callable $http): array
{
    [$status, , $body] = $http('POST', 'https://bsky.social/xrpc/com.atproto.server.createSession',
        ['Content-Type: application/json'],
        json_encode(['identifier' => $_ENV['BLUESKY_HANDLE'] ?? '', 'password' => $_ENV['BLUESKY_APP_PASSWORD'] ?? '']));
    $session = agent_json($body);
    if ($status !== 200 || empty($session['accessJwt']) || empty($session['did'])) {
        throw new AgentPlatformFailed("Bluesky sign-in answered $status: " . mb_substr($body, 0, 300));
    }
    return ['jwt' => $session['accessJwt'], 'did' => $session['did']];
}

function agent_publish_bluesky(string $text, string $link, callable $http): string
{
    $session = agent_bluesky_session($http);

    // Bluesky does not turn a URL into a link by itself. The post has to say which bytes of
    // its text are the link.
    $record = ['$type' => 'app.bsky.feed.post', 'text' => $text, 'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z')];
    if ($link !== '') {
        $record['text'] = $text . "\n\n" . $link;
        $start = strlen($text) + 2;
        $record['facets'] = [[
            'index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($link)],
            'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $link]],
        ]];
    }

    [$status, , $body] = $http('POST', 'https://bsky.social/xrpc/com.atproto.repo.createRecord',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $session['jwt']],
        json_encode(['repo' => $session['did'], 'collection' => 'app.bsky.feed.post', 'record' => $record],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $created = agent_json($body);
    if ($status !== 200 || empty($created['uri'])) {
        throw new AgentPlatformFailed("Bluesky answered $status: " . mb_substr($body, 0, 300));
    }
    return $created['uri'];
}

function agent_publish_linkedin(PDO $pdo, string $text, string $link, callable $http): string
{
    $connection = agent_connection($pdo, 'linkedin');

    $content = ['shareCommentary' => ['text' => $text], 'shareMediaCategory' => $link === '' ? 'NONE' : 'ARTICLE'];
    if ($link !== '') {
        $content['media'] = [['status' => 'READY', 'originalUrl' => $link]];
    }
    [$status, $headers, $body] = $http('POST', 'https://api.linkedin.com/v2/ugcPosts', [
        'Authorization: Bearer ' . $connection['token'],
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
    ], json_encode([
        'author' => 'urn:li:person:' . $connection['account'],
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => ['com.linkedin.ugc.ShareContent' => $content],
        'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if ($status !== 201) {
        throw new AgentPlatformFailed("LinkedIn answered $status: " . mb_substr($body, 0, 300));
    }
    return (string) ($headers['x-restli-id'] ?? '');
}

// ─── Reading back ───

/**
 * How a post has done, where the platform will say. LinkedIn's free access does not return
 * this, so its posts are judged by their tracked link alone.
 *
 * @return array<string, int>|null null when the platform gives nothing back
 */
function agent_post_numbers(PDO $pdo, string $platform, string $postId, callable $http): ?array
{
    if ($postId === '') {
        return null;
    }

    if ($platform === 'bluesky') {
        // Public, so it needs no sign-in.
        [$status, , $body] = $http('GET', 'https://public.api.bsky.app/xrpc/app.bsky.feed.getPosts?uris=' . rawurlencode($postId));
        $post = agent_json($body)['posts'][0] ?? null;
        if ($status !== 200 || $post === null) {
            return null;
        }
        return ['likes' => (int) ($post['likeCount'] ?? 0), 'replies' => (int) ($post['replyCount'] ?? 0),
                'reposts' => (int) ($post['repostCount'] ?? 0), 'quotes' => (int) ($post['quoteCount'] ?? 0)];
    }

    return null;
}
