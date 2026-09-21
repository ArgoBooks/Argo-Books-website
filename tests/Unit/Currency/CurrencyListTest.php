<?php
declare(strict_types=1);

namespace Tests\Unit\Currency;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The website's currency list mirrors the desktop app's CurrencyInfo.cs, and a symbol shown
 * here can end up in an invoice PDF the app renders. These hold both ends to the same rules
 * the app's CurrencyListTests enforce.
 */
final class CurrencyListTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../shared/currencies.php';
    }

    /** @return array<string,array{0:string,1:array<string,mixed>}> */
    public static function currencyProvider(): array
    {
        require_once __DIR__ . '/../../../shared/currencies.php';

        $rows = [];
        foreach (argo_currencies_all() as $code => $info) {
            $rows[$code] = [$code, $info];
        }
        return $rows;
    }

    #[DataProvider('currencyProvider')]
    public function test_entry_is_complete(string $code, array $info): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code, 'Codes are ISO 4217');
        foreach (['name', 'symbol', 'locale', 'decimals'] as $key) {
            $this->assertArrayHasKey($key, $info, "$code is missing '$key'");
        }
        $this->assertNotSame('', trim($info['name']), "$code has no name");
        $this->assertNotSame('', trim($info['symbol']), "$code has no symbol");
        $this->assertMatchesRegularExpression('/^[a-z]{2}-[A-Z]{2}$/', $info['locale'], "$code has no usable locale");
        $this->assertContains($info['decimals'], [0, 2], "$code uses decimals the formatters do not handle");
    }

    /**
     * A symbol is drawn by whatever font the reader has. The app's PDFs ask for Helvetica,
     * which on a Mac carries Latin, Cyrillic and the currency signs and nothing else, so a
     * symbol taken from a script (฿ Thai, ৳ Bengali, ﷼ Arabic) prints as an empty box there.
     * Those currencies use letters instead: THB, Tk.
     */
    #[DataProvider('currencyProvider')]
    public function test_symbol_uses_only_widely_shipped_characters(string $code, array $info): void
    {
        foreach (preg_split('//u', $info['symbol'], -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $point = mb_ord($char, 'UTF-8');
            $ok = $point <= 0x017F                          // Latin, Latin-1, Latin Extended-A
                || ($point >= 0x0400 && $point <= 0x04FF)   // Cyrillic
                || ($point >= 0x20A0 && $point <= 0x20BF);  // Currency Symbols block
            $this->assertTrue($ok, sprintf(
                "%s symbol '%s' (U+%04X) is outside Latin, Cyrillic and the currency block, so it will not render in every PDF font. Use letters instead, as THB and BDT do.",
                $code, $char, $point
            ));
        }
    }

    public function test_common_currencies_are_in_the_list(): void
    {
        $all = argo_currencies_all();
        foreach (argo_currencies_common() as $code) {
            $this->assertArrayHasKey($code, $all, "$code is pinned to the top of pickers but is not in the list");
        }
    }

    public function test_list_is_sorted_by_code(): void
    {
        // Pickers render in this order, and the docs table is generated straight from it.
        $codes = array_keys(argo_currencies_all());
        $sorted = $codes;
        sort($sorted);
        $this->assertSame($sorted, $codes);
    }
}
