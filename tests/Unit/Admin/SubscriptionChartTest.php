<?php
declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;

/** subscription_chart_series(), behind the admin "Premium Subscriptions Over Time" chart. */
final class SubscriptionChartTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once PROJECT_ROOT . '/admin/license/subscription_chart.php';
    }

    private static function sub(string $created, string $status, string $endDate = '2099-01-01 00:00:00', ?string $cancelledAt = null): array
    {
        return ['created_at' => $created, 'status' => $status, 'cancelled_at' => $cancelledAt, 'end_date' => $endDate];
    }

    public function test_no_subscriptions_means_no_points(): void
    {
        $this->assertSame([], subscription_chart_series([], '2026-10-16'));
    }

    public function test_runs_every_day_from_the_first_subscription_to_today_even_without_signups(): void
    {
        $series = subscription_chart_series([
            self::sub('2026-06-16 10:00:00', 'active'),
            self::sub('2026-06-28 09:00:00', 'active'),
        ], '2026-10-16');

        $this->assertSame('2026-06-16', $series[0]['date']);
        $this->assertSame('2026-10-16', end($series)['date']);
        $this->assertCount(123, $series);
        $this->assertSame(['date' => '2026-06-27', 'total' => 1, 'active' => 1], $series[11]);
        $this->assertSame(['date' => '2026-10-16', 'total' => 2, 'active' => 2], end($series));
    }

    public function test_a_cancelled_subscription_stops_counting_as_active_on_the_day_it_was_cancelled(): void
    {
        $series = array_column(subscription_chart_series([
            self::sub('2026-06-01 10:00:00', 'cancelled', '2026-11-01 00:00:00', '2026-06-03 12:00:00'),
        ], '2026-06-05'), null, 'date');

        $this->assertSame(1, $series['2026-06-02']['active']);
        $this->assertSame(0, $series['2026-06-03']['active']);
        $this->assertSame(1, $series['2026-06-05']['total']);
    }

    public function test_an_expired_subscription_stops_counting_as_active_at_its_end_date(): void
    {
        $series = array_column(subscription_chart_series([
            self::sub('2026-06-01 10:00:00', 'expired', '2026-06-04 00:00:00'),
        ], '2026-06-06'), null, 'date');

        $this->assertSame(1, $series['2026-06-03']['active']);
        $this->assertSame(0, $series['2026-06-04']['active']);
    }

    public function test_today_matches_the_stat_cards(): void
    {
        // The stat cards count every subscription as total and only status 'active' as active.
        $rows = [
            self::sub('2026-06-01 10:00:00', 'active'),
            self::sub('2026-06-02 10:00:00', 'payment_failed', '2026-12-01 00:00:00'),
            self::sub('2026-06-03 10:00:00', 'cancelled', '2026-12-01 00:00:00'),
            self::sub('2026-06-04 10:00:00', 'expired', '2026-07-01 00:00:00'),
        ];

        $series = subscription_chart_series($rows, '2026-10-16');

        $this->assertSame(['date' => '2026-10-16', 'total' => 4, 'active' => 1], end($series));
    }

    public function test_a_subscription_created_today_is_a_single_point(): void
    {
        $this->assertSame(
            [['date' => '2026-10-16', 'total' => 1, 'active' => 1]],
            subscription_chart_series([self::sub('2026-10-16 08:00:00', 'active')], '2026-10-16')
        );
    }
}
