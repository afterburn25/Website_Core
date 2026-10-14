<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Modules\ArchiveInspector;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class PackageManagerTest extends TestCase
{
    private string $tmp;
    private Connection $db;
    private PackageManager $pm;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('zip')) {
            $this->markTestSkipped('needs pdo_sqlite + zip');
        }
        $this->tmp = sys_get_temp_dir() . '/wc-pm-' . bin2hex(random_bytes(4));
        foreach (['staging', 'industries', 'addons', 'themes'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $registry = new ModuleRegistry(
            $this->db,
            $this->tmp . '/industries', $this->tmp . '/addons', $this->tmp . '/themes'
        );
        $this->pm = new PackageManager(
            $this->db, $registry, new ArchiveInspector(),
            $this->tmp . '/staging', $this->tmp . '/industries',
            $this->tmp . '/addons', $this->tmp . '/themes',
            $this->tmp . '/op.lock'
        );
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

    private function zip(array $members, string $name = 'pkg.zip'): string
    {
        $path = $this->tmp . '/' . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($members as $rel => $content) {
            if (is_array($content)) {
                // [content, unix_mode] for symlink tests etc.
                $zip->addFromString($rel, $content[0]);
                $zip->setExternalAttributesName($rel, ZipArchive::OPSYS_UNIX, $content[1] << 16);
            } else {
                $zip->addFromString($rel, $content);
            }
        }
        $zip->close();
        return $path;
    }

    private function manifest(array $o = []): string
    {
        return json_encode(array_merge([
            'schema' => 1, 'id' => 'acme/widgets', 'type' => 'addon',
            'name' => 'Widgets', 'version' => '1.0.0', 'license' => 'MIT',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
        ], $o));
    }

    public function testTraversalRejected(): void
    {
        $zip = $this->zip([
            'pkg/addon.json' => $this->manifest(),
            '../escape.php' => '<?php echo 1;',
        ]);
        $this->assertNotEmpty((new ArchiveInspector())->inspect($zip));
    }

    public function testSymlinkRejected(): void
    {
        $zip = $this->zip([
            'pkg/addon.json' => $this->manifest(),
            'pkg/link' => ['/etc/passwd', 0xA1FF], // S_IFLNK
        ]);
        $errors = (new ArchiveInspector())->inspect($zip);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Symlink', implode(' ', $errors));
    }

    public function testCaseCollisionRejected(): void
    {
        $zip = $this->zip([
            'pkg/addon.json' => $this->manifest(),
            'pkg/Src/A.php' => 'x',
            'pkg/src/a.php' => 'y',
        ]);
        $errors = (new ArchiveInspector())->inspect($zip);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('collision', strtolower(implode(' ', $errors)));
    }

    public function testMissingManifestRejected(): void
    {
        $zip = $this->zip(['pkg/index.php' => '<?php']);
        $this->assertNotEmpty((new ArchiveInspector())->inspect($zip));
    }

    public function testFullLifecycle(): void
    {
        $zip = $this->zip([
            'widgets/addon.json' => $this->manifest(),
            'widgets/src/Provider.php' => '<?php namespace AcmeWidgets; class Provider { public function register($ctx) {} }',
            'widgets/src/data.txt' => 'payload',
        ]);
        [$staged, $err] = $this->pm->stage($zip);
        $this->assertNull($err);
        $this->assertNotNull($staged);
        $this->assertSame('staged', $this->state('acme/widgets'));

        [$ok, $err] = $this->pm->install('acme/widgets');
        $this->assertTrue($ok, (string) $err);
        $this->assertSame('installed', $this->state('acme/widgets'));
        $this->assertTrue(is_file($this->tmp . '/addons/widgets/addon.json'));
        $this->assertTrue(is_file($this->tmp . '/addons/widgets/src/data.txt'));

        [$ok, $err] = $this->pm->enable('acme/widgets');
        $this->assertTrue($ok, (string) $err);
        $this->assertSame('enabled', $this->state('acme/widgets'));

        [$ok] = $this->pm->disable('acme/widgets');
        $this->assertTrue($ok);
        $this->assertSame('disabled', $this->state('acme/widgets'));

        [$ok] = $this->pm->uninstall('acme/widgets');
        $this->assertTrue($ok);
        $this->assertSame('removed', $this->state('acme/widgets'));
        $this->assertFalse(is_dir($this->tmp . '/addons/widgets'));
    }

    public function testIntegrityMismatchBlocksInstall(): void
    {
        $manifest = $this->manifest([
            'integrity' => ['files' => ['src/data.txt' => str_repeat('0', 64)]],
        ]);
        $zip = $this->zip([
            'widgets/addon.json' => $manifest,
            'widgets/src/data.txt' => 'payload',
        ]);
        [, $err] = $this->pm->stage($zip);
        $this->assertNull($err);
        [$ok, $err] = $this->pm->install('acme/widgets');
        $this->assertFalse($ok);
        $this->assertStringContainsString('integrity', (string) $err);
        $this->assertSame('failed', $this->state('acme/widgets'));
    }

    public function testEnableBlockedByMissingDependency(): void
    {
        $zip = $this->zip([
            'rest/addon.json' => $this->manifest([
                'id' => 'acme/restaurant', 'type' => 'industry',
                'dependencies' => ['acme/forms' => '>=1.0.0'],
            ]),
        ]);
        [, $err] = $this->pm->stage($zip);
        $this->assertNull($err);
        $this->pm->install('acme/restaurant');
        [$ok, $err] = $this->pm->enable('acme/restaurant');
        $this->assertFalse($ok);
        $this->assertStringContainsString('missing dependency', (string) $err);
    }

    public function testDependencyEnablesBeforeDependent(): void
    {
        $this->zip([
            'forms/addon.json' => $this->manifest(['id' => 'acme/forms']),
        ]);
        $zipForms = $this->tmp . '/forms.zip';
        // recreate zip (helper wrote to tmp root)
        $zip = new ZipArchive();
        $zip->open($zipForms, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('forms/addon.json', $this->manifest(['id' => 'acme/forms']));
        $zip->close();

        $this->pm->stage($zipForms);
        $this->pm->install('acme/forms');

        $zipRest = $this->zip([
            'rest/addon.json' => $this->manifest([
                'id' => 'acme/restaurant', 'type' => 'industry',
                'dependencies' => ['acme/forms' => '>=1.0.0'],
            ]),
        ], 'rest.zip');
        $this->pm->stage($zipRest);
        $this->pm->install('acme/restaurant');

        [$ok, $err] = $this->pm->enable('acme/restaurant');
        $this->assertTrue($ok, (string) $err);
        $this->assertSame('enabled', $this->state('acme/forms'));
        $this->assertSame('enabled', $this->state('acme/restaurant'));

        // Cannot disable the dependency while the dependent is enabled.
        [$ok, $err] = $this->pm->disable('acme/forms');
        $this->assertFalse($ok);
        $this->assertStringContainsString('depends', (string) $err);
    }

    public function testPurgeDropsModuleTables(): void
    {
        $zip = $this->zip(['widgets/addon.json' => $this->manifest()]);
        $this->pm->stage($zip);
        $this->pm->install('acme/widgets');
        $this->pm->uninstall('acme/widgets');

        $this->db->pdo()->exec('CREATE TABLE wc_widgets_data (id INT)');
        $this->assertTrue($this->db->tableExists('widgets_data'));

        [$ok, $err] = $this->pm->purgeData('acme/widgets');
        $this->assertTrue($ok, (string) $err);
        $this->assertFalse($this->db->tableExists('widgets_data'));
    }

    public function testPackageMigrationsRunOnInstallAndPurgeDropsThem(): void
    {
        $migration = <<<'PHP'
<?php
return new class extends \Core\Database\Migration {
    public function id(): string { return 'widgets_0001_init'; }
    public function description(): string { return 'widgets schema'; }
    public function up(\Core\Database\Connection $db): void {
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('widgets_items') . '` (id INTEGER PRIMARY KEY)');
    }
};
PHP;
        $zip = $this->zip([
            'widgets/addon.json' => $this->manifest(['migrations' => 'migrations']),
            'widgets/migrations/0001_init.php' => $migration,
        ]);
        $this->pm->stage($zip);
        [$ok, $err] = $this->pm->install('acme/widgets');
        $this->assertTrue($ok, (string) $err);
        $this->assertTrue($this->db->tableExists('widgets_items'));

        // Data survives file removal; purge drops it.
        $this->pm->uninstall('acme/widgets');
        $this->assertTrue($this->db->tableExists('widgets_items'));
        [$ok, $err] = $this->pm->purgeData('acme/widgets');
        $this->assertTrue($ok, (string) $err);
        $this->assertFalse($this->db->tableExists('widgets_items'));
    }

    private function state(string $id): string
    {
        return (string) $this->db->fetchColumn(
            'SELECT state FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
            [$id]
        );
    }
}
