<?php
// The app's launch check and every server-backed Premium feature read Premium through these, so they never disagree about one subscription.

/** Statuses that keep Premium until end_date: a cancelled subscription runs out the time paid for, and a failed payment keeps it while the customer fixes their card. */
const PREMIUM_ENTITLED_STATUSES = ['active', 'cancelled', 'payment_failed'];

/** Days an auto-renewing subscription keeps Premium past end_date, because the renewal cron retries a declined charge daily and gives up after the third try. */
const PREMIUM_RENEWAL_GRACE_DAYS = 3;

/** Whether a premium_subscriptions row, which needs status, end_date and auto_renew, carries Premium now. */
function premium_subscription_entitled(array $subscription): bool
{
    $status = $subscription['status'] ?? null;
    if (!in_array($status, PREMIUM_ENTITLED_STATUSES, true)) {
        return false;
    }
    $endDate = new DateTime((string) $subscription['end_date']);
    if ($status === 'active' && (int) ($subscription['auto_renew'] ?? 0) === 1) {
        $endDate->modify('+' . PREMIUM_RENEWAL_GRACE_DAYS . ' days');
    }
    return $endDate > new DateTime();
}

/** The same rule as a SQL condition over premium_subscriptions; $alias is a table prefix such as 's.'. */
function premium_entitled_sql(string $alias = ''): string
{
    $statuses = "'" . implode("', '", PREMIUM_ENTITLED_STATUSES) . "'";
    $graceDays = PREMIUM_RENEWAL_GRACE_DAYS;
    return "(({$alias}status IN ($statuses) AND {$alias}end_date > NOW())"
        . " OR ({$alias}status = 'active' AND {$alias}auto_renew = 1 AND {$alias}end_date > NOW() - INTERVAL $graceDays DAY))";
}

/** Whether a subscription whose end_date has passed may be marked expired: a renewal still being retried, or a failed payment the customer can still fix, must stay as it is. */
function premium_subscription_may_expire(array $subscription): bool
{
    $status = $subscription['status'] ?? null;
    if ($status === 'expired' || $status === 'payment_failed') {
        return false;
    }
    return !($status === 'active' && (int) ($subscription['auto_renew'] ?? 0) === 1);
}
