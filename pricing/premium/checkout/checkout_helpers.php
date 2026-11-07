<?php
// Shared by process-subscription.php and check-active.php, so the PayPal button and the server agree on who already has Premium.

require_once __DIR__ . '/../../../premium_entitlement.php';

/** The user's active Premium subscription in this environment, counting one whose renewal is still being retried, or null. */
function premium_active_subscription(PDO $pdo, int $userId): ?array
{
    // A renewal still being retried may yet charge the customer, so a second subscription then would bill them twice.
    $stmt = $pdo->prepare("
        SELECT * FROM premium_subscriptions
        WHERE user_id = ? AND status = 'active' AND environment = ?
        AND (end_date > NOW() OR " . premium_in_renewal_grace_sql() . ")
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId, current_environment()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** Whether a refused checkout should cancel the PayPal subscription it was handed; $customIdOf returns its PayPal custom_id, or null when PayPal cannot be asked. */
function premium_should_cancel_refused_paypal(PDO $pdo, int $userId, string $paypalSubscriptionId, callable $customIdOf): bool
{
    // A second delivery of a checkout that already succeeded carries the subscription it created, which must keep billing.
    $stmt = $pdo->prepare("
        SELECT 1 FROM premium_subscriptions
        WHERE paypal_subscription_id = ? OR previous_paypal_subscription_id = ?
        LIMIT 1
    ");
    $stmt->execute([$paypalSubscriptionId, $paypalSubscriptionId]);
    if ($stmt->fetchColumn() !== false) {
        return false;
    }

    // Only a subscription this user approved here is theirs to cancel, and the checkout stamps it with their id.
    return $customIdOf($paypalSubscriptionId) === "user_$userId";
}

/** The PayPal plan the checkout page subscribes to for this billing cycle in this environment, or '' when none is configured. */
function premium_paypal_plan_id(string $billing): string
{
    $environment = (($_ENV['APP_ENV'] ?? 'development') === 'production') ? 'LIVE' : 'SANDBOX';
    $cycle = $billing === 'yearly' ? 'YEARLY' : 'MONTHLY';
    return (string) ($_ENV["PAYPAL_{$environment}_{$cycle}_PLAN_ID"] ?? '');
}

/** Why checkout must refuse a PayPal subscription, or null when PayPal confirms it; $fetchSubscription returns PayPal's record of it, or null when PayPal cannot be asked. */
function premium_paypal_subscription_refusal(string $paypalSubscriptionId, int $userId, string $expectedPlanId, callable $fetchSubscription): ?string
{
    $subscription = $fetchSubscription($paypalSubscriptionId);
    if (!is_array($subscription)) {
        return 'unconfirmed';
    }
    // The checkout page stamps every subscription it creates with the buyer's user id.
    if (($subscription['custom_id'] ?? null) !== "user_$userId") {
        return 'other_user';
    }
    // APPROVED comes before the first charge, so only ACTIVE shows the customer has paid.
    if (($subscription['status'] ?? '') === 'APPROVED') {
        return 'not_yet_active';
    }
    if (($subscription['status'] ?? '') !== 'ACTIVE') {
        return 'not_active';
    }
    // Without this a monthly subscription could be posted as a yearly one.
    if ($expectedPlanId === '' || ($subscription['plan_id'] ?? null) !== $expectedPlanId) {
        return 'wrong_plan';
    }
    return null;
}

/** PayPal's record of a subscription, asked again for a few seconds while it is missing or still APPROVED, since it turns ACTIVE shortly after the buyer approves. */
function premium_fetch_settled_paypal_subscription(string $paypalSubscriptionId, callable $fetch, callable $wait, int $attempts = 6): ?array
{
    $subscription = null;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $subscription = $fetch($paypalSubscriptionId);
        if (is_array($subscription) && ($subscription['status'] ?? '') !== 'APPROVED') {
            return $subscription;
        }
        if ($attempt < $attempts) {
            $wait();
        }
    }
    return $subscription;
}
