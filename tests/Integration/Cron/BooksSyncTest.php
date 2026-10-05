<?php
declare(strict_types=1);

namespace Tests\Integration\Cron;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\LocalApiServer;

/**
 * The cron that pushes Argo Books' own sales into a company through the public API.
 *
 * Every figure it sends is money that moved, so the mistakes that matter are sending a sale
 * twice, sending a test payment from the other environment, and getting an amount wrong.
 * The cron is a script and the API is a separate service, so this runs both for real: the
 * cron as its own process, against the API on a local server, with nothing stubbed. That
 * also covers the cron being refused by the API, which is how it last broke.
 */
final class BooksSyncTest extends TestCase
{
    use LocalApiServer;

    private static string $siteUrl = '';

    private PDO $pdo;
    private int $accountId = 0;
    private string $apiKey = '';
    private string $tag = '';

    public static function setUpBeforeClass(): void
    {
        self::$siteUrl = self::startApiServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopApiServer();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $GLOBALS['pdo'];
        $this->tag = bin2hex(random_bytes(4));

        if (!is_dir(PROJECT_ROOT . '/cron/logs')) {
            mkdir(PROJECT_ROOT . '/cron/logs', 0755, true);
        }

        // The map is the cron's memory of what it has sent, and it is not scoped to an
        // account. Left over from another run it would make this one skip rows as sent.
        $this->pdo->exec('DELETE FROM argo_books_sync_map');

        // The API is Premium only, so the company being written into needs a subscription.
        $this->subscription("PREM-TEST-SYNC-BOOKS-{$this->tag}", null, current_environment(), 'free_key');

        $publicId = api_generate_id('acct');
        $this->pdo->prepare(
            'INSERT INTO api_accounts (public_id, owner_identity_hash, company_uid, subscription_id, display_name)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$publicId, hash('sha256', $publicId), "books-sync-{$this->tag}", "PREM-TEST-SYNC-BOOKS-{$this->tag}", 'Argo Books (test)']);
        $this->accountId = (int) $this->pdo->lastInsertId();

        $this->apiKey = api_generate_secret_key();
        $this->pdo->prepare(
            'INSERT INTO api_keys (account_id, public_id, key_hash, key_hint, label, scopes) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$this->accountId, api_generate_id('key'), hash('sha256', $this->apiKey), api_key_hint($this->apiKey), 'books sync test', 'read,write']);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([$this->accountId]);
        $this->pdo->prepare('DELETE FROM premium_subscription_payments WHERE subscription_id LIKE ?')->execute(["PREM-TEST-SYNC-%-{$this->tag}"]);
        $this->pdo->prepare('DELETE FROM premium_subscriptions WHERE subscription_id LIKE ?')->execute(["PREM-TEST-SYNC-%-{$this->tag}"]);
        $this->pdo->exec('DELETE FROM argo_books_sync_map');
        $this->pdo->exec("DELETE FROM cron_runs WHERE cron_name = 'argo_books_sync'");
        parent::tearDown();
    }

    public function test_it_sends_this_environments_payments_once_with_the_right_amounts(): void
    {
        $otherEnvironment = current_environment() === 'production' ? 'sandbox' : 'production';
        $mine = "PREM-TEST-SYNC-MINE-{$this->tag}";
        $theirs = "PREM-TEST-SYNC-OTHER-{$this->tag}";
        $this->subscription($mine, "buyer-{$this->tag}@example.test", current_environment());
        $this->subscription($theirs, "tester-{$this->tag}@example.test", $otherEnvironment);

        $sale = $this->payment($mine, 19.99, 'completed', current_environment());
        $refunded = $this->payment($mine, 150.00, 'refunded', current_environment());
        $failed = $this->payment($mine, 15.00, 'failed', current_environment());
        $otherEnvironmentSale = $this->payment($theirs, 77.00, 'completed', $otherEnvironment);

        $this->runSync();

        $revenue = $this->revenueByPayment();
        $this->assertSame(1999, $revenue[$sale]['amount'] ?? null, 'the sale, in cents');
        $this->assertSame(15000, $revenue[$refunded]['amount'] ?? null, 'a refunded sale is still a sale');
        $this->assertArrayNotHasKey($failed, $revenue, 'a failed payment is not income');
        $this->assertArrayNotHasKey($otherEnvironmentSale, $revenue, 'a payment from the other environment reached the books');

        // The refund is its own entry, against the sale it reverses.
        $refunds = $this->pdo->prepare('SELECT revenue, amount FROM api_refunds WHERE account_id = ?');
        $refunds->execute([$this->accountId]);
        $refunds = $refunds->fetchAll();
        $this->assertCount(1, $refunds);
        $this->assertSame($revenue[$refunded]['id'], $refunds[0]['revenue']);
        $this->assertSame(15000, (int) $refunds[0]['amount']);

        // The buyer is on the books; the other environment's is not.
        $emails = $this->pdo->prepare('SELECT email FROM api_customers WHERE account_id = ?');
        $emails->execute([$this->accountId]);
        $emails = $emails->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains("buyer-{$this->tag}@example.test", $emails);
        $this->assertNotContains("tester-{$this->tag}@example.test", $emails);

        // A second run finds nothing new to send.
        $objectsBefore = $this->objectCount();
        $this->runSync();
        $this->assertSame($objectsBefore, $this->objectCount(), 'the second run sent something again');
    }

    public function test_a_payment_that_changed_is_corrected_in_place_not_sent_again(): void
    {
        $mine = "PREM-TEST-SYNC-MINE-{$this->tag}";
        $this->subscription($mine, "buyer-{$this->tag}@example.test", current_environment());
        $sale = $this->payment($mine, 19.99, 'completed', current_environment());

        $this->runSync();
        $first = $this->revenueByPayment()[$sale];

        $this->pdo->prepare('UPDATE premium_subscription_payments SET amount = 24.99 WHERE id = ?')->execute([$sale]);
        $this->runSync();

        $revenue = $this->revenueByPayment();
        $this->assertSame(2499, $revenue[$sale]['amount']);
        $this->assertSame($first['id'], $revenue[$sale]['id'], 'the change made a second entry');
        $this->assertSame(1, $revenue[$sale]['rows']);
    }

    // -- helpers -------------------------------------------------------------

    private function subscription(string $id, ?string $email, string $environment, string $method = 'stripe'): void
    {
        $this->pdo->prepare(
            "INSERT INTO premium_subscriptions
             (subscription_id, email, billing_cycle, amount, currency, start_date, end_date,
              status, payment_method, transaction_id, auto_renew, environment, created_at)
             VALUES (?, ?, 'yearly', 150.00, 'CAD', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR),
                     'active', ?, ?, 0, ?, NOW())"
        )->execute([$id, $email, $method, $id, $environment]);
    }

    /** @return int The new payment's id, which is what the cron files its revenue under. */
    private function payment(string $subscriptionId, float $amount, string $status, string $environment): int
    {
        $this->pdo->prepare(
            "INSERT INTO premium_subscription_payments
             (subscription_id, amount, currency, payment_method, transaction_id, status, payment_type, environment, created_at)
             VALUES (?, ?, 'CAD', 'stripe', ?, ?, 'initial', ?, NOW())"
        )->execute([$subscriptionId, $amount, 'txn_' . bin2hex(random_bytes(8)), $status, $environment]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Runs the cron as its own process, the way cPanel does, and fails the test if it did not finish cleanly. */
    private function runSync(): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/books-sync-runner.php', self::$siteUrl, $this->apiKey],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            PROJECT_ROOT
        );
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $run = $this->pdo->query(
            "SELECT status, error_message FROM cron_runs WHERE cron_name = 'argo_books_sync' ORDER BY id DESC LIMIT 1"
        )->fetch();

        $this->assertSame(0, $exit, "the cron exited $exit: $output " . ($run['error_message'] ?? ''));
        $this->assertSame('ok', $run['status'] ?? null, (string) ($run['error_message'] ?? 'no run was recorded'));
    }

    /** @return array<int, array{id: string, amount: int, rows: int}> Revenue on the books, by the payment it came from. */
    private function revenueByPayment(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT public_id, amount, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.payment_id')) AS payment_id
               FROM api_revenue WHERE account_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$this->accountId]);

        $byPayment = [];
        foreach ($stmt->fetchAll() as $row) {
            $paymentId = (int) $row['payment_id'];
            $byPayment[$paymentId] = [
                'id'     => (string) $row['public_id'],
                'amount' => (int) $row['amount'],
                'rows'   => ($byPayment[$paymentId]['rows'] ?? 0) + 1,
            ];
        }
        return $byPayment;
    }

    /** Everything the account holds, across the tables the cron writes to. */
    private function objectCount(): int
    {
        $total = 0;
        foreach (['api_categories', 'api_customers', 'api_suppliers', 'api_revenue', 'api_expenses', 'api_refunds'] as $table) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE account_id = ?");
            $stmt->execute([$this->accountId]);
            $total += (int) $stmt->fetchColumn();
        }
        return $total;
    }
}
