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
            'capabilities' => ['forms.view', 'forms.manage'],
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
}
