<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Mail\SmtpProbe;
use PHPUnit\Framework\TestCase;

final class SmtpProbeTest extends TestCase
{
    private string $directory;
    private array $processes = [];

    protected function setUp(): void
    {
        if (!function_exists('proc_open') || !extension_loaded('openssl')) $this->markTestSkipped('needs proc_open and OpenSSL');
        $this->directory = \WcProcessTmpDirs::make('wc-smtp-');
        mkdir($this->directory, 0700);
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'mail.example.test'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($cert, $public);
        openssl_pkey_export($key, $private);
        file_put_contents($this->directory . '/ca.pem', $public);
        file_put_contents($this->directory . '/server.pem', $public . $private);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as [$process, $pipes]) {
            proc_terminate($process);
            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
        }
    }

    private function server(string $mode): int
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/smtp-probe/server.php', $mode,
            $this->directory . '/server.pem', $this->directory . '/commands.log'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $this->processes[] = [$process, $pipes];
        stream_set_timeout($pipes[1], 3);
        $address = trim((string) fgets($pipes[1]));
        self::assertMatchesRegularExpression('/^127\.0\.0\.1:\d+$/', $address);
        return (int) substr(strrchr($address, ':'), 1);
    }

    private function trustedProbe(int $port, string $secure, string $host = 'mail.example.test'): array
    {
        $code = 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';'
            . 'echo json_encode((new Core\\Mail\\SmtpProbe())->check('
            . var_export($host, true) . ',"127.0.0.1",' . $port . ',' . var_export($secure, true) . ',1.5));';
        $process = proc_open([PHP_BINARY, '-d', 'openssl.cafile=' . $this->directory . '/ca.pem', '-r', $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testRealImplicitTlsAndStarttlsNeverAuthenticateOrSend(): void
    {
        foreach (['ssl', 'tls'] as $secure) {
            $result = $this->trustedProbe($this->server($secure), $secure);
            self::assertTrue($result['ok'], $result['message']);
        }
        $commands = file_get_contents($this->directory . '/commands.log');
        self::assertStringContainsString('EHLO', $commands);
        self::assertStringContainsString('STARTTLS', $commands);
        self::assertStringNotContainsString('AUTH', $commands);
        self::assertStringNotContainsString('MAIL FROM', $commands);
        self::assertStringNotContainsString('RCPT TO', $commands);
    }

    public function testTrustedCertificateWithWrongHostnameStillFails(): void
    {
        $result = $this->trustedProbe($this->server('ssl'), 'ssl', 'wrong.example.test');
        self::assertFalse($result['ok']);
        self::assertStringContainsString('certificate verification failed', $result['message']);
        self::assertStringNotContainsString('fixture.invalid', $result['message']);
    }

    public function testPlainServerIsNotReportedAsEncryptedSmtp(): void
    {
        $result = (new SmtpProbe())->check('mail.example.test', '127.0.0.1', $this->server('plain'), 'tls', 0.5);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('does not advertise STARTTLS', $result['message']);
    }

    public function testSilentSocketRespectsReadDeadline(): void
    {
        $port = $this->server('silent');
        $start = microtime(true);
        $result = (new SmtpProbe())->check('mail.example.test', '127.0.0.1', $port, 'tls', 0.15);
        self::assertFalse($result['ok']);
        self::assertLessThan(0.6, microtime(true) - $start);
        self::assertStringContainsString('No valid SMTP greeting', $result['message']);
    }
}
