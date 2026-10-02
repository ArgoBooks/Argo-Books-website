<?php
/**
 * outreach_pipeline.php
 *
 * Runs the complete outreach pipeline automatically: picks the next target city
 * from the expansion list, discovers businesses via Google Places, imports them
 * (skipping duplicates), generates AI email drafts for leads without one,
 * auto-approves drafts, and sends approved emails up to the daily limit. Full
 * detail in read-me/Cron-jobs.md.
 *
 * Schedule: daily at 8:00 AM.
 *   0 8 * * * /usr/bin/php /home/argorobots/public_html/cron/outreach_pipeline.php
 *
 * Flags:
 *   --discover-only   Only run discovery + import (Google Places + Shopify)
 *   --shopify-only    Only run Shopify discovery
 *   --draft-only      Only run draft generation
 *   --send-only       Only run send (same as outreach_email.php)
 *   --dry-run         Log what would happen without doing it
 */

set_time_limit(600); // 10 minutes max for full pipeline

// Only allow CLI, or CGI cron (no REMOTE_ADDR means not a web request)
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    die('Access denied. This script can only be run via CLI/cron.');
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/lib/outreach_helpers.php';
require_once __DIR__ . '/lib/shopify_discovery.php';
require_once __DIR__ . '/lib/run_tracker.php';

// ─── Lock file to prevent overlapping runs ───

$lockFile = __DIR__ . '/logs/outreach_pipeline.lock';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

// ─── Configuration ───

define('DAILY_SEND_LIMIT', (int) ($_ENV['OUTREACH_DAILY_SEND_LIMIT'] ?? 10));
// Per-source discovery caps. The two sources run independently and each
// keeps searching (Google: cycling cities; Shopify: cycling dorks) until
// their cap is hit. Defaults split DAILY_SEND_LIMIT roughly in half so
// the two sources contribute equally to the day's queue.
define('DAILY_GOOGLE_DISCOVERY_LIMIT', (int) ($_ENV['OUTREACH_DAILY_GOOGLE_DISCOVERY_LIMIT'] ?? (int) ceil(DAILY_SEND_LIMIT / 2)));
// Follow-ups have their own daily cap, separate from first-touch sends.
// With the multi-touch sequence (touches 2 through N), this cap applies
// across ALL touch positions combined. Default 75; raise via env var
// (OUTREACH_DAILY_FOLLOWUP_LIMIT) once domain reputation supports more.
define('DAILY_FOLLOWUP_LIMIT', (int) ($_ENV['OUTREACH_DAILY_FOLLOWUP_LIMIT'] ?? 75));
define('DAILY_DRAFT_LIMIT', (int) ($_ENV['OUTREACH_DAILY_DRAFT_LIMIT'] ?? 100));

// Parse CLI flags ($argv is null under CGI, fall back to empty array)
$args = array_slice($argv ?? [], 1);
$discoverOnly = in_array('--discover-only', $args);
$shopifyOnly = in_array('--shopify-only', $args);
$draftOnly = in_array('--draft-only', $args);
$sendOnly = in_array('--send-only', $args);
$dryRun = in_array('--dry-run', $args);
$runAll = !$discoverOnly && !$draftOnly && !$sendOnly && !$shopifyOnly;

// ─── Target Cities (Saskatchewan first, then expand outward) ───

$targetCities = [
    // Saskatchewan: primary market
    ['city' => 'Saskatoon', 'province' => 'Saskatchewan'],
    ['city' => 'Regina', 'province' => 'Saskatchewan'],
    ['city' => 'Prince Albert', 'province' => 'Saskatchewan'],
    ['city' => 'Moose Jaw', 'province' => 'Saskatchewan'],
    ['city' => 'Swift Current', 'province' => 'Saskatchewan'],
    ['city' => 'Yorkton', 'province' => 'Saskatchewan'],
    ['city' => 'North Battleford', 'province' => 'Saskatchewan'],
    ['city' => 'Estevan', 'province' => 'Saskatchewan'],
    ['city' => 'Weyburn', 'province' => 'Saskatchewan'],
    ['city' => 'Martensville', 'province' => 'Saskatchewan'],
    ['city' => 'Warman', 'province' => 'Saskatchewan'],
    ['city' => 'Humboldt', 'province' => 'Saskatchewan'],
    ['city' => 'Melfort', 'province' => 'Saskatchewan'],
    ['city' => 'Meadow Lake', 'province' => 'Saskatchewan'],
    ['city' => 'Lloydminster', 'province' => 'Saskatchewan'],
    // Alberta: neighboring province
    ['city' => 'Edmonton', 'province' => 'Alberta'],
    ['city' => 'Calgary', 'province' => 'Alberta'],
    ['city' => 'Red Deer', 'province' => 'Alberta'],
    ['city' => 'Lethbridge', 'province' => 'Alberta'],
    ['city' => 'Medicine Hat', 'province' => 'Alberta'],
    ['city' => 'Grande Prairie', 'province' => 'Alberta'],
    ['city' => 'Airdrie', 'province' => 'Alberta'],
    ['city' => 'Spruce Grove', 'province' => 'Alberta'],
    ['city' => 'St. Albert', 'province' => 'Alberta'],
    // Manitoba: neighboring province
    ['city' => 'Winnipeg', 'province' => 'Manitoba'],
    ['city' => 'Brandon', 'province' => 'Manitoba'],
    ['city' => 'Steinbach', 'province' => 'Manitoba'],
    ['city' => 'Thompson', 'province' => 'Manitoba'],
    ['city' => 'Portage la Prairie', 'province' => 'Manitoba'],
    // British Columbia
    ['city' => 'Vancouver', 'province' => 'British Columbia'],
    ['city' => 'Victoria', 'province' => 'British Columbia'],
    ['city' => 'Kelowna', 'province' => 'British Columbia'],
    ['city' => 'Kamloops', 'province' => 'British Columbia'],
    ['city' => 'Nanaimo', 'province' => 'British Columbia'],
    // Ontario
    ['city' => 'Toronto', 'province' => 'Ontario'],
    ['city' => 'Ottawa', 'province' => 'Ontario'],
    ['city' => 'Hamilton', 'province' => 'Ontario'],
    ['city' => 'London', 'province' => 'Ontario'],
    ['city' => 'Kitchener', 'province' => 'Ontario'],
    ['city' => 'Windsor', 'province' => 'Ontario'],
    ['city' => 'Barrie', 'province' => 'Ontario'],
    ['city' => 'Sudbury', 'province' => 'Ontario'],
    ['city' => 'Thunder Bay', 'province' => 'Ontario'],
];

// ─── Logging ───

function logPipeline($message, $type = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";

    $logFile = __DIR__ . '/logs/outreach_pipeline_' . date('Y-m-d') . '.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// log_activity() is provided by cron/lib/outreach_helpers.php

function getState($pdo, $key, $default = null)
{
    $stmt = $pdo->prepare("SELECT state_value FROM outreach_pipeline_state WHERE state_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['state_value'] : $default;
}

function setState($pdo, $key, $value)
{
    $stmt = $pdo->prepare("INSERT INTO outreach_pipeline_state (state_key, state_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)");
    $stmt->execute([$key, $value]);
}

// ══════════════════════════════════════════════════════════════
//  MAIN PIPELINE
// ══════════════════════════════════════════════════════════════

logPipeline('=== Outreach Pipeline Starting ===');
if ($dryRun) logPipeline('DRY RUN MODE: no changes will be made');

global $pdo;
$cronRunId = $dryRun ? 0 : cron_run_start($pdo, 'outreach_pipeline');

try {
    // ─── Master kill-switch: admin can disable the entire outreach system
    // from the Settings tab. When off, the server cron still fires but does
    // nothing until re-enabled.
    $outreachEnabled = getState($pdo, 'outreach_enabled', '1');
    if ($outreachEnabled !== '1') {
        logPipeline('Outreach is DISABLED via admin Settings (outreach_enabled != "1"). Pipeline exiting without running any steps.');
        // Finish cleanly: this is a normal no-op, not a crash. Without this the
        // run row would stay 'running' and the admin Crons pill would read
        // "Running" forever while outreach is toggled off.
        cron_run_finish($pdo, $cronRunId, 'ok');
        return;
    }

    // ─── STEP 1 & 2: Discover + Import ───
    if ($runAll || $discoverOnly) {
        stepDiscover($pdo, $dryRun);
    }

    // ─── STEP 1b: Discover Shopify Stores (parallel discovery source) ───
    if ($runAll || $discoverOnly || $shopifyOnly) {
        stepDiscoverShopify($pdo, $dryRun);
    }

    // ─── STEP 3: Generate AI Drafts ───
    if ($runAll || $draftOnly) {
        stepGenerateDrafts($pdo, $dryRun);
    }

    // ─── STEP 4: Auto-Approve ───
    // Runtime-toggled via outreach_pipeline_state.auto_send_mode
    // ('auto' | 'review'). Defaults to 'auto' on a DB that hasn't had the
    // toggle set yet. Admin can flip to review-mode in the Settings tab.
    $autoSendMode = getState($pdo, 'auto_send_mode', 'auto');
    if (($runAll || $draftOnly) && $autoSendMode === 'auto') {
        stepAutoApprove($pdo, $dryRun);
    } elseif (($runAll || $draftOnly) && $autoSendMode === 'review') {
        logPipeline('Send mode: review. Drafts generated but auto-approve skipped.');
    }

    // ─── STEP 5: Send Emails ───
    if ($runAll || $sendOnly) {
        stepSendEmails($pdo, $dryRun);
    }

    // ─── STEP 5.5: Halt Follow-ups (replies / unsubscribes / bounces) ───
    // Also runs in --draft-only so we don't waste Gemini drafts on leads who
    // have already replied/unsubscribed/bounced since the last run.
    if ($runAll || $sendOnly || $draftOnly) {
        stepHaltFollowups($pdo, $dryRun);
    }

    // ─── STEP 5.6: Draft Follow-ups (Gemini, lazy ~1 day before send) ───
    // Always runs regardless of send mode. Drafting itself is harmless.
    // The review-vs-auto gating happens INSIDE stepDraftFollowups (which
    // advances drafted → approved only when auto_send_mode = 'auto').
    if ($runAll || $sendOnly || $draftOnly) {
        stepDraftFollowups($pdo, $dryRun);
    }

    // ─── STEP 6: Send Follow-ups ───
    // Step 6 always runs; review-vs-auto gating is implicit in row statuses.
    // (Review mode: rows stay 'drafted' awaiting admin approval, not picked
    // up by the WHERE status='approved' query.)
    if ($runAll || $sendOnly) {
        stepSendFollowups($pdo, $dryRun);
    }

    logPipeline('=== Outreach Pipeline Complete ===');
    cron_run_finish($pdo, $cronRunId, 'ok');

} catch (Throwable $e) {
    // Throwable, not Exception: also catch PHP Errors (TypeError, OOM-adjacent
    // fatals) so a non-Exception failure still records 'error' instead of
    // leaving the run row orphaned as 'running'.
    logPipeline("Pipeline fatal error: " . $e->getMessage(), 'ERROR');
    cron_run_finish($pdo, $cronRunId, 'error', $e->getMessage());
    exit(1);
} finally {
    // Release lock file
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

// ══════════════════════════════════════════════════════════════
//  STEP IMPLEMENTATIONS
// ══════════════════════════════════════════════════════════════

function stepDiscover($pdo, $dryRun)
{
    global $targetCities;

    $apiKey = $_ENV['GOOGLE_PLACES_API_KEY'] ?? '';
    if (empty($apiKey)) {
        logPipeline('Google Places API key not configured. Skipping discovery.', 'WARN');
        return;
    }

    $totalCategories = count(OUTREACH_CATEGORY_POOL);
    $totalCities = count($targetCities);

    if ($dryRun) {
        $cityIndex = (int) getState($pdo, 'current_city_index', '0');
        if ($cityIndex >= $totalCities) $cityIndex = 0;
        $cur = $targetCities[$cityIndex];
        logPipeline("[DRY RUN] Would loop cities starting at '{$cur['city']}, {$cur['province']}' (#" . ($cityIndex + 1) . "/$totalCities) until import cap of " . DAILY_GOOGLE_DISCOVERY_LIMIT . " is hit or all cities tried.");
        return;
    }

    $totalImportedThisRun = 0;
    $citiesProcessedThisRun = 0;
    $citiesUsed = [];

    logPipeline('--- Step 1: Google Places Discovery ---');

    while (true) {
        if ($totalImportedThisRun >= DAILY_GOOGLE_DISCOVERY_LIMIT) {
            logPipeline("Google Places cap reached ($totalImportedThisRun/" . DAILY_GOOGLE_DISCOVERY_LIMIT . "). Stopping city loop.");
            break;
        }
        if ($citiesProcessedThisRun >= $totalCities) {
            logPipeline("All $totalCities cities tried this run. Stopping city loop.");
            break;
        }

        // Determine which city to search next
        $cityIndex = (int) getState($pdo, 'current_city_index', '0');
        if ($cityIndex >= $totalCities) {
            $cityIndex = 0;
            setState($pdo, 'current_city_index', '0');
            setState($pdo, 'current_city_category_offset', '0');
            logPipeline('All cities searched. Wrapping around to start.');
        }
        $categoryOffset = (int) getState($pdo, 'current_city_category_offset', '0');

        $target = $targetCities[$cityIndex];
        $city = $target['city'];
        $province = $target['province'];
        $citiesUsed[] = "$city";

        $remainingCap = DAILY_GOOGLE_DISCOVERY_LIMIT - $totalImportedThisRun;

        logPipeline("Searching $city, $province (city #" . ($cityIndex + 1) . "/$totalCities, starting at category " . ($categoryOffset + 1) . "/$totalCategories, remaining cap: $remainingCap)");

        // Get existing place IDs to skip duplicates during search
        $stmt = $pdo->prepare("SELECT places_id FROM outreach_leads WHERE places_id IS NOT NULL AND places_id != ''");
        $stmt->execute();
        $existingPlaceIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'places_id');

        // Walk the category pool from $categoryOffset, wrapping at the end. Stop
        // as soon as we have $remainingCap businesses OR have visited every
        // category. maxRounds=1 since each category is already specific.
        $businesses = [];
        $categoriesSearched = 0;
        $apiErrors = 0;
        $categoriesUsed = [];

        for ($i = 0; $i < $totalCategories; $i++) {
            if (count($businesses) >= $remainingCap) break;

            $catIdx = ($categoryOffset + $i) % $totalCategories;
            $cat = OUTREACH_CATEGORY_POOL[$catIdx];
            $categoriesUsed[] = $cat;

            $remaining = $remainingCap - count($businesses);
            $result = search_businesses_core($city, $province, $cat, $remaining, $apiKey, $existingPlaceIds, 1);
            $categoriesSearched++;

            if (isset($result['error'])) {
                logPipeline("API error searching '$cat' in $city: {$result['error']}", 'WARN');
                $apiErrors++;
                continue;
            }

            foreach ($result['businesses'] as $biz) {
                if (!empty($biz['places_id'])) {
                    $existingPlaceIds[] = $biz['places_id'];
                }
                $businesses[] = $biz;
            }
        }

        // If every single API call failed, don't advance state. Let the next
        // pipeline run retry these same categories. Break the loop so we don't
        // burn through every city while the API is down.
        if ($apiErrors > 0 && $apiErrors === $categoriesSearched) {
            logPipeline("All $apiErrors API calls failed for $city. Will retry same categories next run.", 'ERROR');
            break;
        }

        logPipeline("Discovered " . count($businesses) . " businesses with emails in $city ($categoriesSearched category searches; $apiErrors errors)");

        // Import discovered businesses
        $imported = 0;
        $skipped = 0;

        foreach ($businesses as $biz) {
            // Double-check dedup by places_id
            if (!empty($biz['places_id'])) {
                $check = $pdo->prepare("SELECT id FROM outreach_leads WHERE places_id = ?");
                $check->execute([$biz['places_id']]);
                if ($check->fetch()) {
                    $skipped++;
                    continue;
                }
            }

            // Also dedup by email to avoid emailing same address twice
            if (!empty($biz['email'])) {
                $check = $pdo->prepare("SELECT id FROM outreach_leads WHERE email = ?");
                $check->execute([$biz['email']]);
                if ($check->fetch()) {
                    $skipped++;
                    continue;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO outreach_leads
                (business_name, phone, website, address, category, city, source, places_id, contact_page_url, email)
                VALUES (?, ?, ?, ?, ?, ?, 'google_places_auto', ?, ?, ?)");
            $stmt->execute([
                $biz['business_name'] ?? 'Unknown',
                $biz['phone'] ?? null,
                $biz['website'] ?? null,
                $biz['address'] ?? null,
                $biz['category'] ?? null,
                $biz['city'] ?? null,
                $biz['places_id'] ?? null,
                $biz['contact_page_url'] ?? null,
                $biz['email'] ?? null,
            ]);

            $id = $pdo->lastInsertId();
            log_activity($pdo, $id, 'lead_created', "Auto-imported from Google Places ($city, $province)");
            $imported++;
        }

        logPipeline("Imported $imported new leads, skipped $skipped duplicates from $city");
        cron_metric_incr('leads_discovered', $imported);
        $totalImportedThisRun += $imported;
        $citiesProcessedThisRun++;

        setState($pdo, 'last_discovery_date', date('Y-m-d'));
        setState($pdo, 'last_discovery_city', "$city, $province");

        // Decide whether to continue looping or stop. If we cycled through every
        // category for this city, the city is "done" for now. Advance the
        // pointer and let the loop pick up the next city. If we stopped before
        // cycling all categories, it means we hit the per-city cap; save the
        // offset so the next pipeline run resumes here, and break.
        $cycledThroughAll = ($categoriesSearched >= $totalCategories);

        if ($cycledThroughAll) {
            setState($pdo, 'current_city_index', (string)($cityIndex + 1));
            setState($pdo, 'current_city_category_offset', '0');
            // Loop continues to next city if cap not hit
        } else {
            $newOffset = ($categoryOffset + $categoriesSearched) % $totalCategories;
            setState($pdo, 'current_city_category_offset', (string)$newOffset);
            logPipeline("Cap hit in $city after $categoriesSearched categories. Next run resumes at category " . ($newOffset + 1) . "/$totalCategories.");
            break;
        }
    }

    logPipeline("Google Places discovery complete. Cities processed: $citiesProcessedThisRun (" . implode(', ', $citiesUsed) . "), total imported: $totalImportedThisRun");

    // Prune expired scrape-cache rows so the table doesn't grow unbounded.
    try {
        $deleted = $pdo->exec("DELETE FROM outreach_scrape_cache
            WHERE last_attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($deleted > 0) {
            logPipeline("Pruned $deleted expired scrape-cache rows.");
        }
    } catch (PDOException $e) {
        // Cleanup failure shouldn't fail the pipeline; table may not exist yet.
    }
}

function stepDiscoverShopify($pdo, $dryRun)
{
    logPipeline('--- Step 1b: Discover Shopify Stores ---');

    // ─── Guard 1: Feature flag ───
    $enabled = $_ENV['OUTREACH_SHOPIFY_ENABLED'] ?? 'false';
    if ($enabled !== 'true') {
        logPipeline('Shopify discovery is DISABLED via OUTREACH_SHOPIFY_ENABLED. Skipping.');
        return;
    }

    // ─── Guard 2: SerpAPI key ───
    $serpapiKey = $_ENV['SERPAPI_KEY'] ?? '';
    if (empty($serpapiKey)) {
        logPipeline('SERPAPI_KEY not configured. Skipping Shopify discovery.', 'WARN');
        return;
    }

    // ─── Guard 3: Daily counter reset ───
    $today = date('Y-m-d');
    $lastReset = getState($pdo, 'shopify_last_reset_date', '');
    if ($lastReset !== $today) {
        setState($pdo, 'shopify_imports_today', '0');
        setState($pdo, 'serpapi_calls_today', '0');
        setState($pdo, 'shopify_last_reset_date', $today);
        logPipeline("Daily Shopify counters reset for $today.");
    }

    // ─── Guard 4: SerpAPI daily limit ───
    $serpapiLimit = (int) ($_ENV['SERPAPI_DAILY_QUERY_LIMIT'] ?? 3);
    $serpapiCallsToday = (int) getState($pdo, 'serpapi_calls_today', '0');
    if ($serpapiCallsToday >= $serpapiLimit) {
        logPipeline("SerpAPI daily limit reached ($serpapiCallsToday/$serpapiLimit). Skipping Shopify discovery.");
        return;
    }

    // ─── Guard 5: Shopify import daily limit ───
    $shopifyImportLimit = (int) ($_ENV['OUTREACH_DAILY_SHOPIFY_DISCOVERY_LIMIT'] ?? 5);
    $shopifyImportsToday = (int) getState($pdo, 'shopify_imports_today', '0');
    if ($shopifyImportsToday >= $shopifyImportLimit) {
        logPipeline("Shopify import daily limit reached ($shopifyImportsToday/$shopifyImportLimit). Skipping Shopify discovery.");
        return;
    }

    // ─── Dry run ───
    if ($dryRun) {
        $cursor = (int) getState($pdo, 'shopify_dork_cursor', '0');
        $query = SHOPIFY_DORK_POOL[$cursor % count(SHOPIFY_DORK_POOL)];
        logPipeline("[DRY RUN] Would loop SerpAPI dorks starting at cursor=$cursor (next='$query') until import cap ($shopifyImportLimit) or SerpAPI budget ($serpapiLimit) is hit.");
        return;
    }

    // ─── Loop dorks until import cap hit, SerpAPI budget exhausted, or all
    // dorks in the pool have been tried this run. High rejection rates are
    // expected (most candidates are too_old / agency_operated / dupes), so a
    // single dork rarely fills the cap on its own. ───
    $dorkPool = SHOPIFY_DORK_POOL;
    $totalDorks = count($dorkPool);
    $dorksTriedThisRun = 0;
    $totalImported = 0;
    $totalEvaluated = 0;
    $totalRejected = 0;
    $totalErrored = 0;
    $totalSerpapiResults = 0;
    $queriesUsed = [];

    while (true) {
        if ($shopifyImportsToday >= $shopifyImportLimit) {
            logPipeline("Shopify import cap reached ($shopifyImportsToday/$shopifyImportLimit). Stopping dork loop.");
            break;
        }
        if ($serpapiCallsToday >= $serpapiLimit) {
            logPipeline("SerpAPI daily budget exhausted ($serpapiCallsToday/$serpapiLimit). Stopping dork loop.");
            break;
        }
        if ($dorksTriedThisRun >= $totalDorks) {
            logPipeline("All $totalDorks dorks tried this run. Stopping dork loop.");
            break;
        }

        // Pick next dork and advance cursor immediately so failures don't get re-tried next dork
        $cursor = (int) getState($pdo, 'shopify_dork_cursor', '0');
        $idx = $cursor % $totalDorks;
        $query = $dorkPool[$idx];
        setState($pdo, 'shopify_dork_cursor', (string) ($cursor + 1));
        $dorksTriedThisRun++;
        $queriesUsed[] = $query;

        // Per-dork page offset (shared with the admin Run button via the
        // shopify_dork_pages state) so each pass pulls a DEEPER result page
        // instead of re-reading page 1 and re-seeing already-imported stores.
        // Capped by SHOPIFY_MAX_PAGE_DEPTH; a tapped-out dork is skipped (the
        // $dorksTriedThisRun guard still terminates the loop).
        $shopifyPages = json_decode(getState($pdo, 'shopify_dork_pages', '{}'), true);
        if (!is_array($shopifyPages)) {
            $shopifyPages = [];
        }
        $maxStart = (max(1, (int) ($_ENV['SHOPIFY_MAX_PAGE_DEPTH'] ?? 3)) - 1) * 100;
        $start = (int) ($shopifyPages[(string) $idx] ?? 0);
        if ($start > $maxStart) {
            logPipeline("Dork '$query' past page-depth cap (start=$start). Skipping this pass.");
            continue;
        }

        logPipeline("Shopify dork #$dorksTriedThisRun/$totalDorks (cursor=$cursor, page=" . (intdiv($start, 100) + 1) . "): '$query'");

        // SerpAPI call via 14-day response cache. Append global exclusions
        // so we don't pay for results that openly advertise being decades
        // old. num=100 is Google's per-page cap and costs the same as
        // num=10 on SerpAPI, 10x the candidates per credit. Cache hits
        // do NOT consume daily SerpAPI quota.
        $queryWithExclusions = $query . SHOPIFY_DORK_EXCLUSIONS;
        $queryResult = serpapi_query_cached($queryWithExclusions, $serpapiKey, 100, $pdo, $start);
        $results = $queryResult['results'];
        if ($queryResult['from_cache']) {
            logPipeline("'$query' served from SerpAPI cache (no credit spent).");
        } else {
            $serpapiCallsToday++;
            setState($pdo, 'serpapi_calls_today', (string) $serpapiCallsToday);
        }
        // Advance this dork's page offset so the next pass goes deeper.
        $shopifyPages[(string) $idx] = $start + 100;
        setState($pdo, 'shopify_dork_pages', json_encode($shopifyPages));

        if (empty($results)) {
            logPipeline("SerpAPI returned no results for '$query'. Moving to next dork.");
            continue;
        }
        $totalSerpapiResults += count($results);

        // Insert candidates via INSERT IGNORE, collect newly-inserted
        $newCandidates = [];
        foreach ($results as $result) {
            $rawLink = $result['link'] ?? '';
            if ($rawLink === '') {
                continue;
            }
            $canonical = shopify_canonical_url($rawLink);
            if ($canonical === '') {
                continue;
            }

            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO outreach_shopify_candidates (canonical_url, status, last_query)
                 VALUES (?, 'pending', ?)"
            );
            $stmt->execute([$canonical, $query]);

            if ($stmt->rowCount() === 1) {
                $newCandidates[] = $canonical;
            }
        }

        logPipeline("'$query' → " . count($results) . " SerpAPI results, " . count($newCandidates) . " new candidates.");

        // Evaluate candidates (stop early if cap reached mid-loop)
        foreach ($newCandidates as $canonical) {
            if ($shopifyImportsToday >= $shopifyImportLimit) {
                break;
            }

            $totalEvaluated++;

            try {
                $evalResult = evaluate_shopify_candidate($canonical);
            } catch (Exception $e) {
                logPipeline("Error evaluating candidate '$canonical': " . $e->getMessage(), 'ERROR');
                $pdo->prepare(
                    "UPDATE outreach_shopify_candidates SET status='error', reject_detail=? WHERE canonical_url=?"
                )->execute([substr($e->getMessage(), 0, 500), $canonical]);
                $totalErrored++;
                continue;
            }

            if ($evalResult['fit'] === true) {
                $meta      = $evalResult['metadata'];
                $email     = $meta['email'] ?? null;
                $finalUrl  = $evalResult['final_url'] ?? $canonical;

                // Dedup check: email or website already in outreach_leads
                $dupCheck = $pdo->prepare(
                    "SELECT id FROM outreach_leads WHERE email = ? OR website = ? LIMIT 1"
                );
                $dupCheck->execute([$email, $finalUrl]);
                if ($dupCheck->fetch()) {
                    $upd = $pdo->prepare(
                        "UPDATE outreach_shopify_candidates
                         SET status='rejected', reject_reason='duplicate'
                         WHERE canonical_url = ?"
                    );
                    $upd->execute([$canonical]);
                    logPipeline("Shopify candidate '$canonical' skipped: already in outreach_leads (duplicate).");
                    $totalRejected++;
                    continue;
                }

                $productsCount          = $meta['products_count'] ?? 0;
                $firstProductCreatedAt  = $meta['first_product_created_at'] ?? '';
                $featuredProduct        = $meta['featured_product'] ?? null;
                $businessSummary = "Shopify store. {$productsCount} products."
                    . ($firstProductCreatedAt ? " First product created {$firstProductCreatedAt}." : '')
                    . ($featuredProduct ? " Recently added product: \"{$featuredProduct}\"." : '')
                    . " Discovered via: {$query}";

                $insLead = $pdo->prepare(
                    "INSERT INTO outreach_leads
                     (business_name, email, website, source, contact_page_url, business_summary, country)
                     VALUES (?, ?, ?, 'shopify_auto', ?, ?, ?)"
                );
                $insLead->execute([
                    $meta['business_name'] ?? $canonical,
                    $email,
                    $finalUrl,
                    $finalUrl,
                    $businessSummary,
                    $meta['country'] ?? 'CA',
                ]);
                $leadId = (int) $pdo->lastInsertId();

                $updCand = $pdo->prepare(
                    "UPDATE outreach_shopify_candidates
                     SET status='imported',
                         lead_id=?,
                         harvested_email=?,
                         products_count=?,
                         first_product_created_at=?,
                         detected_country=?,
                         myshopify_url=?
                     WHERE canonical_url = ?"
                );
                $updCand->execute([
                    $leadId,
                    $email,
                    $productsCount,
                    $firstProductCreatedAt ?: null,
                    $meta['country'] ?? null,
                    $canonical,
                    $canonical,
                ]);

                log_activity($pdo, $leadId, 'lead_created', "Auto-imported from Shopify via dork: $query");

                $shopifyImportsToday++;
                setState($pdo, 'shopify_imports_today', (string) $shopifyImportsToday);

                logPipeline("Imported Shopify lead #$leadId: '{$meta['business_name']}' <$email> from $canonical");
                $totalImported++;

            } else {
                $rejectReason = $evalResult['reason'] ?? 'unknown';
                $rejectDetail = substr($evalResult['detail'] ?? '', 0, 500);

                $updRej = $pdo->prepare(
                    "UPDATE outreach_shopify_candidates
                     SET status='rejected', reject_reason=?, reject_detail=?
                     WHERE canonical_url = ?"
                );
                $updRej->execute([$rejectReason, $rejectDetail, $canonical]);

                logPipeline("Shopify candidate '$canonical' rejected: $rejectReason: $rejectDetail");
                $totalRejected++;
            }
        }
    }

    logPipeline(
        "Shopify discovery complete. Dorks tried: $dorksTriedThisRun/$totalDorks, "
        . "SerpAPI results: $totalSerpapiResults, evaluated: $totalEvaluated, "
        . "imported: $totalImported, rejected: $totalRejected, errored: $totalErrored. "
        . "Queries: " . implode(' | ', $queriesUsed)
    );
    cron_metric_incr('leads_discovered', $totalImported);
    cron_metric_incr('shopify_rejected', $totalRejected);
}

function stepGenerateDrafts($pdo, $dryRun)
{
    logPipeline('--- Step 3: Generate AI Drafts ---');

    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($geminiKey)) {
        logPipeline('Gemini API key not configured. Skipping draft generation.', 'WARN');
        return;
    }

    // Find leads that have an email but no draft yet
    $stmt = $pdo->prepare("
        SELECT *
        FROM outreach_leads
        WHERE email IS NOT NULL AND email != ''
          AND (draft_subject IS NULL OR draft_subject = '')
          AND sent_at IS NULL
          AND status NOT IN ('contacted', 'replied', 'interested', 'not_interested', 'onboarded', 'email_bounced', 'disqualified')
        ORDER BY date_added ASC
        LIMIT ?
    ");
    $stmt->execute([DAILY_SEND_LIMIT]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No leads need drafts. Skipping.');
        return;
    }

    logPipeline("Found " . count($leads) . " leads needing AI drafts");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would generate draft for: {$lead['business_name']} ({$lead['email']})");
        }
        return;
    }

    $success = 0;
    $failed = 0;
    $disqualified = 0;

    foreach ($leads as $lead) {
        try {
            $result = generate_draft_for_lead($pdo, $lead);

            if (isset($result['error'])) {
                logPipeline("Draft generation failed for {$lead['business_name']}: {$result['error']}", 'ERROR');
                $failed++;
            } elseif (!empty($result['disqualified'])) {
                // AI size gate rejected this lead. log_activity + status update
                // were already done inside disqualify_lead(); just surface it
                // in the pipeline log for the daily summary.
                logPipeline("Disqualified by AI size gate: {$lead['business_name']} ({$result['reason']}: {$result['detail']})");
                $disqualified++;
            } else {
                log_activity($pdo, $lead['id'], 'draft_generated', 'AI draft auto-generated by pipeline');
                logPipeline("Draft generated for {$lead['business_name']}");
                $success++;
            }

            // Rate limit: pause between Gemini calls
            sleep(1);

        } catch (Exception $e) {
            logPipeline("Draft error for {$lead['business_name']}: " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }

    logPipeline("Drafts generated: $success, disqualified: $disqualified, failed: $failed");
    cron_metric_incr('drafts_generated', $success);
    if ($disqualified > 0) {
        cron_metric_incr('leads_disqualified', $disqualified);
    }
}

function stepAutoApprove($pdo, $dryRun)
{
    logPipeline('--- Step 4: Auto-Approve Drafts ---');

    // Approve all leads that have a draft but haven't been approved yet.
    // Excludes disqualified leads as a belt-and-suspenders guard: disqualify_lead()
    // already clears approval_status, but if a draft was created before the
    // disqualify (e.g. via the AI gate post-draft retroactive backfill) we
    // don't want to re-approve it here.
    $stmt = $pdo->prepare("
        SELECT id, business_name
        FROM outreach_leads
        WHERE draft_subject IS NOT NULL AND draft_subject != ''
          AND draft_body IS NOT NULL AND draft_body != ''
          AND email IS NOT NULL AND email != ''
          AND approval_status != 'approved'
          AND sent_at IS NULL
          AND status != 'disqualified'
    ");
    $stmt->execute();
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No drafts to approve.');
        return;
    }

    logPipeline("Found " . count($leads) . " drafts to auto-approve");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would auto-approve: {$lead['business_name']}");
        }
        return;
    }

    $count = 0;
    foreach ($leads as $lead) {
        $stmt = $pdo->prepare("UPDATE outreach_leads SET approval_status = 'approved' WHERE id = ?");
        $stmt->execute([$lead['id']]);
        log_activity($pdo, $lead['id'], 'auto_approved', 'Draft auto-approved by pipeline');
        $count++;
    }

    logPipeline("Auto-approved $count drafts");
}

function stepSendEmails($pdo, $dryRun)
{
    logPipeline('--- Step 5: Send Emails ---');

    // Check how many already sent today
    $stmt = $pdo->prepare("SELECT COUNT(*) as sent_today FROM outreach_leads WHERE DATE(sent_at) = CURDATE()");
    $stmt->execute();
    $sentToday = (int) $stmt->fetch(PDO::FETCH_ASSOC)['sent_today'];

    $remaining = DAILY_SEND_LIMIT - $sentToday;

    if ($remaining <= 0) {
        logPipeline("Daily limit of " . DAILY_SEND_LIMIT . " emails already reached ($sentToday sent today). Skipping.");
        return;
    }

    logPipeline("Already sent $sentToday today. Will send up to $remaining more.");

    // Find approved leads with drafts that haven't been sent.
    // status != 'disqualified' is a safety net: disqualify_lead() resets
    // approval_status, but explicitly excluding here means a disqualified row
    // can never appear in the send queue regardless of approval_status state.
    $stmt = $pdo->prepare("
        SELECT id, business_name, email, draft_subject, draft_body, unsubscribe_token
        FROM outreach_leads
        WHERE approval_status = 'approved'
          AND draft_subject IS NOT NULL AND draft_subject != ''
          AND draft_body IS NOT NULL AND draft_body != ''
          AND email IS NOT NULL AND email != ''
          AND sent_at IS NULL
          AND status != 'disqualified'
        ORDER BY date_added ASC
        LIMIT ?
    ");
    $stmt->execute([$remaining]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        logPipeline('No approved leads ready to send.');
        return;
    }

    logPipeline("Found " . count($leads) . " approved leads to send");

    if ($dryRun) {
        foreach ($leads as $lead) {
            logPipeline("[DRY RUN] Would send email to: {$lead['business_name']} <{$lead['email']}>");
        }
        return;
    }

    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;

    foreach ($leads as $lead) {
        $id = $lead['id'];
        $businessName = $lead['business_name'];
        $email = $lead['email'];

        try {
            $reason = null;
            if (send_outreach_lead($pdo, $lead, $reason)) {
                log_activity($pdo, $id, 'email_sent', 'Outreach email sent automatically via pipeline to: ' . $email);
                logPipeline("Sent email to $businessName <$email> (lead #$id)");

                // Schedule the multi-touch follow-up sequence (if any configured).
                $scheduled = schedule_followups_for_lead($pdo, $id);
                if ($scheduled > 0) {
                    logPipeline("Scheduled $scheduled follow-up(s) for lead #$id");
                }

                $successCount++;
            } elseif ($reason === 'already_sent' || $reason === 'suppressed') {
                // Skip outcomes are already logged inside send_outreach_lead;
                // don't double-log as failures and don't count toward fail tally.
                logPipeline("Skipped $businessName <$email> (lead #$id): $reason");
                $skipCount++;
            } else {
                log_activity($pdo, $id, 'email_failed', 'Pipeline email send failed for: ' . $email . ' (' . ($reason ?? 'unknown') . ')');
                logPipeline("Failed to send email to $businessName <$email> (lead #$id): " . ($reason ?? 'unknown'), 'ERROR');
                $failCount++;
            }

            // Brief pause between sends
            if ($successCount + $failCount + $skipCount < count($leads)) {
                sleep(2);
            }

        } catch (Exception $e) {
            log_activity($pdo, $id, 'email_failed', 'Pipeline email error: ' . $e->getMessage());
            logPipeline("Error sending to $businessName <$email> (lead #$id): " . $e->getMessage(), 'ERROR');
            $failCount++;
        }
    }

    logPipeline("Send complete. Sent: $successCount, Failed: $failCount, Skipped: $skipCount");
    cron_metric_incr('first_emails_sent', $successCount);
}

function stepSendFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 6: Send Follow-ups ---');

    // Count how many follow-up sends have happened today (across all touch positions)
    $sentToday = (int) $pdo->query(
        "SELECT COUNT(*) FROM outreach_followups WHERE DATE(sent_at) = CURDATE()"
    )->fetchColumn();
    $remaining = DAILY_FOLLOWUP_LIMIT - $sentToday;

    if ($remaining <= 0) {
        logPipeline("Follow-up daily limit of " . DAILY_FOLLOWUP_LIMIT . " reached ($sentToday sent today). Skipping.");
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM outreach_followups
         WHERE status = 'approved'
           AND scheduled_for <= NOW()
         ORDER BY scheduled_for ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $remaining, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        logPipeline('No follow-ups ready to send.');
        return;
    }

    logPipeline('Found ' . count($rows) . ' follow-up(s) ready to send (cap remaining: ' . $remaining . ').');

    if ($dryRun) {
        foreach ($rows as $r) {
            logPipeline("[DRY RUN] Would send followup #{$r['id']} (lead #{$r['lead_id']}, touch {$r['touch_number']})");
        }
        return;
    }

    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;

    foreach ($rows as $row) {
        try {
            $reason = null;
            if (send_followup_row($pdo, $row, $reason)) {
                logPipeline("Sent followup #{$row['id']} (lead #{$row['lead_id']}, touch {$row['touch_number']})");
                $successCount++;
            } elseif ($reason === 'not_eligible') {
                $skipCount++;
            } else {
                logPipeline("Failed to send followup #{$row['id']}: " . ($reason ?? 'unknown'), 'WARN');
                $failCount++;
            }

            if ($successCount + $failCount + $skipCount < count($rows)) {
                sleep(2);
            }
        } catch (Throwable $e) {
            logPipeline("Error sending followup #{$row['id']}: " . $e->getMessage(), 'ERROR');
            $failCount++;
        }
    }

    logPipeline("Follow-ups complete. Sent: $successCount, Failed: $failCount, Skipped: $skipCount");
    cron_metric_incr('followups_sent', $successCount);
}

function stepHaltFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 5.5: Halt Follow-ups ---');

    if ($dryRun) {
        // Count how many WOULD be halted, but don't write
        $countStmt = $pdo->query(
            "SELECT COUNT(*) FROM outreach_followups f
             JOIN outreach_leads l ON l.id = f.lead_id
             WHERE f.status IN ('scheduled','drafted','approved')
               AND (
                   l.status IN ('replied','interested','not_interested','onboarded','email_bounced')
                   OR EXISTS (SELECT 1 FROM email_suppressions s WHERE LOWER(s.email) = LOWER(l.email) AND s.context = 'outreach')
               )"
        );
        $count = (int) $countStmt->fetchColumn();
        logPipeline("[DRY RUN] Would halt $count follow-up row(s).");
        return;
    }

    $counts = halt_followups_bulk($pdo);
    $total = array_sum($counts);
    if ($total === 0) {
        logPipeline('No follow-ups halted.');
    } else {
        logPipeline("Halted $total follow-up(s): " . json_encode($counts));
    }
}

function stepDraftFollowups($pdo, $dryRun)
{
    logPipeline('--- Step 5.6: Draft Follow-ups ---');

    $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    if (empty($geminiKey)) {
        logPipeline('Gemini API key not configured. Skipping follow-up draft generation.', 'WARN');
        return;
    }

    // Find rows whose draft window has opened (scheduled_for within next 24h)
    $stmt = $pdo->prepare(
        "SELECT * FROM outreach_followups
         WHERE status = 'scheduled'
           AND scheduled_for <= DATE_ADD(NOW(), INTERVAL 1 DAY)
         ORDER BY scheduled_for ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, DAILY_DRAFT_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        logPipeline('No follow-ups need drafts. Skipping.');
        return;
    }

    logPipeline('Found ' . count($rows) . ' follow-up(s) needing AI drafts (cap: ' . DAILY_DRAFT_LIMIT . ').');

    if ($dryRun) {
        foreach ($rows as $r) {
            logPipeline("[DRY RUN] Would draft followup #{$r['id']} (lead #{$r['lead_id']}, touch {$r['touch_number']})");
        }
        return;
    }

    $autoSendMode = getState($pdo, 'auto_send_mode', 'auto');

    $success = 0;
    $failed = 0;
    foreach ($rows as $row) {
        try {
            $ok = draft_followup_via_gemini($pdo, $row);
            if ($ok) {
                $success++;
                // In auto-send mode, advance drafted → approved immediately
                if ($autoSendMode === 'auto') {
                    $pdo->prepare("UPDATE outreach_followups SET status = 'approved' WHERE id = ? AND status = 'drafted'")
                        ->execute([(int) $row['id']]);
                }
            } else {
                $failed++;
            }
            sleep(1); // Rate-limit Gemini calls
        } catch (Throwable $e) {
            logPipeline("Draft followup error (followup #{$row['id']}): " . $e->getMessage(), 'ERROR');
            $failed++;
        }
    }

    logPipeline("Follow-up drafts: $success generated, $failed failed.");
    cron_metric_incr('followup_drafts_generated', $success);
}
