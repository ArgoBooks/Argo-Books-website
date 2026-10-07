<?php
declare(strict_types=1);

/**
 * Adaptive refund velocity engine. Computed at the
 * code_verified → next state transition.
 *
 * Returns ['tier' => ..., 'cooling_off_seconds' => int, 'reason' => string,
 *          'today_cents' => int, 'hour_count' => int]
 *
 * Tiers:
 *   normal      → state=processing, no extra friction
 *   soft_warn   → state=processing, UI showed extra confirmation upstream
 *   delayed     → state=cooling_off, refund held for cooling_off_seconds
 *   hard_block  → state=failed + portal_companies.locked = 1
 */

function refund_load_velocity_config(PDO $pdo, int $company_id): array {
    // Try company-specific override first, fall back to global default (company_id IS NULL).
    $stmt = $pdo->prepare("SELECT * FROM refund_velocity_config WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cfg) {
        $stmt = $pdo->query("SELECT * FROM refund_velocity_config WHERE company_id IS NULL LIMIT 1");
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // Final fallback if config table is empty (shouldn't happen post-migration). The *_floor_cents value MUST be strictly greater than *_cooling_cents in both age brackets.
    if (!$cfg) {
        return [
            'soft_warn_multiplier' => 3.0,
            'cooling_multiplier' => 10.0,
            'cooling_revenue_pct' => 0.25,
            'hard_revenue_pct' => 0.50,
            'cooling_off_minutes' => 15,
            'new_account_floor_cents' => 500000,      // $5,000/day hard-block
            'new_account_soft_cents' => 50000,        //   $500/day soft warn
            'new_account_cooling_cents' => 100000,    // $1,000/day cooling
            'young_account_floor_cents' => 1000000,   // $10,000/day hard-block
            'young_account_soft_cents' => 100000,     // $1,000/day soft warn
            'young_account_cooling_cents' => 300000,  // $3,000/day cooling
        ];
    }
    // A row from before the young_account_* columns can return NULL, so the documented defaults stand in rather than disabling the bracket.
    $cfg['young_account_floor_cents']   = $cfg['young_account_floor_cents']   ?? 1000000;
    $cfg['young_account_soft_cents']    = $cfg['young_account_soft_cents']    ?? 100000;
    $cfg['young_account_cooling_cents'] = $cfg['young_account_cooling_cents'] ?? 300000;
    return $cfg;
}

function refund_assess_velocity(PDO $pdo, array $company, int $amount_cents): array {
    $cfg = refund_load_velocity_config($pdo, (int)$company['id']);

    // Today's spend + count + last-hour count, INCLUDING the current request.
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount_cents), 0) AS today_cents,
            COALESCE(SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END), 0) AS hour_count
        FROM refund_requests
        WHERE company_id = ?
          AND state IN ('cooling_off','processing','completed')
          AND created_at >= CURDATE()
    ");
    $stmt->execute([$company['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_cents = (int)$row['today_cents'] + $amount_cents;
    $hour_count = (int)$row['hour_count'] + 1;

    $age_days = (time() - strtotime($company['created_at'] ?? 'now')) / 86400;

    // ----- New-account absolute floors (first 30 days) -----
    if ($age_days < 7) {
        if ($today_cents >= (int)$cfg['new_account_floor_cents']) {
            return ['tier' => 'hard_block', 'cooling_off_seconds' => 0, 'reason' => 'new_account_floor', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        if ($today_cents >= (int)$cfg['new_account_cooling_cents']) {
            return ['tier' => 'delayed', 'cooling_off_seconds' => (int)$cfg['cooling_off_minutes'] * 60, 'reason' => 'new_account_cooling', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        if ($today_cents >= (int)$cfg['new_account_soft_cents']) {
            return ['tier' => 'soft_warn', 'cooling_off_seconds' => 0, 'reason' => 'new_account_soft', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        return ['tier' => 'normal', 'cooling_off_seconds' => 0, 'today_cents' => $today_cents, 'hour_count' => $hour_count];
    }

    if ($age_days < 30) {
        if ($today_cents >= (int)$cfg['young_account_floor_cents']) {
            return ['tier' => 'hard_block', 'cooling_off_seconds' => 0, 'reason' => 'young_account_hard', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        if ($today_cents >= (int)$cfg['young_account_cooling_cents']) {
            return ['tier' => 'delayed', 'cooling_off_seconds' => (int)$cfg['cooling_off_minutes'] * 60, 'reason' => 'young_account_cool', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        if ($today_cents >= (int)$cfg['young_account_soft_cents']) {
            return ['tier' => 'soft_warn', 'cooling_off_seconds' => 0, 'reason' => 'young_account_soft', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
        }
        return ['tier' => 'normal', 'cooling_off_seconds' => 0, 'today_cents' => $today_cents, 'hour_count' => $hour_count];
    }

    // ----- Established accounts: baseline-derived -----
    $bstmt = $pdo->prepare("SELECT * FROM refund_velocity_baselines WHERE company_id = ?");
    $bstmt->execute([$company['id']]);
    $b = $bstmt->fetch(PDO::FETCH_ASSOC) ?: ['daily_avg_refund_cents' => 0, 'revenue_30d_cents' => 0];

    $daily_avg = (int)$b['daily_avg_refund_cents'];
    $rev_30d = (int)$b['revenue_30d_cents'];

    // Hard
    if ($hour_count >= 25
        || ($rev_30d > 0 && $today_cents >= (int)($rev_30d * (float)$cfg['hard_revenue_pct']))) {
        return ['tier' => 'hard_block', 'cooling_off_seconds' => 0, 'reason' => 'hard_threshold', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
    }
    // Cooling-off
    $cool_threshold = (int)max(
        $daily_avg * (float)$cfg['cooling_multiplier'],
        $rev_30d * (float)$cfg['cooling_revenue_pct']
    );
    if ($hour_count >= 10 || ($cool_threshold > 0 && $today_cents >= $cool_threshold)) {
        return ['tier' => 'delayed', 'cooling_off_seconds' => (int)$cfg['cooling_off_minutes'] * 60, 'reason' => 'cooling_threshold', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
    }
    // Soft
    $soft_threshold = (int)($daily_avg * (float)$cfg['soft_warn_multiplier']);
    if ($hour_count >= 5 || ($soft_threshold > 0 && $today_cents >= $soft_threshold)) {
        return ['tier' => 'soft_warn', 'cooling_off_seconds' => 0, 'reason' => 'soft_threshold', 'today_cents' => $today_cents, 'hour_count' => $hour_count];
    }
    return ['tier' => 'normal', 'cooling_off_seconds' => 0, 'today_cents' => $today_cents, 'hour_count' => $hour_count];
}
