<?php
declare(strict_types=1);

namespace Tests\Integration\Renewal;

use Tests\Helpers\DatabaseTestCase;

final class IdempotencyGuardTest extends DatabaseTestCase
{
    private const SUB_ID = 'PREM-IDEM-POTE-NCYY-TEST';

    public function test_returns_true_when_completed_renewal_within_23h(): void
    {
        $this->insertRenewalPayment(2, 'completed', 'renewal');
        $this->assertTrue(recently_renewed($this->pdo, self::SUB_ID));
    }

    public function test_returns_false_when_last_renewal_was_24h_ago(): void
    {
        $this->insertRenewalPayment(25, 'completed', 'renewal');
        $this->assertFalse(recently_renewed($this->pdo, self::SUB_ID));
    }

    public function test_returns_false_when_only_failed_renewal_exists(): void
    {
        $this->insertRenewalPayment(1, 'failed', 'renewal');
        $this->assertFalse(recently_renewed($this->pdo, self::SUB_ID));
    }

    public function test_returns_true_for_credit_covered_renewal_within_window(): void
    {
        // The credit-covered branch writes payment_type='credit' and leans on this guard, so an overlapping run cannot extend twice.
        $this->insertRenewalPayment(2, 'completed', 'credit');
        $this->assertTrue(recently_renewed($this->pdo, self::SUB_ID));
    }

    public function test_returns_false_for_initial_payment_within_window(): void
    {
        // payment_type=initial shouldn't satisfy the renewal idempotency guard.
        $this->insertRenewalPayment(1, 'completed', 'initial');
        $this->assertFalse(recently_renewed($this->pdo, self::SUB_ID));
    }

    /**
     * Use MySQL's NOW() - INTERVAL N HOUR for the timestamp so the test is
     * timezone-agnostic (PHP and MySQL clocks differ in default Laragon
     * setups: PHP is UTC, MySQL uses system local time).
     */
    private function insertRenewalPayment(int $hoursAgo, string $status, string $type): void
    {
        $this->pdo->prepare(
            "INSERT INTO premium_subscription_payments
             (subscription_id, amount, currency, payment_method, transaction_id,
              status, payment_type, environment, created_at)
             VALUES (?, 10.00, 'CAD', 'stripe', ?, ?, ?, 'sandbox',
                     DATE_SUB(NOW(), INTERVAL ? HOUR))"
        )->execute([self::SUB_ID, 'TXN_' . bin2hex(random_bytes(4)), $status, $type, $hoursAgo]);
    }
}
