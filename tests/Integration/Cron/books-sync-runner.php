<?php
/**
 * Runs cron/argo_books_sync.php for BooksSyncTest.
 *
 * The cron is a separate process from PHPUnit, so on its own it would load .env, read the
 * development database, and push to the live site. Loading .env.testing first points it at
 * the test database: db_connect.php loads .env with createImmutable, which will not
 * overwrite anything already set here. The address of the local API server and the key the
 * test made for it arrive as the first two arguments, and the rest are passed on as the
 * cron's own flags.
 *
 * Usage: php books-sync-runner.php <site url> <api key> [--dry-run] [--since=DATE] [--limit=N]
 */
declare(strict_types=1);

$root = dirname(__DIR__, 3);

require_once $root . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable($root, '.env.testing')->load();

// Refuse to run against anything but the test database, and at anything but a local
// server. This script writes, and the real cron's target is the owner's own books.
if (($_ENV['DB_NAME'] ?? '') !== 'argo_books_test') {
    fwrite(STDERR, "books-sync-runner refused to start: DB_NAME is not argo_books_test\n");
    exit(2);
}
if (!isset($argv[1], $argv[2]) || !str_starts_with($argv[1], 'http://127.0.0.1:')) {
    fwrite(STDERR, "books-sync-runner refused to start: it only runs against a local test server\n");
    exit(2);
}

$_ENV['SITE_URL'] = $argv[1];
$_ENV['ARGO_BOOKS_API_KEY'] = $argv[2];
$argv = array_merge([$argv[0]], array_slice($argv, 3));

require $root . '/cron/argo_books_sync.php';
