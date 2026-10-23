<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallerCleanupTest extends TestCase
{
    private string $root;
    private string $scriptName;

    protected function setUp(): void
    {
        require_once WC_ROOT . '/includes/installer-cleanup.php';
        $this->root = sys_get_temp_dir() . '/wc-installer-cleanup-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/install/views', 0775, true);
        file_put_contents($this->root . '/install/Installer.php', '<?php');
        file_put_contents($this->root . '/install/views/installer.php', '<?php');
        file_put_contents($this->root . '/install.php', '<?php');
        file_put_contents($this->root . '/index.php', '<?php // must survive');
        $this->scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    }

    protected function tearDown(): void
    {
        $_SERVER['SCRIPT_NAME'] = $this->scriptName;
        foreach (glob($this->root . '/.wc-private-installer-*') ?: [] as $path) {
            is_dir($path) ? wc_remove_installer_tree($path) : @unlink($path);
        }
        foreach (['install/views/installer.php', 'install/Installer.php', 'install.php', 'index.php', '.htaccess', 'broken-config.php', 'config/config.php', 'config/installed.lock'] as $file) {
            @unlink($this->root . '/' . $file);
        }
        @rmdir($this->root . '/install/views');
        @rmdir($this->root . '/install');
        @rmdir($this->root . '/config');
        @rmdir($this->root);
    }

    public function testCleanupRemovesOnlyAllowlistedInstallerArtifacts(): void
    {
        self::assertSame([], wc_remove_installer_artifacts($this->root));
        self::assertDirectoryDoesNotExist($this->root . '/install');
        self::assertFileDoesNotExist($this->root . '/install.php');
        self::assertFileExists($this->root . '/index.php');
    }

    public function testCleanupCapabilityProbeExercisesDocumentRootMutations(): void
    {
        self::assertTrue(wc_probe_installer_cleanup_access($this->root));
        self::assertSame([], glob($this->root . '/.wc-cleanup-probe-*') ?: []);
    }

    public function testSameParentRetirementRemovesCanonicalPathAndProtectsResidues(): void
    {
        file_put_contents($this->root . '/.htaccess', "# existing application rules\n");

        self::assertTrue(wc_retire_installer_artifact_same_parent(
            $this->root . '/install',
            $this->root,
            true
        ));
        self::assertDirectoryDoesNotExist($this->root . '/install');
        $protection = (string) file_get_contents($this->root . '/.htaccess');
        self::assertStringContainsString('# existing application rules', $protection);
        self::assertStringContainsString('# Website Core private installer residues', $protection);
        self::assertStringContainsString('RewriteRule ^\\.wc-private-installer-', $protection);
    }

    public function testRetiredSameParentResiduesAreSweptOnLaterLoads(): void
    {
        $residue = $this->root . '/.wc-private-installer-entry-0123456789abcdefabcd.disabled';
        file_put_contents($residue, '<?php');

        wc_sweep_retired_installer_artifacts($this->root);

        self::assertFileDoesNotExist($residue);
    }

    public function testCleanupPageIsStyledAndUsesSubfolderLinks(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/catering/install.php';
        $html = wc_installer_cleanup_page($this->root, ['install/']);

        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('Recheck cleanup', $html);
        self::assertStringContainsString('Visit website', $html);
        self::assertStringContainsString('Open admin', $html);
        self::assertStringContainsString('href="/catering/?installer_cleanup_recheck=1"', $html);
        self::assertStringContainsString('href="/catering/index.php?p=/admin"', $html);
        self::assertStringContainsString('src="/catering/assets/images/cajunwebpro-logo.png"', $html);
    }

    public function testAdminEntryStillResolvesTheSiteRoot(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/catering/admin/index.php';
        self::assertSame('/catering', wc_installer_base_path());
    }

    public function testCompletedInstallRequiresMarkerAndValidGeneratedConfig(): void
    {
        mkdir($this->root . '/config', 0775, true);
        file_put_contents($this->root . '/config/installed.lock', "2026-10-23T00:00:00+00:00 install complete\n");
        self::assertFalse(wc_completed_installation($this->root . '/config'));

        file_put_contents($this->root . '/config/config.php', <<<'PHP'
<?php
return [
    'app' => ['url' => 'https://example.test'],
    'database' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'name' => 'existing_site', 'user' => 'site', 'prefix' => 'wc_'],
    'paths' => ['storage' => '/srv/storage', 'upload' => '/srv/upload'],
    'security' => ['app_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'],
];
PHP);
        self::assertTrue(wc_completed_installation($this->root . '/config'));

        unlink($this->root . '/config/config.php');
        unlink($this->root . '/config/installed.lock');
        rmdir($this->root . '/config');
    }

    public function testDamagedConfigFallsBackToInstallerInsteadOfFatalError(): void
    {
        $path = $this->root . '/broken-config.php';
        file_put_contents($path, '<?php this is not valid PHP');
        $config = new \Core\Config($path);

        self::assertFalse($config->loaded());
        unlink($path);
    }
}
