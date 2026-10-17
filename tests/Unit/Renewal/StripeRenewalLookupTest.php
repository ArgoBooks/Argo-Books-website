<?php
declare(strict_types=1);

namespace Tests\Unit\Renewal;

use PHPUnit\Framework\TestCase;

/** stripe_find_paid_renewal() with Stripe's search replaced, so no request leaves the test. */
final class StripeRenewalLookupTest extends TestCase
{
    public function test_query_matches_one_subscription_and_period_and_only_succeeded_charges(): void
    {
        $this->assertSame(
            "metadata['subscription_id']:'PREM-AAAA-BBBB-CCCC-DDDD' AND metadata['renewal_period']:'2026-11-16 12:00:00' AND status:'succeeded'",
            stripe_renewal_search_query('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00')
        );
    }

    public function test_a_quote_in_a_value_cannot_end_the_string_early(): void
    {
        $query = stripe_renewal_search_query("PREM' OR status:'succeeded", '2026-11-16 12:00:00');

        $this->assertStringContainsString("metadata['subscription_id']:'PREM\\' OR status:\\'succeeded'", $query);
    }

    public function test_returns_the_id_of_the_charge_stripe_finds(): void
    {
        $sent = null;
        $search = function (array $params) use (&$sent) {
            $sent = $params;
            return (object) ['data' => [(object) ['id' => 'pi_123', 'amount_received' => 1574]]];
        };

        $this->assertSame(['id' => 'pi_123', 'amount' => 15.74], stripe_find_paid_renewal('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00', $search));
        $this->assertSame(stripe_renewal_search_query('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00'), $sent['query']);
    }

    public function test_a_charge_refunded_in_full_does_not_count_as_paid(): void
    {
        $search = fn(array $params) => (object) ['data' => [
            (object) ['id' => 'pi_refunded', 'latest_charge' => (object) ['refunded' => true]],
            (object) ['id' => 'pi_kept', 'amount_received' => 1574, 'latest_charge' => (object) ['refunded' => false]],
        ]];

        $this->assertSame('pi_kept', stripe_find_paid_renewal('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00', $search)['id'] ?? null);
    }

    public function test_asks_stripe_for_the_charge_so_a_refund_can_be_seen(): void
    {
        $sent = null;
        $search = function (array $params) use (&$sent) {
            $sent = $params;
            return (object) ['data' => []];
        };

        stripe_find_paid_renewal('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00', $search);

        $this->assertSame(['data.latest_charge'], $sent['expand']);
    }

    public function test_returns_null_when_stripe_finds_nothing(): void
    {
        $search = fn(array $params) => (object) ['data' => []];

        $this->assertNull(stripe_find_paid_renewal('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00', $search));
    }
}
