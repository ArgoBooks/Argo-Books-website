<?php
declare(strict_types=1);

namespace Tests\Unit\PublicApi;

use ApiResponseSent;
use PHPUnit\Framework\TestCase;

/**
 * Field validation for /v1.
 *
 * These are the rules a developer hits first and complains about loudest, so
 * each test asserts the error CODE and the PARAM as well as the rejection.
 * A 400 that does not say which field is wrong is barely better than a 500.
 */
final class ValidationTest extends TestCase
{
    /** Run a validator and return the error payload it produced. */
    private function expectRejection(callable $fn): array
    {
        try {
            $fn();
        } catch (ApiResponseSent $e) {
            return $e->payload['error'] ?? [];
        }
        $this->fail('Expected the request to be rejected, but it was accepted.');
    }

    private function customerSpec(): array
    {
        return api_resource_definitions()['customers'];
    }

    private function revenueSpec(): array
    {
        return api_resource_definitions()['revenue'];
    }

    // -- money ---------------------------------------------------------------

    public function testIntegerAmountIsAccepted(): void
    {
        $out = api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 11300,
            'currency'    => 'usd',
            'occurred_on' => '2026-08-14',
        ], true, 0);

        $this->assertSame(11300, $out['amount']);
    }

    public function testIntegralStringAmountIsAccepted(): void
    {
        $out = api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => '11300',
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14',
        ], true, 0);

        $this->assertSame(11300, $out['amount']);
    }

    /**
     * The single most important validation rule here. Rounding somebody's
     * accounting data because their client sent a float is not a kindness.
     */
    public function testDecimalAmountIsRejectedNotRounded(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 19.99,
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14',
        ], true, 0));

        $this->assertSame('parameter_invalid_amount', $error['code']);
        $this->assertSame('amount', $error['param']);
    }

    public function testNegativeAmountIsAllowedAtFieldLevel(): void
    {
        // Field-level validation permits it; resource invariants decide whether a negative makes sense for that object.
        $out = api_validate_input($this->revenueSpec(), [
            'description' => 'Correction',
            'amount'      => -500,
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14',
        ], true, 0);

        $this->assertSame(-500, $out['amount']);
    }

    // -- required and unknown fields -----------------------------------------

    public function testMissingRequiredFieldNamesTheField(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), [], true, 0
        ));

        $this->assertSame('parameter_missing', $error['code']);
        $this->assertSame('name', $error['param']);
    }

    public function testUnknownParameterIsRejectedWithItsName(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), ['name' => 'Acme', 'nmae' => 'typo'], true, 0
        ));

        $this->assertSame('unknown_parameter', $error['code']);
        $this->assertSame('nmae', $error['param']);
    }

    public function testRequiredFieldCannotBeClearedOnUpdate(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), ['name' => null], false, 0
        ));

        $this->assertSame('parameter_invalid_empty', $error['code']);
    }

    public function testOptionalFieldCanBeClearedOnUpdate(): void
    {
        $out = api_validate_input($this->customerSpec(), ['phone' => null], false, 0);

        $this->assertArrayHasKey('phone', $out);
        $this->assertNull($out['phone']);
    }

    public function testUpdateDoesNotRequireFieldsItWasNotGiven(): void
    {
        $out = api_validate_input($this->customerSpec(), ['phone' => '555'], false, 0);

        $this->assertSame(['phone' => '555'], $out);
    }

    public function testDefaultsAreAppliedOnCreateOnly(): void
    {
        $created = api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14',
        ], true, 0);
        $this->assertSame(0, $created['tax_amount']);

        $updated = api_validate_input($this->revenueSpec(), ['description' => 'Order'], false, 0);
        $this->assertArrayNotHasKey('tax_amount', $updated);
    }

    // -- formats -------------------------------------------------------------

    public function testCurrencyIsUppercased(): void
    {
        $out = api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'eur',
            'occurred_on' => '2026-08-14',
        ], true, 0);

        $this->assertSame('EUR', $out['currency']);
    }

    public function testFourLetterCurrencyIsRejected(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'USDD',
            'occurred_on' => '2026-08-14',
        ], true, 0));

        $this->assertSame('parameter_invalid_currency', $error['code']);
    }

    public function testThreeLetterCountryIsRejected(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), ['name' => 'Acme', 'country' => 'CAN'], true, 0
        ));

        $this->assertSame('parameter_invalid_country', $error['code']);
    }

    public function testCalendarImpossibleDateIsRejected(): void
    {
        // A regex would accept this. createFromFormat plus a round-trip compare
        // is what makes 30 February fail rather than silently becoming 2 March.
        $error = $this->expectRejection(fn () => api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'USD',
            'occurred_on' => '2026-02-30',
        ], true, 0));

        $this->assertSame('parameter_invalid_date', $error['code']);
    }

    public function testTimestampIsRejectedAsADate(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14T10:00:00Z',
        ], true, 0));

        $this->assertSame('parameter_invalid_date', $error['code']);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), ['name' => 'Acme', 'email' => 'not-an-email'], true, 0
        ));

        $this->assertSame('parameter_invalid_email', $error['code']);
    }

    public function testOverlongStringIsRejected(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input(
            $this->customerSpec(), ['name' => str_repeat('a', 256)], true, 0
        ));

        $this->assertSame('parameter_too_long', $error['code']);
        $this->assertSame('name', $error['param']);
    }

    // -- metadata ------------------------------------------------------------

    public function testMetadataRoundTripsAsStrings(): void
    {
        $out = api_validate_input($this->customerSpec(), [
            'name'     => 'Acme',
            'metadata' => ['source' => 'shopify', 'order' => 1042],
        ], true, 0);

        $this->assertSame(
            ['source' => 'shopify', 'order' => '1042'],
            json_decode($out['metadata'], true)
        );
    }

    public function testNestedMetadataIsRejectedRatherThanFlattened(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input($this->customerSpec(), [
            'name'     => 'Acme',
            'metadata' => ['nested' => ['a' => 'b']],
        ], true, 0));

        $this->assertSame('parameter_invalid_metadata', $error['code']);
    }

    public function testTooManyMetadataKeysIsRejected(): void
    {
        $error = $this->expectRejection(fn () => api_validate_input($this->customerSpec(), [
            'name'     => 'Acme',
            'metadata' => array_combine(
                array_map(static fn ($i) => "k$i", range(1, 51)),
                array_fill(0, 51, 'v')
            ),
        ], true, 0));

        $this->assertSame('metadata_too_large', $error['code']);
    }

    public function testEmptyMetadataBecomesNullNotAnEmptyObject(): void
    {
        $out = api_validate_input($this->customerSpec(), ['name' => 'Acme', 'metadata' => []], true, 0);

        $this->assertNull($out['metadata']);
    }

    // -- references ----------------------------------------------------------

    public function testWrongPrefixOnAReferenceIsRejectedBeforeAnyLookup(): void
    {
        // Catching this on shape alone means a cross-resource id gets a message
        // naming the expected type, instead of a confusing "no such object".
        $error = $this->expectRejection(fn () => api_validate_input($this->revenueSpec(), [
            'description' => 'Order',
            'amount'      => 100,
            'currency'    => 'USD',
            'occurred_on' => '2026-08-14',
            'customer'    => 'cat_000000000000000000000000',
        ], true, 0));

        $this->assertSame('parameter_invalid_reference', $error['code']);
        $this->assertSame('customer', $error['param']);
    }
}
