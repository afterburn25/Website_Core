<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Install\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Contract test for the installer's license check against the shared mock
 * endpoint (tests/fixtures/license-api). Only HTTP 200 may produce a
 * definitive verdict: a denial blocks install, while every inconclusive
 * outcome (unreachable, 429, 5xx — even a 500 body carrying
 * {"authorized":false} — or malformed JSON) must report denied=false so
 * the install defers enforcement to the scheduled validator instead of
 * refusing a paying customer's install on a transient fault.
 */
final class InstallerLicenseContractTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private int $port = 0;
    private string $logFile = '';

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'e2e-log-') ?: '';
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
        putenv('WC_LICENSE_API_URL');
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    private function startServer(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            $this->markTestSkipped('cannot allocate a local port');
        }
        $name = (string) stream_socket_get_name($probe, false);
        $this->port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($probe);

        $docroot = dirname(__DIR__) . '/fixtures/license-api';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']];
        // Export via putenv + inherit — a reduced env breaks binding on Windows.
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

    /**
     * @return array{0:bool,1:?string,2:bool} [valid, error, explicit-denial]
     */
    private function validate(string $key, string $domain): array
    {
        putenv('WC_LICENSE_API_URL=http://127.0.0.1:' . $this->port . '/api');
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Installer::class, 'validateLicense');
        $method->setAccessible(true);
        return $method->invoke($installer, $key, $domain);
    }

    public function testAuthorizedLicensePasses(): void
    {
        [$valid, $error, $denied] = $this->validate('GOOD-KEY-1', 'e2e.example.com');
        $this->assertTrue($valid);
        $this->assertNull($error);
        $this->assertFalse($denied);
    }

    public function testUnregisteredDomainIsDenialAndBlocksInstall(): void
    {
        [$valid, $error, $denied] = $this->validate('GOOD-KEY-1', 'wrong.example.com');
        $this->assertFalse($valid);
        $this->assertSame('Domain not registered', $error);
        $this->assertTrue($denied, 'a definitive denial must block installation');
    }

    public function testUnknownLicenseIsDenial(): void
    {
        [$valid, , $denied] = $this->validate('NOPE-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertTrue($denied);
    }

    public function testServerErrorJsonIsInconclusiveNotDenial(): void
    {
        // ERROR-KEY returns HTTP 500 WITH an authorized:false JSON body —
        // the installer must read the status line, not trust the payload.
        [$valid, , $denied] = $this->validate('ERROR-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'HTTP 500 is inconclusive even when its JSON body says authorized:false');
    }

    public function testRateLimitedIsInconclusiveNotDenial(): void
    {
        [$valid, , $denied] = $this->validate('RATELIMIT-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'HTTP 429 is a transient throttle, never a denial');
    }

    public function testMalformedResponseIsInconclusiveNotDenial(): void
    {
        [$valid, , $denied] = $this->validate('BROKEN-KEY', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied);
    }

    public function testUnreachableServerIsInconclusiveNotDenial(): void
    {
        putenv('WC_LICENSE_API_URL=http://127.0.0.1:2/api');
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Installer::class, 'validateLicense');
        $method->setAccessible(true);
        [$valid, , $denied] = $method->invoke($installer, 'GOOD-KEY-1', 'e2e.example.com');
        $this->assertFalse($valid);
        $this->assertFalse($denied, 'an unreachable license server must not block or deny install');
    }
}
