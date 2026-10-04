<?php
declare(strict_types=1);

namespace Tests\Integration\PublicApi;

/**
 * The idempotency claim and import-batch atomicity.
 *
 * Both are the kind of thing that looks right and is not, and both fail in ways
 * that show up as duplicated money or as books and queue disagreeing about what
 * was taken. Neither had any automated cover before.
 */
final class IdempotencyAndBatchTest extends ApiIntegrationTestCase
{
    private function customerBody(string $name = 'Idem Co'): string
    {
        return json_encode(['name' => $name]);
    }

    private function runCreate(string $key, string $body): array
    {
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;

        return $this->capture(function () use ($body) {
            api_with_idempotency($this->accountId, $body, function () use ($body) {
                // Stand-in for a real create: insert a row, return it.
                $publicId = $this->seedObject('api_customers', $this->accountId, [
                    'public_id' => api_generate_id('cus'),
                    'name'      => json_decode($body, true)['name'] ?? 'x',
                ]);
                api_json(201, ['id' => $publicId]);
            });
        });
    }

    // -- idempotency ---------------------------------------------------------

    public function testCreateWithoutAKeyIsRefused(): void
    {
        [$status, $payload] = $this->capture(function () {
            api_with_idempotency($this->accountId, '{}', fn () => api_json(201, []));
        });

        $this->assertSame(400, $status);
        $this->assertSame('idempotency_key_required', $payload['error']['code']);
    }

    public function testReadsMayRunWithoutAKey(): void
    {
        [$status] = $this->capture(function () {
            api_with_idempotency($this->accountId, '', fn () => api_json(200, ['ok' => true]), false);
        });

        $this->assertSame(200, $status);
    }

    /**
     * The point of the whole mechanism: a retry must not create a second row.
     */
    public function testReplayReturnsTheFirstResponseAndCreatesNothingNew(): void
    {
        $body = $this->customerBody();

        [, $first] = $this->runCreate('replay-1', $body);
        $firstId = $first['id'];

        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'replay-1';
        [$status, $payload] = $this->capture(function () use ($body) {
            api_with_idempotency($this->accountId, $body, fn () => api_json(201, ['id' => 'SHOULD NOT RUN']));
        });

        $this->assertSame(201, $status, 'the replay should reproduce the original status');
        $this->assertSame($firstId, $payload['id'], 'the replay ran the handler again instead of replaying');

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM api_customers WHERE account_id = ?');
        $count->execute([$this->accountId]);
        $this->assertSame(1, (int) $count->fetchColumn(), 'the retry created a second row');
    }

    /**
     * A key stays in the table past its 24 hours until the cleanup reaches it. Used
     * again in that gap it has to run as a new request. It used to find its own
     * expired row, restart, and find it again without end.
     */
    public function testAKeyPastItsDayRunsAsANewRequest(): void
    {
        $body = $this->customerBody();
        $this->runCreate('expired-1', $body);

        $this->pdo->prepare(
            'UPDATE api_idempotency_cache SET created_at = DATE_SUB(NOW(), INTERVAL 25 HOUR)
              WHERE account_id = ? AND idempotency_key = ?'
        )->execute([$this->accountId, 'expired-1']);

        [$status] = $this->runCreate('expired-1', $body);

        $this->assertSame(201, $status);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM api_customers WHERE account_id = ?');
        $count->execute([$this->accountId]);
        $this->assertSame(2, (int) $count->fetchColumn());
    }

    public function testSameKeyWithADifferentBodyIsRefused(): void
    {
        $this->runCreate('conflict-1', $this->customerBody('First'));

        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'conflict-1';
        [$status, $payload] = $this->capture(function () {
            api_with_idempotency($this->accountId, $this->customerBody('Second'), fn () => api_json(201, []));
        });

        $this->assertSame(409, $status);
        $this->assertSame('idempotency_key_reused', $payload['error']['code']);
    }

    /** A claim left mid-flight by a crashed handler must not block the retry. */
    public function testAClaimIsReleasedWhenTheHandlerThrows(): void
    {
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'boom-1';
        try {
            api_with_idempotency($this->accountId, '{}', function () {
                throw new \RuntimeException('handler exploded');
            });
            $this->fail('the exception should have propagated');
        } catch (\RuntimeException) {
            // expected
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM api_idempotency_cache WHERE account_id = ? AND idempotency_key = ?'
        );
        $stmt->execute([$this->accountId, 'boom-1']);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'the failed claim was not released');
    }

    /** An in-flight claim from another caller reports 409, not a duplicate run. */
    public function testAnInFlightClaimIsReported(): void
    {
        $this->pdo->prepare(
            "INSERT INTO api_idempotency_cache
                 (account_id, idempotency_key, body_hash, response_status, response_body)
             VALUES (?, ?, ?, 0, '')"
        )->execute([$this->accountId, 'inflight-1', hash('sha256', '{}')]);

        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'inflight-1';
        [$status, $payload] = $this->capture(function () {
            api_with_idempotency($this->accountId, '{}', fn () => api_json(201, ['ran' => true]));
        });

        $this->assertSame(409, $status);
        $this->assertSame('idempotency_key_in_flight', $payload['error']['code']);
    }

    // -- batches -------------------------------------------------------------

    private function seedPending(string $table, string $prefix, array $extra = []): string
    {
        return $this->seedObject($table, $this->accountId, array_merge(
            ['public_id' => api_generate_id($prefix)],
            $extra
        ));
    }

    private function statusOf(string $table, string $publicId): string
    {
        $stmt = $this->pdo->prepare("SELECT import_status FROM $table WHERE public_id = ?");
        $stmt->execute([$publicId]);
        return (string) $stmt->fetchColumn();
    }

    public function testABatchClaimsEveryObjectAndRecordsLocalRefs(): void
    {
        $cus = $this->seedPending('api_customers', 'cus', ['name' => 'Batch Co']);
        $exp = $this->seedPending('api_expenses', 'exp', [
            'description' => 'Hosting', 'amount' => 2400, 'currency' => 'USD', 'occurred_on' => '2026-08-01',
        ]);

        $auth = $this->authenticateAs($this->writeKey);
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'batch-1';
        $this->withBody(['objects' => [$cus, $exp], 'local_refs' => [$cus => 'CUS-001']], function () use ($auth) {
            [$status, $payload] = $this->capture(fn () => api_handle_create_batch($auth));
            $this->assertSame(201, $status);
            $this->assertSame('completed', $payload['status']);
        });

        $this->assertSame('imported', $this->statusOf('api_customers', $cus));
        $this->assertSame('imported', $this->statusOf('api_expenses', $exp));

        $stmt = $this->pdo->prepare('SELECT local_ref FROM api_customers WHERE public_id = ?');
        $stmt->execute([$cus]);
        $this->assertSame('CUS-001', $stmt->fetchColumn());
    }

    /**
     * The important one. If any object in a batch cannot be claimed, nothing in
     * that batch may be claimed, or the books and the queue end up disagreeing
     * about what was taken.
     */
    public function testAnUnclaimableObjectRollsTheWholeBatchBack(): void
    {
        $good = $this->seedPending('api_customers', 'cus', ['name' => 'Good']);
        $already = $this->seedPending('api_customers', 'cus', ['name' => 'Already', 'import_status' => 'imported']);

        $auth = $this->authenticateAs($this->writeKey);
        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'batch-rollback-1';

        $this->withBody(['objects' => [$good, $already]], function () use ($auth) {
            [$status, $payload] = $this->capture(fn () => api_handle_create_batch($auth));
            $this->assertSame(409, $status);
            $this->assertSame('object_not_claimable', $payload['error']['code']);
        });

        $this->assertSame('pending', $this->statusOf('api_customers', $good), 'the good object was left claimed');

        $batches = $this->pdo->prepare('SELECT COUNT(*) FROM api_import_batches WHERE account_id = ?');
        $batches->execute([$this->accountId]);
        $this->assertSame(0, (int) $batches->fetchColumn(), 'a batch row survived the rollback');
    }

    public function testRevertReturnsObjectsToPending(): void
    {
        $cus = $this->seedPending('api_customers', 'cus', ['name' => 'Revert Co']);
        $auth = $this->authenticateAs($this->writeKey);

        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'batch-2';
        $batchId = null;
        $this->withBody(['objects' => [$cus]], function () use ($auth, &$batchId) {
            [, $payload] = $this->capture(fn () => api_handle_create_batch($auth));
            $batchId = $payload['id'];
        });
        $this->assertSame('imported', $this->statusOf('api_customers', $cus));

        $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'batch-2-revert';
        $this->withBody([], function () use ($auth, $batchId) {
            [$status, $payload] = $this->capture(fn () => api_handle_revert_batch($auth, $batchId));
            $this->assertSame(200, $status);
            $this->assertSame('reverted', $payload['status']);
        });

        $this->assertSame('pending', $this->statusOf('api_customers', $cus));
    }

    /** Drive a handler with a request body, via the API_TESTING seam. */
    private function withBody(array $body, callable $fn): void
    {
        $raw = json_encode($body);
        $GLOBALS['__api_test_body'] = [$body, $raw];
        try {
            $fn();
        } finally {
            unset($GLOBALS['__api_test_body']);
        }
    }
}
