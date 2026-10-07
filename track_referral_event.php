<?php
/**
 * Funnel-event logger for cross-session referral attribution.
 *
 * Writes one row per funnel step (landing, downloads_page, download_click,
 * app_first_run, premium_signup, premium_paid, premium_churned) into the
 * referral_events table. Events are tied together by a 1-year visitor_id
 * cookie so a user who lands on an ad today and buys Premium next week
 * still gets attributed to the original source.
 *
 * On Premium signup, the calling code should backfill all prior events for
 * the visitor with the new subscription_id + user_id so analytics queries
 * can join cleanly.
 */

require_once __DIR__ . '/statistics.php';
require_once __DIR__ . '/db_connect.php';

const ARGO_VISITOR_COOKIE = 'argo_visitor_id';
const ARGO_VISITOR_TTL    = 31536000; // 1 year

/**
 * Generate an RFC 4122 v4 UUID.
 */
function generate_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Return the current visitor's UUID, creating + setting the cookie if needed.
 *
 * If headers have already been sent (e.g. inside a binary file-stream
 * endpoint like get_avalonia_installer.php), we still mint a UUID for the
 * current request so the event has a stable visitor_id, but skip
 * setcookie() so PHP doesn't emit a warning. The next page load will set
 * the cookie cleanly.
 */
function get_or_set_visitor_id(): string
{
    if (!empty($_COOKIE[ARGO_VISITOR_COOKIE])
        && preg_match('/^[0-9a-f-]{36}$/i', $_COOKIE[ARGO_VISITOR_COOKIE])) {
        return $_COOKIE[ARGO_VISITOR_COOKIE];
    }
    $uuid = generate_uuid_v4();
    if (!headers_sent()) {
        $secure = (current_environment() === 'production');
        setcookie(ARGO_VISITOR_COOKIE, $uuid, [
            'expires'  => time() + ARGO_VISITOR_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[ARGO_VISITOR_COOKIE] = $uuid;
    }
    return $uuid;
}

/**
 * 8-char HMAC token tying an installer download to a visitor_id.
 *
 * The ONLY place this recipe may live. get_avalonia_installer.php embeds the
 * token in the served installer filename; api/track-app-event.php recomputes
 * it per candidate visitor to resolve the install back to the original click.
 * Any change here (length, algorithm, casing) must ship to both sides at once,
 * which is exactly why they share this function.
 *
 * Returns '' when REFERRAL_TOKEN_SECRET is not configured.
 */
function referral_install_token(string $visitor_id): string
{
    $secret = $_ENV['REFERRAL_TOKEN_SECRET'] ?? '';
    if ($secret === '') {
        return '';
    }
    return substr(hash_hmac('sha256', $visitor_id, $secret), 0, 8);
}

/**
 * Look up country + region + city for an IP, reusing prior lookups when
 * possible so we don't burn ipinfo.io quota for an IP we've already resolved.
 *
 * Returns ['country' => ?string, 'region' => ?string, 'city' => ?string].
 * Any field may be null when it can't be resolved.
 *
 * Cache order: a prior referral_events row for this IP that already has a
 * city (full geo), then a country-only row from referral_events /
 * referral_visits, then a single ipinfo.io/json call.
 */
function lookup_geo_for_ip(?string $ip): array
{
    $empty = ['country' => null, 'region' => null, 'city' => null];
    if (empty($ip)) {
        return $empty;
    }
    global $pdo;
    if (!$pdo) {
        return $empty;
    }

    try {
        // Prefer a prior row that already resolved the full geo for this IP.
        $stmt = $pdo->prepare('SELECT country_code, region, city FROM referral_events
                               WHERE ip_address = ? AND city IS NOT NULL AND city != ""
                               LIMIT 1');
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return [
                'country' => $row['country_code'] ?: null,
                'region'  => $row['region'] ?: null,
                'city'    => $row['city'] ?: null,
            ];
        }

        // Fall back to a country-only cache hit (older rows, or referral_visits).
        $stmt = $pdo->prepare('SELECT country_code FROM referral_events
                               WHERE ip_address = ? AND country_code IS NOT NULL AND country_code != ""
                               LIMIT 1');
        $stmt->execute([$ip]);
        $cached_country = $stmt->fetchColumn();
        if ($cached_country === false) {
            $stmt = $pdo->prepare('SELECT country_code FROM referral_visits
                                   WHERE ip_address = ? AND country_code IS NOT NULL AND country_code != ""
                                   LIMIT 1');
            $stmt->execute([$ip]);
            $cached_country = $stmt->fetchColumn();
        }
        // A country-only cache hit still means we've never resolved region/city
        // for this IP, so fall through to a live lookup to backfill them.
    } catch (PDOException $e) {
        return $empty;
    }

    if (!function_exists('curl_init')) {
        return ['country' => $cached_country ?: null, 'region' => null, 'city' => null];
    }

    $ch = curl_init("https://ipinfo.io/{$ip}/json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ArgoSalesTracker/1.0');
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            return [
                'country' => !empty($data['country']) ? substr(trim($data['country']), 0, 2) : ($cached_country ?: null),
                'region'  => !empty($data['region']) ? substr(trim($data['region']), 0, 100) : null,
                'city'    => !empty($data['city']) ? substr(trim($data['city']), 0, 100) : null,
            ];
        }
    }
    return ['country' => $cached_country ?: null, 'region' => null, 'city' => null];
}

/**
 * Backwards-compatible country-only lookup. Kept for callers that only need
 * the country code (api/data/upload.php, api/data/crash.php).
 */
function lookup_country_for_ip(?string $ip): ?string
{
    return lookup_geo_for_ip($ip)['country'];
}

/**
 * Record a funnel event.
 *
 * @param string $event_type One of: landing, downloads_page, download_click,
 *                           app_first_run, premium_signup, premium_paid,
 *                           premium_churned
 * @param array  $opts       Optional overrides. Recognized keys:
 *                             - visitor_id      (string)   defaults to cookie
 *                             - source_code     (string)   defaults to $_SESSION['referral_source']
 *                             - event_data      (array)    arbitrary JSON payload
 *                             - subscription_id (string)
 *                             - user_id         (int)
 *                             - page_url        (string)   defaults to REQUEST_URI
 *                             - allow_bot       (bool)     skip bot filter (CLI / desktop app)
 *                             - allow_admin     (bool)     skip admin-session filter
 * @return bool True on success
 */
function track_referral_event(string $event_type, array $opts = []): bool
{
    $allow_admin = $opts['allow_admin'] ?? false;
    if (!$allow_admin
        && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return false;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $allow_bot  = $opts['allow_bot'] ?? false;
    if (!$allow_bot && is_likely_bot($user_agent)) {
        return false;
    }

    // Same IP exclusion as the visit/page-view trackers: owner's own connection + crawler netblocks.
    if (!$allow_bot && is_nontracked_ip($_SERVER['REMOTE_ADDR'] ?? null)) {
        return false;
    }

    global $pdo;
    if (!$pdo) {
        return false;
    }

    // Distinguish "caller omitted visitor_id" (mint from cookie) from "caller explicitly passed null" (record unattributed).
    if (array_key_exists('visitor_id', $opts)) {
        $visitor_id = $opts['visitor_id'];
    } else {
        $visitor_id = get_or_set_visitor_id();
    }

    $source_code = $opts['source_code'] ?? ($_SESSION['referral_source'] ?? null);
    $ip_address  = $_SERVER['REMOTE_ADDR'] ?? null;
    $page_url    = $opts['page_url'] ?? ($_SERVER['REQUEST_URI'] ?? null);

    $geo = lookup_geo_for_ip($ip_address);

    // Campaign/search keyword: caller override, else ?utm_term on this request.
    $keyword = $opts['keyword'] ?? null;
    if ($keyword === null && !empty($_GET['utm_term'])) {
        $keyword = trim((string)$_GET['utm_term']);
    }
    $keyword = ($keyword !== null && $keyword !== '') ? substr($keyword, 0, 150) : null;

    $event_data_json = null;
    if (!empty($opts['event_data']) && is_array($opts['event_data'])) {
        $event_data_json = json_encode($opts['event_data'], JSON_UNESCAPED_SLASHES);
    }

    // A page view starts unconfirmed and a client-side beacon promotes it, so a bot that never runs JS stays out of the funnel.
    $js_confirmed = in_array($event_type, ['landing', 'downloads_page'], true) ? 0 : 1;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO referral_events
                (visitor_id, source_code, event_type, event_data,
                 subscription_id, user_id, page_url, ip_address,
                 user_agent, country_code, region, city, keyword, js_confirmed, environment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $visitor_id,
            $source_code,
            $event_type,
            $event_data_json,
            $opts['subscription_id'] ?? null,
            $opts['user_id']         ?? null,
            $page_url,
            $ip_address,
            substr($user_agent, 0, 255),
            $geo['country'],
            $geo['region'],
            $geo['city'],
            $keyword,
            $js_confirmed,
            current_environment(),
        ]);
    } catch (PDOException $e) {
        error_log('track_referral_event failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Backfill prior events for the given visitor with subscription_id + user_id
 * once a Premium signup commits. Called from process-subscription.php and
 * the free-key redemption path.
 */
function backfill_visitor_events(string $visitor_id, string $subscription_id, ?int $user_id = null): void
{
    global $pdo;
    if (!$pdo || empty($visitor_id)) {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE referral_events
                SET subscription_id = ?,
                    user_id = COALESCE(user_id, ?)
              WHERE visitor_id = ? AND subscription_id IS NULL'
        );
        $stmt->execute([$subscription_id, $user_id, $visitor_id]);
    } catch (PDOException $e) {
        error_log('backfill_visitor_events failed: ' . $e->getMessage());
    }
}

/**
 * First-touch referral source for a visitor, read from their recorded events.
 * A durable fallback for when $_SESSION['referral_source'] was lost between the
 * ?source= landing and the purchase (e.g. the buyer created an account or
 * logged in in between, which resets the PHP session). Environment-scoped so it
 * never crosses sandbox/production.
 */
function get_referral_source_for_visitor(string $visitor_id): ?string
{
    global $pdo;
    if (!$pdo || empty($visitor_id)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT source_code FROM referral_events
              WHERE visitor_id = ? AND source_code IS NOT NULL AND environment = ?
              ORDER BY created_at ASC, id ASC
              LIMIT 1'
        );
        $stmt->execute([$visitor_id, current_environment()]);
        $src = $stmt->fetchColumn();
        return ($src !== false && $src !== null) ? (string) $src : null;
    } catch (PDOException $e) {
        error_log('get_referral_source_for_visitor failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Fire a subscription-keyed funnel event (premium_paid / premium_churned) from
 * a webhook, cron, or portal handler.
 *
 * Wraps resolve-attribution, track and log for those call sites. Attribution (visitor_id / source_code / user_id) is resolved
 * from the subscription's premium_signup event; $fallbacks fills any field
 * that resolves to null (browser contexts pass their cookie/session values so
 * a missing signup event still attributes).
 *
 * Never throws: funnel logging must not break payment processing. Failures go
 * to error_log unless $opts['log'] provides a callable (crons pass their own
 * logger so failures land in the cron log).
 *
 * $opts:
 *   - allow_bot (bool, default true): crons and webhooks have no browser UA,
 *     so the bot/IP filters must be bypassed. Browser contexts (portal cancel,
 *     payment retry) pass false to keep them.
 *   - log (callable(string):void): custom failure logger.
 */
function track_subscription_event(string $event_type, string $subscription_id, array $event_data, array $fallbacks = [], array $opts = []): bool
{
    try {
        $attr = find_visitor_for_subscription($subscription_id);
        return track_referral_event($event_type, [
            'visitor_id'      => $attr['visitor_id'] ?? ($fallbacks['visitor_id'] ?? null),
            'source_code'     => $attr['source_code'] ?? ($fallbacks['source_code'] ?? null),
            'subscription_id' => $subscription_id,
            'user_id'         => $attr['user_id'] ?? ($fallbacks['user_id'] ?? null),
            'event_data'      => $event_data,
            'allow_bot'       => $opts['allow_bot'] ?? true,
        ]);
    } catch (Throwable $e) {
        $msg = "$event_type funnel event failed for $subscription_id: " . $e->getMessage();
        if (isset($opts['log']) && is_callable($opts['log'])) {
            ($opts['log'])($msg);
        } else {
            error_log($msg);
        }
        return false;
    }
}

/**
 * Resolve visitor_id + source_code from the most recent premium_signup
 * event for a subscription. Used by webhook/cron handlers that have no
 * session or cookie context.
 *
 * @return array{visitor_id: ?string, source_code: ?string, user_id: ?int}
 */
function find_visitor_for_subscription(string $subscription_id): array
{
    global $pdo;
    $empty = ['visitor_id' => null, 'source_code' => null, 'user_id' => null];
    if (!$pdo || empty($subscription_id)) {
        return $empty;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT visitor_id, source_code, user_id
               FROM referral_events
              WHERE subscription_id = ? AND event_type = 'premium_signup'
              ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$subscription_id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $empty;
        }
        return [
            'visitor_id'  => $row['visitor_id'],
            'source_code' => $row['source_code'],
            'user_id'     => $row['user_id'] !== null ? (int)$row['user_id'] : null,
        ];
    } catch (PDOException $e) {
        return $empty;
    }
}
