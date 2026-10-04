<?php

/**
 * Read an environment variable with a fallback default.
 *
 * Always checks both $_ENV and getenv() because $_ENV is only populated when
 * php.ini's variables_order includes "E", which is not guaranteed on every host.
 *
 * Returns the raw env value when the variable is set, even if it's "0" or "".
 * Only falls back to $default when the variable is truly unset. This matters
 * for boolean-style vars like INVOICE_LOG_ENABLED=0.
 *
 * @param string $key
 * @param mixed $default Returned when the variable is not set at all
 * @return mixed
 */
function env(string $key, $default = '')
{
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

/**
 * Build an absolute URL to a path on the site.
 *
 * Uses the SITE_URL env var (default: https://argorobots.com) so this works in
 * cron contexts where $_SERVER is empty.
 */
function site_url(string $path = ''): string
{
    $base = rtrim(env('SITE_URL', 'https://argorobots.com'), '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}
