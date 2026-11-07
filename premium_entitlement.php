<?php
// The app's launch check and every server-backed Premium feature read Premium through these, so they never disagree about one subscription.

/** Statuses that keep Premium until end_date: a cancelled subscription runs out the time paid for, and a failed payment keeps it while the customer fixes their card. */
const PREMIUM_ENTITLED_STATUSES = ['active', 'cancelled', 'payment_failed'];

/** Whether a premium_subscriptions row, which needs status and end_date, carries Premium now. */
function premium_subscription_entitled(array $subscription): bool
{
    if (!in_array($subscription['status'] ?? null, PREMIUM_ENTITLED_STATUSES, true)) {
        return false;
    }
    return new DateTime((string) $subscription['end_date']) > new DateTime();
}

/** The same rule as a SQL condition over premium_subscriptions; $alias is a table prefix such as 's.'. */
function premium_entitled_sql(string $alias = ''): string
{
    $statuses = "'" . implode("', '", PREMIUM_ENTITLED_STATUSES) . "'";
    return "({$alias}status IN ($statuses) AND {$alias}end_date > NOW())";
}
