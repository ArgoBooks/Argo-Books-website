<?php
/**
 * Shared admin session bootstrap.
 *
 * Every admin entry point includes this INSTEAD of calling session_start()
 * directly, so the admin login is configured identically everywhere and no
 * longer depends on the live host's php.ini.
 *
 * Why this exists: on the production server the default
 * session.gc_maxlifetime was short enough that admins were logged out after a
 * few minutes of inactivity. This bootstrap fixes that by:
 *   1. Setting the admin session lifetime in code (8 hours), so idle time no
 *      longer relies on the host default.
 *   2. Storing admin sessions in a private directory, so another app's
 *      short-lifetime garbage collection (or a host cron sweeping the default
 *      session dir) can't delete them early. Falls back to the default path if
 *      that directory can't be created or written, so login never breaks.
 *   3. Using a dedicated cookie name, so the public site's session (a community
 *      login regenerating its id, for example) can't disturb an admin session.
 *
 * Include this before any output.
 */

// Never double-start (some endpoints may be reached more than one way).
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

require_once __DIR__ . '/../env_helper.php';

// 8 hours: long enough that an admin is never logged out mid-session for being
// idle, short enough that an abandoned session still expires the same day.
ini_set('session.gc_maxlifetime', (string) (8 * 60 * 60));

// Keep admin sessions out of the shared system session directory (which the host may sweep aggressively).
$admin_session_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'argo_admin_sessions';
if (!is_dir($admin_session_dir)) {
    @mkdir($admin_session_dir, 0700, true);
}
if (is_dir($admin_session_dir) && is_writable($admin_session_dir)) {
    session_save_path($admin_session_dir);
}

// Dedicated cookie so admin and public-site sessions are fully isolated.
session_name('ARGOADMINSESS');

// Same hardening the admin cookie has always used: HTTPS-only in production,
// not readable by JS, and not sent on cross-site requests.
session_set_cookie_params([
    'lifetime'  => 0,
    'path'      => '/',
    'secure'    => env('APP_ENV', 'sandbox') === 'production',
    'httponly'  => true,
    'samesite'  => 'Strict',
]);

session_start();

// Remembers the admin page being asked for so login can return there, skipping the login and logout endpoints and anything that is not a GET.
$admin_request_uri = $_SERVER['REQUEST_URI'] ?? '';
if ($admin_request_uri !== ''
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && strpos($admin_request_uri, '/login.php') === false
    && strpos($admin_request_uri, '/logout.php') === false) {
    $_SESSION['admin_return_to'] = $admin_request_uri;
}
