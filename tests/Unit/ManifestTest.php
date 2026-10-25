<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Modules\Manifest;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    private function valid(): array
    {
        return [
            'schema'  => 1,
            'id'      => 'acme/contact-form',
            'type'    => 'addon',
            'name'    => 'Contact Form',
            'version' => '1.0.0',
            'license' => 'MIT',
            'requires' => ['core' => '>=0.0.1', 'api' => 1, 'php' => '>=8.4'],
            'dependencies' => [],
            'entry'   => 'src/Provider.php',
            'provider' => 'Acme\\ContactForm\\Provider',
            'psr4'    => ['Acme\\ContactForm\\' => 'src/'],
            'capabilities' => ['contact_form.forms.view', 'contact_form.forms.manage'],
        ];
    }

    public function testValidManifestPasses(): void
    {
        $this->assertSame([], Manifest::validate($this->valid()));
    }

    public function testRejectsBadId(): void
    {
        $m = $this->valid();
        $m['id'] = 'NoSlash';
        $this->assertNotEmpty(Manifest::validate($m));
        $m['id'] = 'UPPER/case';
        $this->assertNotEmpty(Manifest::validate($m));
        $m['id'] = 'a/b';
        $m['capabilities'] = ['b.forms.view', 'b.forms.manage'];
        $this->assertSame([], Manifest::validate($m));
    }

    public function testRejectsUnknownType(): void
    {
        $m = $this->valid();
        $m['type'] = 'plugin';
        $this->assertNotEmpty(Manifest::validate($m));
    }

    public function testRejectsBadVersion(): void
    {
        $m = $this->valid();
        $m['version'] = 'v1';
        $this->assertNotEmpty(Manifest::validate($m));
    }

    public function testRequiresLicense(): void
    {
        $m = $this->valid();
        unset($m['license']);
        $this->assertNotEmpty(Manifest::validate($m));
    }

    public function testCompatibility(): void
    {
        $m = $this->valid();
        $this->assertSame([], Manifest::compatible($m, '0.1.0-dev', 1, '8.4.25'));
        $m['requires']['api'] = 2;
        $this->assertNotEmpty(Manifest::compatible($m, '0.1.0-dev', 1, '8.4.25'));
        $m['requires']['api'] = 1;
        $m['requires']['core'] = '>=9.0.0';
        $this->assertNotEmpty(Manifest::compatible($m, '0.1.0-dev', 1, '8.4.25'));
        $m['requires']['core'] = '>=0.1.0';
        $m['requires']['php'] = '>=9.0';
        $this->assertNotEmpty(Manifest::compatible($m, '0.1.0-dev', 1, '8.4.25'));
    }

    public function testRejectsEscapingPathsAndExecutableThemeHooks(): void
    {
        $m = $this->valid();
        $m['entry'] = '../outside.php';
        $m['psr4'] = ['Acme\\Unsafe\\' => '/srv/outside'];
        $m['migrations'] = ['migrations', '../other'];
        $m['assets'] = ['images' => ['assets/logo.png', '../../secret']];
        $this->assertNotEmpty(Manifest::validate($m));

        $theme = $this->valid();
        $theme['type'] = 'theme';
        $this->assertContains('theme packages cannot declare executable PHP hooks or migrations', Manifest::validate($theme));
    }

    public function testMigrationDirectoryListIsNormalized(): void
    {
        $manifest = $this->valid();
        $manifest['migrations'] = ['migrations/core', 'migrations/seed/'];
        $this->assertSame([], Manifest::validate($manifest));
        $this->assertSame(['migrations/core', 'migrations/seed'], Manifest::migrationDirectories($manifest));
        $this->assertFalse(Manifest::relativePathOk('/etc/passwd'));
        $this->assertFalse(Manifest::relativePathOk('src/../secret'));
    }

    public function testCapabilitiesStayInPackageNamespace(): void
    {
        $manifest = $this->valid();
        $manifest['capabilities'][] = 'settings.manage';
        $this->assertStringContainsString('must be prefixed', implode(' ', Manifest::validate($manifest)));
    }
}
