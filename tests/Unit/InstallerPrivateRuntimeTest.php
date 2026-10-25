<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Install\Installer;
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

    public function testDocumentRootCleanupFailureIsAnAdvisoryWarning(): void
    {
        $installer = file_get_contents(WC_ROOT . '/core/Install/Installer.php');
        self::assertIsString($installer);
        self::assertMatchesRegularExpression(
            "/'Installer self-removal',[\\s\\S]+?File Manager[^']*',\\s*false\\s*\\)/",
            $installer
        );
        self::assertStringContainsString('Manual cleanup recommended.', $installer);
    }

    public function testInstallerHasThinPublicArtifactsAndProtectedImplementation(): void
    {
        self::assertFileExists(WC_ROOT . '/core/Install/Installer.php');
        self::assertFileExists(WC_ROOT . '/core/Install/views/installer.php');
        self::assertFileExists(WC_ROOT . '/install.php');
        self::assertFileExists(WC_ROOT . '/install/index.php');
        self::assertFileExists(WC_ROOT . '/install/.htaccess');
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

    public function testInstallerHasCsrfConcurrencyAtomicConfigAndReinstallGates(): void
    {
        $installer = (string) file_get_contents(WC_ROOT . '/core/Install/Installer.php');
        $view = (string) file_get_contents(WC_ROOT . '/core/Install/views/installer.php');
        self::assertStringContainsString('wc_install_csrf', $installer);
        self::assertStringContainsString('LOCK_EX | LOCK_NB', $installer);
        self::assertStringContainsString('writeConfigAtomically', $installer);
        self::assertStringContainsString('existingAppKey', $installer);
        self::assertStringContainsString('confirm_existing_database', $installer . $view);
        self::assertStringContainsString('existing_install', $installer);
        self::assertStringContainsString('Recheck cleanup and open the website', $installer);
    }

    public function testInstallerAcceptsOnlyCanonicalHttpsBaseUrls(): void
    {
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Installer::class, 'validBaseUrl');
        self::assertTrue($method->invoke($installer, 'https://example.com/catering'));
        self::assertFalse($method->invoke($installer, 'http://example.com'));
        self::assertFalse($method->invoke($installer, 'https://user:pass@example.com'));
        self::assertFalse($method->invoke($installer, 'https://example.com/?redirect=other'));
        self::assertFalse($method->invoke($installer, 'https://example.com/#setup'));
    }
}
