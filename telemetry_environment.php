<?php

/**
 * Which environment a telemetry install belongs to.
 *
 * One database sits behind both argorobots.com and dev.argorobots.com, and the
 * telemetry auth path does not filter on the environment column: a sandbox
 * subscription authenticates against production and its uploads land in
 * production's data-logs. Nothing inside an uploaded file records where it came
 * from, so the only evidence is the subscription the install authenticated as.
 *
 * Premium authIds are 'subscription:<subscription_id>'. An id that is not in
 * premium_subscriptions for the current environment is not this environment's, and
 * that covers both a sandbox subscription and one that has since been deleted:
 * either way there is no live account here to attribute the install to.
 *
 * Free authIds are 'device:<hash>', a bare SHA-256 of a device id with no row
 * behind it, so a free install from a dev machine cannot be told apart from a real
 * one. Those have to be listed in FOUNDER_AUTH_IDS instead: see founder_identity.php.
 *
 * Requires the global $pdo and current_environment() from db_connect.php.
 */

if (!function_exists('telemetry_environment_subscription_ids')) {
    /**
     * subscription_id => true for every subscription in the current environment.
     * Queried once per request. Returns null when the lookup is unavailable, which
     * callers must read as "cannot tell" rather than "no match".
     *
     * @return array<string,bool>|null
     */
    function telemetry_environment_subscription_ids(): ?array
    {
        static $ids    = null;
        static $loaded = false;
        if ($loaded) {
            return $ids;
        }
        $loaded = true;

        global $pdo;
        if (!isset($pdo)) {
            return $ids = null;
        }

        try {
            $stmt = $pdo->prepare('SELECT subscription_id FROM premium_subscriptions WHERE environment = ?');
            $stmt->execute([current_environment()]);
            $ids = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $ids[(string)$sid] = true;
            }
        } catch (PDOException $e) {
            // Fails open, because a dashboard hiding every premium install over one failed query is worse than one showing a test install.
            error_log('telemetry environment lookup failed: ' . $e->getMessage());
            $ids = null;
        }
        return $ids;
    }
}

if (!function_exists('is_other_environment_auth_id')) {
    /**
     * True when this install's premium came from a subscription that does not belong
     * to the environment being viewed. False for free installs and whenever the
     * lookup could not run, so the answer is only ever a positive identification.
     */
    function is_other_environment_auth_id(?string $authId): bool
    {
        if ($authId === null || strncmp($authId, 'subscription:', 13) !== 0) {
            return false;
        }
        $ids = telemetry_environment_subscription_ids();
        if ($ids === null) {
            return false;
        }
        return !isset($ids[substr($authId, 13)]);
    }
}
