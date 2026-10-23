<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InstallerCleanupGateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wc-cleanup-gate-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/config', 0777, true);
        mkdir($this->root . '/install', 0777, true);
        file_put_contents($this->root . '/config/installed.lock', "2026-10-23T00:00:00+00:00 install complete\n");
        file_put_contents($this->root . '/config/config.php', <<<'PHP'
<?php
return [
    'app' => ['url' => 'https://example.test'],
    'database' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'name' => 'existing_site', 'user' => 'site', 'prefix' => 'wc_'],
    'paths' => ['storage' => '/srv/storage', 'upload' => '/srv/upload'],
    'security' => ['app_key' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'],
];
PHP);
        file_put_contents($this->root . '/install/Installer.php', '<?php');
        file_put_contents($this->root . '/install.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (['install/Installer.php', 'install.php', 'config/cleanup-required.lock', 'config/installed.lock', 'config/config.php'] as $file) {
            @unlink($this->root . '/' . $file);
        }
        @rmdir($this->root . '/install');
        @rmdir($this->root . '/config');
        @rmdir($this->root);
    }

    public function testCompletedSiteLoadAutomaticallyRemovesReuploadedInstallerArtifacts(): void
    {
        $gate = require dirname(__DIR__, 2) . '/site/includes/install-cleanup-gate.php';

        $this->assertFalse($gate($this->root));
        $this->assertDirectoryDoesNotExist($this->root . '/install');
        $this->assertFileDoesNotExist($this->root . '/install.php');
        $this->assertFileDoesNotExist($this->root . '/config/cleanup-required.lock');
    }

    public function testCompletedSiteNeverStaysLockedByLegacyCleanupMarker(): void
    {
        file_put_contents($this->root . '/config/cleanup-required.lock', "legacy cleanup pending\n");
        $gate = require dirname(__DIR__, 2) . '/site/includes/install-cleanup-gate.php';

        $this->assertFalse($gate($this->root));
        $this->assertFileDoesNotExist($this->root . '/config/cleanup-required.lock');
    }

    public function testMarkerWithoutValidConfigNeverAuthorizesAutomaticDeletion(): void
    {
        unlink($this->root . '/config/config.php');
        $gate = require dirname(__DIR__, 2) . '/site/includes/install-cleanup-gate.php';

        $this->assertFalse($gate($this->root));
        $this->assertDirectoryExists($this->root . '/install');
        $this->assertFileExists($this->root . '/install.php');
    }
}
