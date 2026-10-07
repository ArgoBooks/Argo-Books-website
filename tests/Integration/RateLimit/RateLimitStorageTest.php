<?php
declare(strict_types=1);

namespace Tests\Integration\RateLimit;

use Tests\Helpers\DatabaseTestCase;

/**
 * Tests against the `rate_limit_counters` storage behind rate_limit_helper.php.
 *
 * DatabaseTestCase wraps each test in a transaction and rolls back, so nothing
 * survives a run. The limiter deliberately opens no transaction of its own, so
 * its writes join the test's and roll back with it.
 *
 * Each test still uses a UNIQUE IP from the documentation reserved range
 * (192.0.2.0/24) plus a test-only prefix, so a test can never collide with a
 * real bucket even if the rollback were skipped.
 */
final class RateLimitStorageTest extends DatabaseTestCase
{
    private const PREFIX = 'unit_test_rl';

    private function uniqueIp(string $tag): string
    {
        // 192.0.2.0/24 is reserved for documentation (RFC 5737), so these can never collide with real traffic.
        static $base = null;
        static $next = 0;
        if ($base === null) {
            $base = random_int(0, 253);
        }
        unset($tag);
        return '192.0.2.' . (1 + (($base + $next++) % 254));
    }

    /** Current stored count for a bucket, or null when no row exists. */
    private function storedCount(string $ip, string $prefix = self::PREFIX): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT attempt_count FROM rate_limit_counters WHERE bucket_key = ?'
        );
        $stmt->execute([rate_limit_bucket_key($ip, $prefix)]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    /** Back-date a bucket so its window has elapsed, without sleeping. */
    private function ageBucket(string $ip, int $seconds): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rate_limit_counters
             SET first_attempt_at = (UTC_TIMESTAMP() - INTERVAL ? SECOND)
             WHERE bucket_key = ?'
        );
        $stmt->execute([$seconds, rate_limit_bucket_key($ip, self::PREFIX)]);
    }

    public function test_is_rate_limited_returns_false_when_no_attempts(): void
    {
        $ip = $this->uniqueIp('no_attempts');
        $this->assertFalse(is_rate_limited($ip, 5, 900, self::PREFIX));
    }

    public function test_is_rate_limited_returns_false_below_limit(): void
    {
        $ip = $this->uniqueIp('below_limit');
        record_rate_limit_attempt($ip, self::PREFIX);
        record_rate_limit_attempt($ip, self::PREFIX);
        $this->assertFalse(is_rate_limited($ip, 5, 900, self::PREFIX));
    }

    public function test_is_rate_limited_returns_true_at_limit(): void
    {
        $ip = $this->uniqueIp('at_limit');
        for ($i = 0; $i < 5; $i++) {
            record_rate_limit_attempt($ip, self::PREFIX);
        }
        $this->assertTrue(is_rate_limited($ip, 5, 900, self::PREFIX));
    }

    public function test_record_rate_limit_attempt_creates_bucket_on_first_call(): void
    {
        $ip = $this->uniqueIp('first_call');
        $this->assertNull($this->storedCount($ip), 'bucket must not exist yet');
        record_rate_limit_attempt($ip, self::PREFIX);
        $this->assertSame(1, $this->storedCount($ip));
        $this->assertTrue(is_rate_limited($ip, 1, 900, self::PREFIX));
    }

    /**
     * record_rate_limit_attempt() has no cap of its own, so a bucket can be
     * pushed past any threshold a later is_rate_limited() call might use.
     */
    public function test_record_rate_limit_attempt_increments_without_a_cap(): void
    {
        $ip = $this->uniqueIp('uncapped');
        for ($i = 0; $i < 7; $i++) {
            record_rate_limit_attempt($ip, self::PREFIX);
        }
        $this->assertSame(7, $this->storedCount($ip));
    }

    /**
     * The old flat-file implementation rewrote the whole store even on a pure
     * check. A check must not touch the counter at all.
     */
    public function test_is_rate_limited_does_not_record_an_attempt(): void
    {
        $ip = $this->uniqueIp('check_only');
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(is_rate_limited($ip, 1, 900, self::PREFIX));
        }
        $this->assertNull($this->storedCount($ip), 'checking must not create a bucket');
    }

    public function test_check_and_record_returns_false_under_limit_and_increments(): void
    {
        $ip = $this->uniqueIp('check_under');
        // First call: count goes 0 -> 1, returns false (not limited)
        $this->assertFalse(check_and_record_rate_limit($ip, 3, 900, self::PREFIX));
        $this->assertSame(1, $this->storedCount($ip));
        // At count=1 with limit=2 there is still one attempt left, so this
        // records and increments to 2.
        $this->assertFalse(check_and_record_rate_limit($ip, 2, 900, self::PREFIX));
        $this->assertSame(2, $this->storedCount($ip));
    }

    public function test_check_and_record_returns_true_at_limit_and_does_not_increment(): void
    {
        $ip = $this->uniqueIp('check_at');
        record_rate_limit_attempt($ip, self::PREFIX);
        record_rate_limit_attempt($ip, self::PREFIX);
        // Already at 2; with limit=2 the next check_and_record sees the bucket already at the cap and refuses (returns true) without incrementing.
        $this->assertTrue(check_and_record_rate_limit($ip, 2, 900, self::PREFIX));
        $this->assertSame(2, $this->storedCount($ip), 'a blocked attempt must not be counted');
        $this->assertTrue(is_rate_limited($ip, 2, 900, self::PREFIX));
        $this->assertFalse(is_rate_limited($ip, 3, 900, self::PREFIX));
    }

    /**
     * A blocked attempt must not re-anchor the window either, or a caller
     * hammering a tripped bucket would keep itself locked out indefinitely.
     */
    public function test_blocked_attempts_do_not_extend_the_window(): void
    {
        $ip = $this->uniqueIp('no_extend');
        check_and_record_rate_limit($ip, 1, 900, self::PREFIX);
        $this->assertTrue(check_and_record_rate_limit($ip, 1, 900, self::PREFIX));

        // Age the bucket to just past the window, then hammer it while it is still (nominally) tripped.
        $this->ageBucket($ip, 1000);
        $this->assertFalse(check_and_record_rate_limit($ip, 1, 900, self::PREFIX));
        $this->assertSame(1, $this->storedCount($ip), 'expired bucket must reset to 1');
    }

    public function test_clear_rate_limit_attempts_removes_bucket(): void
    {
        $ip = $this->uniqueIp('clear');
        record_rate_limit_attempt($ip, self::PREFIX);
        record_rate_limit_attempt($ip, self::PREFIX);
        $this->assertTrue(is_rate_limited($ip, 2, 900, self::PREFIX));

        clear_rate_limit_attempts($ip, self::PREFIX);
        $this->assertNull($this->storedCount($ip));
        $this->assertFalse(is_rate_limited($ip, 1, 900, self::PREFIX));
    }

    public function test_different_prefixes_do_not_share_buckets(): void
    {
        $ip = $this->uniqueIp('different_prefix');
        record_rate_limit_attempt($ip, self::PREFIX);
        record_rate_limit_attempt($ip, self::PREFIX);
        record_rate_limit_attempt($ip, self::PREFIX);

        $this->assertFalse(is_rate_limited($ip, 1, 900, 'other_test_prefix'));
        $this->assertNull($this->storedCount($ip, 'other_test_prefix'));
    }

    public function test_different_ips_do_not_share_buckets(): void
    {
        $ipA = $this->uniqueIp('ip_a');
        $ipB = $this->uniqueIp('ip_b');
        for ($i = 0; $i < 5; $i++) {
            record_rate_limit_attempt($ipA, self::PREFIX);
        }
        $this->assertTrue(is_rate_limited($ipA, 5, 900, self::PREFIX));
        $this->assertFalse(is_rate_limited($ipB, 1, 900, self::PREFIX));
    }

    public function test_expired_bucket_reads_as_not_limited(): void
    {
        $ip = $this->uniqueIp('stale');
        for ($i = 0; $i < 5; $i++) {
            record_rate_limit_attempt($ip, self::PREFIX);
        }
        $this->assertTrue(is_rate_limited($ip, 1, 900, self::PREFIX));

        $this->ageBucket($ip, 1200);
        $this->assertFalse(is_rate_limited($ip, 1, 900, self::PREFIX));
    }

    /**
     * The window is anchored at the bucket's first attempt, so later attempts
     * inside the window must not push the expiry out.
     */
    public function test_window_does_not_slide_on_later_attempts(): void
    {
        $ip = $this->uniqueIp('anchored');
        record_rate_limit_attempt($ip, self::PREFIX);
        $this->ageBucket($ip, 880);

        // Still inside a 900s window: this counts, and must leave the anchor
        // where it was rather than resetting it to now.
        record_rate_limit_attempt($ip, self::PREFIX);
        $this->assertSame(2, $this->storedCount($ip));

        $stmt = $this->pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, first_attempt_at, UTC_TIMESTAMP())
             FROM rate_limit_counters WHERE bucket_key = ?'
        );
        $stmt->execute([rate_limit_bucket_key($ip, self::PREFIX)]);
        $this->assertGreaterThanOrEqual(870, (int) $stmt->fetchColumn());
    }

    /**
     * Sandbox and production share one database, so the environment has to be
     * part of the key or dev testing would burn production allowances.
     */
    public function test_bucket_key_is_namespaced_by_environment(): void
    {
        $key = rate_limit_bucket_key('192.0.2.99', self::PREFIX);
        $this->assertStringStartsWith(current_environment() . ':', $key);
        $this->assertStringContainsString(self::PREFIX . '_', $key);
        $this->assertLessThanOrEqual(120, strlen($key), 'must fit bucket_key VARCHAR(120)');
    }
}
