<?php
declare(strict_types=1);

namespace Tests\Unit;

use Install\LocalFtpProvisioner;
use PHPUnit\Framework\TestCase;

final class InstallerOwnershipFallbackTest extends TestCase
{
    public function testFtpTransportIsRestrictedToLoopbackHosts(): void
    {
        self::assertTrue(LocalFtpProvisioner::isLocalHost('127.0.0.1'));
        self::assertTrue(LocalFtpProvisioner::isLocalHost('localhost'));
        self::assertTrue(LocalFtpProvisioner::isLocalHost('::1'));
        self::assertFalse(LocalFtpProvisioner::isLocalHost('ftp.example.com'));
        self::assertFalse(LocalFtpProvisioner::isLocalHost('192.0.2.10'));
    }

    public function testFallbackNeverUsesWorldWritablePermissions(): void
    {
        $source = file_get_contents(WC_ROOT . '/install/LocalFtpProvisioner.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('0777', $source);
        self::assertStringContainsString('0775', $source);
    }

    public function testInstallerRequestsButNeverRepopulatesFtpPassword(): void
    {
        $view = file_get_contents(WC_ROOT . '/install/views/installer.php');
        self::assertIsString($view);
        self::assertStringContainsString('name="ftp_password" type="password"', $view);
        self::assertStringNotContainsString('name="ftp_password" value=', $view);
        self::assertStringContainsString("action: 'test_hosting_access'", $view);
        self::assertStringContainsString('value="127.0.0.1" readonly', $view);
    }
}
