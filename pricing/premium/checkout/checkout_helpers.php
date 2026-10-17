<?php
// Shared by process-subscription.php and check-active.php, so the PayPal button and the server agree on who already has Premium.

/** The user's active, unexpired Premium subscription in this environment, or null. */
function premium_active_subscription(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT * FROM premium_subscriptions
        WHERE user_id = ? AND status = 'active' AND environment = ? AND end_date > NOW()
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
