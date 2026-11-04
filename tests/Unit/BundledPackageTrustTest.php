<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Install\BundledPackageTrust;
use Core\Update\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class BundledPackageTrustTest extends TestCase
{
    private string $tmp;
    private string $originalKeys;

    protected function setUp(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('The Ed25519 signer is required');
        }
        $this->tmp = sys_get_temp_dir() . '/wc-bundle-trust-' . bin2hex(random_bytes(5));
        mkdir($this->tmp . '/src', 0750, true);
        mkdir($this->tmp . '/assets', 0750, true);
        $before = getenv('WC_INSTALL_TRUST_PUBLIC_KEYS');
        $this->originalKeys = is_string($before) ? $before : '';
        file_put_contents($this->tmp . '/src/Provider.php', '<?php /* synthetic provider */');
        file_put_contents($this->tmp . '/assets/hero.webp', 'synthetic-image-bytes');
    }

    protected function tearDown(): void
    {
        putenv('WC_INSTALL_TRUST_PUBLIC_KEYS=' . $this->originalKeys);
        if (!is_dir($this->tmp)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
    }

    /** @return array{0:array<string,mixed>,1:array{public:string,secret:string}} */
    private function signed(): array
    {
        $keypair = SignatureVerifier::generateKeypair();
        $manifest = [
            'schema' => 1,
            'id' => 'acme/catering',
            'type' => 'industry',
            'name' => 'Synthetic Catering',
            'version' => '2.1.2',
            'license' => 'proprietary',
            'license_key' => 'TEST-LICENSE-ONLY-123',
            'requires' => ['core' => '>=2.6.2,<3.0.0', 'api' => 1],
            'integrity' => [
                'files' => [
                    'src/Provider.php' => hash_file('sha256', $this->tmp . '/src/Provider.php'),
                    'assets/hero.webp' => hash_file('sha256', $this->tmp . '/assets/hero.webp'),
                ],
            ],
        ];
        $manifest = SignatureVerifier::signManifest($manifest, $keypair['secret']);
        file_put_contents($this->tmp . '/addon.json', json_encode($manifest));
        return [$manifest, $keypair];
    }

    public function testSignedLicensedBundleAcceptedOnlyWithMatchingIndependentKey(): void
    {
        [$manifest, $keys] = $this->signed();
        $this->assertNull(BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
        $this->assertStringContainsString('signature', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [SignatureVerifier::generateKeypair()['public']]));
        $this->assertStringContainsString('trust keys', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, []));
    }

    public function testAlteredLicenseAndUnsignedManifestAreRejected(): void
    {
        [$manifest, $keys] = $this->signed();
        $manifest['license_key'] = 'FORGED-LICENSE-ONLY-123';
        $this->assertStringContainsString('signature', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
        unset($manifest['signature']);
        $this->assertStringContainsString('signature', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
    }

    public function testTamperedFileIsRejectedEvenThoughSignatureStillMatches(): void
    {
        [$manifest, $keys] = $this->signed();
        file_put_contents($this->tmp . '/src/Provider.php', '<?php /* ALTERED */');
        $this->assertStringContainsString('checksum', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
    }

    public function testAdditionalUnsignedFileIsRejected(): void
    {
        [$manifest, $keys] = $this->signed();
        file_put_contents($this->tmp . '/src/Extra.php', '<?php /* injected */');
        $this->assertStringContainsString('unsigned extra', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
    }

    public function testMissingProtectedSourceIsRejected(): void
    {
        [$manifest, $keys] = $this->signed();
        unlink($this->tmp . '/assets/hero.webp');
        $this->assertStringContainsString('missing a signed', (string)
            BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
    }

    public function testSymlinkIsRejectedBeforePayloadChecks(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('No symlink support');
        }
        [$manifest, $keys] = $this->signed();
        $outside = sys_get_temp_dir() . '/wc-bundle-external-' . bin2hex(random_bytes(4));
        file_put_contents($outside, 'outside-sensitive-file');
        if (!@symlink($outside, $this->tmp . '/src/Link.php')) {
            @unlink($outside);
            $this->markTestSkipped('Symlink creation unavailable');
        }
        try {
            $this->assertStringContainsString('symbolic link', (string)
                BundledPackageTrust::verify($this->tmp, $manifest, [$keys['public']]));
            $this->assertSame('outside-sensitive-file', file_get_contents($outside));
        } finally {
            @unlink($outside);
        }
    }

    public function testKeylessTestBundleRemainsDistinctFromCommercialInstall(): void
    {
        [$manifest] = $this->signed();
        unset($manifest['license_key'], $manifest['signature'], $manifest['integrity']);
        $this->assertNull(BundledPackageTrust::verify($this->tmp, $manifest, []));
    }

    public function testInstallerPublicKeyParsingDoesNotAcceptMalformedRoots(): void
    {
        $keys = SignatureVerifier::generateKeypair();
        putenv('WC_INSTALL_TRUST_PUBLIC_KEYS=' . $keys['public']);
        $this->assertSame([$keys['public']], BundledPackageTrust::trustedPublicKeys());
        putenv('WC_INSTALL_TRUST_PUBLIC_KEYS=wrong-key');
        $this->assertSame([], BundledPackageTrust::trustedPublicKeys());
        putenv('WC_INSTALL_TRUST_PUBLIC_KEYS=' . $keys['public'] . ',invalid');
        $this->assertSame([], BundledPackageTrust::trustedPublicKeys());
    }
}
