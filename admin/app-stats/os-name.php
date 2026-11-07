<?php

// Windows 11 still reports itself as version 10.0, so only the build number tells the releases apart.
const TELEMETRY_WINDOWS_RELEASES = [
    17763 => 'Windows 10 1809',
    18362 => 'Windows 10 1903',
    18363 => 'Windows 10 1909',
    19041 => 'Windows 10 2004',
    19042 => 'Windows 10 20H2',
    19043 => 'Windows 10 21H1',
    19044 => 'Windows 10 21H2',
    19045 => 'Windows 10 22H2',
    22000 => 'Windows 11 21H2',
    22621 => 'Windows 11 22H2',
    22631 => 'Windows 11 23H2',
    26100 => 'Windows 11 24H2',
    26200 => 'Windows 11 25H2',
];

// Uploads from app versions that do not send an OS version show the platform alone.
function telemetry_os_name(string $platform, ?string $osVersion): string
{
    if ($osVersion === null || $osVersion === '') {
        return $platform;
    }

    $parts = explode('.', $osVersion);

    switch ($platform) {
        case 'Windows':
            $build = (int) ($parts[2] ?? 0);
            if (isset(TELEMETRY_WINDOWS_RELEASES[$build])) {
                return TELEMETRY_WINDOWS_RELEASES[$build];
            }
            // Insider builds run ahead of every named release.
            if ($parts[0] === '10' && $build >= 22000) {
                return "Windows 11 (build $build)";
            }
            return "Windows $osVersion";

        case 'macOS':
            return 'macOS ' . $parts[0] . '.' . ($parts[1] ?? '0');

        case 'Linux':
            // Linux reports its kernel, which says nothing about the distribution.
            return "Linux (kernel $osVersion)";

        default:
            return trim("$platform $osVersion");
    }
}
