<?php
declare(strict_types=1);

namespace Tests\Integration\License;

use Tests\Helpers\IntegrationTestCase;

/**
 * redeem_premium_key() and _recreate_subscription_for_key() each call
 * $pdo->beginTransaction() internally, so these tests can't use the
 * transaction-rolled DatabaseTestCase. They use IntegrationTestCase, which
 * tracks fixture rows and deletes them in tearDown().
 */
final class RedeemPremiumKeyTest extends IntegrationTestCase
{
    private const DEVICE = 'device-hash-redeem-001';

    public function test_first_redemption_creates_subscription_and_marks_key_redeemed(): void
    {
        $key = $this->seedPremiumKey(durationMonths: 6);

        $result = redeem_premium_key($key, self::DEVICE);

        $this->assertTrue($result['success']);
        $this->assertSame('active', $result['status']);
        $this->assertNotEmpty($result['subscription_id']);

        // Track for cleanup
        $this->trackSubscription($result['subscription_id']);

        // Verify subscription row was inserted
        $stmt = $this->pdo->prepare('SELECT * FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$result['subscription_id']]);
        $sub = $stmt->fetch();
        $this->assertNotFalse($sub);
        $this->assertSame('active', $sub['status']);
        $this->assertSame('monthly', $sub['billing_cycle']);

        // Verify key marked redeemed
        $stmt = $this->pdo->prepare('SELECT redeemed_at, device_id FROM premium_subscription_keys WHERE subscription_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $this->assertNotNull($row['redeemed_at']);
        $this->assertSame(self::DEVICE, $row['device_id']);
    }

    public function test_first_redemption_uses_yearly_billing_for_12_month_duration(): void
    {
        $key = $this->seedPremiumKey(durationMonths: 12);
        $result = redeem_premium_key($key, self::DEVICE);
        $this->trackSubscription($result['subscription_id']);

        $stmt = $this->pdo->prepare('SELECT billing_cycle FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$result['subscription_id']]);
        $this->assertSame('yearly', $stmt->fetch()['billing_cycle']);
    }

    public function test_first_redemption_with_zero_duration_creates_lifetime_subscription(): void
    {
        $key = $this->seedPremiumKey(durationMonths: 0);
        $result = redeem_premium_key($key, self::DEVICE);
        $this->trackSubscription($result['subscription_id']);

        $stmt = $this->pdo->prepare('SELECT end_date FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$result['subscription_id']]);
        $endDate = $stmt->fetch()['end_date'];

        // 100 years out (give or take). Assert at least 99 years from now
        $minExpected = (new \DateTime('+99 years'))->format('Y-m-d');
        $this->assertGreaterThanOrEqual($minExpected, substr($endDate, 0, 10));
    }

    public function test_re_redemption_active_subscription_transfers_device_id(): void
    {
        $subId = 'PREM-RERED-EMPT-AAAA-BBBB';
        $this->seedSubscription($subId, (new \DateTime('+30 days'))->format('Y-m-d H:i:s'));
        $key = $this->seedRedeemedKey('original-device', $subId);

        $result = redeem_premium_key($key, 'new-device-after-transfer');

        $this->assertTrue($result['success']);
        $this->assertSame('active', $result['status']);
        $this->assertSame($subId, $result['subscription_id']);

        // Verify device_id transferred on the key
        $stmt = $this->pdo->prepare('SELECT device_id FROM premium_subscription_keys WHERE subscription_key = ?');
        $stmt->execute([$key]);
        $this->assertSame('new-device-after-transfer', $stmt->fetch()['device_id']);
    }

    public function test_re_redemption_expired_subscription_returns_expired(): void
    {
        $subId = 'PREM-EXPIR-EDDD-AAAA-CCCC';
        $this->seedSubscription($subId, (new \DateTime('-5 days'))->format('Y-m-d H:i:s'), status: 'active');
        $key = $this->seedRedeemedKey('original-device', $subId);

        $result = redeem_premium_key($key, 'new-device');

        $this->assertFalse($result['success']);
        $this->assertSame('expired', $result['status']);

        // Side effect: subscription marked expired in DB
        $stmt = $this->pdo->prepare('SELECT status FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subId]);
        $this->assertSame('expired', $stmt->fetch()['status']);
    }

    public function test_re_redemption_moves_a_key_subscription_into_this_environment(): void
    {
        // A key carries no environment, so one redeemed on the other site can be redeemed here.
        $subId = 'PREM-OTHER-ENVS-AAAA-DDDD';
        $this->seedSubscription($subId, (new \DateTime('+30 days'))->format('Y-m-d H:i:s'));
        $this->pdo->prepare("UPDATE premium_subscriptions SET environment = 'production' WHERE subscription_id = ?")
            ->execute([$subId]);
        $key = $this->seedRedeemedKey('original-device', $subId);

        $result = redeem_premium_key($key, 'new-device');

        $this->assertTrue($result['success']);
        $this->assertSame('active', $result['status']);

        // The device moved, and the subscription came with it rather than being
        // left stamped with the other environment where later queries would miss it.
        $stmt = $this->pdo->prepare('SELECT device_id, subscription_id FROM premium_subscription_keys WHERE subscription_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $this->assertSame('new-device', $row['device_id']);
        $this->assertSame($subId, $row['subscription_id']);

        $stmt = $this->pdo->prepare('SELECT environment FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subId]);
        $this->assertSame('sandbox', $stmt->fetchColumn());

        // Moved, not duplicated.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM premium_subscriptions WHERE transaction_id = ?');
        $stmt->execute([$key]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function test_re_redemption_refuses_a_subscription_paid_for_in_the_other_environment(): void
    {
        // Bought through a sandbox checkout rather than minted by a key. Moving this
        // one would hand out production premium for test money.
        $subId = 'PREM-OTHER-PAID-AAAA-DDDD';
        $this->seedSubscription($subId, (new \DateTime('+30 days'))->format('Y-m-d H:i:s'));
        $this->pdo->prepare("UPDATE premium_subscriptions SET environment = 'production', payment_method = 'stripe' WHERE subscription_id = ?")
            ->execute([$subId]);
        $key = $this->seedRedeemedKey('original-device', $subId);

        $result = redeem_premium_key($key, 'new-device');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_key', $result['status']);

        $stmt = $this->pdo->prepare('SELECT device_id FROM premium_subscription_keys WHERE subscription_key = ?');
        $stmt->execute([$key]);
        $this->assertSame('original-device', $stmt->fetchColumn());

        $stmt = $this->pdo->prepare('SELECT environment FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subId]);
        $this->assertSame('production', $stmt->fetchColumn());
    }

    public function test_re_redemption_missing_subscription_recreates_it(): void
    {
        // Key marked redeemed but pointing at a subscription_id that doesn't exist
        $key = $this->seedRedeemedKey('original-device', 'PREM-NOSUCH-SUBS-RIPT-IONA', durationMonths: 6);

        $result = redeem_premium_key($key, 'new-device');

        $this->assertTrue($result['success']);
        $this->assertSame('active', $result['status']);
        $this->assertNotSame('PREM-NOSUCH-SUBS-RIPT-IONA', $result['subscription_id']);

        $this->trackSubscription($result['subscription_id']);

        // New subscription row exists
        $stmt = $this->pdo->prepare('SELECT status FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$result['subscription_id']]);
        $this->assertSame('active', $stmt->fetch()['status']);
    }
}
