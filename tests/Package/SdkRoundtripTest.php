<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use Core\Modules\ArchiveInspector;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Consumer-side proof: the SDK scaffold produces a package that the Module
 * Manager can stage, install, enable — and whose provider can then exercise
 * the supported ModuleContext API (route, admin page, job, permission)
 * without touching core internals.
 */
final class SdkRoundtripTest extends TestCase
{
    private string $tmp;
    private Connection $db;
    private PackageManager $pm;
    private ModuleRegistry $registry;
    private Router $router;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('zip')) {
            $this->markTestSkipped('needs pdo_sqlite + zip');
        }
        require_once WC_ROOT . '/../sdk/src/Scaffold.php';
        $this->tmp = sys_get_temp_dir() . '/wc-sdk-' . bin2hex(random_bytes(4));
        foreach (['staging', 'industries', 'addons', 'themes', 'scaffold'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->router = new Router();
        $this->registry = new ModuleRegistry(
            $this->db, $this->tmp . '/industries', $this->tmp . '/addons', $this->tmp . '/themes'
        );
        $this->pm = new PackageManager(
            $this->db, $this->registry, new ArchiveInspector(),
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

    public function testScaffoldedPackageInstallsAndRegisters(): void
    {
        // Scaffold a real package with the SDK.
        \WebsiteCore\Sdk\Scaffold::make('addon', 'acme/probe', $this->tmp . '/scaffold');
        $pkgDir = $this->tmp . '/scaffold/probe';
        $this->assertFileExists($pkgDir . '/addon.json');
        $manifest = json_decode((string) file_get_contents($pkgDir . '/addon.json'), true);
        $this->assertSame([], \Core\Modules\Manifest::validate($manifest));

        // Give the generated provider a body that exercises the context API.
        $provider = file_get_contents($pkgDir . '/src/Provider.php');
        $provider = str_replace(
            '// Register routes, admin pages, jobs, content types, listeners.',
            <<<'PHP'
$ctx->route('GET', '/probe', fn (\Core\Http\Request $r) => \Core\Http\Response::text('probe-ok'));
            $ctx->adminPage('Probe', '/admin/probe', 'probe.view');
            $ctx->scheduledJob('probe.ping', fn (array $p) => null);
            $ctx->permission('probe.view', 'View probe page');
PHP,
            $provider
        );
        file_put_contents($pkgDir . '/src/Provider.php', $provider);

        // Zip it exactly as the Module Manager would receive it.
        $zipPath = $this->tmp . '/probe.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pkgDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = 'probe/' . str_replace('\\', '/', substr($f->getPathname(), strlen($pkgDir) + 1));
                $zip->addFile($f->getPathname(), $rel);
            }
        }
        $zip->close();

        // Full lifecycle through the real PackageManager.
        [, $err] = $this->pm->stage($zipPath);
        $this->assertNull($err);
        [$ok, $err] = $this->pm->install('acme/probe');
        $this->assertTrue($ok, (string) $err);
        [$ok, $err] = $this->pm->enable('acme/probe');
        $this->assertTrue($ok, (string) $err);

        // Boot and prove the context registrations took effect.
        $this->registry->discover();
        $perms = new \Core\Auth\Permissions();
        $booted = $this->registry->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            fn (string $id) => new \Core\Modules\ModuleContext(
                $id, $this->router, new \Core\Events\Dispatcher(), $perms
            )
        );
        $this->assertSame(['acme/probe'], $booted);

        $req = new Request('GET', '/probe', '', [], [], [], [], []);
        $resp = $this->router->dispatch($req);
        $this->assertSame('probe-ok', $resp->body());

        $nav = $this->registry->adminNavItems();
        $this->assertSame('Probe', $nav[0]['label']);
        $this->assertArrayHasKey('probe.ping', $this->registry->jobHandlers());
        $this->assertContains('probe.view', $perms->allCapabilities());
        $this->assertTrue($perms->allows('admin', 'probe.view'));
        $this->assertFalse($perms->allows('editor', 'probe.view'));
    }
}
