<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * Simulates the installer's bundled-module path: a license-carrying
 * industry module pre-extracted at site/modules/industries/ gets a
 * license row, a packages row, its own migrations, and 'enabled' state —
 * then must actually boot via ModuleRegistry.
 *
 * Uses the real example restaurant module + real manifests, on sqlite
 * (dev/test driver). MySQL/HTTP coverage stays in the CI native suite.
 */
final class BundledModuleInstallTest extends TestCase
{
    private string $tmp = '';
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->tmp = sys_get_temp_dir() . '/wc-bundle-' . bin2hex(random_bytes(6));
        mkdir($this->tmp . '/industries', 0777, true);
        mkdir($this->tmp . '/addons', 0777, true);
        mkdir($this->tmp . '/themes', 0777, true);
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
    }

    private function copyTree(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)
        ) as $f) {
            $rel = substr($f->getPathname(), strlen($src) + 1);
            $target = $dst . '/' . $rel;
            if ($f->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0777, true);
                }
                copy($f->getPathname(), $target);
            }
        }
    }

    /** Mirrors Installer: optional license row + package row + module migrations + enabled. */
    private function registerBundled(string $moduleDir, ?string $licenseKey, string $domain): array
    {
        if ($licenseKey !== null) {
            $licenseTable = $this->db->table('license');
            $this->db->run(
                "INSERT INTO `{$licenseTable}` (license_key, license_domain, license_valid, license_validated_at, created_at)
                 VALUES (?, ?, 1, ?, ?)",
                [$licenseKey, $domain, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }

        $manifest = json_decode((string) file_get_contents($moduleDir . '/addon.json'), true);
        $this->assertIsArray($manifest);
        $packageId = (string) ($manifest['id'] ?? basename($moduleDir));
        $packagesTable = $this->db->table('packages');
        $this->db->run(
            "INSERT INTO `{$packagesTable}` (package_id, state, version, manifest, updated_at) VALUES (?, 'installed', ?, ?, ?)",
            [$packageId, (string) ($manifest['version'] ?? ''), json_encode($manifest), date('Y-m-d H:i:s')]
        );

        $migDir = trim((string) ($manifest['migrations'] ?? ''), '/');
        if ($migDir !== '' && is_dir($moduleDir . '/' . $migDir)) {
            (new Migrator($this->db, $moduleDir . '/' . $migDir))->migrate();
        }

        $this->db->run(
            "UPDATE `{$packagesTable}` SET state = 'enabled', updated_at = ? WHERE package_id = ?",
            [date('Y-m-d H:i:s'), $packageId]
        );
        $this->db->insert('package_journal', [
            'package_id' => $packageId, 'operation' => 'bundled-install',
            'result' => 'ok', 'detail' => 'Bundled module enabled during install',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$packageId, $manifest];
    }

    public function testBundledRestaurantModuleRegistersMigratesAndBoots(): void
    {
        $src = dirname(WC_ROOT) . '/examples/industry-restaurant';
        $this->assertDirectoryExists($src, 'example module checkout required');

        // Inject a license_key like the Cajun_Web bundle builder does.
        // Strip provider/psr4/entry: loading the real Wcref\Restaurant\Provider
        // would cache the class against THIS temp path process-wide — other
        // tests' module copies would then resolve __DIR__ to a deleted dir.
        $dest = $this->tmp . '/industries/industry-restaurant';
        $this->copyTree($src, $dest);
        $manifest = json_decode((string) file_get_contents($dest . '/addon.json'), true);
        $manifest['license_key'] = 'E2E-BUNDLE-KEY';
        unset($manifest['provider'], $manifest['psr4'], $manifest['entry']);
        file_put_contents($dest . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT));

        [$packageId] = $this->registerBundled($dest, 'E2E-BUNDLE-KEY', 'e2e.example.com');
        $this->assertSame('wcref/restaurant', $packageId);

        // License stored and marked valid.
        $license = $this->db->fetch('SELECT * FROM `' . $this->db->table('license') . '` LIMIT 1');
        $this->assertSame('E2E-BUNDLE-KEY', $license['license_key']);
        $this->assertSame('e2e.example.com', $license['license_domain']);
        $this->assertSame(1, (int) $license['license_valid']);

        // Module migrations created its tables.
        $this->assertTrue($this->db->tableExists('restaurant_categories'));
        $this->assertTrue($this->db->tableExists('restaurant_items'));

        // Registry sees it enabled, compatible, and boots the real provider.
        $registry = new ModuleRegistry($this->db, $this->tmp . '/industries', $this->tmp . '/addons', $this->tmp . '/themes');
        $registry->discover();
        $this->assertSame('enabled', $registry->state('wcref/restaurant'));
        $this->assertSame([], $registry->all()['wcref/restaurant']['errors']);

        $booted = $registry->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            fn (string $id) => new ModuleContext($id, new Router(), new Dispatcher())
        );
        $this->assertSame(['wcref/restaurant'], $booted);

        $journal = $this->db->fetch(
            'SELECT result FROM `' . $this->db->table('package_journal') . "` WHERE package_id = ? AND operation = 'bundled-install'",
            ['wcref/restaurant']
        );
        $this->assertSame('ok', $journal['result']);
    }

    public function testMissingMigrationsDirIsSkippedCleanly(): void
    {
        // A module without a migrations dir must still register + enable.
        $dest = $this->tmp . '/industries/industry-nomig';
        mkdir($dest, 0777, true);
        file_put_contents($dest . '/addon.json', json_encode([
            'schema' => 1, 'id' => 'wcref/nomig', 'type' => 'industry',
            'name' => 'No migrations', 'version' => '1.0.0',
            'license_key' => 'E2E-BUNDLE-KEY',
        ]));
        [$packageId] = $this->registerBundled($dest, 'E2E-BUNDLE-KEY', 'e2e.example.com');
        $this->assertSame('wcref/nomig', $packageId);
        $this->assertSame(1, (int) $this->db->fetch(
            'SELECT COUNT(*) c FROM `' . $this->db->table('packages') . "` WHERE package_id = ? AND state = 'enabled'",
            ['wcref/nomig']
        )['c']);
    }

    public function testKeylessTestingBundleRegistersMigratesAndBoots(): void
    {
        $src = dirname(WC_ROOT) . '/examples/industry-restaurant';
        $dest = $this->tmp . '/industries/industry-restaurant';
        $this->copyTree($src, $dest);

        $manifest = json_decode((string) file_get_contents($dest . '/addon.json'), true);
        unset($manifest['license_key'], $manifest['provider'], $manifest['psr4'], $manifest['entry']);
        file_put_contents($dest . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT));

        [$packageId] = $this->registerBundled($dest, null, 'testing.example.com');
        $this->assertSame('wcref/restaurant', $packageId);
        $this->assertSame(0, (int) $this->db->fetch(
            'SELECT COUNT(*) c FROM `' . $this->db->table('license') . '`'
        )['c']);
        $this->assertSame(1, (int) $this->db->fetch(
            'SELECT COUNT(*) c FROM `' . $this->db->table('packages') . "` WHERE package_id = ? AND state = 'enabled'",
            [$packageId]
        )['c']);
        $this->assertTrue($this->db->tableExists('restaurant_categories'));
        $this->assertTrue($this->db->tableExists('restaurant_items'));

        $registry = new ModuleRegistry($this->db, $this->tmp . '/industries', $this->tmp . '/addons', $this->tmp . '/themes');
        $booted = $registry->bootEnabled(
            fn (string $id) => new ModuleContext($id, new Router(), new Dispatcher())
        );
        $this->assertSame(['wcref/restaurant'], $booted);
    }
}
