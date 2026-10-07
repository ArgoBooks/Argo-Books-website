<?php
declare(strict_types=1);

namespace Tests\Unit\PublicApi;

use ApiResponseSent;
use PHPUnit\Framework\TestCase;

/**
 * The parts of the API that are hardest to change once developers depend on
 * them: id format, the error envelope, pagination bounds, and the promise that
 * every emitted error code is documented.
 */
final class ContractTest extends TestCase
{
    private array $getBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    private function expectRejection(callable $fn): array
    {
        try {
            $fn();
        } catch (ApiResponseSent $e) {
            return $e->payload['error'] ?? [];
        }
        $this->fail('Expected rejection, got acceptance.');
    }

    // -- ids -----------------------------------------------------------------

    public function testGeneratedIdsAreOpaqueAndPrefixed(): void
    {
        $id = api_generate_id('cus');

        $this->assertMatchesRegularExpression('/^cus_[0-9a-f]{24}$/', $id);
        $this->assertTrue(api_id_has_prefix($id, 'cus'));
    }

    public function testIdsAreNotSequential(): void
    {
        // Opaque means opaque: nothing about the account or row number should be
        // recoverable, so two ids made back to back must not be adjacent.
        $this->assertNotSame(api_generate_id('cus'), api_generate_id('cus'));
    }

    public function testPrefixMatchingRejectsOtherResources(): void
    {
        $this->assertFalse(api_id_has_prefix(api_generate_id('cat'), 'cus'));
    }

    /**
     * `re` is a prefix of nothing else, but `rev` starts with `re`. Without an
     * anchored full-length match, a revenue id would pass as a refund id.
     */
    public function testRefundPrefixDoesNotSwallowRevenueIds(): void
    {
        $this->assertFalse(api_id_has_prefix(api_generate_id('rev'), 're'));
        $this->assertFalse(api_id_has_prefix(api_generate_id('re'), 'rev'));
    }

    public function testEveryResourceHasAUniquePrefix(): void
    {
        $prefixes = array_column(api_resource_definitions(), 'prefix');

        $this->assertSame(count($prefixes), count(array_unique($prefixes)));
    }

    public function testSecretKeysArePrefixedForLeakScanners(): void
    {
        $secret = api_generate_secret_key();

        $this->assertMatchesRegularExpression('/^ab_[0-9a-f]{48}$/', $secret);
        $this->assertSame('ab_' . substr($secret, 3, 4) . '...' . substr($secret, -4), api_key_hint($secret));
    }

    public function testKeyHintNeverContainsTheWholeSecret(): void
    {
        $secret = api_generate_secret_key();

        $this->assertStringNotContainsString(substr($secret, 3, 20), api_key_hint($secret));
    }

    // -- error envelope ------------------------------------------------------

    public function testErrorEnvelopeCarriesEveryDocumentedField(): void
    {
        $error = $this->expectRejection(
            fn () => api_error(400, 'invalid_request_error', 'parameter_missing', 'Missing.', 'name')
        );

        $this->assertSame('invalid_request_error', $error['type']);
        $this->assertSame('parameter_missing', $error['code']);
        $this->assertSame('Missing.', $error['message']);
        $this->assertSame('name', $error['param']);
        $this->assertStringContainsString('#parameter_missing', $error['doc_url']);
        $this->assertMatchesRegularExpression('/^req_[0-9a-f]{24}$/', $error['request_id']);
    }

    public function testParamIsOmittedWhenNoSingleFieldIsToBlame(): void
    {
        $error = $this->expectRejection(
            fn () => api_error(500, 'api_error', 'internal_error', 'Boom.')
        );

        $this->assertArrayNotHasKey('param', $error);
    }

    public function testRequestIdIsStableWithinOneRequest(): void
    {
        $this->assertSame(api_request_id(), api_request_id());
    }

    /**
     * Every doc_url must resolve to a real anchor on the errors page, since that
     * link is what a developer clicks the moment something breaks.
     */
    public function testEveryEmittedErrorCodeIsDocumented(): void
    {
        $emitted = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(PROJECT_ROOT . '/api/v1')
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (preg_match_all(
                "/api_error\s*\(\s*\d+\s*,\s*'[a-z_]+'\s*,\s*'([a-z_]+)'/s",
                (string) file_get_contents($file->getPathname()),
                $m
            )) {
                $emitted = array_merge($emitted, $m[1]);
            }
        }
        $emitted = array_unique($emitted);
        $this->assertNotEmpty($emitted, 'Found no error codes to check, so the scan is broken.');

        // The public docs ship separately from the API itself, so that the API can run privately before anything is visible on the site.
        $docsPath = PROJECT_ROOT . '/documentation/pages/api/errors.php';
        if (!is_file($docsPath)) {
            $this->markTestSkipped('The public errors page has not shipped yet, so there is nothing to check codes against.');
        }

        $docs = (string) file_get_contents($docsPath);
        foreach ($emitted as $code) {
            $this->assertStringContainsString(
                "'$code' => ['status'",
                $docs,
                "Error code '$code' is emitted but has no row on the errors documentation page."
            );
        }
    }

    // -- pagination ----------------------------------------------------------

    public function testDefaultLimitIsTen(): void
    {
        $this->assertSame(10, api_pagination_params()['limit']);
    }

    public function testLimitAboveTheMaximumIsRejected(): void
    {
        $_GET['limit'] = '500';

        $this->assertSame('parameter_out_of_range', $this->expectRejection('api_pagination_params')['code']);
    }

    public function testZeroLimitIsRejected(): void
    {
        $_GET['limit'] = '0';

        $this->assertSame('parameter_out_of_range', $this->expectRejection('api_pagination_params')['code']);
    }

    public function testNonNumericLimitIsRejected(): void
    {
        $_GET['limit'] = 'ten';

        $this->assertSame('parameter_invalid_type', $this->expectRejection('api_pagination_params')['code']);
    }

    public function testBothCursorsAtOnceIsRejected(): void
    {
        $_GET['starting_after'] = 'cus_1';
        $_GET['ending_before'] = 'cus_2';

        $this->assertSame('parameter_conflict', $this->expectRejection('api_pagination_params')['code']);
    }

    public function testMaximumLimitIsAccepted(): void
    {
        $_GET['limit'] = '100';

        $this->assertSame(100, api_pagination_params()['limit']);
    }

    // -- versioning ----------------------------------------------------------

    public function testAbsentVersionHeaderIsAccepted(): void
    {
        unset($_SERVER['HTTP_ARGO_VERSION']);
        api_enforce_version();

        $this->assertTrue(true); // reaching here without throwing is the assertion
    }

    public function testUnknownVersionIsRejectedRatherThanSilentlyDowngraded(): void
    {
        $_SERVER['HTTP_ARGO_VERSION'] = '2020-01-01';
        try {
            $error = $this->expectRejection('api_enforce_version');
            $this->assertSame('unknown_api_version', $error['code']);
        } finally {
            unset($_SERVER['HTTP_ARGO_VERSION']);
        }
    }

    // -- serialization -------------------------------------------------------

    public function testTimestampsBecomeIntegersOrNull(): void
    {
        $this->assertIsInt(api_timestamp('2026-08-14 10:00:00'));
        $this->assertNull(api_timestamp(null));
        $this->assertNull(api_timestamp(''));
        $this->assertNull(api_timestamp('0000-00-00 00:00:00'));
    }

    public function testAbsentMetadataSerializesAsAnObjectNotAnArray(): void
    {
        // json_encode turns an empty PHP array into [], which would make
        // `metadata` change JSON type depending on whether it had keys.
        $this->assertSame('{}', json_encode(api_decode_metadata(null)));
        $this->assertSame('{}', json_encode(api_decode_metadata('')));
    }

    public function testListEnvelopeShape(): void
    {
        $list = api_list_response([['id' => 'cus_1']], true, '/v1/customers');

        $this->assertSame('list', $list['object']);
        $this->assertSame('/v1/customers', $list['url']);
        $this->assertTrue($list['has_more']);
        $this->assertCount(1, $list['data']);
    }

    // -- definitions ---------------------------------------------------------

    public function testEveryResourceDeclaresWhatTheEngineNeeds(): void
    {
        foreach (api_resource_definitions() as $segment => $spec) {
            foreach (['object', 'table', 'prefix', 'fields'] as $key) {
                $this->assertArrayHasKey($key, $spec, "$segment is missing '$key'");
            }
            $this->assertNotEmpty($spec['fields'], "$segment has no fields");
        }
    }

    public function testEveryFieldTypeHasAValidator(): void
    {
        $known = ['string', 'text', 'email', 'amount', 'currency', 'country', 'date', 'decimal', 'enum', 'ref', 'metadata'];

        foreach (api_resource_definitions() as $segment => $spec) {
            foreach ($spec['fields'] as $name => $field) {
                $this->assertContains($field['type'], $known, "$segment.$name has unknown type '{$field['type']}'");
            }
        }
    }

    public function testEveryReferenceFieldNamesARealTable(): void
    {
        $tables = array_column(api_resource_definitions(), 'table');

        foreach (api_resource_definitions() as $segment => $spec) {
            foreach ($spec['fields'] as $name => $field) {
                if ($field['type'] !== 'ref') {
                    continue;
                }
                $this->assertContains($field['table'], $tables, "$segment.$name points at an unknown table");
            }
        }
    }
}
