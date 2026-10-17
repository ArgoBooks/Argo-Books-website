<?php
declare(strict_types=1);

namespace Tests\Integration\ExchangeRates;

use Tests\Helpers\DatabaseTestCase;

/** api/_exchange_rate_sources.php with the network replaced by a recorder, against the test database. */
final class ExchangeRateSourcesTest extends DatabaseTestCase
{
    private const TODAY = '2026-10-16';

    /** @var list<string> */
    private array $requested = [];

    private array $savedEnv = [];

    public static function setUpBeforeClass(): void
    {
        require_once PROJECT_ROOT . '/api/_exchange_rate_sources.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['OPENEXCHANGERATES_API_KEY', 'OER_MONTHLY_BUDGET'] as $name) {
            $this->savedEnv[$name] = $_ENV[$name] ?? null;
        }
        $_ENV['OPENEXCHANGERATES_API_KEY'] = 'test_key';
        $_ENV['OER_MONTHLY_BUDGET'] = '900';
        $this->requested = [];
        $this->pdo->exec('DELETE FROM exchange_rates');
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

    /** A network stand-in: $answer maps a URL to [status, body]; anything unmatched is a 404. */
    private function net(callable $answer): callable
    {
        return function (array $urls) use ($answer): array {
            $out = [];
            foreach ($urls as $key => $url) {
                $this->requested[] = $url;
                $out[$key] = $answer($url) ?? [404, null];
            }
            return $out;
        };
    }

    /** A rate for every currency the app supports, with EUR set to $eur. */
    private static function allRates(float $eur, array $leaveOut = []): array
    {
        $rates = [];
        foreach (array_keys(argo_currencies_all()) as $i => $code) {
            if (!in_array($code, $leaveOut, true)) {
                $rates[$code] = $code === 'USD' ? 1.0 : 1.5 + $i;
            }
        }
        $rates['EUR'] = $eur;
        return $rates;
    }

    private static function oerBody(float $eur): string
    {
        return json_encode(['base' => 'USD', 'rates' => self::allRates($eur)]);
    }

    private static function fawazBody(string $date, float $eur): string
    {
        return json_encode(['date' => $date, 'usd' => array_change_key_case(self::allRates($eur), CASE_LOWER) + ['btc' => 0.00001, '1inch' => 3.2]]);
    }

    /** A Frankfurter v2 body; $dates maps a currency to the day its rate was published, defaulting to $date. */
    private static function frankfurterBody(string $date, float $eur, array $dates = [], array $leaveOut = []): string
    {
        $quotes = [];
        foreach (self::allRates($eur, $leaveOut) as $code => $rate) {
            if ($code !== 'USD') {
                $quotes[] = ['date' => $dates[$code] ?? $date, 'base' => 'USD', 'quote' => $code, 'rate' => $rate];
            }
        }
        return json_encode($quotes);
    }

    private function stored(string $date): ?array
    {
        $stmt = $this->pdo->prepare('SELECT rates, source FROM exchange_rates WHERE rate_date = ?');
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        return $row ? ['rates' => json_decode($row['rates'], true), 'source' => $row['source']] : null;
    }

    private function seedOerRows(int $count, string $fetchedAgo = '1 DAY'): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO exchange_rates (rate_date, rates, source, fetched_at) VALUES (?, '{}', 'openexchangerates', NOW() - INTERVAL $fetchedAgo)");
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([date('Y-m-d', strtotime("2001-01-01 +$i days"))]);
        }
    }

    public function test_fawaz_rates_are_reshaped_like_open_exchange_rates(): void
    {
        $rates = exchange_rate_parse_fawaz(self::fawazBody('2025-07-04', 0.85), '2025-07-04');

        $this->assertSame(0.85, $rates['EUR']);
        $this->assertSame(1.0, $rates['USD']);
        $this->assertSame(0.00001, $rates['BTC']);
        $this->assertArrayNotHasKey('1INCH', $rates);
    }

    public function test_a_fawaz_file_for_another_day_is_refused(): void
    {
        $this->assertNull(exchange_rate_parse_fawaz(self::fawazBody('2025-07-03', 0.85), '2025-07-04'));
    }

    public function test_a_fawaz_file_without_euro_is_refused(): void
    {
        $this->assertNull(exchange_rate_parse_fawaz(json_encode(['date' => '2025-07-04', 'usd' => ['zar' => 17.4]]), '2025-07-04'));
        $this->assertNull(exchange_rate_parse_fawaz('not json', '2025-07-04'));
    }

    public function test_open_exchange_rates_is_used_while_there_is_allowance(): void
    {
        $net = $this->net(fn($url) => str_contains($url, 'openexchangerates.org/api/historical/2025-07-04') ? [200, self::oerBody(0.84)] : null);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame(0.84, $got['2025-07-04']['EUR']);
        $this->assertSame('openexchangerates', $this->stored('2025-07-04')['source']);
        $this->assertCount(2, $this->requested, 'Frankfurter, then Open Exchange Rates');
    }

    public function test_today_is_asked_for_as_the_latest_rates(): void
    {
        $net = $this->net(fn($url) => str_contains($url, '/api/latest.json') ? [200, self::oerBody(0.89)] : null);

        $got = exchange_rates_fetch_and_store($this->pdo, [self::TODAY], self::TODAY, $net);

        $this->assertSame(0.89, $got[self::TODAY]['EUR']);
    }

    public function test_a_refusal_from_open_exchange_rates_falls_back_to_fawaz(): void
    {
        $net = $this->net(function ($url) {
            if (str_contains($url, 'openexchangerates.org')) return [403, '{"error":true,"message":"access_restricted"}'];
            if (str_contains($url, 'jsdelivr.net/npm/@fawazahmed0/currency-api@2025-07-04')) return [200, self::fawazBody('2025-07-04', 0.85)];
            return null;
        });

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame(0.85, $got['2025-07-04']['EUR']);
        $this->assertSame('fawazahmed0', $this->stored('2025-07-04')['source']);
    }

    public function test_the_mirror_is_asked_only_when_jsdelivr_has_nothing(): void
    {
        $net = $this->net(function ($url) {
            if (str_contains($url, '2025-07-04.currency-api.pages.dev')) return [200, self::fawazBody('2025-07-04', 0.86)];
            return null;
        });

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame(0.86, $got['2025-07-04']['EUR']);
        $this->assertCount(4, $this->requested, 'Frankfurter, Open Exchange Rates, jsDelivr, then the mirror');
    }

    public function test_a_date_before_fawaz_begins_is_left_unpriced_when_open_exchange_rates_fails(): void
    {
        $net = $this->net(fn($url) => str_contains($url, 'openexchangerates.org') ? [429, '{}'] : [200, self::fawazBody('2019-05-01', 0.9)]);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2019-05-01'], self::TODAY, $net);

        $this->assertSame([], $got);
        $this->assertNull($this->stored('2019-05-01'));
        $this->assertCount(2, $this->requested, 'Frankfurter and Open Exchange Rates; fawazahmed0 has nothing that old, so it is not asked');
    }

    public function test_open_exchange_rates_is_not_asked_once_the_allowance_is_used(): void
    {
        $_ENV['OER_MONTHLY_BUDGET'] = '5';
        $this->seedOerRows(5);
        $net = $this->net(fn($url) => str_contains($url, 'jsdelivr') ? [200, self::fawazBody('2025-07-04', 0.85)] : [200, self::oerBody(0.84)]);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame('fawazahmed0', $this->stored('2025-07-04')['source']);
        $this->assertSame(0.85, $got['2025-07-04']['EUR']);
        foreach ($this->requested as $url) {
            $this->assertStringNotContainsString('openexchangerates.org', $url);
        }
    }

    public function test_the_allowance_only_counts_the_last_31_days_of_open_exchange_rates_fetches(): void
    {
        $_ENV['OER_MONTHLY_BUDGET'] = '10';
        $this->seedOerRows(4, '40 DAY');
        $this->seedOerRowsFrom('2002-01-01', 3, 'fawazahmed0');
        $this->seedOerRowsFrom('2003-01-01', 2, 'openexchangerates');

        $this->assertSame(8, exchange_rate_oer_calls_left($this->pdo));
    }

    public function test_a_batch_bigger_than_the_allowance_sends_only_the_allowance_to_open_exchange_rates(): void
    {
        $_ENV['OER_MONTHLY_BUDGET'] = '2';
        $dates = ['2025-07-01', '2025-07-02', '2025-07-03'];
        $net = $this->net(function ($url) {
            if (preg_match('#historical/(\d{4}-\d{2}-\d{2})#', $url)) return [200, self::oerBody(0.84)];
            if (preg_match('#currency-api@(\d{4}-\d{2}-\d{2})#', $url, $m)) return [200, self::fawazBody($m[1], 0.85)];
            return null;
        });

        $got = exchange_rates_fetch_and_store($this->pdo, $dates, self::TODAY, $net);

        $this->assertCount(3, $got);
        $this->assertSame('openexchangerates', $this->stored('2025-07-01')['source']);
        $this->assertSame('openexchangerates', $this->stored('2025-07-02')['source']);
        $this->assertSame('fawazahmed0', $this->stored('2025-07-03')['source']);
    }

    public function test_without_an_api_key_only_fawaz_is_asked(): void
    {
        unset($_ENV['OPENEXCHANGERATES_API_KEY']);
        $net = $this->net(fn($url) => str_contains($url, 'jsdelivr') ? [200, self::fawazBody('2025-07-04', 0.85)] : null);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame(0.85, $got['2025-07-04']['EUR']);
        foreach ($this->requested as $url) {
            $this->assertStringNotContainsString('openexchangerates.org', $url);
        }
    }

    public function test_a_date_stored_meanwhile_keeps_its_first_rates(): void
    {
        // Another request stored this date between the caller's check and this fetch.
        $this->pdo->prepare("INSERT INTO exchange_rates (rate_date, rates, source, fetched_at) VALUES ('2025-07-04', ?, 'openexchangerates', NOW())")
            ->execute([json_encode(['USD' => 1, 'EUR' => 0.84])]);
        $net = $this->net(fn($url) => [200, str_contains($url, 'openexchangerates') ? self::oerBody(0.99) : self::fawazBody('2025-07-04', 0.99)]);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2025-07-04'], self::TODAY, $net);

        $this->assertSame(0.84, $got['2025-07-04']['EUR']);
        $this->assertSame(0.84, $this->stored('2025-07-04')['rates']['EUR']);
    }

    public function test_frankfurter_is_asked_first_and_open_exchange_rates_is_then_left_alone(): void
    {
        $net = $this->net(fn($url) => str_contains($url, 'api.frankfurter.dev/v2/rates?base=USD&date=2019-05-01') ? [200, self::frankfurterBody('2019-05-01', 0.89)] : [200, self::oerBody(0.5)]);

        $got = exchange_rates_fetch_and_store($this->pdo, ['2019-05-01'], self::TODAY, $net);

        $this->assertSame(0.89, $got['2019-05-01']['EUR']);
        $this->assertSame('frankfurter', $this->stored('2019-05-01')['source']);
        $this->assertCount(1, $this->requested);
    }

    public function test_a_weekend_takes_the_last_rates_published_before_it(): void
    {
        // Saturday 2026-10-10: every bank last published on the Friday.
        $friday = [];
        foreach (array_keys(argo_currencies_all()) as $code) {
            $friday[$code] = '2026-10-09';
        }

        $rates = exchange_rate_parse_frankfurter(self::frankfurterBody('2026-10-10', 0.89, $friday), '2026-10-10', self::TODAY);

        $this->assertSame(0.89, $rates['EUR'] ?? null);
    }

    public function test_a_rate_more_than_a_week_old_does_not_count_for_the_date(): void
    {
        $rates = exchange_rate_parse_frankfurter(self::frankfurterBody('2026-10-10', 0.89, ['VND' => '2026-10-02']), '2026-10-10', self::TODAY);

        $this->assertNull($rates, 'without a usable VND rate the day is incomplete');
    }

    public function test_today_waits_until_most_central_banks_have_published(): void
    {
        $yesterday = array_fill_keys(array_keys(argo_currencies_all()), '2026-10-15');

        $this->assertNull(exchange_rate_parse_frankfurter(self::frankfurterBody(self::TODAY, 0.89, $yesterday), self::TODAY, self::TODAY));
        $this->assertNotNull(exchange_rate_parse_frankfurter(self::frankfurterBody(self::TODAY, 0.89), self::TODAY, self::TODAY));
    }

    public function test_before_central_banks_publish_today_comes_from_open_exchange_rates(): void
    {
        $net = $this->net(function ($url) {
            if (str_contains($url, 'api.frankfurter.dev')) return [200, self::frankfurterBody(self::TODAY, 0.89, array_fill_keys(array_keys(argo_currencies_all()), '2026-10-15'))];
            if (str_contains($url, '/api/latest.json')) return [200, self::oerBody(0.88)];
            return null;
        });

        $got = exchange_rates_fetch_and_store($this->pdo, [self::TODAY], self::TODAY, $net);

        $this->assertSame(0.88, $got[self::TODAY]['EUR']);
        $this->assertSame('openexchangerates', $this->stored(self::TODAY)['source']);
    }

    public function test_the_lev_is_worked_out_from_the_euro_when_it_is_not_published(): void
    {
        $rates = exchange_rate_parse_frankfurter(self::frankfurterBody('2026-10-10', 0.9, [], ['BGN']), '2026-10-10', self::TODAY);

        $this->assertEqualsWithDelta(0.9 * 1.95583, $rates['BGN'], 1e-9);
    }

    public function test_a_day_missing_a_supported_currency_goes_to_the_next_source(): void
    {
        $net = $this->net(function ($url) {
            if (str_contains($url, 'api.frankfurter.dev')) return [200, self::frankfurterBody('2019-05-01', 0.89, [], ['NGN'])];
            if (str_contains($url, 'historical/2019-05-01')) return [200, self::oerBody(0.88)];
            return null;
        });

        $got = exchange_rates_fetch_and_store($this->pdo, ['2019-05-01'], self::TODAY, $net);

        $this->assertSame('openexchangerates', $this->stored('2019-05-01')['source']);
        $this->assertArrayHasKey('NGN', $got['2019-05-01']);
    }

    public function test_a_currency_that_did_not_exist_yet_does_not_make_a_day_incomplete(): void
    {
        $this->assertNotNull(exchange_rate_parse_frankfurter(self::frankfurterBody('2005-06-15', 0.82, [], ['BYN', 'GHS']), '2005-06-15', self::TODAY));
        $this->assertNull(exchange_rate_parse_frankfurter(self::frankfurterBody('2018-06-15', 0.86, [], ['BYN']), '2018-06-15', self::TODAY));
    }

    public function test_future_dates_are_never_fetched(): void
    {
        $net = $this->net(fn($url) => [200, self::oerBody(0.84)]);

        $this->assertSame([], exchange_rates_fetch_and_store($this->pdo, ['2026-10-17'], self::TODAY, $net));
        $this->assertSame([], $this->requested);
    }

    private function seedOerRowsFrom(string $start, int $count, string $source): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO exchange_rates (rate_date, rates, source, fetched_at) VALUES (?, '{}', ?, NOW() - INTERVAL 1 DAY)");
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([date('Y-m-d', strtotime("$start +$i days")), $source]);
        }
    }
}
