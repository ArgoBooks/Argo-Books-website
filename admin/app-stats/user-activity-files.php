<?php
/**
 * The telemetry files behind the User Activity tab, and the delete its button posts.
 *
 * Kept apart from the tab because the delete has to be answered before the page prints
 * anything: index.php handles the POST and redirects to the same URL, so refreshing
 * afterwards reloads the page instead of asking the browser to send the delete again.
 * The tab itself is included far too late in the page to send a redirect header.
 */

if (!function_exists('ua_collect_files')) {
    /**
     * Every telemetry file, keyed by basename so one present in both the current folder
     * and the legacy root is counted once.
     *
     * @return array<string,string> basename => full path
     */
    function ua_collect_files(): array
    {
        $files = [];
        foreach ([__DIR__ . '/../data-logs/telemetry/', __DIR__ . '/../data-logs/'] as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '*.json') ?: [] as $f) {
                $name = basename($f);
                if (!isset($files[$name])) {
                    $files[$name] = $f;
                }
            }
        }
        return $files;
    }
}

if (!function_exists('ua_delete_posted_files')) {
    /**
     * Deletes the files a delete post names. Each name is reduced to its basename and has
     * to be one of the collected files already, so nothing outside data-logs/ can be
     * touched. Matching is by filename rather than authId so legacy files that carry no
     * authId can be deleted too.
     */
    function ua_delete_posted_files(array $requested): void
    {
        $files = ua_collect_files();
        foreach ($requested as $reqName) {
            $base = basename((string)$reqName);
            if (isset($files[$base])) {
                @unlink($files[$base]);
            }
        }
    }
}
