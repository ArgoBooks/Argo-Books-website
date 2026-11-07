<?php
declare(strict_types=1);

namespace Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;

/** paypal_sale_is_recent(), which decides whether a sale for an unknown subscription is worth PayPal sending again. */
final class PayPalSaleRecencyTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public static function setUpBeforeClass(): void
    {
        require_once PROJECT_ROOT . '/paypal-helper.php';
    }

    private static function saleMadeSecondsAgo(int $seconds): array
    {
        return ['create_time' => gmdate('Y-m-d\TH:i:s\Z', self::NOW - $seconds)];
    }

    public function test_a_sale_from_moments_ago_is_recent(): void
    {
        // The first sale can beat checkout to the server by a few seconds.
        $this->assertTrue(paypal_sale_is_recent(self::saleMadeSecondsAgo(20), self::NOW));
    }

    public function test_a_sale_from_hours_ago_is_not_recent(): void
    {
        $this->assertFalse(paypal_sale_is_recent(self::saleMadeSecondsAgo(2 * 3600), self::NOW));
    }

    public function test_a_sale_without_a_readable_time_counts_as_recent(): void
    {
        $this->assertTrue(paypal_sale_is_recent([], self::NOW));
        $this->assertTrue(paypal_sale_is_recent(['create_time' => 'not a time'], self::NOW));
    }
}
