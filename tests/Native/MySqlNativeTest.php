<?php
declare(strict_types=1);

namespace Tests\Native;

use Core\Backup\BackupService;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Modules\ArchiveInspector;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use Core\Security\RateLimiter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Native MySQL/MariaDB tests — the required DB acceptance layer. These run
 * in CI against real mysql/mariadb services via WC_TEST_MYSQL_DSN, e.g.
 *   WC_TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306;dbname=wc_test"
 *   WC_TEST_MYSQL_USER=root  WC_TEST_MYSQL_PASS=secret
 * They are skipped (not silently passed) when no DSN is provided.
 */
final class MySqlNativeTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $dsn = getenv('WC_TEST_MYSQL_DSN');
        if (!$dsn) {
            $this->markTestSkipped('WC_TEST_MYSQL_DSN not set — native MySQL tests skipped');
        }
        $pdo = new \PDO(
            $dsn,
            getenv('WC_TEST_MYSQL_USER') ?: 'root',
            getenv('WC_TEST_MYSQL_PASS') ?: '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $this->db = new Connection($pdo, 'wctest_');
    }

    public function testCoreSchemaOnMysql(): void
    {
        $this->assertSame('mysql', $this->db->driver());
        $migrator = new Migrator($this->db, WC_ROOT . '/database/migrations');
        $applied = $migrator->migrate();
        $this->assertContains('2026_10_14_0001', $applied);
        foreach (['admins', 'settings', 'pages', 'packages', 'jobs', 'audit_log', 'rate_limits'] as $t) {
            $this->assertTrue($this->db->tableExists($t), "missing {$t}");
        }
        // Idempotent re-run.
        $this->assertSame([], $migrator->migrate());
    }

    public function testCrudOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $id = $this->db->insert('pages', [
            'slug' => 'native-test', 'title' => 'Native', 'body' => '<p>ok</p>',
            'status' => 'published', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $row = $this->db->fetch('SELECT * FROM `' . $this->db->table('pages') . '` WHERE id=?', [$id]);
        $this->assertSame('native-test', $row['slug']);
    }

    public function testRateLimiterOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $l = new RateLimiter($this->db);
        $bucket = 'native:' . bin2hex(random_bytes(4));
        $this->assertTrue($l->attempt($bucket, 2, 60));
        $this->assertTrue($l->attempt($bucket, 2, 60));
        $this->assertFalse($l->attempt($bucket, 2, 60));
    }

    public function testBackupCreateAndInspectOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $tmp = \WcProcessTmpDirs::make('wc-native-backup-');
        foreach (['backups', 'staging', 'site/config', 'site/upload'] as $dir) {
            mkdir($tmp . '/' . $dir, 0777, true);
        }
        file_put_contents($tmp . '/site/config/config.php', '<?php return [];');

        $service = new BackupService(
            $this->db,
            $tmp . '/backups',
            $tmp . '/staging',
            $tmp . '/site'
        );
        [$path, $error] = $service->create();
        $this->assertNull($error);
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        [$meta, $error] = $service->inspect((string) $path);
        $this->assertNull($error);
        $this->assertNotNull($meta);
        $this->assertContains($this->db->table('pages'), $meta['tables']);
    }

    public function testLandscapingModuleMigrationOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $migrator = new Migrator($this->db, WC_ROOT . '/../examples/industry-landscaping/migrations');
        $this->assertContains('landscaping_0001_commercial_foundation', $migrator->migrate());
        foreach ([
            'landscaping_services', 'landscaping_projects', 'landscaping_plans',
            'landscaping_service_areas', 'landscaping_seasonal_services',
            'landscaping_estimates',
        ] as $table) {
            $this->assertTrue($this->db->tableExists($table), "missing {$table}");
        }
        $this->assertSame(6, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('landscaping_services') . '`'
        ));
        $this->assertSame(3, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('landscaping_plans') . '`'
        ));
        $this->assertSame([], $migrator->migrate());
    }

    public function testLandscapingPackageLifecycleOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        (new Migrator(
            $this->db,
            WC_ROOT . '/../examples/industry-landscaping/migrations'
        ))->migrate();

        $tmp = \WcProcessTmpDirs::make('wc-native-landscaping-');
        foreach (['staging', 'industries', 'addons', 'themes'] as $dir) {
            mkdir($tmp . '/' . $dir, 0777, true);
        }
        $registry = new ModuleRegistry(
            $this->db,
            $tmp . '/industries',
            $tmp . '/addons',
            $tmp . '/themes'
        );
        $manager = new PackageManager(
            $this->db,
            $registry,
            new ArchiveInspector(),
            $tmp . '/staging',
            $tmp . '/industries',
            $tmp . '/addons',
            $tmp . '/themes',
            $tmp . '/op.lock'
        );

        $stable = $this->landscapingZip($tmp . '/landscaping-1.0.0.zip');
        [, $error] = $manager->stage($stable);
        $this->assertNull($error);
        [$ok, $error] = $manager->install('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->enable('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);

        $slug = 'native-client-' . bin2hex(random_bytes(4));
        $this->db->insert('landscaping_services', [
            'slug' => $slug,
            'name' => 'Native Client Service',
            'category' => 'Custom',
            'summary' => 'Customer-created data.',
            'details' => 'Must survive replacement, rollback, and uninstall.',
            'audience' => 'both',
            'image_url' => '',
            'featured' => 0,
            'visible' => 1,
            'sort_order' => 999,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ]);

        [, $error] = $manager->stage($stable);
        $this->assertNull($error);
        [$ok, $error] = $manager->install('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $this->packageState('cajunwebpro/landscaping'));
        $this->assertSame('Native Client Service', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('landscaping_services') . '` WHERE slug = ?',
            [$slug]
        ));

        $broken = $this->landscapingZip($tmp . '/landscaping-broken.zip', true);
        [, $error] = $manager->stage($broken);
        $this->assertNull($error);
        [$ok, $error] = $manager->install('cajunwebpro/landscaping');
        $this->assertFalse($ok);
        $this->assertStringContainsString('Previous package files were restored', (string) $error);
        $this->assertSame('enabled', $this->packageState('cajunwebpro/landscaping'));
        $this->assertSame('1.0.0', $this->db->fetchColumn(
            'SELECT version FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
            ['cajunwebpro/landscaping']
        ));
        $this->assertSame('Native Client Service', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('landscaping_services') . '` WHERE slug = ?',
            [$slug]
        ));

        [$ok, $error] = $manager->disable('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->uninstall('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        $this->assertDirectoryDoesNotExist($tmp . '/industries/landscaping');
        $this->assertTrue($this->db->tableExists('landscaping_services'));
        $this->assertSame('Native Client Service', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('landscaping_services') . '` WHERE slug = ?',
            [$slug]
        ));
    }

    private function landscapingZip(string $path, bool $failingMigration = false): string
    {
        $source = WC_ROOT . '/../examples/industry-landscaping';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            if ($relative === 'addon.json' && $failingMigration) {
                $manifest = json_decode((string) file_get_contents($file->getPathname()), true);
                $manifest['version'] = '1.0.1';
                $zip->addFromString(
                    'landscaping/addon.json',
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                );
                continue;
            }
            $zip->addFile($file->getPathname(), 'landscaping/' . $relative);
        }
        if ($failingMigration) {
            $zip->addFromString(
                'landscaping/migrations/9999_expected_failure.php',
                <<<'PHP'
<?php
return new class extends \Core\Database\Migration {
    public function id(): string { return 'landscaping_9999_native_expected_failure'; }
    public function description(): string { return 'expected native rollback failure'; }
    public function up(\Core\Database\Connection $db): void {
        throw new \RuntimeException('expected native migration failure');
    }
};
PHP
            );
        }
        $zip->close();
        return $path;
    }

    private function packageState(string $packageId): string
    {
        return (string) $this->db->fetchColumn(
            'SELECT state FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
            [$packageId]
        );
    }
}
