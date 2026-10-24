<?php
/**
 * Connects LinkedIn to the marketing agent by signing in to it.
 *
 *   connect.php?platform=linkedin    sends the admin to LinkedIn to sign in
 *   connect.php?code=...&state=...   LinkedIn sends them back here with a code
 *
 * The code is swapped for a sign-in the site can post with, which is stored encrypted. The
 * agent never sees it. The address LinkedIn sends back to carries no parameters, because it
 * only accepts the exact address registered with it:
 *   https://argorobots.com/admin/agent/connect.php
 *
 * Bluesky is not connected here. It uses an app password in .env.
 */

// The admin cookie is SameSite=Strict, so the browser leaves it off the request a platform sends here, and starting a session without it would replace the admin's login with an empty one. Reloading from this page makes the request same-site, and the cookie comes with it.
if ((isset($_GET['code']) || isset($_GET['error'])) && !isset($_GET['returned'])) {
    $again = htmlspecialchars('connect.php?' . http_build_query($_GET + ['returned' => '1']), ENT_QUOTES, 'UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $again . '">'
       . '<title>Finishing the sign-in</title><p>Finishing the sign-in. <a href="' . $again . '">Continue</a> if this page stays.</p>';
    exit;
}

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

    header('Location: https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $platforms[$platform]['id'],
        'redirect_uri' => $redirectUri,
        'state' => $state,
        // openid and profile are what return the member's id, which a post has to name.
        'scope' => 'openid profile w_member_social',
    ]));
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

$app = $platforms['linkedin'];

[$status, , $body] = agent_http('POST', 'https://www.linkedin.com/oauth/v2/accessToken', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
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
