<?php
/**
 * Router for the throwaway PHP server the documentation-sample tests run
 * against. It stands in for the .htaccess rewrite, which the built-in server
 * does not read, so /v1/... reaches the same front controller it would in
 * production.
 *
 * The server is a separate process from PHPUnit, so it would otherwise load
 * .env and talk to the development database, while the tests create their key
 * in the test database. Loading .env.testing first fixes that: db_connect.php
 * loads .env with createImmutable, which by phpdotenv's contract will not
 * overwrite anything already set here. Same trick as tests/bootstrap.php.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 3);

require_once $root . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable($root, '.env.testing')->load();

// Refuse to serve against anything but the test database. Without this a missing .env.testing would silently point the samples at real data, and they create records.
if (($_ENV['DB_NAME'] ?? '') !== 'argo_books_test') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'sample harness refused to start: DB_NAME is not argo_books_test']]);
    return true;
}

$uri = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/v1(/.*)?$#', (string) $uri)) {
    require $root . '/api/v1/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => ['message' => 'not part of the sample harness: ' . $uri]]);

return true;
