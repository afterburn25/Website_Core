<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Application;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class ApplicationMigrationTest extends TestCase
{
    public function testBootRepairsAnInstalledDatabaseBehindCurrentCoreCode(): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-auto-migrate-');
        mkdir($tmp, 0700, true);
        mkdir($tmp . '/old-migrations', 0700, true);
        mkdir($tmp . '/storage', 0700, true);
        mkdir($tmp . '/upload', 0700, true);

        foreach (glob(WC_ROOT . '/database/migrations/*.php') ?: [] as $migration) {
            if (basename($migration) < '2026_10_23_0009_admin_platform.php') {
                copy($migration, $tmp . '/old-migrations/' . basename($migration));
            }
        }

        $database = $tmp . '/installed.sqlite';
        $old = Connection::sqlite($database);
        (new Migrator($old, $tmp . '/old-migrations'))->migrate();
        self::assertFalse($old->tableExists('admin_notices'));

        $config = $tmp . '/config.php';
        file_put_contents($config, '<?php return ' . var_export([
            'app' => ['timezone' => 'UTC', 'debug' => false],
            'database' => ['driver' => 'sqlite', 'name' => $database, 'prefix' => ''],
            'paths' => [
                'storage' => $tmp . '/storage',
                'upload' => $tmp . '/upload',
                'modules' => WC_MODULES,
                'themes' => WC_THEMES,
            ],
        ], true) . ';');

        $app = new Application(new Config($config));
        $app->bootServices();
        $upgraded = $app->container()->get(Connection::class);

        self::assertTrue($upgraded->tableExists('admin_notices'));
        self::assertTrue($upgraded->tableExists('not_found_log'));
        self::assertSame(1, (int) $upgraded->fetchColumn(
            'SELECT COUNT(*) FROM `' . $upgraded->table('migrations') . '` WHERE migration_id = ?',
            [WC_SCHEMA_VERSION]
        ));
        self::assertContains('deleted_at', array_column(
            $upgraded->fetchAll('PRAGMA table_info(`' . $upgraded->table('pages') . '`)'),
            'name'
        ));
    }
}
