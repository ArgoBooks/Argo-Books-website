<?php
declare(strict_types=1);

namespace Tests\Integration\PublicApi;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The API is Premium only, checked on every request against the subscription recorded on the
 * account, so a key keeps working exactly as long as someone is paying for it.
 */
final class ApiPremiumAccessTest extends TestCase
{
    private PDO $pdo;
    private int $accountId;
    private string $subscriptionId;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $GLOBALS['pdo'];
        $this->subscriptionId = 'PREM-TEST-API-' . bin2hex(random_bytes(4));

        $publicId = api_generate_id('acct');
        $this->pdo->prepare(
            'INSERT INTO api_accounts (public_id, owner_identity_hash, company_uid, subscription_id, display_name)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$publicId, hash('sha256', 'phpunit-' . $publicId), 'phpunit-' . bin2hex(random_bytes(6)), $this->subscriptionId, 'PHPUnit Co']);
        $this->accountId = (int) $this->pdo->lastInsertId();

        $this->secret = api_generate_secret_key();
        $this->pdo->prepare(
            'INSERT INTO api_keys (account_id, public_id, key_hash, key_hint, label, scopes) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$this->accountId, api_generate_id('key'), hash('sha256', $this->secret), api_key_hint($this->secret), 'phpunit', 'read,write']);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([$this->accountId]);
        $this->pdo->prepare('DELETE FROM premium_subscriptions WHERE subscription_id = ?')->execute([$this->subscriptionId]);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    public function testAKeyWorksWhileTheSubscriptionIsActive(): void
    {
        $this->subscription('active', '+30 days');

        $this->assertSame($this->accountId, $this->authenticate()['account_id']);
    }

    public function testACancelledSubscriptionKeepsWorkingUntilItsEndDate(): void
    {
        $this->subscription('cancelled', '+10 days');

        $this->assertSame($this->accountId, $this->authenticate()['account_id']);
    }

    public function testAKeyCreatedSubscriptionCountsInTheOtherEnvironment(): void
    {
        $this->subscription('active', '+30 days', $this->otherEnvironment());

        $this->assertSame($this->accountId, $this->authenticate()['account_id']);
    }

    public function testASubscriptionPaidForInTheOtherEnvironmentIsRefused(): void
    {
        $this->subscription('active', '+30 days', $this->otherEnvironment(), 'stripe');

        $this->assertRefusedForPremium();
    }

    public function testAKeyStopsWorkingOnceTheSubscriptionHasEnded(): void
    {
        $this->subscription('active', '-1 day');

        $this->assertRefusedForPremium();
    }

    public function testAKeyWithNoSubscriptionBehindItIsRefused(): void
    {
        $this->assertRefusedForPremium();
    }

    private function subscription(string $status, string $endsIn, ?string $environment = null, string $paymentMethod = 'free_key'): void
    {
        $this->pdo->prepare(
            "INSERT INTO premium_subscriptions
             (subscription_id, billing_cycle, amount, currency, start_date, end_date,
              status, payment_method, transaction_id, auto_renew, environment, created_at)
             VALUES (?, 'yearly', 0.00, 'CAD', NOW(), ?, ?, ?, ?, 0, ?, NOW())"
        )->execute([
            $this->subscriptionId,
            date('Y-m-d H:i:s', strtotime($endsIn)),
            $status,
            $paymentMethod,
            $this->subscriptionId,
            $environment ?? current_environment(),
        ]);
    }

    private function otherEnvironment(): string
    {
        return current_environment() === 'production' ? 'sandbox' : 'production';
    }

    private function authenticate(): array
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->secret;
        return api_authenticate();
    }

    private function assertRefusedForPremium(): void
    {
        try {
            $this->authenticate();
            $this->fail('A key with no active Premium subscription was accepted.');
        } catch (\ApiResponseSent $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('premium_required', $e->payload['error']['code']);
        }
    }
}
