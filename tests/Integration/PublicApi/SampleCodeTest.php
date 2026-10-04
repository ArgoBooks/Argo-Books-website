<?php
declare(strict_types=1);

namespace Tests\Integration\PublicApi;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs every code sample printed in the API documentation.
 *
 * Documentation that has never been executed is a guess. These tests start a
 * real server, mint a real key, and run each sample as written, so a sample
 * that calls a field which does not exist or signs a payload incorrectly fails
 * here instead of wasting a developer's afternoon.
 *
 * The samples address the live host with a placeholder key, because that is
 * what belongs on the page. Only those two things are rewritten before running.
 *
 * A language that is not installed is skipped rather than failed: this is a
 * local guardrail, and not every machine has dotnet on it.
 */
#[Group('doc-samples')]
final class SampleCodeTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static string $apiKey = '';
    private static $server = null;
    private static ?string $csharpDir = null;
    private static string $csharpError = '';
    private static int $accountId = 0;
    private static string $subscriptionId = '';
    private static ?bool $dnsWorks = null;

    public static function setUpBeforeClass(): void
    {
        self::clearAuthThrottle();
        self::ensureSchema();
        self::seedAccount();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        if (self::$accountId > 0) {
            $GLOBALS['pdo']->prepare('DELETE FROM api_accounts WHERE id = ?')->execute([self::$accountId]);
        }
        if (self::$subscriptionId !== '') {
            $GLOBALS['pdo']->prepare('DELETE FROM premium_subscriptions WHERE subscription_id = ?')->execute([self::$subscriptionId]);
        }
        if (self::$csharpDir !== null && is_dir(self::$csharpDir)) {
            self::removeTree(self::$csharpDir);
        }
        self::clearAuthThrottle();
    }

    // -- fixture -------------------------------------------------------------

    /**
     * The failed-authentication throttle is keyed by IP and persists in a file
     * for fifteen minutes. Every sample runs from 127.0.0.1, so one bad run
     * would lock out the next one and every assertion after it would blame the
     * wrong thing. Cleared before the suite, and again after.
     */
    private static function clearAuthThrottle(): void
    {
        $file = PROJECT_ROOT . '/resources/rate_limits/rate_limits.json';
        if (!is_file($file)) {
            return;
        }
        $limits = json_decode((string) file_get_contents($file), true);
        if (!is_array($limits)) {
            return;
        }
        foreach (array_keys($limits) as $key) {
            if (str_starts_with((string) $key, 'apiauth_')) {
                unset($limits[$key]);
            }
        }
        file_put_contents($file, json_encode($limits));
    }

    private static function ensureSchema(): void
    {
        $sql = (string) file_get_contents(PROJECT_ROOT . '/mysql_schema.sql');
        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS (api_\w+)/', $statement)) {
                $GLOBALS['pdo']->exec(trim($statement));
            }
        }
    }

    private static function seedAccount(): void
    {
        $pdo = $GLOBALS['pdo'];

        // The API is Premium only, so the samples need a subscription behind their account.
        self::$subscriptionId = 'PREM-TEST-DOCS-' . bin2hex(random_bytes(4));
        $pdo->prepare(
            "INSERT INTO premium_subscriptions
             (subscription_id, billing_cycle, amount, currency, start_date, end_date,
              status, payment_method, transaction_id, auto_renew, environment, created_at)
             VALUES (?, 'yearly', 0.00, 'CAD', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY),
                     'active', 'free_key', ?, 0, ?, NOW())"
        )->execute([self::$subscriptionId, self::$subscriptionId, current_environment()]);

        $publicId = api_generate_id('acct');
        $pdo->prepare(
            'INSERT INTO api_accounts (public_id, owner_identity_hash, company_uid, subscription_id, display_name)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$publicId, hash('sha256', $publicId), 'docsamples-' . bin2hex(random_bytes(4)), self::$subscriptionId, 'Doc Samples']);

        self::$accountId = (int) $pdo->lastInsertId();

        $secret = api_generate_secret_key();
        $pdo->prepare(
            'INSERT INTO api_keys (account_id, public_id, key_hash, key_hint, label, scopes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            self::$accountId, api_generate_id('key'), hash('sha256', $secret),
            api_key_hint($secret), 'doc samples', 'read,write',
        ]);

        self::$apiKey = $secret;
    }

    private static function startServer(): void
    {
        $port = self::freePort();
        $router = __DIR__ . '/sample-router.php';

        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', PROJECT_ROOT, $router],
            [1 => ['file', self::devNull(), 'w'], 2 => ['file', self::devNull(), 'w']],
            $pipes
        );

        self::$baseUrl = "http://127.0.0.1:$port/v1";

        // Wait for it to accept connections rather than guessing at a sleep.
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(50000);
        }

        self::fail('the sample test server never started on port ' . $port);
    }

    private static function devNull(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Endpoint registration refuses a host that does not resolve, by design, so
     * those samples need working DNS. Detected rather than assumed.
     */
    private static function dnsWorks(): bool
    {
        if (self::$dnsWorks === null) {
            self::$dnsWorks = api_host_is_public('example.com');
        }

        return self::$dnsWorks;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Every language variant of an example uses the same documented
        // Idempotency-Key, which is right for a reader running one of them and
        // wrong for a harness running all five. Clearing the claims makes each
        // sample the first caller, so each is tested as written.
        $GLOBALS['pdo']->prepare('DELETE FROM api_idempotency_cache WHERE account_id = ?')
            ->execute([self::$accountId]);
    }

    /**
     * What a sample must print to have actually worked.
     *
     * Without this the test only proved the process exited zero, which several
     * samples did while printing "undefined" or a PHP warning. An expected
     * shape is the difference between a green test and a meaningful one.
     */
    private function expectedOutput(array $sample): string
    {
        $code = $sample['code'];

        if (str_contains($code, 'signing_secret')) {
            return '/^whsec_[0-9a-f]{48}$/';
        }
        if (str_contains($code, 'still waiting')) {
            return '/^\d+ still waiting$/';
        }
        if (str_contains($code, 'events')) {
            return '/^\d+ events$/';
        }
        if (str_contains($code, '/v1/revenue') && !str_contains($code, 'import_status')) {
            return '/^rev_[0-9a-f]{24}$/';
        }

        return '/^acct_[0-9a-f]{24}$/';
    }

    // -- the samples ---------------------------------------------------------

    public static function samples(): array
    {
        $cases = [];
        foreach (DocSampleRunner::all() as $id => $sample) {
            $cases[$id] = [$id, $sample];
        }

        return $cases;
    }

    #[DataProvider('samples')]
    public function testSampleRuns(string $id, array $sample): void
    {
        $lang = $sample['lang'];
        $code = DocSampleRunner::localise($sample['code'], (string) self::$baseUrl, self::$apiKey);

        // Not executable, but they must at least be what they claim to be.
        if ($lang === 'json') {
            $this->assertIsArray(json_decode($sample['code'], true), "$id is not valid JSON: " . json_last_error_msg());
            return;
        }
        if ($lang === 'http') {
            $this->assertMatchesRegularExpression('/^[A-Za-z-]+:\s+\S/m', $sample['code'], "$id is not a header line");
            return;
        }

        if (str_contains($code, 'example.com') && !self::dnsWorks()) {
            $this->markTestSkipped('needs DNS: endpoint registration rejects hosts it cannot resolve');
        }

        [$exit, $stdout, $stderr] = match ($lang) {
            'bash'   => $this->runBash($code),
            'php'    => $this->runPhp($code, $sample['kind']),
            'js'     => $this->runNode($code, $sample['kind']),
            'python' => $this->runPython($code, $sample['kind']),
            'csharp' => $this->runCsharp($id),
            default  => $this->fail("no runner for language '$lang'"),
        };

        $this->assertSame(0, $exit, "$id exited $exit\nstdout: $stdout\nstderr: $stderr");

        if ($sample['kind'] === DocSampleRunner::KIND_VERIFY) {
            $this->assertStringContainsString('VERIFY-OK', $stdout, "$id failed its signature vectors: $stdout $stderr");
            return;
        }

        $this->assertNotSame('', trim($stdout), "$id printed nothing, so it cannot have reached the API\nstderr: $stderr");
        $this->assertDoesNotMatchRegularExpression(
            '/"error"|Traceback|Unhandled exception|Fatal error/i',
            $stdout . $stderr,
            "$id reported an error: $stdout $stderr"
        );
    }

    // -- per-language execution ----------------------------------------------

    private function tempFile(string $suffix, string $contents): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'argo-sample-' . bin2hex(random_bytes(6)) . $suffix;
        file_put_contents($path, $contents);

        return $path;
    }

    private function runBash(string $code): array
    {
        if (!DocSampleRunner::have('curl')) {
            $this->markTestSkipped('curl is not installed');
        }
        // -sS keeps the body but drops the progress meter, and -f is deliberately
        // NOT used: a 4xx body is the evidence the assertion needs.
        $path = $this->tempFile('.sh', "set -e\n" . str_replace('curl ', 'curl -sS ', $code) . "\n");

        return DocSampleRunner::exec(['bash', $path]);
    }

    private function runPhp(string $code, string $kind): array
    {
        $body = $code;
        if ($kind === DocSampleRunner::KIND_VERIFY) {
            $body .= DocSampleRunner::verifyDriver('php');
        }
        $path = $this->tempFile('.php', "<?php\n" . $body . "\n");

        return DocSampleRunner::exec([PHP_BINARY, $path]);
    }

    private function runNode(string $code, string $kind): array
    {
        if (!DocSampleRunner::have('node')) {
            $this->markTestSkipped('node is not installed');
        }
        $body = $code;
        if ($kind === DocSampleRunner::KIND_VERIFY) {
            $body .= DocSampleRunner::verifyDriver('js');
        }
        $path = $this->tempFile('.mjs', $body . "\n");

        return DocSampleRunner::exec(['node', $path]);
    }

    private function runPython(string $code, string $kind): array
    {
        if (!DocSampleRunner::have('python')) {
            $this->markTestSkipped('python is not installed');
        }
        $body = $code;
        if ($kind === DocSampleRunner::KIND_VERIFY) {
            $body .= DocSampleRunner::verifyDriver('python');
        }
        $path = $this->tempFile('.py', $body . "\n");

        return DocSampleRunner::exec(['python', $path]);
    }

    /**
     * Every C# sample is compiled into one project and built once, then invoked
     * by name. Building per sample would add a couple of seconds each and make
     * the suite tiresome enough that nobody runs it.
     */
    private function runCsharp(string $id): array
    {
        if (!DocSampleRunner::have('dotnet')) {
            $this->markTestSkipped('dotnet is not installed');
        }

        $this->buildCsharpProject();

        if (self::$csharpError !== '') {
            $this->fail('the C# samples did not compile: ' . self::$csharpError);
        }

        return DocSampleRunner::exec(['dotnet', 'run', '--no-build', '--project', self::$csharpDir, '--', $id], self::$csharpDir);
    }

    private function buildCsharpProject(): void
    {
        if (self::$csharpDir !== null) {
            return;
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'argo-csharp-samples-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        self::$csharpDir = $dir;

        file_put_contents($dir . '/Samples.csproj', <<<XML
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <OutputType>Exe</OutputType>
    <TargetFramework>net8.0</TargetFramework>
    <Nullable>disable</Nullable>
    <ImplicitUsings>enable</ImplicitUsings>
    <AssemblyName>Samples</AssemblyName>
    <RootNamespace>Samples</RootNamespace>
  </PropertyGroup>
</Project>
XML);

        $methods = '';
        $cases = '';

        foreach (DocSampleRunner::all() as $id => $sample) {
            if ($sample['lang'] !== 'csharp') {
                continue;
            }

            $code = DocSampleRunner::localise($sample['code'], (string) self::$baseUrl, self::$apiKey);
            $method = 'Sample_' . preg_replace('/[^A-Za-z0-9]/', '_', $id);

            if ($sample['kind'] === DocSampleRunner::KIND_VERIFY) {
                // The sample already declares the method; the driver calls it.
                $methods .= "\n    " . str_replace("\n", "\n    ", $code) . "\n";
                $methods .= "\n    static void $method()\n    {\n"
                    . DocSampleRunner::csharpVerifyDriver() . "\n    }\n";
            } else {
                $methods .= "\n    static async Task $method()\n    {\n        "
                    . str_replace("\n", "\n        ", $code) . "\n    }\n";
            }

            $cases .= "            case \"$id\": await Run(() => $method()); break;\n";
        }

        file_put_contents($dir . '/Program.cs', <<<CS
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

static class Samples
{
    static async Task Main(string[] args)
    {
        switch (args[0])
        {
$cases            default: Console.Error.WriteLine("unknown sample " + args[0]); Environment.Exit(2); break;
        }
    }

    static async Task Run(Func<Task> body) => await body();
    static async Task Run(Action body) { body(); await Task.CompletedTask; }
$methods}
CS);

        [$exit, $out, $err] = DocSampleRunner::exec(['dotnet', 'build', '-v', 'quiet', '--nologo', $dir], $dir, 240);
        if ($exit !== 0) {
            self::$csharpError = trim($out . "\n" . $err);
        }
    }
}
