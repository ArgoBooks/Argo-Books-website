<?php
// The app's launch check and every server-backed Premium feature read Premium through these, all on the database's clock, so they never disagree about one subscription.

/** Statuses that keep Premium until end_date: a cancelled subscription runs out the time paid for, and a failed payment keeps it while the customer fixes their card. */
const PREMIUM_ENTITLED_STATUSES = ['active', 'cancelled', 'payment_failed'];

/** Days an auto-renewing subscription keeps Premium past end_date, because the renewal cron retries a declined charge daily and gives up after the third try. */
const PREMIUM_RENEWAL_GRACE_DAYS = 3;

/** The rule as a SQL condition over premium_subscriptions; $alias is a table prefix such as 's.'. */
function premium_entitled_sql(string $alias = ''): string
{
    $statuses = "'" . implode("', '", PREMIUM_ENTITLED_STATUSES) . "'";
    return "(({$alias}status IN ($statuses) AND {$alias}end_date > NOW()) OR " . premium_in_renewal_grace_sql($alias) . ")";
}

/** An auto-renewing subscription past end_date whose renewal is still being retried, as a SQL condition. */
function premium_in_renewal_grace_sql(string $alias = ''): string
{
    $graceDays = PREMIUM_RENEWAL_GRACE_DAYS;
    return "({$alias}status = 'active' AND COALESCE({$alias}auto_renew, 0) = 1 AND {$alias}end_date > NOW() - INTERVAL $graceDays DAY)";
}

/** The rule and whether an ended row may be marked expired, as the SELECT columns premium_entitled and premium_expirable. */
function premium_entitlement_columns(string $alias = ''): string
{
    return "COALESCE(" . premium_entitled_sql($alias) . ", 0) AS premium_entitled, "
        . "COALESCE({$alias}status NOT IN ('expired', 'payment_failed') AND {$alias}end_date <= NOW() AND NOT " . premium_in_renewal_grace_sql($alias) . ", 0) AS premium_expirable";
}

/** Whether a row selected with premium_entitlement_columns() carries Premium now. */
function premium_subscription_entitled(array $subscription): bool
{
    return premium_entitlement_flag($subscription, 'premium_entitled');
}

/** Whether a row selected with premium_entitlement_columns() has ended and may be marked expired: a renewal inside its grace and a failed payment the customer can still fix stay as they are. */
function premium_subscription_may_expire(array $subscription): bool
{
    return premium_entitlement_flag($subscription, 'premium_expirable');
}

function premium_entitlement_flag(array $subscription, string $column): bool
{
    // Deciding in PHP would use PHP's clock, which can differ from the database's NOW() by hours.
    if (!array_key_exists($column, $subscription)) {
        throw new LogicException("Select the subscription with premium_entitlement_columns() to read $column.");
    }
    return (int) $subscription[$column] === 1;
}
