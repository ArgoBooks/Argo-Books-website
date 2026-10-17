<?php
declare(strict_types=1);

namespace Tests\Integration\Checkout;

use Tests\Helpers\DatabaseTestCase;

/** premium_active_subscription(), which decides whether checkout refuses a second subscription. */
final class ActiveSubscriptionTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once PROJECT_ROOT . '/pricing/premium/checkout/checkout_helpers.php';
    }

    private function subscribe(int $userId, string $status, string $endDate, string $environment = 'sandbox'): string
    {
        $id = 'PREM-ACTV-' . strtoupper(bin2hex(random_bytes(4)));
        $this->pdo->prepare(
            "INSERT INTO premium_subscriptions
             (subscription_id, user_id, email, billing_cycle, amount, currency, start_date, end_date,
              status, payment_method, auto_renew, environment, created_at)
             VALUES (?, ?, 'a@example.test', 'monthly', 15.74, 'CAD', NOW(), ?, ?, 'stripe', 1, ?, NOW())"
        )->execute([$id, $userId, $endDate, $status, $environment]);
        return $id;
    }

    public function test_finds_an_active_subscription_that_has_not_ended(): void
    {
        $user = $this->seedCommunityUser();
        $id = $this->subscribe($user, 'active', date('Y-m-d H:i:s', strtotime('+20 days')));

        $this->assertSame($id, premium_active_subscription($this->pdo, $user)['subscription_id'] ?? null);
    }

    public function test_a_user_with_no_subscription_has_none(): void
    {
        $this->assertNull(premium_active_subscription($this->pdo, $this->seedCommunityUser()));
    }

    public function test_cancelled_and_failed_subscriptions_do_not_block_checkout(): void
    {
        $user = $this->seedCommunityUser();
        $this->subscribe($user, 'cancelled', date('Y-m-d H:i:s', strtotime('+20 days')));
        $this->subscribe($user, 'payment_failed', date('Y-m-d H:i:s', strtotime('+20 days')));

        $this->assertNull(premium_active_subscription($this->pdo, $user));
    }

    public function test_an_active_subscription_past_its_end_date_does_not_block_checkout(): void
    {
        $user = $this->seedCommunityUser();
        $this->subscribe($user, 'active', date('Y-m-d H:i:s', strtotime('-1 day')));

        $this->assertNull(premium_active_subscription($this->pdo, $user));
    }

    public function test_a_subscription_in_the_other_environment_is_ignored(): void
    {
        // Production and sandbox share one database, and the tests run as sandbox.
        $user = $this->seedCommunityUser();
        $this->subscribe($user, 'active', date('Y-m-d H:i:s', strtotime('+20 days')), 'production');

        $this->assertNull(premium_active_subscription($this->pdo, $user));
    }

    public function test_a_new_paypal_subscription_stamped_with_this_user_is_cancelled(): void
    {
        $user = $this->seedCommunityUser();

        $this->assertTrue(premium_should_cancel_refused_paypal($this->pdo, $user, 'I-NEWNEWNEW', fn() => "user_$user"));
    }

    public function test_a_paypal_subscription_already_on_file_is_never_cancelled(): void
    {
        // A second delivery of the checkout that created it, which must keep billing.
        $user = $this->seedCommunityUser();
        $id = $this->subscribe($user, 'active', date('Y-m-d H:i:s', strtotime('+20 days')));
        $this->pdo->prepare('UPDATE premium_subscriptions SET paypal_subscription_id = ? WHERE subscription_id = ?')->execute(['I-ONFILE001', $id]);
        $asked = false;

        $this->assertFalse(premium_should_cancel_refused_paypal($this->pdo, $user, 'I-ONFILE001', function () use (&$asked, $user) {
            $asked = true;
            return "user_$user";
        }));
        $this->assertFalse($asked, 'PayPal need not be asked about a subscription already on file');
    }

    public function test_a_paypal_subscription_replaced_by_a_cycle_switch_is_never_cancelled(): void
    {
        $user = $this->seedCommunityUser();
        $id = $this->subscribe($user, 'active', date('Y-m-d H:i:s', strtotime('+20 days')));
        $this->pdo->prepare('UPDATE premium_subscriptions SET previous_paypal_subscription_id = ? WHERE subscription_id = ?')->execute(['I-OLDCYCLE1', $id]);

        $this->assertFalse(premium_should_cancel_refused_paypal($this->pdo, $user, 'I-OLDCYCLE1', fn() => "user_$user"));
    }

    public function test_someone_elses_paypal_subscription_is_never_cancelled(): void
    {
        $user = $this->seedCommunityUser();
        $other = $this->seedCommunityUser();

        $this->assertFalse(premium_should_cancel_refused_paypal($this->pdo, $user, 'I-NOTYOURS1', fn() => "user_$other"));
    }

    public function test_nothing_is_cancelled_when_paypal_cannot_confirm_whose_it_is(): void
    {
        $user = $this->seedCommunityUser();

        $this->assertFalse(premium_should_cancel_refused_paypal($this->pdo, $user, 'I-UNKNOWN01', fn() => null));
    }

    public function test_another_users_subscription_is_ignored(): void
    {
        $this->subscribe($this->seedCommunityUser(), 'active', date('Y-m-d H:i:s', strtotime('+20 days')));

        $this->assertNull(premium_active_subscription($this->pdo, $this->seedCommunityUser()));
    }
}
