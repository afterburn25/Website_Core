<?php
declare(strict_types=1);

namespace Tests\Native;

use Core\Backup\BackupService;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Modules\ArchiveInspector;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use Core\Update\SignatureVerifier;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Disposable native MySQL/MariaDB rehearsal from the exact historical
 * Catering 2.0.2 source, not an invented synthetic "old" manifest.
 *
 * CI checks out Core_Mods at 6d037077... as an immutable external fixture.
 * No production host, customer credentials, or actual customer data touched.
 */
final class CateringVersionUpgradeTest extends TestCase
{
    private const LEGACY_SHA = '6d0370777e4edb54f9565b50229e5bba0f47ffe9';
    private const PACKAGE_ID = 'cajunwebpro/catering';

    private function archiveSource(string $source, string $output, ?string $secretKey = null, bool $failing = false): void
    {
        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            $first = explode('/', $relative, 2)[0];
            if (in_array($first, ['docs', 'tests', 'tools', 'releases'], true)) continue;
            $items[$relative] = (string) file_get_contents($file->getPathname());
        }
        $this->assertArrayHasKey('addon.json', $items);
        $manifest = json_decode($items['addon.json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(self::PACKAGE_ID, $manifest['id']);
        $this->assertSame($secretKey === null ? '2.0.2' : ($failing ? '2.1.3' : '2.1.2'), $manifest['version']);

        if ($failing) {
            $items['migrations/9999_rehearsal_failure.php'] = <<<'MIGRATION'
<?php
return new class extends \Core\Database\Migration {
    public function id(): string { return 'catering_9999_native_upgrade_rehearsal_failure'; }
    public function description(): string { return 'intentional test-only failure'; }
    public function up(\Core\Database\Connection $db): void {
        throw new \RuntimeException('intentional native upgrade rehearsal failure');
    }
};
MIGRATION;
        }
        if ($secretKey !== null) {
            // Real package-manager upload accepts only trusted, signed
            // upgrades; sign all member bytes with an EPHEMERAL CI key.
            unset($manifest['signature']);
            $hashes = [];
            foreach ($items as $relative => $bytes) {
                if ($relative === 'addon.json') continue;
                $hashes[$relative] = hash('sha256', $bytes);
            }
            ksort($hashes, SORT_STRING);
            $manifest['integrity'] = ['files' => $hashes];
            $manifest = SignatureVerifier::signManifest($manifest, $secretKey);
            $items['addon.json'] = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        ksort($items, SORT_STRING);
        foreach ($items as $name => $bytes) {
            $this->assertFalse(str_ends_with(strtolower($name), '.zip'), 'No ZIP within ZIP.');
            $this->assertTrue($zip->addFromString('catering/' . $name, $bytes));
        }
        $this->assertTrue($zip->close());
        $this->assertSame([], (new ArchiveInspector())->inspect($output));
    }

    public function testRealCatering202To212UpgradeRetainsDataAndRecoversFromFailure(): void
    {
        $dsn = getenv('WC_TEST_MYSQL_DSN');
        $legacy = getenv('WC_CATERING_LEGACY_202_DIR');
        if (!is_string($dsn) || $dsn === '') {
            $this->markTestSkipped('Native acceptance requires WC_TEST_MYSQL_DSN.');
        }
        if (!is_string($legacy) || $legacy === '') {
            $this->markTestSkipped('Historical source required; CI must explicitly checkout pinned 2.0.2 fixture.');
        }
        $this->assertDirectoryExists($legacy);
        $legacyManifest = json_decode((string) file_get_contents($legacy . '/addon.json'), true);
        $this->assertSame('2.0.2', $legacyManifest['version']);
        $latest = WC_ROOT . '/../examples/industry-catering';
        $latestManifest = json_decode((string) file_get_contents($latest . '/addon.json'), true);
        $this->assertSame('2.1.2', $latestManifest['version']);

        $pdo = new \PDO($dsn, getenv('WC_TEST_MYSQL_USER') ?: 'root',
            getenv('WC_TEST_MYSQL_PASS') ?: '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'wcu' . bin2hex(random_bytes(3)) . '_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();

        $tmp = \WcProcessTmpDirs::make('wc-catering-202-upgrade-');
        $root = $tmp . '/site';
        foreach (['staging', 'backups', 'site/config', 'site/upload/customer',
                  'site/storage/media', 'site/modules/industries', 'site/modules/addons',
                  'site/themes'] as $directory) {
            $this->assertTrue(mkdir($tmp . '/' . $directory, 0750, true));
        }
        $configFile = $root . '/config/config.php';
        $customerFile = $root . '/upload/customer/event-menu.txt';
        file_put_contents($configFile, '<?php return ["customer" => "fixture"];');
        file_put_contents($customerFile, 'CUSTOMER-MEDIA-BEFORE-UPGRADE');
        $industryRoot = $root . '/modules/industries';
        $registry = new ModuleRegistry($db, $industryRoot, $root . '/modules/addons', $root . '/themes');
        $manager = new PackageManager($db, $registry, new ArchiveInspector(),
            $tmp . '/staging', $industryRoot, $root . '/modules/addons',
            $root . '/themes', $tmp . '/package.lock');

        $oldArchive = $tmp . '/catering-2.0.2.zip';
        $this->archiveSource($legacy, $oldArchive);
        [, $error] = $manager->stage($oldArchive);
        $this->assertNull($error, (string) $error);
        [$ok, $error] = $manager->install(self::PACKAGE_ID);
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $manager->enable(self::PACKAGE_ID);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $registry->state(self::PACKAGE_ID));

        $menuTable = $db->table('catering_menu_items');
        $packagesTable = $db->table('catering_packages');
        $inquiriesTable = $db->table('catering_inquiries');
        $packagesRegistry = $db->table('packages');
        $menuId = $db->fetchColumn('SELECT id FROM ' . $menuTable . ' ORDER BY id LIMIT 1');
        $packageId = $db->fetchColumn('SELECT id FROM ' . $packagesTable . ' ORDER BY id LIMIT 1');
        $this->assertNotFalse($menuId, 'Historical menu fixtures must exist');
        $this->assertNotFalse($packageId, 'Historical package fixtures must exist');
        $menuName = 'Customer gumbo menu 202';
        $packageName = 'Customer wedding package 202';
        $db->run('UPDATE ' . $menuTable . ' SET name=? WHERE id=?', [$menuName, $menuId]);
        $db->run('UPDATE ' . $packagesTable . ' SET name=? WHERE id=?', [$packageName, $packageId]);
        $inquiryId = $db->insert('catering_inquiries', [
            'kind' => 'quote', 'status' => 'new', 'name' => 'CI Customer Fixture',
            'email' => 'ci-catering@example.test', 'guest_count' => 57,
            'message' => 'Retain this private fixture inquiry across releases',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // This is the actual Core theme table used by public/admin choice.
        $db->insert('module_theme_state', [
            'owner_package' => self::PACKAGE_ID, 'draft_variant' => 'grand-wedding',
            'draft_palette' => 'champagne', 'draft_media_json' => '{}',
            'published_variant' => 'grand-wedding', 'published_palette' => 'champagne',
            'published_media_json' => '{}', 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $themesTable = $db->table('module_theme_state');

        $backup = new BackupService($db, $tmp . '/backups', $tmp . '/staging',
            $root, $root . '/storage', $root . '/config', $root . '/upload');
        [$archive, $error] = $backup->create();
        $this->assertNull($error, (string) $error);
        $this->assertNotNull($archive);
        [$meta, $error] = $backup->inspect((string) $archive);
        $this->assertNull($error, (string) $error);
        $this->assertNotNull($meta);
        $this->assertArrayHasKey('modules/industries/catering/addon.json', $meta['files']);
        $this->assertArrayHasKey('upload/customer/event-menu.txt', $meta['files']);

        $ephemeral = SignatureVerifier::generateKeypair();
        $strict = new PackageManager($db, $registry, new ArchiveInspector(),
            $tmp . '/staging', $industryRoot, $root . '/modules/addons',
            $root . '/themes', $tmp . '/package.lock',
            new SignatureVerifier([$ephemeral['public']], false));
        $update = $tmp . '/catering-2.1.2-signed-ci.zip';
        $this->archiveSource($latest, $update, $ephemeral['secret']);
        [, $error] = $strict->stage($update);
        $this->assertNull($error, (string) $error);
        [$ok, $error] = $strict->install(self::PACKAGE_ID);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('2.1.2', $db->fetchColumn(
            'SELECT version FROM ' . $packagesRegistry . ' WHERE package_id=?', [self::PACKAGE_ID]));
        $this->assertSame('enabled', $registry->state(self::PACKAGE_ID));
        $moduleDir = $industryRoot . '/catering';
        $this->assertFileExists($moduleDir . '/views/themes/grand-wedding.php');
        $this->assertSame('2.1.2', json_decode((string) file_get_contents(
            $moduleDir . '/addon.json'), true)['version']);

        $this->assertSame($menuName, $db->fetchColumn('SELECT name FROM ' . $menuTable . ' WHERE id=?', [$menuId]));
        $this->assertSame($packageName, $db->fetchColumn('SELECT name FROM ' . $packagesTable . ' WHERE id=?', [$packageId]));
        $this->assertSame(57, (int) $db->fetchColumn('SELECT guest_count FROM ' . $inquiriesTable . ' WHERE id=?', [$inquiryId]));
        $this->assertSame('grand-wedding', $db->fetchColumn('SELECT published_variant FROM '
            . $themesTable . ' WHERE owner_package=?', [self::PACKAGE_ID]));
        $this->assertSame('CUSTOMER-MEDIA-BEFORE-UPGRADE', file_get_contents($customerFile));

        // Force a validly signed next-version migration to throw; package
        // manager must restore the previous 2.1.2 files and enabled state.
        $failedSource = $tmp . '/broken-source';
        $this->assertTrue(mkdir($failedSource, 0750));
        // archiveSource() reads the real 2.1.2 tree; override version only
        // in a disposable copy of the manifest, never in the source repo.
        $failedManifest = $latestManifest;
        $failedManifest['version'] = '2.1.3';
        $failedSourceManifest = $failedSource . '/addon.json';
        file_put_contents($failedSourceManifest, json_encode($failedManifest));
        $failingArchive = $tmp . '/catering-2.1.3-failure-ci.zip';
        // Use the real current tree plus an injected failure only in a CI ZIP:
        $this->archiveFailingNextVersion($latest, $failingArchive, $ephemeral['secret']);
        [, $error] = $strict->stage($failingArchive);
        $this->assertNull($error, (string) $error);
        [$ok, $error] = $strict->install(self::PACKAGE_ID);
        $this->assertFalse($ok);
        $this->assertStringContainsString('Previous package files were restored', (string) $error);
        $this->assertSame('2.1.2', $db->fetchColumn(
            'SELECT version FROM ' . $packagesRegistry . ' WHERE package_id=?', [self::PACKAGE_ID]));
        $this->assertSame('enabled', $registry->state(self::PACKAGE_ID));
        $this->assertSame($menuName, $db->fetchColumn('SELECT name FROM ' . $menuTable . ' WHERE id=?', [$menuId]));
        $this->assertSame($packageName, $db->fetchColumn('SELECT name FROM ' . $packagesTable . ' WHERE id=?', [$packageId]));
        $this->assertSame('CUSTOMER-MEDIA-BEFORE-UPGRADE', file_get_contents($customerFile));

        // Disaster recovery from the verified 2.0.2 full backup restores
        // the database and protected files without production credentials.
        $db->run('UPDATE ' . $menuTable . ' SET name=? WHERE id=?', ['MUTATED AFTER BACKUP', $menuId]);
        file_put_contents($customerFile, 'MUTATED-MEDIA-AFTER-BACKUP');
        [$restored, $error] = $backup->restore((string) $archive);
        $this->assertTrue($restored, (string) $error);
        $this->assertSame('2.0.2', $db->fetchColumn(
            'SELECT version FROM ' . $packagesRegistry . ' WHERE package_id=?', [self::PACKAGE_ID]));
        $this->assertSame('2.0.2', json_decode((string) file_get_contents(
            $moduleDir . '/addon.json'), true)['version']);
        $this->assertSame($menuName, $db->fetchColumn('SELECT name FROM ' . $menuTable . ' WHERE id=?', [$menuId]));
        $this->assertSame($packageName, $db->fetchColumn('SELECT name FROM ' . $packagesTable . ' WHERE id=?', [$packageId]));
        $this->assertSame(57, (int) $db->fetchColumn('SELECT guest_count FROM ' . $inquiriesTable . ' WHERE id=?', [$inquiryId]));
        $this->assertSame('grand-wedding', $db->fetchColumn('SELECT published_variant FROM '
            . $themesTable . ' WHERE owner_package=?', [self::PACKAGE_ID]));
        $this->assertSame('CUSTOMER-MEDIA-BEFORE-UPGRADE', file_get_contents($customerFile));
        $this->assertSame('<?php return ["customer" => "fixture"];', file_get_contents($configFile));
    }

    private function archiveFailingNextVersion(string $latest, string $output, string $secret): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-catering-failed-source-');
        $tree = $tmp . '/module';
        mkdir($tree, 0750, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($latest, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) continue;
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($latest) + 1));
            if (in_array(explode('/', $rel, 2)[0], ['docs', 'tests', 'tools', 'releases'], true)) continue;
            $dest = $tree . '/' . $rel;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0750, true);
            copy($file->getPathname(), $dest);
        }
        $manifest = json_decode((string) file_get_contents($tree . '/addon.json'), true);
        $manifest['version'] = '2.1.3';
        file_put_contents($tree . '/addon.json', json_encode($manifest));
        $this->archiveSource($tree, $output, $secret, true);
    }
}
