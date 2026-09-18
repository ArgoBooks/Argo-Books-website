<?php
/**
 * Affiliate program helpers: commission math, source-code generation, and the
 * per-affiliate stat queries that power both the affiliate dashboard and the
 * admin section. Including this file twice is safe (function_exists guards).
 *
 * Commission model: nothing about earned commission is stored. It is computed
 * on the fly from premium_subscription_payments within each subscription's
 * commission window. The only persisted money is affiliate_payouts (what was
 * actually paid out). owed = earned - paid. A refunded payment simply stops
 * counting, so refunds self-correct with no clawback bookkeeping.
 */

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../config/pricing.php';

if (!function_exists('affiliate_processing_fee_config')) {
    /**
     * Processing-fee rate applied to subscription charges, used to strip the
     * fee back out so commission is paid on the subscription price only. Returns
     * ['percent' => float, 'fixed' => float].
     */
    function affiliate_processing_fee_config(): array
    {
        $cfg = function_exists('get_pricing_config') ? get_pricing_config() : [];
        return [
            'percent' => (float) ($cfg['processing_fee_percent'] ?? 0),
            'fixed'   => (float) ($cfg['processing_fee_fixed'] ?? 0),
        ];
    }
}

if (!function_exists('affiliate_hold_days')) {
    /**
     * Days a commission is held after each payment before it becomes payable.
     * Covers the refund/chargeback window so we never pay out on money that can
     * still be reversed (which also protects the merchant account from the
     * dispute ratios Stripe/Square/PayPal penalize). Override via
     * AFFILIATE_HOLD_DAYS; defaults to 30.
     */
    function affiliate_hold_days(): int
    {
        $raw = $_ENV['AFFILIATE_HOLD_DAYS'] ?? getenv('AFFILIATE_HOLD_DAYS');
        $days = (int) ($raw !== false && $raw !== null && $raw !== '' ? $raw : 30);
        return $days > 0 ? $days : 30;
    }
}

if (!function_exists('affiliate_attribution_days')) {
    /**
     * Referral cookie window: how long after a click an affiliate can still be
     * credited for the resulting purchase. The visitor cookie itself lives a year
     * (analytics), but affiliate attribution is deliberately capped shorter so we
     * don't pay commission on sales an affiliate didn't meaningfully drive.
     * Override via AFFILIATE_ATTRIBUTION_DAYS; defaults to 100.
     */
    function affiliate_attribution_days(): int
    {
        $raw = $_ENV['AFFILIATE_ATTRIBUTION_DAYS'] ?? getenv('AFFILIATE_ATTRIBUTION_DAYS');
        $days = (int) ($raw !== false && $raw !== null && $raw !== '' ? $raw : 100);
        return $days > 0 ? $days : 100;
    }
}

if (!function_exists('affiliate_source_within_window')) {
    /**
     * Whether a resolved referral source should still be credited to an affiliate
     * for a purchase happening now. Enforces the affiliate cookie window at
     * conversion time so it decides attribution once, permanently, rather than
     * retroactively re-scoring history when the setting changes.
     *
     * Only affiliate source codes are gated. Non-affiliate channels (ads, AI,
     * social) always pass through untouched. Fails open (returns true) on any
     * missing data or DB error so a lookup gap never silently drops a legitimate
     * referral; the only case that strips attribution is a confirmed affiliate
     * whose first recorded click for this visitor is older than the window.
     */
    function affiliate_source_within_window(?string $visitor_id, ?string $source_code, string $environment): bool
    {
        global $pdo;
        if (empty($source_code) || !$pdo) {
            return true;
        }
        try {
            $isAff = $pdo->prepare('SELECT 1 FROM affiliates WHERE source_code = ? AND environment = ? LIMIT 1');
            $isAff->execute([$source_code, $environment]);
            if (!$isAff->fetchColumn()) {
                return true; // not an affiliate code; leave attribution untouched
            }
            if (empty($visitor_id)) {
                return true; // no visitor to trace a click for; don't strip
            }
            // A cookie refreshes on every click, so measure the window from the
            // most recent click for this source: credit the affiliate if they were
            // clicked at all within the window, even if there's also an older click.
            $stmt = $pdo->prepare(
                'SELECT MAX(created_at) FROM referral_events
                  WHERE visitor_id = ? AND source_code = ? AND environment = ?'
            );
            $stmt->execute([$visitor_id, $source_code, $environment]);
            $latest = $stmt->fetchColumn();
            if ($latest === false || $latest === null) {
                return true; // no recorded click; leave attribution
            }
            return strtotime((string) $latest) >= strtotime('-' . affiliate_attribution_days() . ' days');
        } catch (PDOException $e) {
            error_log('affiliate_source_within_window failed: ' . $e->getMessage());
            return true; // fail open
        }
    }
}

if (!function_exists('compute_commission')) {
    /**
     * Pure commission math (no DB) so it can be unit-tested in isolation.
     *
     * Given the completed payments for ONE referred subscription and that
     * subscription's start_date, return the commission earned: rate × the sum
     * of payment amounts that fall within [start_date, start_date + window).
     *
     * @param array  $payments   Each: ['amount' => float|string, 'status' => string,
     *                            'created_at' => 'Y-m-d H:i:s', 'environment' => string]
     * @param string $start_date Subscription start_date ('Y-m-d H:i:s')
     * @param float  $rate       e.g. 0.5 for 50%
     * @param int    $window_months Commission window length in months
     * @param string $environment   Only payments in this environment count
     * @param float  $fee_percent   Processing-fee percent that was added to each
     *                              charge (stripped so commission is on the
     *                              subscription price, not the fee)
     * @param float  $fee_fixed     Fixed processing-fee amount added to each charge
     * @return float Commission earned, rounded to 2 decimals
     */
    function compute_commission(array $payments, string $start_date, float $rate, int $window_months, string $environment, float $fee_percent = 0.0, float $fee_fixed = 0.0): float
    {
        $start = strtotime($start_date);
        if ($start === false) {
            return 0.0;
        }
        // Anchor the window to the subscription's own start_date. strtotime with
        // a relative "+N months" matches MySQL DATE_ADD(..., INTERVAL N MONTH).
        $window_end = strtotime('+' . $window_months . ' months', $start);

        $base_total = 0.0;
        foreach ($payments as $p) {
            if (($p['status'] ?? '') !== 'completed') {
                continue; // excludes pending / failed / refunded
            }
            if (($p['environment'] ?? '') !== $environment) {
                continue;
            }
            $when = strtotime($p['created_at'] ?? '');
            if ($when === false || $when < $start || $when >= $window_end) {
                continue; // outside the per-subscription commission window
            }
            // The charged amount = subscription price + processing fee. Reverse
            // the fee (amount = base*(1+pct/100) + fixed) so commission is paid
            // on the subscription price only. Floor at 0 for credit/$0 charges.
            $base = round(((float) $p['amount'] - $fee_fixed) / (1 + $fee_percent / 100), 2);
            $base_total += max(0.0, $base);
        }

        return round($base_total * $rate, 2);
    }
}

if (!function_exists('compute_commission_split')) {
    /**
     * Pure version of the eligible/pending split (no DB) for unit testing.
     * Eligible = commission on qualifying payments older than the hold window
     * (payable now); pending = payments still inside the hold window.
     *
     * @param string $now       Reference "now" ('Y-m-d H:i:s') so tests are deterministic
     * @return array{eligible: float, pending: float}
     */
    function compute_commission_split(array $payments, string $start_date, float $rate, int $window_months, string $environment, float $fee_percent, float $fee_fixed, int $hold_days, string $now): array
    {
        $start = strtotime($start_date);
        $now_ts = strtotime($now);
        if ($start === false || $now_ts === false) {
            return ['eligible' => 0.0, 'pending' => 0.0];
        }
        $window_end = strtotime('+' . $window_months . ' months', $start);
        $cutoff = strtotime('-' . $hold_days . ' days', $now_ts); // <= cutoff is seasoned

        $eligible_base = 0.0;
        $pending_base = 0.0;
        foreach ($payments as $p) {
            if (($p['status'] ?? '') !== 'completed' || ($p['environment'] ?? '') !== $environment) {
                continue;
            }
            $when = strtotime($p['created_at'] ?? '');
            if ($when === false || $when < $start || $when >= $window_end) {
                continue;
            }
            $base = max(0.0, round(((float) $p['amount'] - $fee_fixed) / (1 + $fee_percent / 100), 2));
            if ($when <= $cutoff) {
                $eligible_base += $base;
            } else {
                $pending_base += $base;
            }
        }

        return [
            'eligible' => round($eligible_base * $rate, 2),
            'pending'  => round($pending_base * $rate, 2),
        ];
    }
}

if (!function_exists('affiliate_earnings_breakdown')) {
    /**
     * Commission split into eligible (past the hold window, payable now) and
     * pending (still inside the hold window, could still be refunded). Attribution
     * is via the premium_signup event's subscription_id; money comes from
     * premium_subscription_payments. Fee-stripped, self-referral-guarded, and
     * windowed per-subscription to its own start_date.
     *
     * @param int $affiliate_user_id community_users.id of the affiliate, so
     *                               their own purchases never earn commission.
     * @return array{eligible: float, pending: float}
     */
    function affiliate_earnings_breakdown(string $source_code, float $rate, int $window_months, int $affiliate_user_id, string $environment): array
    {
        global $pdo;

        $fee  = affiliate_processing_fee_config();
        $hold = affiliate_hold_days();

        // Base = subscription price with the processing fee stripped back out.
        // seasoned = charge is older than the hold window (past refund risk).
        // Computed once per row in the derived table so each bound parameter is
        // used a single time (required with emulated prepares disabled).
        $sql = "
            SELECT
              COALESCE(SUM(CASE WHEN t.seasoned = 1 THEN t.base ELSE 0 END), 0) AS eligible_base,
              COALESCE(SUM(CASE WHEN t.seasoned = 0 THEN t.base ELSE 0 END), 0) AS pending_base
            FROM (
                SELECT
                    GREATEST(ROUND((p.amount - :fee_fixed) / (1 + :fee_pct / 100), 2), 0) AS base,
                    CASE WHEN p.created_at <= DATE_SUB(NOW(), INTERVAL :hold DAY) THEN 1 ELSE 0 END AS seasoned
                FROM (
                    SELECT DISTINCT subscription_id
                    FROM referral_events
                    WHERE event_type = 'premium_signup'
                      AND source_code = :src
                      AND environment = :env
                      AND subscription_id IS NOT NULL
                ) sub
                JOIN premium_subscriptions ps
                  ON ps.subscription_id = sub.subscription_id
                 AND ps.environment = :env2
                 AND (ps.user_id IS NULL OR ps.user_id <> :aff_user)
                JOIN premium_subscription_payments p
                  ON p.subscription_id = sub.subscription_id
                 AND p.status = 'completed'
                 AND p.environment = :env3
                 AND p.created_at >= ps.start_date
                 AND p.created_at <  DATE_ADD(ps.start_date, INTERVAL :win MONTH)
            ) t";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':src'       => $source_code,
            ':env'       => $environment,
            ':env2'      => $environment,
            ':env3'      => $environment,
            ':aff_user'  => $affiliate_user_id,
            ':win'       => $window_months,
            ':fee_fixed' => $fee['fixed'],
            ':fee_pct'   => $fee['percent'],
            ':hold'      => $hold,
        ]);
        $row = $stmt->fetch() ?: ['eligible_base' => 0, 'pending_base' => 0];

        return [
            'eligible' => round(((float) $row['eligible_base']) * $rate, 2),
            'pending'  => round(((float) $row['pending_base']) * $rate, 2),
        ];
    }
}

if (!function_exists('affiliate_total_paid')) {
    /** Sum of payouts already recorded for an affiliate (current environment). */
    function affiliate_total_paid(int $affiliate_id, string $environment): float
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM affiliate_payouts WHERE affiliate_id = ? AND environment = ?');
        $stmt->execute([$affiliate_id, $environment]);
        return round((float) $stmt->fetchColumn(), 2);
    }
}

if (!function_exists('affiliate_money_summary')) {
    /**
     * Earned / paid / owed for one affiliate row (as returned by
     * get_affiliate_for_user / the admin list).
     *
     * @return array{eligible: float, pending: float, earned: float, paid: float, owed: float}
     *   eligible = seasoned commission (past the hold window, payable)
     *   pending  = commission still inside the hold window
     *   earned   = eligible + pending (total commission generated)
     *   paid     = payouts already recorded
     *   owed     = eligible - paid (payable now; negative = clawback after a
     *              paid-out payment was refunded, netted off future payouts)
     */
    function affiliate_money_summary(array $affiliate, string $environment): array
    {
        $rate   = (float) $affiliate['commission_rate'];
        $window = (int) $affiliate['commission_window_months'];
        $b      = affiliate_earnings_breakdown($affiliate['source_code'], $rate, $window, (int) $affiliate['user_id'], $environment);
        $paid   = affiliate_total_paid((int) $affiliate['id'], $environment);

        $eligible = $b['eligible'];
        $pending  = $b['pending'];
        return [
            'eligible' => $eligible,
            'pending'  => $pending,
            'earned'   => round($eligible + $pending, 2),
            'paid'     => $paid,
            'owed'     => round($eligible - $paid, 2),
        ];
    }
}

if (!function_exists('affiliate_program_totals')) {
    /**
     * Program-wide affiliate money totals (earned, paid, owed) across all
     * approved/suspended affiliates in an environment. Resilient by design:
     * returns zeros if the affiliate tables don't exist yet on this server, so
     * callers like the admin dashboard never break before the schema is created.
     *
     * @return array{earned: float, pending: float, paid: float, owed: float}
     */
    function affiliate_program_totals(string $environment): array
    {
        global $pdo;
        $totals = ['earned' => 0.0, 'pending' => 0.0, 'paid' => 0.0, 'owed' => 0.0];
        try {
            $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE environment = ? AND status IN ('approved', 'suspended')");
            $stmt->execute([$environment]);
            foreach ($stmt->fetchAll() as $a) {
                $m = affiliate_money_summary($a, $environment);
                $totals['earned']  += $m['earned'];
                $totals['pending'] += $m['pending'];
                $totals['paid']    += $m['paid'];
                $totals['owed']    += $m['owed'];
            }
        } catch (PDOException $e) {
            // Affiliate tables not present on this server yet; report zeros.
        }
        return [
            'earned'  => round($totals['earned'], 2),
            'pending' => round($totals['pending'], 2),
            'paid'    => round($totals['paid'], 2),
            'owed'    => round($totals['owed'], 2),
        ];
    }
}

if (!function_exists('get_affiliate_for_user')) {
    /** The affiliate row for a community user in the current environment, or null. */
    function get_affiliate_for_user(int $user_id, string $environment): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT * FROM affiliates WHERE user_id = ? AND environment = ?');
        $stmt->execute([$user_id, $environment]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('get_affiliate_by_id')) {
    function get_affiliate_by_id(int $id, string $environment): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT * FROM affiliates WHERE id = ? AND environment = ?');
        $stmt->execute([$id, $environment]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('get_affiliate_stats')) {
    /**
     * Funnel counts for one affiliate's source_code in the current environment:
     * clicks (referral_visits), plus distinct-visitor counts per funnel stage
     * (referral_events). Shapes mirror admin/referral-links + marketing-funnel.
     *
     * @return array{clicks:int, signups:int, paying:int}
     */
    function get_affiliate_stats(string $source_code, string $environment): array
    {
        global $pdo;

        // Clicks: every recorded visit to this source. referral_visits is not
        // environment-scoped (no column), so this counts all-time clicks; the
        // money figures above are the env-scoped numbers that matter.
        $clicks_stmt = $pdo->prepare('SELECT COUNT(*) FROM referral_visits WHERE source_code = ?');
        $clicks_stmt->execute([$source_code]);
        $clicks = (int) $clicks_stmt->fetchColumn();

        $ev_stmt = $pdo->prepare("
            SELECT
              COUNT(DISTINCT CASE WHEN event_type = 'premium_signup' THEN visitor_id END) AS signups,
              COUNT(DISTINCT CASE WHEN event_type = 'premium_paid'
                                   AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.payment_type')) = 'initial'
                                  THEN subscription_id END) AS paying
            FROM referral_events
            WHERE source_code = ? AND environment = ?");
        $ev_stmt->execute([$source_code, $environment]);
        $ev = $ev_stmt->fetch() ?: ['signups' => 0, 'paying' => 0];

        return [
            'clicks'  => $clicks,
            'signups' => (int) $ev['signups'],
            'paying'  => (int) $ev['paying'],
        ];
    }
}

if (!function_exists('generate_affiliate_source_code')) {
    /**
     * Build a unique, URL-safe source_code for an affiliate from their username.
     *
     * Format: aff-{sanitized-username}, lowercased, non [a-z0-9-] stripped,
     * truncated to fit VARCHAR(50). On collision (or empty username) a short
     * random hex suffix is appended. The "aff-" prefix namespaces affiliate
     * codes in every existing dashboard and avoids the "google-ads-" gclid
     * gate in track_referral.php.
     *
     * @param callable|null $exists Optional predicate (string):bool used to test
     *                              uniqueness; defaults to a DB lookup. Injected
     *                              for unit testing without a database.
     */
    function generate_affiliate_source_code(string $username, ?callable $exists = null): string
    {
        if ($exists === null) {
            $exists = function (string $code): bool {
                global $pdo;
                $stmt = $pdo->prepare('SELECT 1 FROM referral_links WHERE source_code = ? UNION SELECT 1 FROM affiliates WHERE source_code = ? LIMIT 1');
                $stmt->execute([$code, $code]);
                return (bool) $stmt->fetchColumn();
            };
        }

        $slug = strtolower($username);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'user';
        }

        $prefix = 'aff-';
        $max = 50;

        // Base candidate, truncated to leave headroom for a possible suffix.
        $base = $prefix . substr($slug, 0, $max - strlen($prefix));
        $base = rtrim($base, '-');

        if (!$exists($base)) {
            return $base;
        }

        // Collision: append "-XXXX" (4 hex), trimming the slug so the total
        // still fits in 50 chars. Try a handful of random suffixes.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = '-' . substr(bin2hex(random_bytes(4)), 0, 4);
            $room = $max - strlen($prefix) - strlen($suffix);
            $candidate = $prefix . rtrim(substr($slug, 0, max(1, $room)), '-') . $suffix;
            if (!$exists($candidate)) {
                return $candidate;
            }
        }

        // Extremely unlikely fallback: prefix + 8 random hex.
        return $prefix . substr(bin2hex(random_bytes(8)), 0, $max - strlen($prefix));
    }
}

if (!function_exists('affiliate_referral_url')) {
    /** The shareable referral URL an affiliate promotes. */
    function affiliate_referral_url(string $source_code): string
    {
        return 'https://argorobots.com/?source=' . rawurlencode($source_code);
    }
}

if (!function_exists('affiliate_normalize_code')) {
    /** Lowercase + strip anything that isn't URL-safe from a requested code. */
    function affiliate_normalize_code(string $raw): string
    {
        $c = strtolower(trim($raw));
        $c = preg_replace('/[^a-z0-9-]/', '', $c);
        return trim($c, '-');
    }
}

if (!function_exists('affiliate_code_has_reserved_prefix')) {
    /**
     * True if a source_code starts with a prefix the referral system uses to
     * auto-categorize traffic (see referral_default_category + track_referral.php).
     * Affiliate codes must not masquerade as those, and google-ads-* also trips
     * a gclid gate.
     */
    function affiliate_code_has_reserved_prefix(string $code): bool
    {
        foreach (['google-ads-', 'bing-ads-', 'ads-', 'paid-', 'ai-', 'social-', 'guide-', 'guides-', 'youtube-'] as $p) {
            if (strncmp($code, $p, strlen($p)) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('affiliate_source_code_taken')) {
    /**
     * Whether a source_code is already used by a referral link or another
     * affiliate. Pass $ignore_affiliate_id when editing an existing affiliate so
     * its own code doesn't count as a collision.
     */
    function affiliate_source_code_taken(string $code, ?int $ignore_affiliate_id = null): bool
    {
        global $pdo;
        $rl = $pdo->prepare('SELECT 1 FROM referral_links WHERE source_code = ? LIMIT 1');
        $rl->execute([$code]);
        if ($rl->fetchColumn()) {
            return true;
        }
        if ($ignore_affiliate_id !== null) {
            $a = $pdo->prepare('SELECT 1 FROM affiliates WHERE source_code = ? AND id <> ? LIMIT 1');
            $a->execute([$code, $ignore_affiliate_id]);
        } else {
            $a = $pdo->prepare('SELECT 1 FROM affiliates WHERE source_code = ? LIMIT 1');
            $a->execute([$code]);
        }
        return (bool) $a->fetchColumn();
    }
}

if (!function_exists('affiliate_is_deletable')) {
    /**
     * An affiliate can be deleted only when it has no history worth keeping:
     * zero clicks, signups, paying customers, and zero money earned or paid.
     * Deleting one with activity would orphan referral attribution.
     */
    function affiliate_is_deletable(array $affiliate, string $environment): bool
    {
        $stats = get_affiliate_stats($affiliate['source_code'], $environment);
        $money = affiliate_money_summary($affiliate, $environment);
        return $stats['clicks'] === 0
            && $stats['signups'] === 0
            && $stats['paying'] === 0
            && (float) $money['earned'] === 0.0
            && (float) $money['paid'] === 0.0;
    }
}
