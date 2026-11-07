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

    private static function future(): string
    {
        return date('Y-m-d H:i:s', strtotime('+10 days'));
    }

    private static function past(): string
    {
        // Days rather than hours, because local PHP runs in UTC while MySQL's NOW() is local time.
        return date('Y-m-d H:i:s', strtotime('-2 days'));
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
    public function test_the_php_rule_and_the_sql_rule_agree(string $status, bool $endsInFuture, bool $expected): void
    {
        $id = 'PREM-ENTL-' . strtoupper(bin2hex(random_bytes(4)));
        $endDate = $endsInFuture ? self::future() : self::past();
        $this->seedSubscription($id, $endDate, $status);

        $this->assertSame($expected, premium_subscription_entitled(['status' => $status, 'end_date' => $endDate]));
        $this->assertSame($expected, $this->sqlSaysEntitled($id));
    }

    public function test_a_failed_payment_still_inside_its_term_gets_premium_features_as_well_as_the_launch_check(): void
    {
        $id = 'PREM-ENTL-FAIL-AAAA-0001';
        $this->seedSubscription($id, self::future(), 'payment_failed');
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

    public function test_a_past_due_subscription_gets_neither(): void
    {
        $id = 'PREM-ENTL-PAST-AAAA-0002';
        $this->seedSubscription($id, self::future(), 'past_due');
        $key = $this->seedRedeemedKey(self::DEVICE, $id);

        $this->assertSame('expired', validate_license($key, self::DEVICE)['status']);
        $this->assertNull(receipt_scan_quota_identity($this->pdo, $key, null));
    }
}
