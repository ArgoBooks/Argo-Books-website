<?php

/**
 * Founder identity — the single source of truth for "which app installs are the
 * founder's own", so the founder's own testing never pollutes app telemetry,
 * crash reports, or the app-stats dashboards.
 *
 * Identities are the telemetry/crash authIds the desktop endpoints authenticate
 * as: 'subscription:PREM-...' for premium installs, 'device:<hash>' for free ones.
 * They are configured ONCE in .env as FOUNDER_AUTH_IDS (comma-separated) and read
 * only through this helper, so there is one place to manage them.
 *
 * Scope: this covers the app-data pipelines (telemetry + crashes) only. Website
 * page-view / referral exclusion is a separate, IP-based mechanism configured via
 * EXCLUDED_TRACKING_IPS and enforced by is_nontracked_ip() in statistics.php.
 *
 * Founder data is KEPT, not discarded. api/data/upload.php and api/data/crash.php
 * write it to disk like anyone else's, and the read side decides what to do with it:
 *
 *   - admin/app-stats/index.php, crashes-tab.php and admin/marketing-funnel skip it,
 *     so it never reaches a chart, a KPI card, or a funnel count.
 *   - admin/app-stats/user-activity-tab.php is the ONE place it surfaces, badged as
 *     the founder's own install and left out of the user tally.
 *
 * Requires .env to already be loaded (callers load it via Dotenv or db_connect).
 */

if (!function_exists('founder_auth_ids')) {
    /**
     * Parsed list of the founder's authIds from the FOUNDER_AUTH_IDS env var
     * (comma-separated; surrounding whitespace and empty entries are ignored).
     *
     * @return string[]
     */
    function founder_auth_ids(): array
    {
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }

        // Deploy does not ship .env, so production's copy is edited by hand and may still use the pre-rename name.
        $raw = $_ENV['FOUNDER_AUTH_IDS'] ?? getenv('FOUNDER_AUTH_IDS');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $_ENV['EXCLUDED_AUTH_IDS'] ?? getenv('EXCLUDED_AUTH_IDS');
        }
        if (!is_string($raw) || trim($raw) === '') {
            return $ids = [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $ids[] = $part;
            }
        }
        return $ids;
    }
}

if (!function_exists('is_founder_auth_id')) {
    /**
     * True if the given telemetry/crash authId belongs to the founder. A missing or
     * empty authId is never treated as the founder's.
     */
    function is_founder_auth_id(?string $authId): bool
    {
        if ($authId === null || $authId === '') {
            return false;
        }
        return in_array($authId, founder_auth_ids(), true);
    }
}
