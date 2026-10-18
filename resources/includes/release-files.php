<?php
// A file uploading over FileZilla already has its final name, so a download is offered only once it reaches the size its release recorded.

if (!function_exists('release_feed')) {
    /** The newest version and each file's size from avalonia-update.xml and release.json, or null when neither can be read, which offers every file. */
    function release_feed(): ?array
    {
        static $feed = false;
        if ($feed !== false) {
            return $feed;
        }

        $version = null;
        $sizes = [];

        $xml = @simplexml_load_file(__DIR__ . '/../../avalonia-update.xml');
        if ($xml !== false) {
            foreach ($xml->channel->item as $item) {
                $itemVersion = (string) $item->children('sparkle', true)->version;
                if ($itemVersion !== '' && ($version === null || version_compare($itemVersion, $version) > 0)) {
                    $version = $itemVersion;
                }
                $path = parse_url((string) $item->enclosure['url'], PHP_URL_PATH);
                if (is_string($path) && $path !== '') {
                    $sizes[rawurldecode(basename($path))] = (int) $item->enclosure['length'];
                }
            }
        }

        $raw = @file_get_contents(__DIR__ . '/../updates/release.json');
        $release = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($release) && is_string($release['version'] ?? null)) {
            if ($version === null || version_compare($release['version'], $version) > 0) {
                $version = $release['version'];
            }
            foreach ($release['downloads'] ?? [] as $name => $size) {
                $sizes[(string) $name] = (int) $size;
            }
        }

        return $feed = $version === null ? null : ['version' => $version, 'sizes' => $sizes];
    }

    function release_file_ready(string $version, string $filename, string $filepath): bool
    {
        if (!is_file($filepath)) {
            return false;
        }

        $feed = release_feed();
        if ($feed === null) {
            return true;
        }

        // A folder newer than the feed is a release that hasn't been published yet.
        if (version_compare($version, $feed['version']) > 0) {
            return false;
        }

        $expected = $feed['sizes'][$filename] ?? 0;
        return $expected <= 0 || filesize($filepath) === $expected;
    }

    /** The first of a platform's file name patterns that is ready to download, since Windows has both a Setup.exe and the older installer. */
    function release_find_file(string $version, string|array $patterns, string $downloadsDir): ?array
    {
        foreach ((array) $patterns as $pattern) {
            $filename = str_replace('{version}', $version, $pattern);
            $filepath = rtrim($downloadsDir, '/') . '/' . $version . '/' . $filename;
            if (release_file_ready($version, $filename, $filepath)) {
                return ['filename' => $filename, 'filepath' => $filepath];
            }
        }
        return null;
    }
}
