<?php
declare(strict_types=1);

namespace Tests\Unit\Bank;

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/api/bank/_statement_check.php';

final class StatementCheckTest extends TestCase
{
    private static function statement(array $amounts, ?float $opening, ?float $closing, bool $creditCard = false, int $accounts = 1): string
    {
        return json_encode([
            'openingBalance' => $opening,
            'closingBalance' => $closing,
            'creditCard' => $creditCard,
            'accountCount' => $accounts,
            'lines' => array_map(fn ($a) => ['date' => '2026-04-01', 'description' => 'Row', 'amount' => $a], $amounts),
        ]);
    }

    public function testRowsThatReachTheClosingBalanceAreBalanced(): void
    {
        $check = bs_check_balance(bs_parse_statement(self::statement([100.10, -50.05, 0.2], 1000.00, 1050.25)));

        $this->assertSame('balanced', $check['status']);
        $this->assertSame(0.0, $check['gap']);
    }

    public function testAMissingRowShowsAsTheGap(): void
    {
        $check = bs_check_balance(bs_parse_statement(self::statement([100.00], 1000.00, 1040.00)));

        $this->assertSame('off', $check['status']);
        $this->assertSame(-60.0, $check['gap']);
    }

    public function testCardSpendingRaisesTheBalanceOwed(): void
    {
        $check = bs_check_balance(bs_parse_statement(self::statement([-25.00, -75.00, 40.00], 200.00, 260.00, creditCard: true)));

        $this->assertSame('balanced', $check['status']);
    }

    public function testAStatementWithoutPrintedBalancesIsUnchecked(): void
    {
        $check = bs_check_balance(bs_parse_statement(self::statement([10.00], null, 50.00)));

        $this->assertSame('unchecked', $check['status']);
        $this->assertNull($check['gap']);
    }

    public function testSeveralAccountsInOneDocumentAreUnchecked(): void
    {
        $check = bs_check_balance(bs_parse_statement(self::statement([10.00], 0.00, 10.00, accounts: 2)));

        $this->assertSame('unchecked', $check['status']);
    }

    public function testBalancesPrintedWithSeparatorsAreRead(): void
    {
        $parsed = bs_parse_statement('{"openingBalance":"3,049.71","closingBalance":"$3,006.35","lines":[{"date":"2026-04-01","description":"Fee","amount":-43.36}]}');

        $this->assertSame(3049.71, $parsed['opening']);
        $this->assertSame('balanced', bs_check_balance($parsed)['status']);
    }

    public function testABareListOfRowsStillParses(): void
    {
        $parsed = bs_parse_statement('[{"date":"2026-04-01","description":"Fee","amount":"-4.50"}]');

        $this->assertCount(1, $parsed['lines']);
        $this->assertSame(-4.5, $parsed['lines'][0]['amount']);
        $this->assertSame('unchecked', bs_check_balance($parsed)['status']);
    }
}
