<?php
/**
 * Receipt-scan quota: identity resolution and atomic consumption.
 *
 * Two endpoints need to agree about this, and they must agree exactly:
 *
 *   - usage.php answers the app's "how many do I have left?" question, and is what
 *     the desktop UI shows and gates its bulk-scan batch on.
 *   - completions.php spends the quota, because that is the request that actually
 *     costs money at Gemini.
 *
 * They key the same table row, so the identifier mapping lives here rather than in
 * either one. If they ever computed it differently the app would report one balance
 * and the server would enforce against another, which reads as the limit firing at
 * random.
 *
 * Why the spend is counted here: a client that calls usage.php's increment itself
 * can skip the call, or send more requests than it reports, and would then be
 * unmetered. Consuming inside the request that spends the money closes that.
 *
 * Requires a live $pdo and config/pricing.php to be loadable.
 */

require_once __DIR__ . '/../../config/pricing.php';

if (!function_exists('receipt_scan_quota_identity')) {
    /**
     * Resolves who is scanning and what they are allowed, mirroring the tiering the
     * app is told about.
     *
     * A premium key is only honoured once it has been redeemed AND its subscription
     * is live: an unredeemed promo code that leaked via a screenshot or support
     * ticket must not buy the premium allowance.
     *
     * @param string      $licenseKey   Raw key as sent by the client, may be ''.
     * @param string|null $deviceIdHash Already-hashed device id (sha256 of X-Device-Id).
     * @return array{tier:string,limit:int,identifier:string}|null Null when neither identifies anyone.
     */
    function receipt_scan_quota_identity(PDO $pdo, string $licenseKey, ?string $deviceIdHash): ?array
    {
        $config = get_pricing_config();
        $licenseKey = trim($licenseKey);

        if ($licenseKey !== '') {
            if (strpos($licenseKey, 'PREM-') === 0) {
                $stmt = $pdo->prepare("
                    SELECT subscription_id, redeemed_at
                    FROM premium_subscription_keys
                    WHERE subscription_key = ?
                ");
                $stmt->execute([$licenseKey]);
                $premiumKey = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($premiumKey && $premiumKey['redeemed_at'] !== null) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM premium_subscriptions
                        WHERE subscription_id = ?
                        AND environment = ?
                        AND status IN ('active', 'cancelled')
                        AND end_date > NOW()
                    ");
                    $stmt->execute([$premiumKey['subscription_id'], current_environment()]);
                    if ($stmt->fetch()) {
                        return ['tier' => 'premium', 'limit' => (int)$config['receipt_scan_monthly_limit'], 'identifier' => $licenseKey];
                    }
                }

                // Older installs sent the subscription_id itself as the license key.
                $stmt = $pdo->prepare("
                    SELECT id FROM premium_subscriptions
                    WHERE subscription_id = ?
                    AND environment = ?
                    AND status IN ('active', 'cancelled')
                    AND end_date > NOW()
                ");
                $stmt->execute([$licenseKey, current_environment()]);
                if ($stmt->fetch()) {
                    return ['tier' => 'premium', 'limit' => (int)$config['receipt_scan_monthly_limit'], 'identifier' => $licenseKey];
                }
            }

            $stmt = $pdo->prepare("SELECT id FROM license_keys WHERE license_key = ?");
            $stmt->execute([$licenseKey]);
            if ($stmt->fetch()) {
                return ['tier' => 'free', 'limit' => (int)$config['free_receipt_scan_monthly_limit'], 'identifier' => $licenseKey];
            }
        }

        if ($deviceIdHash !== null && $deviceIdHash !== '') {
            // usage.php receives the raw device id and hashes it; callers that already
            // hold the hash (completions.php authenticates from the header) pass it in.
            return [
                'tier'       => 'free',
                'limit'      => (int)$config['free_receipt_scan_monthly_limit'],
                'identifier' => 'device_' . $deviceIdHash,
            ];
        }

        return null;
    }
}

if (!function_exists('receipt_scan_quota_consume')) {
    /**
     * Takes one scan from this month's allowance, or reports that there is none left.
     *
     * The INSERT and the UPDATE are both conditional, so concurrent callers cannot
     * both pass a read-then-check and land on the same row: whichever loses the race
     * updates zero rows and is refused. This is what makes a parallel batch safe.
     *
     * @return array{allowed:bool,scan_count:int,limit:int} scan_count is the count AFTER a successful take.
     */
    function receipt_scan_quota_consume(PDO $pdo, string $identifier, int $limit): array
    {
        $usageMonth = date('Y-m-01');

        $environment = current_environment();

        // INSERT IGNORE rather than a read-then-insert: two first-of-the-month requests
        // would otherwise both see no row and both try to create it.
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO receipt_scan_usage (license_key, usage_month, scan_count, monthly_limit, environment)
            VALUES (?, ?, 0, ?, ?)
        ");
        $stmt->execute([$identifier, $usageMonth, $limit, $environment]);

        $stmt = $pdo->prepare("
            UPDATE receipt_scan_usage
            SET scan_count = scan_count + 1
            WHERE license_key = ? AND usage_month = ? AND environment = ? AND scan_count < ?
        ");
        $stmt->execute([$identifier, $usageMonth, $environment, $limit]);
        $allowed = $stmt->rowCount() > 0;

        $stmt = $pdo->prepare("
            SELECT scan_count FROM receipt_scan_usage
            WHERE license_key = ? AND usage_month = ? AND environment = ?
        ");
        $stmt->execute([$identifier, $usageMonth, $environment]);
        $count = (int)($stmt->fetchColumn() ?: 0);

        return ['allowed' => $allowed, 'scan_count' => $count, 'limit' => $limit];
    }
}

if (!function_exists('receipt_scan_quota_refund')) {
    /**
     * Hands a consumed scan back after the upstream call failed.
     *
     * The quota is taken before Gemini is called, because taking it afterwards is
     * what made it skippable. That means a genuine upstream failure or timeout would
     * otherwise bill the user for a scan they never received, so those paths refund.
     * Floored at zero so a double refund can never mint allowance.
     */
    function receipt_scan_quota_refund(PDO $pdo, string $identifier): void
    {
        try {
            $stmt = $pdo->prepare("
                UPDATE receipt_scan_usage
                SET scan_count = scan_count - 1
                WHERE license_key = ? AND usage_month = ? AND environment = ? AND scan_count > 0
            ");
            $stmt->execute([$identifier, date('Y-m-01'), current_environment()]);
        } catch (PDOException $e) {
            // A failed refund must not turn an upstream error into a 500. The user has
            // already lost the scan; log it and let the original failure surface.
            error_log('Receipt scan quota refund failed for ' . $identifier . ': ' . $e->getMessage());
        }
    }
}
