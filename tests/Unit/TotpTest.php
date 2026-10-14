<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Security\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function testBase32RoundTrip(): void
    {
        $data = random_bytes(20);
        $this->assertSame($data, Totp::base32Decode(Totp::base32Encode($data)));
    }

    public function testSecretGeneration(): void
    {
        $a = Totp::generateSecret();
        $b = Totp::generateSecret();
        $this->assertNotSame($a, $b);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $a);
    }

    public function testCodeIsSixDigitsAndVerifies(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::code($secret, 1700000000);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $counter = Totp::verify($secret, $code, -1, 1700000000);
        $this->assertNotNull($counter);
    }

    public function testReplayProtection(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::code($secret, 1700000000);
        $counter = Totp::verify($secret, $code, -1, 1700000000);
        $this->assertNotNull($counter);
        // Same code at same counter must not be accepted again.
        $this->assertNull(Totp::verify($secret, $code, $counter, 1700000000));
    }

    public function testRejectsMalformedCodes(): void
    {
        $secret = Totp::generateSecret();
        $this->assertNull(Totp::verify($secret, 'abcdef', -1, 1700000000));
        $this->assertNull(Totp::verify($secret, '12345', -1, 1700000000));
        $this->assertNull(Totp::verify($secret, '', -1, 1700000000));
    }

    public function testWindowAllowsAdjacentCounter(): void
    {
        $secret = Totp::generateSecret();
        $past = Totp::code($secret, 1700000000 - 30);
        $this->assertNotNull(Totp::verify($secret, $past, -1, 1700000000));
        $far = Totp::code($secret, 1700000000 - 300);
        $this->assertNull(Totp::verify($secret, $far, -1, 1700000000));
    }

    public function testOtpauthUri(): void
    {
        $uri = Totp::otpauthUri('My Site', 'admin', 'ABCDEF234567');
        $this->assertStringContainsString('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=ABCDEF234567', $uri);
        $this->assertStringContainsString('issuer=My%20Site', $uri);
    }
}
