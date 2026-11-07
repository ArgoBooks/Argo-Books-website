<?php
declare(strict_types=1);

namespace Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;

/** premium_paypal_subscription_refusal(), which stops checkout granting Premium for a PayPal subscription PayPal does not vouch for. */
final class PayPalSubscriptionRefusalTest extends TestCase
{
    private const PLAN = 'P-MONTHLY-PLAN';

    public static function setUpBeforeClass(): void
    {
        require_once PROJECT_ROOT . '/pricing/premium/checkout/checkout_helpers.php';
    }

    private static function paypalSays(?array $subscription): callable
    {
        return fn(string $id): ?array => $subscription;
    }

    private static function subscription(array $overrides = []): array
    {
        return array_merge(['id' => 'I-REAL', 'status' => 'ACTIVE', 'custom_id' => 'user_42', 'plan_id' => self::PLAN], $overrides);
    }

    public function test_an_active_subscription_of_this_user_on_the_chosen_plan_is_accepted(): void
    {
        $this->assertNull(premium_paypal_subscription_refusal('I-REAL', 42, self::PLAN, self::paypalSays(self::subscription())));
    }

    public function test_a_subscription_not_yet_charged_is_not_accepted(): void
    {
        // APPROVED comes before the first charge, so granting on it would give Premium before any payment.
        $this->assertSame('not_yet_active', premium_paypal_subscription_refusal('I-REAL', 42, self::PLAN, self::paypalSays(self::subscription(['status' => 'APPROVED']))));
    }

    public function test_a_made_up_id_paypal_does_not_know_is_refused(): void
    {
        $this->assertSame('unconfirmed', premium_paypal_subscription_refusal('I-AAAAAAAAAAAA', 42, self::PLAN, self::paypalSays(null)));
    }

    public function test_a_cancelled_or_pending_subscription_is_refused(): void
    {
        foreach (['CANCELLED', 'SUSPENDED', 'EXPIRED', 'APPROVAL_PENDING', ''] as $status) {
            $this->assertSame('not_active', premium_paypal_subscription_refusal('I-REAL', 42, self::PLAN, self::paypalSays(self::subscription(['status' => $status]))), $status);
        }
    }

    public function test_another_users_subscription_is_refused(): void
    {
        $this->assertSame('other_user', premium_paypal_subscription_refusal('I-REAL', 7, self::PLAN, self::paypalSays(self::subscription())));
        $this->assertSame('other_user', premium_paypal_subscription_refusal('I-REAL', 42, self::PLAN, self::paypalSays(self::subscription(['custom_id' => null]))));
    }

    public function test_another_users_subscription_is_named_as_such_whatever_its_status(): void
    {
        // Only other_user is a plain refusal, so a stranger's subscription must never come back as one of the customer's own.
        $this->assertSame('other_user', premium_paypal_subscription_refusal('I-REAL', 7, self::PLAN, self::paypalSays(self::subscription(['status' => 'CANCELLED']))));
        $this->assertSame('other_user', premium_paypal_subscription_refusal('I-REAL', 7, 'P-OTHER', self::paypalSays(self::subscription())));
    }

    public function test_a_subscription_that_turns_active_while_checkout_waits_is_used(): void
    {
        $statuses = ['APPROVED', 'APPROVED', 'ACTIVE'];
        $waits = 0;
        $fetch = function (string $id) use (&$statuses): ?array {
            return self::subscription(['status' => array_shift($statuses)]);
        };

        $subscription = premium_fetch_settled_paypal_subscription('I-REAL', $fetch, function () use (&$waits) { $waits++; });

        $this->assertSame('ACTIVE', $subscription['status']);
        $this->assertSame(2, $waits);
    }

    public function test_waiting_stops_after_the_last_attempt(): void
    {
        $fetches = 0;
        $fetch = function (string $id) use (&$fetches): ?array {
            $fetches++;
            return self::subscription(['status' => 'APPROVED']);
        };

        $subscription = premium_fetch_settled_paypal_subscription('I-REAL', $fetch, fn() => null, 4);

        $this->assertSame('APPROVED', $subscription['status']);
        $this->assertSame(4, $fetches);
    }

    public function test_a_subscription_on_a_different_plan_is_refused(): void
    {
        // A monthly subscription posted as yearly would otherwise buy a year for a month's price.
        $this->assertSame('wrong_plan', premium_paypal_subscription_refusal('I-REAL', 42, 'P-YEARLY-PLAN', self::paypalSays(self::subscription())));
    }

    public function test_nothing_is_accepted_when_no_plan_is_configured(): void
    {
        $this->assertSame('wrong_plan', premium_paypal_subscription_refusal('I-REAL', 42, '', self::paypalSays(self::subscription(['plan_id' => '']))));
    }

    public function test_the_plan_follows_the_environment_and_billing_cycle(): void
    {
        $saved = $_ENV;
        try {
            $_ENV['APP_ENV'] = 'sandbox';
            $_ENV['PAYPAL_SANDBOX_MONTHLY_PLAN_ID'] = 'P-SB-M';
            $_ENV['PAYPAL_SANDBOX_YEARLY_PLAN_ID'] = 'P-SB-Y';
            $_ENV['PAYPAL_LIVE_YEARLY_PLAN_ID'] = 'P-LIVE-Y';
            $this->assertSame('P-SB-M', premium_paypal_plan_id('monthly'));
            $this->assertSame('P-SB-Y', premium_paypal_plan_id('yearly'));

            $_ENV['APP_ENV'] = 'production';
            $this->assertSame('P-LIVE-Y', premium_paypal_plan_id('yearly'));
            unset($_ENV['PAYPAL_LIVE_MONTHLY_PLAN_ID']);
            $this->assertSame('', premium_paypal_plan_id('monthly'));
        } finally {
            $_ENV = $saved;
        }
    }
}
