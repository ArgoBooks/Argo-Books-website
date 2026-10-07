<?php
/**
 * Referral tracking middleware
 * Include this file at the top of pages where you want to track referral sources
 *
 * Source resolution order:
 *   1. ?source= URL param (manual codes: sponsors, outreach campaigns, ads)
 *   2. ?utm_source= URL param mapped to a known channel
 *   3. ?ref= URL param mapped to a known channel (Product Hunt, directories)
 *   4. HTTP Referer header mapped to a known channel (AI chats, social sites)
 */

// dev-gate.php gates the dev subdomain only and is a no-op on production, so including it here is safe on every public page.
require_once __DIR__ . '/dev-gate.php';

require_once __DIR__ . '/statistics.php';
require_once __DIR__ . '/track_referral_event.php';
// get_auto_referral_sources() lives in referral_sources.php (shared with the
// marketing-funnel channel classifier).
require_once __DIR__ . '/referral_sources.php';
require_once __DIR__ . '/referral_categories.php';

/**
 * Ensure a referral_links row exists for an auto-detected source.
 * The admin dashboard joins on referral_links, so the row must exist
 * for visits to appear in the charts.
 */
function ensure_auto_referral_link($source_code, $name)
{
    global $pdo;
    if (!$pdo) {
        return;
    }
    try {
        $check = $pdo->prepare('SELECT 1 FROM referral_links WHERE source_code = ? LIMIT 1');
        $check->execute([$source_code]);
        if ($check->fetch() !== false) {
            return;
        }
        $insert = $pdo->prepare(
            'INSERT INTO referral_links (source_code, name, category, description, target_url, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $insert->execute([
            $source_code,
            $name,
            referral_default_category($source_code),
            'Auto-detected',
            'https://argorobots.com/'
        ]);
    } catch (PDOException $e) {
        // Don't break tracking on a transient DB error
    }
}

/**
 * Normalize an unknown ?ref= value into a directory source.
 *
 * Directory sites (Fazier, Twelve Tools, Startup Fame, ...) tag their outbound
 * links with ?ref=<their-name>. Rather than hand-add every one to
 * get_auto_referral_sources(), any unrecognized ref is auto-filed under the
 * "dir-" prefix, which the admin page groups into its own "Directories" section.
 *
 * Returns ['code' => 'dir-slug', 'name' => 'Slug'] or null if the ref is empty
 * or self-referential (our own domain, which some links echo back).
 */
function normalize_directory_ref($ref)
{
    $slug = strtolower(trim($ref));
    // Collapse anything that isn't a-z0-9 into single hyphens.
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') {
        return null;
    }
    // Skip self-references (e.g. ?ref=argorobots.com) so we don't track ourselves.
    if (strpos($slug, 'argorobots') !== false || strpos($slug, 'argo-books') !== false) {
        return null;
    }
    // Keep codes sane; directory names are short.
    $slug = substr($slug, 0, 40);
    $name = ucwords(str_replace('-', ' ', $slug));
    return ['code' => 'dir-' . $slug, 'name' => $name];
}

$resolved_source = null;
// Holds an auto-detected directory source so its referral_links row is created
// only after a real visit is confirmed (see bottom of file).
$auto_directory = null;

// 1. Explicit ?source= param (sponsors, outreach campaigns, ads)
if (isset($_GET['source']) && !empty($_GET['source'])) {
    $candidate = trim($_GET['source']);
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $candidate)) {
        // Google auto-tagging puts a signed gclid on every real ad click, so a `?source=google- ads-...` URL without one is almost certainly a bot.
        $is_google_ads = strpos($candidate, 'google-ads-') === 0;
        if (!$is_google_ads || !empty($_GET['gclid'])) {
            $resolved_source = $candidate;
        }
    }
}

// 2. ?utm_source= param mapped to a known channel
if ($resolved_source === null && !empty($_GET['utm_source'])) {
    $utm = strtolower(trim($_GET['utm_source']));
    $sources = get_auto_referral_sources();
    if (isset($sources[$utm])) {
        $mapping = $sources[$utm];
        ensure_auto_referral_link($mapping['code'], $mapping['name']);
        $resolved_source = $mapping['code'];
    }
}

// A ?ref= value is accepted only when it matches a known channel and never as a raw source, because ref also carries affiliate ids.
if ($resolved_source === null && !empty($_GET['ref'])) {
    $ref = strtolower(trim($_GET['ref']));
    $sources = get_auto_referral_sources();
    if (isset($sources[$ref])) {
        $mapping = $sources[$ref];
        ensure_auto_referral_link($mapping['code'], $mapping['name']);
        $resolved_source = $mapping['code'];
    } else {
        // Unknown ref: treat as a directory submission and auto-file it.
        // The referral_links row is created later, only if the visit is real.
        $dir = normalize_directory_ref($ref);
        if ($dir !== null) {
            $resolved_source = $dir['code'];
            $auto_directory = $dir;
        }
    }
}

// 4. HTTP Referer header mapped to a known channel
if ($resolved_source === null && !empty($_SERVER['HTTP_REFERER'])) {
    $referer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($referer_host) {
        $referer_host = strtolower($referer_host);
        $sources = get_auto_referral_sources();
        if (isset($sources[$referer_host])) {
            $mapping = $sources[$referer_host];
            ensure_auto_referral_link($mapping['code'], $mapping['name']);
            $resolved_source = $mapping['code'];
        }
    }
}

if ($resolved_source !== null) {
    // Guide and invoice-generator sources register themselves so they appear in the referral admin without being set up by hand.
    if ($resolved_source === 'guides-hub' || strncmp($resolved_source, 'guide-', 6) === 0) {
        $auto_name = $resolved_source === 'guides-hub'
            ? 'Guides hub'
            : 'Guide: ' . ucwords(str_replace('-', ' ', substr($resolved_source, 6)));
        ensure_auto_referral_link($resolved_source, $auto_name);
    } elseif (strncmp($resolved_source, 'invgen-', 7) === 0) {
        $slug = substr($resolved_source, 7);
        $auto_name = $slug === 'tool'
            ? 'Invoice generator (tool)'
            : 'Invoice generator: ' . ucwords(str_replace('-', ' ', $slug));
        ensure_auto_referral_link($resolved_source, $auto_name);
    }

    $tracked = track_referral_visit($resolved_source, $_SERVER['REQUEST_URI']);

    // For an auto-detected directory, only create the referral_links row once a real visit lands (track_referral_visit returns false for bots, our own non-tracked IP, and same-IP repeats).
    if ($tracked && $auto_directory !== null) {
        ensure_auto_referral_link($auto_directory['code'], $auto_directory['name']);
    }
}

// Fire a landing event for every page that requires this file. Tracks visitors with no source too (resolved_source = null) so the funnel captures "direct/unknown" traffic alongside paid sources.
track_referral_event('landing', [
    'source_code' => $resolved_source,
    'event_data'  => [
        'resolved_source' => $resolved_source,
        'referer'         => $_SERVER['HTTP_REFERER'] ?? null,
    ],
]);
