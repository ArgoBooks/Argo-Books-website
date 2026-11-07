<?php
declare(strict_types=1);

namespace Tests\Integration\License;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/api/receipt/scan_quota.php';

/** The one Premium rule, which the app's launch check and the server-backed Premium features must agree on. */
final class PremiumEntitlementTest extends DatabaseTestCase
{
    private const DEVICE = 'device-hash-entitlement-001';

    /** A time on the database's clock, which is the one every check compares end_date against. */
    private function databaseTime(int $hoursFromNow): string
    {
        return (string) $this->pdo->query("SELECT NOW() + INTERVAL $hoursFromNow HOUR")->fetchColumn();
    }

    private function future(): string
    {
        return $this->databaseTime(1);
    }

    private function past(): string
    {
        return $this->databaseTime(-1);
    }

    private function rowOf(string $subscriptionId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, ' . premium_entitlement_columns() . ' FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subscriptionId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function sqlSaysEntitled(string $subscriptionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM premium_subscriptions WHERE subscription_id = ? AND ' . premium_entitled_sql());
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchColumn() !== false;
    }

    public static function cases(): array
    {
        return [
            'active'                    => ['active', true, true],
            'cancelled runs out its term' => ['cancelled', true, true],
            'failed payment keeps it'   => ['payment_failed', true, true],
            'expired'                   => ['expired', true, false],
            'past due'                  => ['past_due', true, false],
            'active but ended'          => ['active', false, false],
            'cancelled and ended'       => ['cancelled', false, false],
        ];
    }

    #[DataProvider('cases')]
    public function test_the_selected_rule_and_the_filter_rule_agree(string $status, bool $endsInFuture, bool $expected): void
    {
        $id = 'PREM-ENTL-' . strtoupper(bin2hex(random_bytes(4)));
        $this->seedSubscription($id, $endsInFuture ? $this->future() : $this->past(), $status);

        $this->assertSame($expected, premium_subscription_entitled($this->rowOf($id)));
        $this->assertSame($expected, $this->sqlSaysEntitled($id));
    }

    public function test_a_row_read_without_the_entitlement_columns_is_refused_rather_than_judged_by_php_time(): void
    {
        $this->expectException(\LogicException::class);
        premium_subscription_entitled(['status' => 'active', 'end_date' => '2099-01-01 00:00:00']);
    }

    public function test_a_failed_payment_still_inside_its_term_gets_premium_features_as_well_as_the_launch_check(): void
    {
        $id = 'PREM-ENTL-FAIL-AAAA-0001';
        $this->seedSubscription($id, $this->future(), 'payment_failed');
        $key = $this->seedRedeemedKey(self::DEVICE, $id);

        $this->assertSame('valid', validate_license($key, self::DEVICE)['status']);
        $this->assertSame('premium', receipt_scan_quota_identity($this->pdo, $key, null)['tier'] ?? null);

        $_SERVER['HTTP_X_LICENSE_KEY'] = $key;
        try {
            $this->assertNotNull(authenticate_license_request());
        } finally {
            unset($_SERVER['HTTP_X_LICENSE_KEY']);
        }
    }

    private function autoRenewing(string $subscriptionId, string $endDate, string $status = 'active'): string
    {
        $this->seedSubscription($subscriptionId, $endDate, $status);
        $this->pdo->prepare('UPDATE premium_subscriptions SET auto_renew = 1 WHERE subscription_id = ?')->execute([$subscriptionId]);
        return $this->seedRedeemedKey(self::DEVICE, $subscriptionId);
    }

    private function statusOf(string $subscriptionId): string
    {
        $stmt = $this->pdo->prepare('SELECT status FROM premium_subscriptions WHERE subscription_id = ?');
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchColumn();
    }

    public function test_a_renewal_being_retried_keeps_premium_and_is_not_expired(): void
    {
        // The renewal cron only retries active rows, so the row stays active through the last hour of its grace.
        $id = 'PREM-ENTL-RTRY-AAAA-0003';
        $key = $this->autoRenewing($id, $this->databaseTime(-71));

        $this->assertSame('valid', validate_license($key, self::DEVICE)['status']);
        $this->assertSame('premium', receipt_scan_quota_identity($this->pdo, $key, null)['tier'] ?? null);
        $this->assertTrue($this->sqlSaysEntitled($id));
        $this->assertSame('active', $this->statusOf($id));
    }

    public function test_a_renewal_past_its_grace_loses_premium_and_is_marked_expired(): void
    {
        // A row the cron never handles, such as one with no stored card, would otherwise stay active for good.
        $id = 'PREM-ENTL-LATE-AAAA-0004';
        $key = $this->autoRenewing($id, $this->databaseTime(-73));

        $this->assertSame('expired', validate_license($key, self::DEVICE)['status']);
        $this->assertFalse($this->sqlSaysEntitled($id));
        $this->assertSame('expired', $this->statusOf($id));
    }

    public function test_a_failed_payment_past_its_term_loses_premium_but_can_still_be_retried(): void
    {
        $id = 'PREM-ENTL-GONE-AAAA-0005';
        $key = $this->autoRenewing($id, $this->past(), 'payment_failed');

        $this->assertSame('expired', validate_license($key, self::DEVICE)['status']);
        $this->assertFalse($this->sqlSaysEntitled($id));
        $this->assertSame('payment_failed', $this->statusOf($id));
    }

    public function test_a_subscription_that_does_not_renew_is_marked_expired_once_it_ends(): void
    {
        $id = 'PREM-ENTL-DONE-AAAA-0006';
        $this->seedSubscription($id, $this->past());
        $key = $this->seedRedeemedKey(self::DEVICE, $id);

        $this->assertSame('expired', validate_license($key, self::DEVICE)['status']);
        $this->assertSame('expired', $this->statusOf($id));
    }

    public function test_a_past_due_subscription_gets_neither(): void
    {
        $id = 'PREM-ENTL-PAST-AAAA-0002';
        $this->seedSubscription($id, $this->future(), 'past_due');
        $key = $this->seedRedeemedKey(self::DEVICE, $id);

        $this->assertSame('expired', validate_license($key, self::DEVICE)['status']);
        $this->assertNull(receipt_scan_quota_identity($this->pdo, $key, null));
    }
}
