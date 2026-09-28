<?php
declare(strict_types=1);

namespace Tests\Integration\License;

use Tests\Helpers\DatabaseTestCase;

final class ValidateLicenseTest extends DatabaseTestCase
{
    private const DEVICE = 'device-hash-test-001';

    public function test_returns_invalid_key_for_unredeemed_key(): void
    {
        $key = $this->seedPremiumKey();
        $result = validate_license($key, self::DEVICE);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_key', $result['status']);
    }

    public function test_returns_wrong_device_when_device_id_mismatches(): void
    {
        $subId = 'PREM-TEST-SUB1-AAAA-BBBB';
        $this->seedSubscription($subId, (new \DateTime('+30 days'))->format('Y-m-d H:i:s'));
        $key = $this->seedRedeemedKey(self::DEVICE, $subId);

        $result = validate_license($key, 'different-device-hash');

        $this->assertFalse($result['success']);
        $this->assertSame('wrong_device', $result['status']);
    }

    public function test_returns_expired_when_subscription_end_date_in_past(): void
    {
        $subId = 'PREM-TEST-SUB2-CCCC-DDDD';
        $pastDate = (new \DateTime('-5 days'))->format('Y-m-d H:i:s');
        $this->seedSubscription($subId, $pastDate, status: 'active');
        $key = $this->seedRedeemedKey(self::DEVICE, $subId);

        $result = validate_license($key, self::DEVICE);

        $this->assertFalse($result['success']);
        $this->assertSame('expired', $result['status']);

        // Verify the side effect: subscription row flipped to 'expired'.
        $stmt = $this->pdo->prepare('SELECT status FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subId]);
        $this->assertSame('expired', $stmt->fetch()['status']);
    }

    public function test_returns_valid_when_redeemed_active_matching_device(): void
    {
        $subId = 'PREM-TEST-SUB3-EEEE-FFFF';
        $this->seedSubscription($subId, (new \DateTime('+90 days'))->format('Y-m-d H:i:s'));
        $key = $this->seedRedeemedKey(self::DEVICE, $subId);

        $result = validate_license($key, self::DEVICE);

        $this->assertTrue($result['success']);
        $this->assertSame('valid', $result['status']);
    }

    public function test_accepts_a_key_subscription_from_the_other_environment_and_moves_it_here(): void
    {
        // Tests run as sandbox, so a production subscription is the "other" one. Switching
        // the app between dev and production must not cost the owner their key.
        $subId = 'PREM-TEST-SUB4-GGGG-HHHH';
        $this->seedSubscription($subId, (new \DateTime('+90 days'))->format('Y-m-d H:i:s'));
        $this->pdo->prepare("UPDATE premium_subscriptions SET environment = 'production' WHERE subscription_id = ?")
            ->execute([$subId]);
        $key = $this->seedRedeemedKey(self::DEVICE, $subId);

        $result = validate_license($key, self::DEVICE);

        $this->assertTrue($result['success']);
        $this->assertSame('valid', $result['status']);
        $stmt = $this->pdo->prepare('SELECT environment FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subId]);
        $this->assertSame('sandbox', $stmt->fetch()['environment']);
    }

    public function test_rejects_a_subscription_paid_for_in_the_other_environment(): void
    {
        $subId = 'PREM-TEST-SUB5-GGGG-HHHH';
        $this->seedSubscription($subId, (new \DateTime('+90 days'))->format('Y-m-d H:i:s'));
        $this->pdo->prepare("UPDATE premium_subscriptions SET environment = 'production', payment_method = 'stripe' WHERE subscription_id = ?")
            ->execute([$subId]);
        $key = $this->seedRedeemedKey(self::DEVICE, $subId);

        $result = validate_license($key, self::DEVICE);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_key', $result['status']);
    }

    public function test_returns_invalid_when_subscription_row_missing(): void
    {
        // Redeemed key pointing at a non-existent subscription_id
        $key = $this->seedRedeemedKey(self::DEVICE, 'PREM-NOSUCH-SUBS-RIPT-IONX');

        $result = validate_license($key, self::DEVICE);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_key', $result['status']);
    }
}
