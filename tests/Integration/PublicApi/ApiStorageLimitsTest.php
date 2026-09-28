<?php
declare(strict_types=1);

namespace Tests\Integration\PublicApi;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * What stops the public API filling the server: the daily create limit per company, and the
 * cron that deletes objects nobody will use again. The purge deletes data, so what it keeps
 * matters as much as what it removes.
 */
final class ApiStorageLimitsTest extends TestCase
{
    private PDO $pdo;
    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $GLOBALS['pdo'];

        $publicId = api_generate_id('acct');
        $this->pdo->prepare(
            'INSERT INTO api_accounts (public_id, owner_identity_hash, company_uid, display_name) VALUES (?, ?, ?, ?)'
        )->execute([$publicId, hash('sha256', 'phpunit-' . $publicId), 'phpunit-' . bin2hex(random_bytes(6)), 'PHPUnit Co']);
        $this->accountId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Line items have no foreign key to their parent, only to the account, so the cascade
        // from deleting the account covers them too.
        $this->pdo->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([$this->accountId]);
        rate_limit_clear('api_v1_creates_per_day', (string) $this->accountId);
        parent::tearDown();
    }

    // -- daily create limit ----------------------------------------------------

    public function testCreatesAreRefusedOnceTheDailyAllowanceIsUsed(): void
    {
        $max = rate_limit_max('api_v1_creates_per_day');
        api_count_create($this->accountId);
        $this->pdo->prepare('UPDATE rate_limit_counters SET attempt_count = ? WHERE bucket_key = ?')
            ->execute([$max - 1, rate_limit_bucket_key((string) $this->accountId, 'api_v1_creates_per_day')]);

        api_enforce_create_quota($this->accountId);

        api_count_create($this->accountId);
        try {
            api_enforce_create_quota($this->accountId);
            $this->fail('The create past the daily allowance was allowed.');
        } catch (\ApiResponseSent $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('daily_create_limit_exceeded', $e->payload['error']['code']);
        }
    }

    // -- retention -------------------------------------------------------------

    public function testDeletedAndRejectedObjectsGoAfterThirtyDays(): void
    {
        $deleted = $this->customer(['deleted_at' => $this->daysAgo(31)]);
        $rejected = $this->revenue(['import_status' => 'rejected', 'updated_at' => $this->daysAgo(31)]);
        $item = $this->lineItem($rejected);

        api_retention_purge_all($this->pdo);

        $this->assertFalse($this->exists('api_customers', $deleted));
        $this->assertFalse($this->exists('api_revenue', $rejected));
        $this->assertFalse($this->exists('api_line_items', $item));
    }

    public function testRecentlyDeadAndImportedObjectsAreKept(): void
    {
        $recent = $this->customer(['deleted_at' => $this->daysAgo(10)]);
        $imported = $this->revenue(['import_status' => 'imported', 'imported_at' => $this->daysAgo(400), 'updated_at' => $this->daysAgo(400)]);
        $item = $this->lineItem($imported);
        $pending = $this->revenue(['updated_at' => $this->daysAgo(400)]);

        api_retention_purge_all($this->pdo);

        $this->assertTrue($this->exists('api_customers', $recent));
        $this->assertTrue($this->exists('api_revenue', $imported));
        $this->assertTrue($this->exists('api_line_items', $item));
        $this->assertTrue($this->exists('api_revenue', $pending));
    }

    public function testAnObjectSomethingStillPointsAtIsKept(): void
    {
        $customer = $this->customer(['import_status' => 'rejected', 'updated_at' => $this->daysAgo(60)]);
        $this->revenue(['customer' => $customer]);

        api_retention_purge_all($this->pdo);

        $this->assertTrue($this->exists('api_customers', $customer));
    }

    public function testADeadObjectOnlyADeadObjectPointedAtGoesInTheSameRun(): void
    {
        $customer = $this->customer(['deleted_at' => $this->daysAgo(40)]);
        $sale = $this->revenue(['customer' => $customer, 'deleted_at' => $this->daysAgo(40)]);

        api_retention_purge_all($this->pdo);

        $this->assertFalse($this->exists('api_revenue', $sale));
        $this->assertFalse($this->exists('api_customers', $customer));
    }

    // -- helpers ---------------------------------------------------------------

    private function daysAgo(int $days): string
    {
        return date('Y-m-d H:i:s', strtotime("-$days days"));
    }

    private function customer(array $columns): string
    {
        return $this->insert('api_customers', 'cus', ['name' => 'Test customer'] + $columns);
    }

    private function revenue(array $columns): string
    {
        return $this->insert('api_revenue', 'rev', [
            'description' => 'Test sale',
            'amount'      => 1000,
            'currency'    => 'CAD',
            'occurred_on' => date('Y-m-d'),
        ] + $columns);
    }

    private function lineItem(string $revenueId): string
    {
        return $this->insert('api_line_items', 'li', [
            'parent_type'      => 'revenue',
            'parent_public_id' => $revenueId,
            'description'      => 'Test item',
            'unit_amount'      => 1000,
        ]);
    }

    private function insert(string $table, string $prefix, array $columns): string
    {
        $publicId = api_generate_id($prefix);
        $columns = ['public_id' => $publicId, 'account_id' => $this->accountId] + $columns;
        $names = implode(', ', array_keys($columns));
        $holders = implode(', ', array_fill(0, count($columns), '?'));
        $this->pdo->prepare("INSERT INTO $table ($names) VALUES ($holders)")->execute(array_values($columns));

        return $publicId;
    }

    private function exists(string $table, string $publicId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM $table WHERE public_id = ?");
        $stmt->execute([$publicId]);

        return $stmt->fetchColumn() !== false;
    }
}
