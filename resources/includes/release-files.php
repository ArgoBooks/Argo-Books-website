<?php
// Decides whether an installer in resources/downloads/ is safe to offer.
//
// Release files go up over FileZilla, and a file that is still uploading already exists
// under its final name, so a plain file_exists() offered half-written installers. The
// release feed (avalonia-update.xml) holds each current file's exact size, written by
// sign-release.ps1, so a file counts as ready once it has reached that size. This works
// whether the release commit is pushed before or after the upload.

if (!function_exists('release_feed')) {
    /**
     * The newest version in the feed and the expected size of each file, keyed by filename.
     * Null when the feed is missing or unreadable, in which case every file is offered.
     */
    function release_feed(): ?array
    {
        static $feed = false;
        if ($feed !== false) {
            return $feed;
        }

        $xml = @simplexml_load_file(__DIR__ . '/../../avalonia-update.xml');
        if ($xml === false) {
            return $feed = null;
        }

        $version = null;
        $sizes = [];
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
}
