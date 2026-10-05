<?php
declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Runs the public API on a throwaway local PHP server, for tests that have to reach it over
 * HTTP from another process: the documentation samples, and the cron that pushes Argo Books'
 * own sales into it.
 *
 * The server is a separate process from PHPUnit. The router it uses loads .env.testing, so it
 * talks to the test database, and refuses to serve against any other.
 */
trait LocalApiServer
{
    /** @var resource|null */
    private static $apiServer = null;

    /** Starts the server and returns its address, without a trailing slash or the /v1 path. */
    protected static function startApiServer(): string
    {
        $port = self::freeApiPort();
        $router = PROJECT_ROOT . '/tests/Integration/PublicApi/sample-router.php';
        $nowhere = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

        self::$apiServer = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', PROJECT_ROOT, $router],
            [1 => ['file', $nowhere, 'w'], 2 => ['file', $nowhere, 'w']],
            $pipes
        );

        // Wait for it to accept connections rather than guessing at a sleep.
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                return "http://127.0.0.1:$port";
            }
            usleep(50000);
        }

        self::fail('the local API server never started on port ' . $port);
    }

    protected static function stopApiServer(): void
    {
        if (is_resource(self::$apiServer)) {
            proc_terminate(self::$apiServer);
            proc_close(self::$apiServer);
        }
        self::$apiServer = null;
    }

    private static function freeApiPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
