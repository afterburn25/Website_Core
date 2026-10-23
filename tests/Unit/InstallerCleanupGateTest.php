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
        file_put_contents($this->root . '/config/installed.lock', "installed\n");
        file_put_contents($this->root . '/install/Installer.php', '<?php');
        file_put_contents($this->root . '/install.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (['install/Installer.php', 'install.php', 'config/cleanup-required.lock', 'config/installed.lock'] as $file) {
            @unlink($this->root . '/' . $file);
        }
        @rmdir($this->root . '/install');
        @rmdir($this->root . '/config');
        @rmdir($this->root);
    }

    public function testInstalledSiteStaysLockedUntilEveryInstallerArtifactIsRemoved(): void
    {
        $gate = require dirname(__DIR__, 2) . '/site/includes/install-cleanup-gate.php';

        $this->assertTrue($gate($this->root));
        $this->assertFileExists($this->root . '/config/cleanup-required.lock');

        unlink($this->root . '/install/Installer.php');
        rmdir($this->root . '/install');
        $this->assertTrue($gate($this->root), 'install.php alone must keep the site locked');

        unlink($this->root . '/install.php');
        $this->assertFalse($gate($this->root));
        $this->assertFileDoesNotExist($this->root . '/config/cleanup-required.lock');
    }
}
