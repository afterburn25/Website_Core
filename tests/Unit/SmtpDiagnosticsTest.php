<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Mail\SmtpDiagnostics;
use PHPUnit\Framework\TestCase;

final class SmtpDiagnosticsTest extends TestCase
{
    public function testOnlyForwardConfirmedProviderIsProbedAndSocketIsPinned(): void
    {
        $calls = [];
        $diagnostic = new SmtpDiagnostics(
            static fn (string $host) => $host === 'mail.example.test' ? ['192.0.2.10'] : ['192.0.2.99', '192.0.2.10'],
            static fn () => 'provider.example.test.',
            static function ($host, $ip, $port, $secure, $timeout) use (&$calls): array {
                $calls[] = [$host, $ip, $port, $secure, $timeout];
                return ['ok' => $host === 'provider.example.test' && $secure === 'tls', 'message' => 'Probe result'];
            },
        );
        $result = $diagnostic->inspect('mail.example.test');
        self::assertTrue($result['ok']);
        self::assertSame('provider.example.test', $result['provider_host']);
        self::assertCount(4, $calls);
        self::assertSame([465, 587, 465, 587], array_column($calls, 2));
        self::assertSame(['192.0.2.10'], array_unique(array_column($calls, 1)));
        self::assertStringContainsString('provider.example.test using STARTTLS/587', $result['message']);
        self::assertStringContainsString('test authentication before saving', $result['message']);
    }

    public function testUnverifiedPtrIsNeverSuggestedOrContacted(): void
    {
        $names = [];
        $diagnostic = new SmtpDiagnostics(
            static fn (string $host) => [$host === 'mail.example.test' ? '192.0.2.10' : '192.0.2.66'],
            static fn () => 'unverified.example.test',
            static function ($host) use (&$names): array {
                $names[] = $host;
                return ['ok' => false, 'message' => 'Timeout'];
            },
        );
        $result = $diagnostic->inspect('mail.example.test');
        self::assertNull($result['provider_host']);
        self::assertSame(['mail.example.test', 'mail.example.test'], $names);
        self::assertStringNotContainsString('unverified', json_encode($result));
    }

    public function testDeadlineStopsFurtherProbesAndReportsUntestedPairs(): void
    {
        $now = 100.0; $calls = 0;
        $diagnostic = new SmtpDiagnostics(
            static fn () => ['192.0.2.10'], static fn () => false,
            static function ($host, $ip, $port, $secure, $timeout) use (&$now, &$calls): array {
                self::assertLessThanOrEqual(1.5, $timeout);
                $now += 9; $calls++;
                return ['ok' => false, 'message' => 'Timeout'];
            },
            static function () use (&$now): float { return $now; },
        );
        $result = $diagnostic->inspect('mail.example.test');
        self::assertSame(1, $calls);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('Not tested', $result['checks'][1]['message']);
    }

    public function testDnsFailureAndResolverExceptionsAreSafe(): void
    {
        $diagnostic = new SmtpDiagnostics(static fn () => false);
        self::assertStringContainsString('DNS lookup failed', $diagnostic->inspect('mail.example.test')['message']);
        $diagnostic = new SmtpDiagnostics(static function (): never {
            throw new \RuntimeException('sensitive resolver detail');
        });
        self::assertStringNotContainsString('sensitive', json_encode($diagnostic->inspect('mail.example.test')));
    }

    public function testMalformedHostsCannotReachNetworkCallbacks(): void
    {
        $diagnostic = new SmtpDiagnostics(static function (): never {
            self::fail('Invalid hostname reached DNS');
        });
        foreach (['', 'ssl://mail.example.test', 'mail.example.test;other.test', "mail.example.test\r\nAUTH secret", 'user:pass@host'] as $host) {
            self::assertFalse($diagnostic->inspect($host)['ok']);
        }
    }

    public function testIpv6ForwardConfirmationUsesAddressIdentity(): void
    {
        $diagnostic = new SmtpDiagnostics(
            static fn (string $host) => [$host === 'mail.example.test' ? '2001:db8::1' : '2001:db8:0:0:0:0:0:1'],
            static fn () => 'provider.example.test',
            static fn () => ['ok' => false, 'message' => 'Timeout'],
        );
        self::assertSame('provider.example.test', $diagnostic->inspect('mail.example.test')['provider_host']);
    }
}
