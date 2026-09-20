<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

require_once PROJECT_ROOT . '/vendor/autoload.php';

// Load .env.testing first so its values land in $_ENV with mutable rights.
// db_connect.php below will then load .env with createImmutable, which
// (per phpdotenv contract) won't overwrite values already set here.
$dotenv = Dotenv\Dotenv::createMutable(PROJECT_ROOT, '.env.testing');
$dotenv->load();

// Hard guard: refuse to run tests against any non-test database.
if (($_ENV['DB_NAME'] ?? '') !== 'argo_books_test') {
    fwrite(STDERR, "Refusing to run: DB_NAME must be 'argo_books_test' (got '" . ($_ENV['DB_NAME'] ?? '') . "')\n");
    exit(1);
}

require_once PROJECT_ROOT . '/env_helper.php';
require_once PROJECT_ROOT . '/db_connect.php';
require_once PROJECT_ROOT . '/config/pricing.php';
require_once PROJECT_ROOT . '/license_functions.php';
require_once PROJECT_ROOT . '/api/portal/portal-helper.php';
require_once PROJECT_ROOT . '/api/portal/quote-helper.php';
require_once PROJECT_ROOT . '/email_sender.php';
require_once PROJECT_ROOT . '/api/portal/webhooks/_square_helpers.php';
require_once PROJECT_ROOT . '/api/portal/webhooks/_stripe_refund_db.php';
require_once PROJECT_ROOT . '/api/portal/_refund_helpers.php';
require_once PROJECT_ROOT . '/cron/lib/renewal_helpers.php';
require_once PROJECT_ROOT . '/cron/lib/purge_helpers.php';

// Public API (/v1). API_TESTING must be defined BEFORE the lib loads, so
// api_json() throws ApiResponseSent instead of ending the process and every
// validator can be exercised in-process.
define('API_TESTING', true);
require_once PROJECT_ROOT . '/api/v1/lib/bootstrap.php';
require_once PROJECT_ROOT . '/api/v1/lib/definitions.php';
require_once PROJECT_ROOT . '/api/v1/lib/events.php';
require_once PROJECT_ROOT . '/api/v1/lib/batches.php';
require_once PROJECT_ROOT . '/api/v1/lib/account.php';
require_once PROJECT_ROOT . '/api/v1/lib/webhook_endpoints.php';

// db_connect.php assigns $pdo at "top-level" of the included file, but when
// included from a function/method scope (e.g. PHPUnit's TestRunner) that
// "top-level" is actually local to the caller. Promote it to $GLOBALS so
// production code paths that do `global $pdo;` see the connection.
$GLOBALS['pdo'] = $pdo ?? null;

if ($GLOBALS['pdo'] === null) {
    fwrite(STDERR, "Test DB connection failed: verify argo_books_test exists and credentials in .env.testing match your local MySQL.\n");
    exit(1);
}
