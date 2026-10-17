<?php
declare(strict_types=1);

namespace Tests\Unit\Renewal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * square_charge_saved_card() and square_find_payment_by_reference() with Square's API
 * replaced by a recorder, so each test sees exactly what would have been sent.
 */
final class SquareChargeTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: ?array}> */
    private array $requests = [];

    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = [];
        foreach (['SQUARE_SANDBOX_LOCATION_ID', 'SQUARE_LIVE_LOCATION_ID'] as $name) {
            $this->savedEnv[$name] = $_ENV[$name] ?? null;
        }
        $_ENV['SQUARE_SANDBOX_LOCATION_ID'] = 'LOC_SANDBOX';
        $_ENV['SQUARE_LIVE_LOCATION_ID'] = 'LOC_LIVE';
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
        parent::tearDown();
    }

    /** A Square stand-in that answers each request with the next of $responses. */
    private function square(array ...$responses): callable
    {
        return function (string $method, string $url, ?array $body, string $token) use (&$responses): array {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $body];
            return array_shift($responses) ?? [500, null];
        };
    }

    private function cardFound(string $customerId = 'CUST_1'): array
    {
        return [200, ['card' => ['id' => 'ccof:abc', 'customer_id' => $customerId]]];
    }

    public function test_idempotency_key_fits_squares_45_character_limit(): void
    {
        $key = square_idempotency_key('sqrenew_PREM-AAAA-BBBB-CCCC-DDDD_2026-11-16 12:00:00_2026-11-15');

        $this->assertSame(45, strlen($key));
        $this->assertSame($key, square_idempotency_key('sqrenew_PREM-AAAA-BBBB-CCCC-DDDD_2026-11-16 12:00:00_2026-11-15'));
        $this->assertNotSame($key, square_idempotency_key('sqrenew_PREM-AAAA-BBBB-CCCC-DDDD_2026-11-16 12:00:00_2026-11-16'));
    }

    public function test_renewal_reference_fits_squares_40_character_limit_and_names_one_period(): void
    {
        $ref = square_renewal_reference('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00');

        $this->assertSame(40, strlen($ref));
        $this->assertSame($ref, square_renewal_reference('PREM-AAAA-BBBB-CCCC-DDDD', '2026-11-16 12:00:00'));
        $this->assertNotSame($ref, square_renewal_reference('PREM-AAAA-BBBB-CCCC-DDDD', '2026-12-16 12:00:00'));
        $this->assertNotSame($ref, square_renewal_reference('PREM-EEEE-BBBB-CCCC-DDDD', '2026-11-16 12:00:00'));
    }

    public function test_charges_the_saved_card_with_the_customer_it_belongs_to(): void
    {
        $send = $this->square(
            $this->cardFound('CUST_42'),
            [200, ['payment' => ['id' => 'PAY_1', 'status' => 'COMPLETED']]]
        );

        $result = square_charge_saved_card('ccof:abc', 10.62, square_idempotency_key('seed'), 'Renewal', 'tok', 'sandbox', 'REF_1', $send);

        $this->assertSame(['success' => true, 'transaction_id' => 'PAY_1'], $result);
        $this->assertCount(2, $this->requests);
        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://connect.squareupsandbox.com/v2/cards/ccof:abc', $this->requests[0]['url']);

        $payment = $this->requests[1]['body'];
        $this->assertSame('https://connect.squareupsandbox.com/v2/payments', $this->requests[1]['url']);
        $this->assertSame('CUST_42', $payment['customer_id']);
        $this->assertSame('ccof:abc', $payment['source_id']);
        $this->assertSame(['amount' => 1062, 'currency' => 'CAD'], $payment['amount_money']);
        $this->assertSame('LOC_SANDBOX', $payment['location_id']);
        $this->assertSame('REF_1', $payment['reference_id']);
        $this->assertTrue($payment['autocomplete']);
        $this->assertLessThanOrEqual(45, strlen($payment['idempotency_key']));
    }

    public function test_amount_is_rounded_to_the_nearest_cent_rather_than_cut_down(): void
    {
        // 9.99 * 100 is 998.999... in floating point, which a plain cast turns into 998.
        $send = $this->square($this->cardFound(), [200, ['payment' => ['id' => 'PAY_1', 'status' => 'COMPLETED']]]);

        square_charge_saved_card('ccof:abc', 9.99, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertSame(999, $this->requests[1]['body']['amount_money']['amount']);
        $this->assertArrayNotHasKey('reference_id', $this->requests[1]['body']);
    }

    public function test_production_uses_the_live_api_and_location(): void
    {
        $send = $this->square($this->cardFound(), [200, ['payment' => ['id' => 'PAY_1', 'status' => 'COMPLETED']]]);

        square_charge_saved_card('ccof:abc', 5.00, 'key', 'n', 'tok', 'production', null, $send);

        $this->assertStringStartsWith('https://connect.squareup.com/v2/', $this->requests[0]['url']);
        $this->assertStringStartsWith('https://connect.squareup.com/v2/', $this->requests[1]['url']);
        $this->assertSame('LOC_LIVE', $this->requests[1]['body']['location_id']);
    }

    public function test_a_key_longer_than_square_allows_is_refused_before_anything_is_sent(): void
    {
        $send = $this->square();

        $result = square_charge_saved_card('ccof:abc', 5.00, hash('sha256', 'x'), 'n', 'tok', 'sandbox', null, $send);

        $this->assertFalse($result['success']);
        $this->assertSame([], $this->requests);
    }

    public function test_a_card_id_that_could_change_the_url_is_refused_before_anything_is_sent(): void
    {
        $send = $this->square();

        $result = square_charge_saved_card('../payments?x=1', 5.00, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertSame(['success' => false, 'error' => 'Invalid card ID format'], $result);
        $this->assertSame([], $this->requests);
    }

    public function test_no_payment_is_attempted_when_the_card_cannot_be_read(): void
    {
        $send = $this->square([404, ['errors' => [['detail' => 'Card not found']]]]);

        $result = square_charge_saved_card('ccof:gone', 5.00, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertSame(['success' => false, 'error' => 'Card not found'], $result);
        $this->assertCount(1, $this->requests);
    }

    public function test_no_payment_is_attempted_when_the_card_has_no_customer(): void
    {
        $send = $this->square([200, ['card' => ['id' => 'ccof:abc']]]);

        $result = square_charge_saved_card('ccof:abc', 5.00, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $this->requests);
    }

    public function test_a_decline_reports_squares_reason(): void
    {
        $send = $this->square($this->cardFound(), [402, ['errors' => [['code' => 'CARD_DECLINED', 'detail' => 'Authorization error: CARD_DECLINED']]]]);

        $result = square_charge_saved_card('ccof:abc', 5.00, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertSame(['success' => false, 'error' => 'Authorization error: CARD_DECLINED'], $result);
    }

    public function test_a_payment_that_did_not_complete_is_not_a_success(): void
    {
        $send = $this->square($this->cardFound(), [200, ['payment' => ['id' => 'PAY_1', 'status' => 'FAILED']]]);

        $result = square_charge_saved_card('ccof:abc', 5.00, 'key', 'n', 'tok', 'sandbox', null, $send);

        $this->assertSame(['success' => false, 'error' => 'Payment not completed: FAILED'], $result);
    }

    public function test_lookup_finds_the_completed_payment_with_the_reference_on_a_later_page(): void
    {
        $send = $this->square(
            [200, ['payments' => [['id' => 'P1', 'reference_id' => 'OTHER', 'status' => 'COMPLETED']], 'cursor' => 'next']],
            [200, ['payments' => [
                ['id' => 'P2', 'reference_id' => 'REF_1', 'status' => 'FAILED'],
                ['id' => 'P3', 'reference_id' => 'REF_1', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 1574]],
            ]]]
        );

        $found = square_find_payment_by_reference('REF_1', new DateTimeImmutable('2026-11-13 12:00:00 UTC'), 'tok', 'sandbox', $send);

        $this->assertSame(['id' => 'P3', 'amount' => 15.74], $found);
        $this->assertCount(2, $this->requests);
        parse_str((string) parse_url($this->requests[0]['url'], PHP_URL_QUERY), $first);
        $this->assertSame('2026-11-13T12:00:00Z', $first['begin_time']);
        $this->assertSame('LOC_SANDBOX', $first['location_id']);
        parse_str((string) parse_url($this->requests[1]['url'], PHP_URL_QUERY), $second);
        $this->assertSame('next', $second['cursor']);
    }

    public function test_lookup_skips_a_payment_refunded_in_full_but_not_one_refunded_in_part(): void
    {
        $send = $this->square([200, ['payments' => [
            ['id' => 'P1', 'reference_id' => 'REF_1', 'status' => 'COMPLETED',
             'amount_money' => ['amount' => 1062], 'refunded_money' => ['amount' => 1062]],
            ['id' => 'P2', 'reference_id' => 'REF_1', 'status' => 'COMPLETED',
             'amount_money' => ['amount' => 1062], 'refunded_money' => ['amount' => 100]],
        ]]]);

        $this->assertSame('P2', square_find_payment_by_reference('REF_1', new DateTimeImmutable('-3 days'), 'tok', 'sandbox', $send)['id'] ?? null);
    }

    public function test_lookup_returns_null_when_no_payment_carries_the_reference(): void
    {
        $send = $this->square([200, ['payments' => [['id' => 'P1', 'reference_id' => 'OTHER', 'status' => 'COMPLETED']]]]);

        $this->assertNull(square_find_payment_by_reference('REF_1', new DateTimeImmutable('-3 days'), 'tok', 'sandbox', $send));
    }

    public function test_lookup_returns_null_for_a_location_with_no_payments(): void
    {
        $send = $this->square([200, []]);

        $this->assertNull(square_find_payment_by_reference('REF_1', new DateTimeImmutable('-3 days'), 'tok', 'sandbox', $send));
    }

    public function test_lookup_throws_when_square_cannot_be_asked(): void
    {
        $send = $this->square([401, ['errors' => [['detail' => 'Unauthorized']]]]);

        $this->expectException(RuntimeException::class);
        square_find_payment_by_reference('REF_1', new DateTimeImmutable('-3 days'), 'tok', 'sandbox', $send);
    }
}
