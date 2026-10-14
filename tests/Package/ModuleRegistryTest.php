<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Modules\ModuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Package lifecycle tests using fixture packages on disk — validates
 * discovery, duplicate rejection, dependency ordering, and boot quarantine
 * without touching core internals.
 */
final class ModuleRegistryTest extends TestCase
{
    private string $tmp;
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->tmp = sys_get_temp_dir() . '/wc-pkg-' . bin2hex(random_bytes(4));
        foreach (['industries', 'addons', 'themes'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
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
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmp);
    }

    private function registry(): ModuleRegistry
    {
        return new ModuleRegistry(
            $this->db,
            $this->tmp . '/industries',
            $this->tmp . '/addons',
            $this->tmp . '/themes'
        );
    }

    private function makePackage(string $dest, string $dirName, array $manifest, array $files = []): string
    {
        $dir = $this->tmp . '/' . $dest . '/' . $dirName;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/addon.json', json_encode($manifest));
        foreach ($files as $rel => $content) {
            $path = $dir . '/' . $rel;
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, $content);
        }
        return $dir;
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'schema' => 1, 'id' => 'acme/thing', 'type' => 'addon',
            'name' => 'Thing', 'version' => '1.0.0', 'license' => 'MIT',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
        ], $overrides);
    }

    public function testDiscoveryAndStates(): void
    {
        $this->makePackage('addons', 'thing', $this->manifest());
        $r = $this->registry();
        $r->discover();
        $all = $r->all();
        $this->assertArrayHasKey('acme/thing', $all);
        $this->assertSame('staged', $all['acme/thing']['state']);
        $this->assertSame([], $all['acme/thing']['errors']);
    }

    public function testInvalidManifestReportedNotFatal(): void
    {
        $this->makePackage('addons', 'bad', ['schema' => 99, 'id' => 'BAD']);
        $r = $this->registry();
        $r->discover();
        $errors = $r->all()['BAD']['errors'] ?? [];
        $this->assertNotEmpty($errors);
    }

    public function testMissingDependencyReported(): void
    {
        $this->makePackage('industries', 'rest', $this->manifest([
            'id' => 'acme/restaurant', 'type' => 'industry',
            'dependencies' => ['acme/inquiry-form' => '>=1.0.0'],
        ]));
        $r = $this->registry();
        $r->discover();
        $this->assertContains('missing dependency acme/inquiry-form', $r->all()['acme/restaurant']['errors']);
    }

    public function testDependencyOrder(): void
    {
        $this->makePackage('addons', 'form', $this->manifest(['id' => 'acme/form']));
        $this->makePackage('industries', 'rest', $this->manifest([
            'id' => 'acme/restaurant', 'type' => 'industry',
            'dependencies' => ['acme/form' => '>=1.0.0'],
        ]));
        $r = $this->registry();
        $r->discover();
        $order = $r->dependencyOrder();
        $this->assertLessThan(
            array_search('acme/restaurant', $order, true),
            array_search('acme/form', $order, true)
        );
    }

    public function testBootQuarantinesThrowingProvider(): void
    {
        $dir = $this->makePackage('addons', 'boom', $this->manifest([
            'id' => 'acme/boom', 'provider' => 'AcmeBoom\\Provider',
            'psr4' => ['AcmeBoom\\' => 'src/'],
        ]), [
            'src/Provider.php' => '<?php namespace AcmeBoom; class Provider { public function register($ctx) { throw new \RuntimeException("kaboom"); } }',
        ]);
        $r = $this->registry();
        $r->discover();
        $r->setState('acme/boom', 'enabled');
        $booted = $r->bootEnabled(
            function (string $prefix, string $dir) {
                \CoreAutoload::register($prefix, $dir);
            },
            new \Core\Modules\ModuleContext('acme/boom', new \Core\Routing\Router(), new \Core\Events\Dispatcher())
        );
        $this->assertSame([], $booted);
        $this->assertSame('failed', $r->state('acme/boom'));
        $journal = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('package_journal') . '` WHERE package_id = ?',
            ['acme/boom']
        );
        $this->assertSame('failed', $journal['result']);
    }

    public function testBootCallsProvider(): void
    {
        $this->makePackage('addons', 'ok', $this->manifest([
            'id' => 'acme/ok', 'provider' => 'AcmeOk\\Provider',
            'psr4' => ['AcmeOk\\' => 'src/'],
        ]), [
            'src/Provider.php' => '<?php namespace AcmeOk; class Provider { public static $called = false; public function register($ctx) { self::$called = true; } }',
        ]);
        $r = $this->registry();
        $r->discover();
        $r->setState('acme/ok', 'enabled');
        $booted = $r->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            new \Core\Modules\ModuleContext('acme/ok', new \Core\Routing\Router(), new \Core\Events\Dispatcher())
        );
        $this->assertSame(['acme/ok'], $booted);
        $this->assertTrue(\AcmeOk\Provider::$called);
    }
}
