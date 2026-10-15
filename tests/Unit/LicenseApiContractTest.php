<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\License\LicenseValidation;
use PHPUnit\Framework\TestCase;

/**
 * Real HTTP contract test: spawns the PHP built-in server hosting the mock
 * Cajunwebpro.com endpoint (tests/fixtures/license-api) and exercises
 * LicenseValidation::validate() over the wire — verifying request shape and
 * the three response classes: authorized, explicit denial, inconclusive.
 */
final class LicenseApiContractTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private int $port = 0;
    private string $logFile = '';
    private string $configFile = '';

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->logFile = tempnam(sys_get_temp_dir(), 'e2e-log-');
        $this->configFile = tempnam(sys_get_temp_dir(), 'e2e-cfg-');
        $this->startServer();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        putenv('E2E_LOG');
        foreach ([$this->logFile, $this->configFile] as $f) {
            if (is_string($f) && is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function startServer(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            $this->markTestSkipped('cannot allocate a local port');
        }
        $this->port = (int) substr(stream_socket_get_name($probe, false), strrpos(stream_socket_get_name($probe, false), ':') + 1);
        fclose($probe);

        $docroot = dirname(__DIR__) . '/fixtures/license-api';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']];
        // Passing a reduced env to the child breaks socket binding on Windows
        // ($_ENV can be empty). Export via putenv + inherit instead.
        putenv('E2E_LOG=' . $this->logFile);
        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $docroot],
            $descriptors,
            $pipes
        );
        if (!is_resource($this->server)) {
            $this->markTestSkipped('php -S could not start');
        }
        $deadline = microtime(true) + 8;
        while (microtime(true) < $deadline) {
            $c = @fsockopen('127.0.0.1', $this->port, $e, $s, 0.25);
            if (is_resource($c)) {
                fclose($c);
                return;
            }
            $status = proc_get_status($this->server);
            if (!$status['running']) {
                $this->markTestSkipped('mock license server exited on start');
            }
            usleep(100_000);
        }
        $this->markTestSkipped('mock license server did not accept connections');
    }

    private function license(): LicenseValidation
    {
        file_put_contents($this->configFile, sprintf(
            '<?php return ["license" => ["api_url" => "http://127.0.0.1:%d/api"]];',
            $this->port
        ));
        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        return new LicenseValidation($db, new Config($this->configFile));
    }

    private function lastRequest(): array
    {
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotEmpty($lines, 'mock server received no request');
        $data = json_decode(end($lines), true);
        $this->assertIsArray($data);
        return $data;
    }

    public function testAuthorizedResponseValidatesAndSendsWellFormedPayload(): void
    {
        $license = $this->license();
        [$valid, $error, $denied] = $license->validate('GOOD-KEY-1', 'e2e.example.com');

        $this->assertTrue($valid);
        $this->assertNull($error);
        $this->assertFalse($denied);

        $request = $this->lastRequest();
        $this->assertSame('GOOD-KEY-1', $request['license_key']);
        $this->assertSame('e2e.example.com', $request['domain']);

        $status = $license->status();
        $this->assertSame(1, $status['license_valid']);
    }

    public function testUnregisteredDomainIsExplicitDenial(): void
    {
        $license = $this->license();
        [$valid, $error, $denied] = $license->validate('GOOD-KEY-1', 'wrong.example.com');

        $this->assertFalse($valid);
        $this->assertSame('Domain not registered', $error);
        $this->assertTrue($denied, 'domain denial must be flagged as a real server denial');
        $this->assertSame(0, $license->status()['license_valid']);
    }

    public function testUnknownLicenseIsExplicitDenial(): void
    {
        $license = $this->license();
        [$valid, , $denied] = $license->validate('NOPE-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertTrue($denied);
    }

    public function testMalformedResponseIsInconclusiveNotDenial(): void
    {
        $license = $this->license();
        [$valid, , $denied] = $license->validate('BROKEN-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'malformed responses must not be treated as explicit denial');
    }

    public function testRateLimitedIsInconclusiveNotDenial(): void
    {
        $license = $this->license();
        [$valid, , $denied] = $license->validate('RATELIMIT-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'HTTP 429 must not deactivate — it is a transient throttle');
    }

    public function testServerErrorIsInconclusiveNotDenial(): void
    {
        $license = $this->license();
        [$valid, , $denied] = $license->validate('ERROR-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'HTTP 500 must not deactivate — the server could not evaluate the license');
    }

    public function testUnreachableServerIsInconclusiveNotDenial(): void
    {
        file_put_contents($this->configFile,
            '<?php return ["license" => ["api_url" => "http://127.0.0.1:2/api"]];');
        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $license = new LicenseValidation($db, new Config($this->configFile));

        [$valid, , $denied] = $license->validate('GOOD-KEY-1', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'network failures must not deactivate the site');
    }

    public function testSuccessfulValidationClearsDeactivatedFlag(): void
    {
        $license = $this->license();
        $license->storeLicenseKey('GOOD-KEY-1', 'e2e.example.com');
        $license->deactivate();
        $this->assertFalse($license->isValid());

        [$valid] = $license->validate('GOOD-KEY-1', 'e2e.example.com');
        $this->assertTrue($valid);
        $this->assertSame(0, (int) $license->status()['license_deactivated']);
    }
}
