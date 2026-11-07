<?php
declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/data/telemetry_filter.php';
require_once __DIR__ . '/../../../admin/app-stats/os-name.php';

final class OsVersionTest extends TestCase
{
    public function test_filter_keeps_a_version_number(): void
    {
        $out = filter_telemetry_payload(['platform' => 'Windows', 'osVersion' => '10.0.19045']);
        $this->assertSame('10.0.19045', $out['osVersion']);
    }

    public function test_filter_drops_anything_but_a_version_number(): void
    {
        $out = filter_telemetry_payload(['platform' => 'Linux', 'osVersion' => 'Linux 6.8.0-45-generic #45-Ubuntu']);
        $this->assertArrayNotHasKey('osVersion', $out);
    }

    public function test_windows_11_is_named_from_its_build(): void
    {
        $this->assertSame('Windows 11 24H2', telemetry_os_name('Windows', '10.0.26100'));
    }

    public function test_windows_10_is_named_from_its_build(): void
    {
        $this->assertSame('Windows 10 22H2', telemetry_os_name('Windows', '10.0.19045'));
    }

    public function test_an_unlisted_windows_11_build_still_says_windows_11(): void
    {
        $this->assertSame('Windows 11 (build 26300)', telemetry_os_name('Windows', '10.0.26300'));
    }

    public function test_an_unlisted_older_build_shows_the_number(): void
    {
        $this->assertSame('Windows 10.0.20348', telemetry_os_name('Windows', '10.0.20348'));
    }

    public function test_macos_shows_major_and_minor(): void
    {
        $this->assertSame('macOS 15.1', telemetry_os_name('macOS', '15.1.0'));
    }

    public function test_an_upload_without_a_version_shows_the_platform(): void
    {
        $this->assertSame('Windows', telemetry_os_name('Windows', null));
    }
}
