<?php
declare(strict_types=1);

namespace Tests\Helpers;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $GLOBALS['pdo'];
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    protected function seedPremiumKey(int $durationMonths = 1, ?string $email = null): string
    {
        $key = 'PREM-TEST-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare(
            'INSERT INTO premium_subscription_keys
             (subscription_key, email, duration_months, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$key, $email, $durationMonths]);
        return $key;
    }

    protected function seedRedeemedKey(string $deviceId, string $subscriptionId, int $durationMonths = 12): string
    {
        $key = $this->seedPremiumKey($durationMonths);
        $stmt = $this->pdo->prepare(
            'UPDATE premium_subscription_keys
             SET redeemed_at = NOW(), device_id = ?, subscription_id = ?
             WHERE subscription_key = ?'
        );
        $stmt->execute([$deviceId, $subscriptionId, $key]);
        return $key;
    }

    protected function seedSubscription(
        string $subscriptionId,
        string $endDate,
        string $status = 'active',
        string $billingCycle = 'yearly'
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO premium_subscriptions
             (subscription_id, billing_cycle, amount, currency, start_date, end_date,
              status, payment_method, transaction_id, auto_renew, environment, created_at)
             VALUES (?, ?, 0.00, 'CAD', NOW(), ?, ?, 'free_key', ?, 0, 'sandbox', NOW())"
        );
        $stmt->execute([$subscriptionId, $billingCycle, $endDate, $status, $subscriptionId]);
    }

    /** @return int New portal_companies.id */
    protected function seedPortalCompany(string $companyName = 'Test Co'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO portal_companies (company_name, environment, is_active, created_at)
             VALUES (?, 'sandbox', 1, NOW())"
        );
        $stmt->execute([$companyName]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return int New community_users.id */
    protected function seedCommunityUser(
        ?string $username = null,
        ?string $email = null,
        ?string $deletionScheduledAt = null
    ): int {
        $suffix = bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare(
            "INSERT INTO community_users
             (username, email, password_hash, role, email_verified, deletion_scheduled_at, created_at)
             VALUES (?, ?, 'fake_hash_for_testing', 'user', 1, ?, NOW())"
        );
        $stmt->execute([
            $username ?? "test_user_{$suffix}",
            $email ?? "test_{$suffix}@example.test",
            $deletionScheduledAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Seed a portal_companies row keyed by SHA-256(api_key). Returns ['id' => int, 'api_key' => string]. */
    protected function seedPortalCompanyWithApiKey(string $companyName = 'Test Co'): array
    {
        $apiKey = 'test_api_key_' . bin2hex(random_bytes(8));
        $hash = hash('sha256', $apiKey);
        $stmt = $this->pdo->prepare(
            "INSERT INTO portal_companies
             (api_key_hash, company_name, environment, is_active, created_at)
             VALUES (?, ?, 'sandbox', 1, NOW())"
        );
        $stmt->execute([$hash, $companyName]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'api_key' => $apiKey];
    }

    protected function seedPortalInvoice(
        int $companyId,
        string $invoiceId,
        float $totalAmount,
        ?float $balanceDue = null,
        string $currency = 'USD',
        string $status = 'sent'
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO portal_invoices
             (company_id, invoice_id, invoice_token, customer_token, customer_name,
              status, total_amount, balance_due, currency, environment, created_at)
             VALUES (?, ?, ?, ?, 'Test Customer', ?, ?, ?, ?, 'sandbox', NOW())"
        );
        $stmt->execute([
            $companyId,
            $invoiceId,
            bin2hex(random_bytes(24)),
            bin2hex(random_bytes(24)),
            $status,
            $totalAmount,
            $balanceDue ?? $totalAmount,
            $currency,
        ]);
    }

    /** @return array{id: int, token: string} The new portal_quotes row */
    protected function seedPortalQuote(
        int $companyId,
        string $quoteId,
        float $totalAmount = 100.00,
        string $status = 'sent',
        ?string $validUntil = null,
        int $syncedToArgo = 1
    ): array {
        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            "INSERT INTO portal_quotes
             (company_id, quote_id, quote_token, customer_name, customer_email, quote_data,
              status, total_amount, currency, valid_until, synced_to_argo, environment, created_at)
             VALUES (?, ?, ?, 'Test Customer', 'customer@example.test', '{}', ?, ?, 'USD', ?, ?, 'sandbox', NOW())"
        );
        $stmt->execute([$companyId, $quoteId, $token, $status, $totalAmount, $validUntil, $syncedToArgo]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'token' => $token];
    }
}
