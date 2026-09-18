<?php
declare(strict_types=1);

namespace Tests\Unit\RateLimit;

use PHPUnit\Framework\TestCase;

/**
 * config/rate_limits.php writes both .env variable names out in full beside each limit so they
 * can be searched for. That only holds while the names match the entry they sit on, which is
 * what a copy-pasted line breaks: the limit would then answer to another limit's variable.
 */
final class RateLimitConfigTest extends TestCase
{
    private const ENTRY_PATTERN =
        "/^\s*'([a-z0-9_]+)' => \[_rl_env\('RL_([A-Z0-9_]+)_MAX', (\d+)\), _rl_env\('RL_([A-Z0-9_]+)_WINDOW', (\d+)\)\],$/m";

    /**
     * @return list<array{name: string, maxKey: string, windowKey: string, max: int, window: int}>
     */
    private function entries(): array
    {
        $source = file_get_contents(PROJECT_ROOT . '/config/rate_limits.php');
        $this->assertNotFalse($source);
        preg_match_all(self::ENTRY_PATTERN, $source, $matches, PREG_SET_ORDER);

        return array_map(static fn(array $m): array => [
            'name' => $m[1],
            'maxKey' => $m[2],
            'windowKey' => $m[4],
            'max' => (int) $m[3],
            'window' => (int) $m[5],
        ], $matches);
    }

    public function test_every_limit_is_declared_in_the_searchable_form(): void
    {
        $names = array_column($this->entries(), 'name');
        $this->assertSame(array_keys(rate_limits()), $names);
    }

    public function test_env_variable_names_match_the_limit_they_sit_on(): void
    {
        foreach ($this->entries() as $entry) {
            $expected = strtoupper($entry['name']);
            $this->assertSame($expected, $entry['maxKey'], "RL_{$entry['maxKey']}_MAX is on '{$entry['name']}'");
            $this->assertSame($expected, $entry['windowKey'], "RL_{$entry['windowKey']}_WINDOW is on '{$entry['name']}'");
        }
    }

    public function test_every_limit_has_a_usable_ceiling_and_window(): void
    {
        foreach (rate_limits() as $name => $limit) {
            $this->assertGreaterThan(0, $limit['max'], "$name has no ceiling");
            $this->assertGreaterThan(0, $limit['window'], "$name has no window");
        }
    }

    public function test_unknown_name_fails_closed(): void
    {
        $this->assertSame(['max' => 5, 'window' => 900], rate_limit('no_such_limit'));
    }
}
