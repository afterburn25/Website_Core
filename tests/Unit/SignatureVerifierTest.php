<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Update\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class SignatureVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('libsodium not loaded in this PHP build');
        }
    }

    private function manifest(): array
    {
        return [
            'schema' => 1, 'id' => 'acme/x', 'type' => 'addon',
            'name' => 'X', 'version' => '1.0.0', 'license' => 'MIT',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
        ];
    }

    public function testSignedByTrustedKeyAccepted(): void
    {
        $kp = SignatureVerifier::generateKeypair();
        $v = new SignatureVerifier([$kp['public']], false);
        $signed = SignatureVerifier::signManifest($this->manifest(), $kp['secret']);
        [$ok, $err] = $v->verify($signed);
        $this->assertTrue($ok, (string) $err);
    }

    public function testTamperedManifestRejected(): void
    {
        $kp = SignatureVerifier::generateKeypair();
        $v = new SignatureVerifier([$kp['public']], true);
        $signed = SignatureVerifier::signManifest($this->manifest(), $kp['secret']);
        $signed['version'] = '9.9.9'; // tamper after signing
        [$ok, $err] = $v->verify($signed);
        $this->assertFalse($ok);
        $this->assertStringContainsString('signature', (string) $err);
    }

    public function testUnknownSignerRejected(): void
    {
        $attacker = SignatureVerifier::generateKeypair();
        $trusted = SignatureVerifier::generateKeypair();
        $v = new SignatureVerifier([$trusted['public']], true);
        $signed = SignatureVerifier::signManifest($this->manifest(), $attacker['secret']);
        [$ok] = $v->verify($signed);
        $this->assertFalse($ok);
    }

    public function testSignedButNoKeysConfiguredRejected(): void
    {
        $kp = SignatureVerifier::generateKeypair();
        $v = new SignatureVerifier([], true);
        $signed = SignatureVerifier::signManifest($this->manifest(), $kp['secret']);
        [$ok, $err] = $v->verify($signed);
        $this->assertFalse($ok);
        $this->assertStringContainsString('no trusted keys', (string) $err);
    }

    public function testUnsignedPolicy(): void
    {
        $permissive = new SignatureVerifier([], true);
        [$ok] = $permissive->verify($this->manifest());
        $this->assertTrue($ok);

        $strict = new SignatureVerifier([], false);
        [$ok, $err] = $strict->verify($this->manifest());
        $this->assertFalse($ok);
        $this->assertStringContainsString('Unsigned', (string) $err);
    }

    public function testMalformedSignatureRejected(): void
    {
        $v = new SignatureVerifier([SignatureVerifier::generateKeypair()['public']], true);
        $m = $this->manifest() + ['signature' => 'not-base64!!'];
        [$ok] = $v->verify($m);
        $this->assertFalse($ok);
    }
}
