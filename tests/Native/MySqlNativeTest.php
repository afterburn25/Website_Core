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

    public function testBackupRestorePreservesMysqlDataAndProtectedFiles(): void
    {
        // Use an isolated prefix, so the destructive restore cannot disturb
        // the other native scenarios sharing the same ephemeral CI database.
        $db = new Connection($this->db->pdo(), 'wcdr_' . bin2hex(random_bytes(3)) . '_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();

        $tmp = \WcProcessTmpDirs::make('wc-native-restore-');
        foreach (['backups', 'staging', 'site/config', 'site/upload/docs'] as $dir) {
            mkdir($tmp . '/' . $dir, 0777, true);
        }
        $config = $tmp . '/site/config/config.php';
        $customerFile = $tmp . '/site/upload/docs/customer-note.txt';
        file_put_contents($config, '<?php return ["keep" => true];');
        file_put_contents($customerFile, 'Customer-original-file');
        $slug = 'restore-check-' . bin2hex(random_bytes(4));
        $original = "Customer data;\\n-- WC-STMT-END\\nnot executable SQL";
        $db->insert('pages', [
            'slug' => $slug,
            'title' => 'Protected client page',
            'body' => $original,
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $backup = new BackupService($db, $tmp . '/backups', $tmp . '/staging', $tmp . '/site');
        [$archive, $error] = $backup->create();
        $this->assertNull($error);
        $this->assertNotNull($archive);
        $this->assertFileExists($archive);
        [$meta, $error] = $backup->inspect((string) $archive);
        $this->assertNull($error);
        $this->assertContains($db->table('pages'), $meta['tables']);
        $this->assertArrayHasKey('config/config.php', $meta['files']);
        $this->assertArrayHasKey('upload/docs/customer-note.txt', $meta['files']);

        $pdo = $db->pdo();
        $change = $pdo->prepare('UPDATE `' . $db->table('pages') . '` SET body = ? WHERE slug = ?');
        $change->execute(['MUTATED DATA', $slug]);
        file_put_contents($config, '<?php return ["keep" => false];');
        file_put_contents($customerFile, 'MUTATED FILE');

        [$restored, $error] = $backup->restore((string) $archive);
        $this->assertTrue($restored, (string) $error);
        $this->assertSame($original, $db->fetchColumn(
            'SELECT body FROM `' . $db->table('pages') . '` WHERE slug = ?', [$slug]
        ));
        $this->assertSame('<?php return ["keep" => true];', file_get_contents($config));
        $this->assertSame('Customer-original-file', file_get_contents($customerFile));
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

    public function testAuthoritativeCateringAndLandscapingSwitchPreservesRecordsOnMysql(): void
    {
        // Separate native DB prefix prevents interference with other tests.
        $db = new Connection($this->db->pdo(), 'wcindustries_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();

        $tmp = \WcProcessTmpDirs::make('wc-native-industries-');
        foreach (['staging', 'industries', 'addons', 'themes'] as $dir) {
            mkdir($tmp . '/' . $dir, 0777, true);
        }
        $registry = new ModuleRegistry(
            $db, $tmp . '/industries', $tmp . '/addons', $tmp . '/themes'
        );
        $manager = new PackageManager(
            $db, $registry, new ArchiveInspector(),
            $tmp . '/staging', $tmp . '/industries', $tmp . '/addons',
            $tmp . '/themes', $tmp . '/op.lock'
        );

        foreach (['catering', 'landscaping'] as $industry) {
            $source = WC_ROOT . '/../examples/industry-' . $industry;
            $archive = $tmp . '/' . $industry . '.zip';
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                $this->assertTrue($zip->addFile($file->getPathname(), $industry . '/' . $rel));
            }
            $this->assertTrue($zip->close());

            [, $error] = $manager->stage($archive);
            $this->assertNull($error);
            [$ok, $error] = $manager->install('cajunwebpro/' . $industry);
            $this->assertTrue($ok, (string) $error);
        }

        $catering = 'cajunwebpro/catering';
        $landscaping = 'cajunwebpro/landscaping';
        $this->assertSame('2.1.1', $db->fetchColumn(
            'SELECT version FROM ' . $db->table('packages') . ' WHERE package_id=?', [$catering]
        ));
        $this->assertSame('1.0.0', $db->fetchColumn(
            'SELECT version FROM ' . $db->table('packages') . ' WHERE package_id=?', [$landscaping]
        ));
        $this->assertTrue($db->tableExists('catering_menu_items'));
        $this->assertTrue($db->tableExists('landscaping_services'));

        [$ok, $error] = $manager->enable($catering);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $registry->state($catering));

        $cateringId = $db->fetchColumn(
            'SELECT id FROM ' . $db->table('catering_menu_items') . ' ORDER BY id LIMIT 1'
        );
        $this->assertNotFalse($cateringId);
        $db->pdo()->prepare(
            'UPDATE ' . $db->table('catering_menu_items') . ' SET name=? WHERE id=?'
        )->execute(['Native catering customer edit', $cateringId]);

        [$ok, $error] = $manager->enable($landscaping);
        $this->assertFalse($ok);
        $this->assertStringContainsString('Disable the active industry package', (string) $error);
        $this->assertSame('enabled', $registry->state($catering));
        $this->assertSame('installed', $registry->state($landscaping));

        [$ok, $error] = $manager->disable($catering);
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->enable($landscaping);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $registry->state($landscaping));

        $landscapingId = $db->fetchColumn(
            'SELECT id FROM ' . $db->table('landscaping_services') . ' ORDER BY id LIMIT 1'
        );
        $this->assertNotFalse($landscapingId);
        $db->pdo()->prepare(
            'UPDATE ' . $db->table('landscaping_services') . ' SET name=? WHERE id=?'
        )->execute(['Native landscaping customer edit', $landscapingId]);

        [$ok, $error] = $manager->disable($landscaping);
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->uninstall($landscaping);
        $this->assertTrue($ok, (string) $error);
        $this->assertTrue($db->tableExists('landscaping_services'));
        $this->assertSame('Native landscaping customer edit', $db->fetchColumn(
            'SELECT name FROM ' . $db->table('landscaping_services') . ' WHERE id=?',
            [$landscapingId]
        ));

        [$ok, $error] = $manager->enable($catering);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $registry->state($catering));
        $this->assertSame('Native catering customer edit', $db->fetchColumn(
            'SELECT name FROM ' . $db->table('catering_menu_items') . ' WHERE id=?',
            [$cateringId]
        ));

        [$ok, $error] = $manager->disable($catering);
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->uninstall($catering);
        $this->assertTrue($ok, (string) $error);
        $this->assertTrue($db->tableExists('catering_menu_items'));
        $this->assertSame('Native catering customer edit', $db->fetchColumn(
            'SELECT name FROM ' . $db->table('catering_menu_items') . ' WHERE id=?',
            [$cateringId]
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
