<?php
declare(strict_types=1);

namespace Tests\Integration\License;

use Tests\Helpers\IntegrationTestCase;

require_once PROJECT_ROOT . '/api/receipt/scan_quota.php';

/**
 * disable_reseller_keys() and enable_reseller_key() open their own transactions, so these
 * tests use IntegrationTestCase, which deletes its fixture rows afterwards.
 */
final class DisableResellerKeyTest extends IntegrationTestCase
{
    private const DEVICE = 'device-hash-disable-001';

    private function inBatch(string $key): string
    {
        $this->pdo->prepare("UPDATE premium_subscription_keys SET batch_label = 'Test Batch' WHERE subscription_key = ?")
            ->execute([$key]);
        return $key;
    }

    private function redeemedBatchKey(string $subscriptionId): string
    {
        $this->seedSubscription($subscriptionId, (new \DateTime('+100 years'))->format('Y-m-d H:i:s'));
        return $this->inBatch($this->seedRedeemedKey(self::DEVICE, $subscriptionId, 0));
    }

    private function subscriptionStatus(string $subscriptionId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subscriptionId]);
        return $stmt->fetch()['status'];
    }

    public function test_a_disabled_key_cannot_be_redeemed(): void
    {
        $key = $this->inBatch($this->seedPremiumKey(durationMonths: 0));

        disable_reseller_keys([$key], 'Refunded');
        $result = redeem_premium_key($key, self::DEVICE);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_key', $result['status']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    public function test_a_redeemed_key_that_is_disabled_stops_validating(): void
    {
        $key = $this->redeemedBatchKey('PREM-DSBL-VALD-AAAA-0001');

        $r = disable_reseller_keys([$key]);

        $this->assertSame(1, $r['disabled']);
        $this->assertSame(1, $r['redeemed']);
        $this->assertSame('invalid_key', validate_license($key, self::DEVICE)['status']);
        $this->assertSame('expired', $this->subscriptionStatus('PREM-DSBL-VALD-AAAA-0001'));
    }

    // The receipt scanner also accepts the subscription id as the key, so that route must lose Premium too.
    public function test_a_disabled_key_loses_the_premium_receipt_allowance(): void
    {
        $subId = 'PREM-DSBL-SCAN-AAAA-0002';
        $key = $this->redeemedBatchKey($subId);
        $this->assertSame('premium', receipt_scan_quota_identity($this->pdo, $key, null)['tier']);

        disable_reseller_keys([$key]);

        $this->assertNull(receipt_scan_quota_identity($this->pdo, $key, null));
        $this->assertNull(receipt_scan_quota_identity($this->pdo, $subId, null));
    }

    public function test_keys_outside_a_reseller_batch_are_left_alone(): void
    {
        $promo = $this->seedPremiumKey();

        $r = disable_reseller_keys([$promo, 'PREM-NOPE-NOPE-NOPE-NOPE']);

        $this->assertSame(0, $r['disabled']);
        $this->assertSame([strtoupper($promo), 'PREM-NOPE-NOPE-NOPE-NOPE'], $r['not_found']);
        $this->assertTrue(validate_premium_key($promo)['success']);
    }

    public function test_pasted_keys_are_matched_whatever_their_case_and_counted_once(): void
    {
        $key = $this->inBatch($this->seedPremiumKey());

        $first = disable_reseller_keys([' ' . strtolower($key) . ' ', $key]);
        $second = disable_reseller_keys([$key]);

        $this->assertSame(1, $first['disabled']);
        $this->assertSame(0, $second['disabled']);
        $this->assertSame(1, $second['already']);
    }

    public function test_enabling_a_key_gives_its_premium_back(): void
    {
        $subId = 'PREM-DSBL-ENBL-AAAA-0003';
        $key = $this->redeemedBatchKey($subId);
        disable_reseller_keys([$key]);

        $stmt = $this->pdo->prepare('SELECT id FROM premium_subscription_keys WHERE subscription_key = ?');
        $stmt->execute([$key]);
        $this->assertTrue(enable_reseller_key((int) $stmt->fetch()['id']));

        $this->assertSame('valid', validate_license($key, self::DEVICE)['status']);
        $this->assertSame('active', $this->subscriptionStatus($subId));
    }
}
