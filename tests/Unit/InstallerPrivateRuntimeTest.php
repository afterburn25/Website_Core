<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallerPrivateRuntimeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once WC_ROOT . '/includes/runtime-paths.php';
    }

    public function testPathContainmentUsesDirectoryBoundaries(): void
    {
        self::assertTrue(wc_path_inside('/srv/www/site/config', '/srv/www/site'));
        self::assertTrue(wc_path_inside('/srv/www/site', '/srv/www/site'));
        self::assertFalse(wc_path_inside('/srv/www/site-two', '/srv/www/site'));
    }

    public function testInstallerContainsNoFtpCredentialFlow(): void
    {
        $installer = file_get_contents(WC_ROOT . '/core/Install/Installer.php');
        $view = file_get_contents(WC_ROOT . '/core/Install/views/installer.php');
        self::assertIsString($installer);
        self::assertIsString($view);
        self::assertStringNotContainsString('ftp_password', $installer . $view);
        self::assertStringNotContainsString('LocalFtpProvisioner', $installer . $view);
    }

    public function testInstallerLivesOnlyInsideProtectedCore(): void
    {
        self::assertFileExists(WC_ROOT . '/core/Install/Installer.php');
        self::assertFileExists(WC_ROOT . '/core/Install/views/installer.php');
        self::assertFileDoesNotExist(WC_ROOT . '/install.php');
        self::assertDirectoryDoesNotExist(WC_ROOT . '/install');
    }

    public function testPrivateRuntimeAndGreenStepContractsArePresent(): void
    {
        $runtime = file_get_contents(WC_ROOT . '/includes/runtime-paths.php');
        $view = file_get_contents(WC_ROOT . '/core/Install/views/installer.php');
        self::assertIsString($runtime);
        self::assertIsString($view);
        self::assertStringContainsString('.website-core-', $runtime);
        self::assertStringNotContainsString('0777', $runtime);
        self::assertStringContainsString('overview-step-1', $view);
        self::assertStringContainsString('is-ready', $view);
        self::assertStringContainsString("note.textContent = ready ? 'Ready'", $view);
    }
}
