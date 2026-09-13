<?php
// Local dev router for `php -S`. Apache reads .htaccess on every request; the
// built-in server does not read it at all. This applies the parts that decide
// whether a URL resolves, so local behaviour matches production. Only ever
// loaded by `php -S`; on the server this file is never executed.

$docRoot = __DIR__;
$path    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$decoded = rawurldecode($path);
$safe    = strpos($decoded, '..') === false;

// --- 1. <FilesMatch> / <Files> deny rules -----------------------------------
// These are not RewriteRules, so they have to be spelled out. Without them
// .env, the schema dump and composer manifests are served as plain text.
$blocked = '#/\.env'
         . '|\.(sql|log|key|pem|ppk|p12|pfx|bak|old|orig|save|swp|dist)$'
         . '|^/composer\.(json|lock)$#i';
if (preg_match($blocked, $path)) {
    http_response_code(403);
    exit('Forbidden (router.php, mirroring .htaccess FilesMatch)');
}

// --- 2. mod_rewrite ---------------------------------------------------------
// .htaccess carries roughly 120 routing rules: the whole /v1 and /api surface,
// the portal and invoice token URLs, /download/avalonia/..., and every guide
// article, each of which is an exact slug at the web root mapping to
// guides/article-page.php. None of those paths exist on disk, so without this
// they 404 locally while working in production.
//
// Parsed from .htaccess rather than transcribed so the two cannot drift.
// RewriteCond-guarded rules are skipped: the only ones here force the canonical
// host and scheme (argorobots.com, https), which .htaccess itself scopes to the
// production hostname and explicitly leaves local installs alone.
function argo_rewrite_rules(string $htaccess): array
{
    if (!is_file($htaccess)) return [];
    $rules = [];
    $guarded = false;
    foreach (file($htaccess, FILE_IGNORE_NEW_LINES) as $line) {
        $t = trim($line);
        if ($t === '' || $t[0] === '#') continue;
        if (stripos($t, 'RewriteCond') === 0) { $guarded = true; continue; }
        if (stripos($t, 'RewriteRule') !== 0) { $guarded = false; continue; }
        if ($guarded) { $guarded = false; continue; }

        if (!preg_match('/^RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?/i', $t, $m)) continue;
        $rules[] = [
            'pattern' => $m[1],
            'sub'     => $m[2],
            'flags'   => array_map('trim', explode(',', $m[3] ?? '')),
        ];
    }
    return $rules;
}

$rel = ltrim($decoded, '/');   // mod_rewrite matches the path without a leading slash

foreach (argo_rewrite_rules($docRoot . '/.htaccess') as $rule) {
    $flags = $rule['flags'];
    $mods  = in_array('NC', $flags, true) ? 'i' : '';
    if (!preg_match('#' . str_replace('#', '\#', $rule['pattern']) . '#' . $mods, $rel, $m)) continue;

    // [F] forbids outright (database/, vendor/, cron/logs/, api/v1/lib/, ...)
    if (in_array('F', $flags, true)) {
        http_response_code(403);
        exit('Forbidden (router.php, mirroring .htaccess RewriteRule [F])');
    }
    if ($rule['sub'] === '-') {
        if (in_array('L', $flags, true)) break;
        continue;
    }

    $sub = $rule['sub'];
    $sub = str_replace('%{REQUEST_URI}', $path, $sub);
    $sub = preg_replace_callback('/\$(\d)/', fn($d) => $m[(int)$d[1]] ?? '', $sub);

    // [R] is an external redirect.
    $redirect = null;
    foreach ($flags as $f) {
        if ($f === 'R' || stripos($f, 'R=') === 0) {
            $redirect = (int)(substr($f, 2) ?: 302);
        }
    }
    if ($redirect) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $join = str_contains($sub, '?') ? '&' : '?';
        header('Location: ' . $sub . ($qs !== '' && in_array('QSA', $flags, true) ? $join . $qs : ''), true, $redirect);
        exit;
    }

    // Internal rewrite: run the target script in place.
    $targetPath = parse_url($sub, PHP_URL_PATH);
    $targetQs   = parse_url($sub, PHP_URL_QUERY) ?? '';
    $file       = $docRoot . '/' . ltrim($targetPath, '/');
    if (!is_file($file)) break;

    parse_str($targetQs, $newGet);
    if (in_array('QSA', $flags, true)) {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $oldGet);
        $newGet = array_merge($oldGet, $newGet);   // rule's own params win, as Apache does
    }
    $_GET = $newGet;
    $_REQUEST = array_merge($_REQUEST, $newGet);
    $_SERVER['QUERY_STRING']   = http_build_query($newGet);
    $_SERVER['SCRIPT_NAME']    = '/' . ltrim($targetPath, '/');
    $_SERVER['PHP_SELF']       = $_SERVER['SCRIPT_NAME'];
    $_SERVER['SCRIPT_FILENAME'] = $file;

    // PHP sets the working directory to the running script's own directory.
    // Including from here would leave it at the document root, breaking the
    // relative paths some pages use (downloads/index.php reads
    // '../resources/downloads/', for one).
    chdir(dirname($file));
    require $file;
    exit;
}

// --- 3. mod_dir DirectorySlash ----------------------------------------------
// Apache 301s /downloads to /downloads/. The built-in server serves the
// directory's index.php at the unslashed URL instead, leaving the browser to
// resolve href="style.css" as /style.css, so page-level stylesheets 404 while
// the ../resources/ ones still resolve. Looks like a broken stylesheet.
if ($path !== '/' && substr($path, -1) !== '/' && $safe && is_dir($docRoot . $decoded)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $path . '/' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}

// --- 4. 404 -----------------------------------------------------------------
// When nothing matches, the built-in server walks up the tree for an index.php
// and serves it, so a typo'd URL renders the homepage with a 200.
if ($path !== '/' && $safe) {
    $target = $docRoot . $decoded;
    $dir    = rtrim($target, '/');
    $ok = is_file($target)
        || (is_dir($target) && (is_file($dir . '/index.php') || is_file($dir . '/index.html')));
    if (!$ok) {
        http_response_code(404);
        $errorPage = $docRoot . '/error-pages/404.html';
        exit(is_file($errorPage) ? file_get_contents($errorPage) : 'Not Found');
    }
}

return false;
