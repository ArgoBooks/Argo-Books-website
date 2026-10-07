<?php
declare(strict_types=1);

namespace Tests\Unit\Renewal;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * calculateNewEndDate() reads `now` internally, so multiple wall-clock reads
 * in a single test (one for input, one for expectation, one inside the helper)
 * could disagree across a midnight boundary. We capture a single immutable
 * base in setUp() and derive everything from it; the helper is still free to
 * read its own `now`, so we accept a small window via assertGreaterThan /
 * assertLessThan rather than exact equality.
 */
final class RenewalHelpersTest extends TestCase
{
    private DateTimeImmutable $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = new DateTimeImmutable('now');
    }

    public function test_yearly_adds_one_year_from_future_end_date(): void
    {
        $futureEnd = $this->base->modify('+30 days')->format('Y-m-d H:i:s');
        $minExpected = $this->base->modify('+1 year +29 days')->format('Y-m-d');
        $maxExpected = $this->base->modify('+1 year +31 days')->format('Y-m-d');

        $newEnd = substr(calculateNewEndDate($futureEnd, 'yearly'), 0, 10);

        $this->assertGreaterThanOrEqual($minExpected, $newEnd);
        $this->assertLessThanOrEqual($maxExpected, $newEnd);
    }

    public function test_monthly_adds_one_month_from_future_end_date(): void
    {
        $futureEnd = $this->base->modify('+10 days')->format('Y-m-d H:i:s');
        $minExpected = $this->base->modify('+1 month +9 days')->format('Y-m-d');
        $maxExpected = $this->base->modify('+1 month +11 days')->format('Y-m-d');

        $newEnd = substr(calculateNewEndDate($futureEnd, 'monthly'), 0, 10);

        $this->assertGreaterThanOrEqual($minExpected, $newEnd);
        $this->assertLessThanOrEqual($maxExpected, $newEnd);
    }

    public function test_extends_from_now_when_end_date_is_in_past(): void
    {
        // An end date 30 days ago: without the stale guard the new one lands about now and could be picked up again.
        $pastEnd = $this->base->modify('-30 days')->format('Y-m-d H:i:s');
        $expectedMin = $this->base->modify('+27 days')->format('Y-m-d');
        $expectedMax = $this->base->modify('+33 days')->format('Y-m-d');

        $newEnd = substr(calculateNewEndDate($pastEnd, 'monthly'), 0, 10);

        $this->assertGreaterThanOrEqual($expectedMin, $newEnd);
        $this->assertLessThanOrEqual($expectedMax, $newEnd);
    }

    public function test_yearly_with_past_end_date_extends_from_now(): void
    {
        $pastEnd = $this->base->modify('-90 days')->format('Y-m-d H:i:s');
        $expectedMin = $this->base->modify('+360 days')->format('Y-m-d');
        $expectedMax = $this->base->modify('+370 days')->format('Y-m-d');

        $newEnd = substr(calculateNewEndDate($pastEnd, 'yearly'), 0, 10);

        $this->assertGreaterThanOrEqual($expectedMin, $newEnd);
        $this->assertLessThanOrEqual($expectedMax, $newEnd);
    }
}
