<?php
/**
 * Saving referral links. The admin page, the marketing agent and the owner's API all add
 * links through here, each with its own rules about who may add what.
 */

require_once __DIR__ . '/referral_categories.php';

/** The longest source code the referral_links table holds. */
const REFERRAL_CODE_MAX_LENGTH = 100;

/**
 * Adds a link. A source code already in use throws PDOException with code 23000, which each
 * caller turns into its own answer.
 */
function referral_link_insert(PDO $pdo, string $code, string $name, string $category, string $target): void
{
    $pdo->prepare('INSERT INTO referral_links (source_code, name, category, target_url) VALUES (?, ?, ?, ?)')
        ->execute([$code, mb_substr($name, 0, 255), $category, $target]);
}

/** The address to share for a link. */
function referral_link_url(string $target, string $code): string
{
    return $target . (str_contains($target, '?') ? '&' : '?') . 'source=' . rawurlencode($code);
}

/** Whether an address is a page on the site, which is the only place a link may lead. */
function referral_is_site_url(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    return ($parts['scheme'] ?? '') === 'https' && ($host === 'argorobots.com' || $host === 'www.argorobots.com');
}

/**
 * Adds one link for the owner's API, or reports the one already there. Asking twice for the same
 * code changes nothing, so a script can be run again safely.
 *
 * @return array{status: string, source_code: string, url: string}
 * @throws InvalidArgumentException when the link is not valid, with a message saying why
 */
function referral_api_add(PDO $pdo, array $in): array
{
    $code = trim((string) ($in['source_code'] ?? ''));
    $name = trim((string) ($in['name'] ?? ''));
    $target = trim((string) ($in['target_url'] ?? 'https://argorobots.com/'));
    $category = (string) ($in['category'] ?? referral_default_category($code));

    // These are the characters track_referral.php accepts, so a code outside them would never record a visit.
    if (strlen($code) > REFERRAL_CODE_MAX_LENGTH || !preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
        throw new InvalidArgumentException('source_code is letters, digits, hyphens and underscores, up to ' . REFERRAL_CODE_MAX_LENGTH . ' characters.');
    }
    if ($name === '') {
        throw new InvalidArgumentException('Give the link a name.');
    }
    if (!isset(referral_categories()[$category])) {
        throw new InvalidArgumentException('category is one of: ' . implode(', ', array_keys(referral_categories())) . '.');
    }
    if (!referral_is_site_url($target)) {
        throw new InvalidArgumentException('target_url must be a page on https://argorobots.com.');
    }

    $existing = $pdo->prepare('SELECT target_url FROM referral_links WHERE source_code = ?');
    $existing->execute([$code]);
    $saved = $existing->fetchColumn();
    if ($saved !== false) {
        return ['status' => 'exists', 'source_code' => $code, 'url' => referral_link_url((string) $saved, $code)];
    }

    referral_link_insert($pdo, $code, $name, $category, $target);
    return ['status' => 'created', 'source_code' => $code, 'url' => referral_link_url($target, $code)];
}

/**
 * Every link with its visits and installs, newest first.
 *
 * @return list<array<string, mixed>>
 */
function referral_api_list(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT rl.source_code, rl.name, rl.category, rl.target_url, rl.is_active, rl.created_at,
                (SELECT COUNT(*) FROM referral_visits rv WHERE rv.source_code = rl.source_code) AS visits,
                (SELECT COUNT(*) FROM referral_events re
                  WHERE re.source_code = rl.source_code AND re.event_type = 'app_first_run'
                    AND re.environment = ?) AS installs
         FROM referral_links rl
         ORDER BY rl.created_at DESC, rl.id DESC"
    );
    $stmt->execute([current_environment()]);
    return $stmt->fetchAll();
}
