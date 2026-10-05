<?php
/**
 * Connects LinkedIn or Threads to the marketing agent by signing in to the platform.
 *
 *   connect.php?platform=linkedin    sends the admin to the platform to sign in
 *   connect.php?code=...&state=...   the platform sends them back here with a code
 *
 * The code is swapped for a sign-in the site can post with, which is stored encrypted. The
 * agent never sees it. The address the platforms send back to carries no parameters, because
 * each platform only accepts the exact address registered with it:
 *   https://argorobots.com/admin/agent/connect.php
 *
 * Bluesky is not connected here. It uses an app password in .env.
 */
require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../env_helper.php';
require_once __DIR__ . '/../../api/agent/actions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$redirectUri = rtrim(env('SITE_URL', 'https://argorobots.com'), '/') . '/admin/agent/connect.php';

$back = function (string $type, string $text): never {
    $_SESSION['agent_message'] = [$type, $text];
    header('Location: index.php?tab=setup');
    exit;
};

$platforms = [
    'linkedin' => ['id' => $_ENV['LINKEDIN_CLIENT_ID'] ?? '', 'secret' => $_ENV['LINKEDIN_CLIENT_SECRET'] ?? ''],
    'threads'  => ['id' => $_ENV['THREADS_APP_ID'] ?? '', 'secret' => $_ENV['THREADS_APP_SECRET'] ?? ''],
];

// ─── Step 1: send the admin to the platform ───

if (isset($_GET['platform'])) {
    $platform = (string) $_GET['platform'];
    if (!isset($platforms[$platform])) {
        $back('error', 'There is no platform by that name to connect.');
    }
    if ($platforms[$platform]['id'] === '' || $platforms[$platform]['secret'] === '') {
        $back('error', ucfirst($platform) . ' cannot be connected yet: its app id and secret are not in .env.');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['agent_connect'] = ['platform' => $platform, 'state' => $state];

    $url = $platform === 'linkedin'
        ? 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $platforms[$platform]['id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            // openid and profile are what return the member's id, which a post has to name.
            'scope' => 'openid profile w_member_social',
        ])
        : 'https://threads.net/oauth/authorize?' . http_build_query([
            'client_id' => $platforms[$platform]['id'],
            'redirect_uri' => $redirectUri,
            'scope' => 'threads_basic,threads_content_publish,threads_manage_insights',
            'response_type' => 'code',
            'state' => $state,
        ]);
    header('Location: ' . $url);
    exit;
}

// ─── Step 2: the platform sent them back ───

$pending = $_SESSION['agent_connect'] ?? null;
unset($_SESSION['agent_connect']);

if (!is_array($pending) || !isset($_GET['state']) || !hash_equals($pending['state'], (string) $_GET['state'])) {
    $back('error', 'That sign-in did not start here, or it took too long. Try connecting again.');
}
if (!isset($_GET['code'])) {
    $back('error', 'The sign-in was not completed: ' . mb_substr((string) ($_GET['error_description'] ?? $_GET['error'] ?? 'no reason given'), 0, 200));
}

$platform = $pending['platform'];
$app = $platforms[$platform];
$form = ['Content-Type: application/x-www-form-urlencoded'];

if ($platform === 'linkedin') {
    [$status, , $body] = agent_http('POST', 'https://www.linkedin.com/oauth/v2/accessToken', $form, http_build_query([
        'grant_type' => 'authorization_code',
        'code' => (string) $_GET['code'],
        'redirect_uri' => $redirectUri,
        'client_id' => $app['id'],
        'client_secret' => $app['secret'],
    ]));
    $token = agent_json($body);
    if ($status !== 200 || empty($token['access_token'])) {
        error_log("agent: LinkedIn token exchange answered $status: " . mb_substr($body, 0, 300));
        $back('error', "LinkedIn would not complete the sign-in (it answered $status).");
    }

    [$status, , $body] = agent_http('GET', 'https://api.linkedin.com/v2/userinfo', ['Authorization: Bearer ' . $token['access_token']]);
    $member = agent_json($body);
    if ($status !== 200 || empty($member['sub'])) {
        error_log("agent: LinkedIn userinfo answered $status: " . mb_substr($body, 0, 300));
        $back('error', 'LinkedIn signed in but would not say which member this is. Check the app has "Sign In with LinkedIn using OpenID Connect" added.');
    }

    agent_store_connection($pdo, 'linkedin', $token['access_token'], (string) $member['sub'], time() + (int) ($token['expires_in'] ?? 5184000));
    $back('success', 'LinkedIn connected as ' . ($member['name'] ?? 'your profile') . '.');
}

// Threads hands back a short sign-in first, which is swapped for one that lasts 60 days.
[$status, , $body] = agent_http('POST', 'https://graph.threads.net/oauth/access_token', $form, http_build_query([
    'client_id' => $app['id'],
    'client_secret' => $app['secret'],
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri,
    'code' => (string) $_GET['code'],
]));
$short = agent_json($body);
if ($status !== 200 || empty($short['access_token']) || empty($short['user_id'])) {
    error_log("agent: Threads token exchange answered $status: " . mb_substr($body, 0, 300));
    $back('error', "Threads would not complete the sign-in (it answered $status).");
}

[$status, , $body] = agent_http('GET', 'https://graph.threads.net/access_token?' . http_build_query([
    'grant_type' => 'th_exchange_token',
    'client_secret' => $app['secret'],
    'access_token' => $short['access_token'],
]));
$long = agent_json($body);
if ($status !== 200 || empty($long['access_token'])) {
    error_log("agent: Threads long-lived exchange answered $status: " . mb_substr($body, 0, 300));
    $back('error', "Threads signed in but would not issue a lasting sign-in (it answered $status).");
}

agent_store_connection($pdo, 'threads', $long['access_token'], (string) $short['user_id'], time() + (int) ($long['expires_in'] ?? 5184000));
$back('success', 'Threads connected.');
