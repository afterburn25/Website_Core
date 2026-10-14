<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Modules\ArchiveInspector;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use Core\Routing\Router;
use Core\Settings\SettingsRepository;
use Core\View\View;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Acceptance proof for the reference deployment model: two genuinely
 * distinct sites (a restaurant site and a salon site) run identical core
 * code, identical core schema, and the byte-identical shared inquiry
 * add-on — while owning completely different data. Also proves theme
 * switching changes presentation only, and uninstall preserves
 * package data until an explicit purge.
 */
final class ReferenceSitesParityTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('zip')) {
            $this->markTestSkipped('needs pdo_sqlite + zip');
        }
        \Core\I18n\Translator::setInstance(new \Core\I18n\Translator('en'));
    }

    /** An isolated site: own dirs, own DB, own package manager + router. */
    private function site(string $name): RefSite
    {
        $root = \WcProcessTmpDirs::make('wc-site-' . $name . '-');
        foreach (['staging', 'industries', 'addons', 'themes'] as $d) {
            mkdir($root . '/' . $d, 0777, true);
        }

        // A real deployment ships the base theme inside the themes dir —
        // mirror that instead of pointing at the repo copy.
        $this->copyTree(WC_THEMES . '/base', $root . '/themes/base');

        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $settings = new SettingsRepository($db);
        $registry = new ModuleRegistry(
            $db, $root . '/industries', $root . '/addons', $root . '/themes'
        );
        $pm = new PackageManager(
            $db, $registry, new ArchiveInspector(),
            $root . '/staging', $root . '/industries', $root . '/addons',
            $root . '/themes', $root . '/op.lock'
        );
        return new RefSite($root, $db, $settings, $registry, $pm);
    }

    private function copyTree(string $src, string $dst): void
    {
        @mkdir($dst, 0777, true);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $t = $dst . '/' . substr($f->getPathname(), strlen($src) + 1);
                @mkdir(dirname($t), 0777, true);
                copy($f->getPathname(), $t);
            }
        }
    }

    private function zipDir(string $srcDir, string $innerName, string $zipPath): string
    {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = $innerName . '/' . substr($f->getPathname(), strlen($srcDir) + 1);
                $zip->addFile($f->getPathname(), str_replace('\\', '/', $rel));
            }
        }
        $zip->close();
        return $zipPath;
    }

    private function installEnable(RefSite $s, string $zipPath, string $id): void
    {
        [, $err] = $s->pm->stage($zipPath);
        $this->assertNull($err, "stage {$id}");
        [$ok, $err] = $s->pm->install($id);
        $this->assertTrue($ok, "install {$id}: {$err}");
        [$ok, $err] = $s->pm->enable($id);
        $this->assertTrue($ok, "enable {$id}: {$err}");
    }

    private function boot(RefSite $s): void
    {
        $view = new View();
        $view->addResolver('admin', fn (string $v): ?string =>
            is_file(WC_ROOT . '/admin/views/' . $v . '.php')
                ? WC_ROOT . '/admin/views/' . $v . '.php' : null);
        // Same resolution as Application: active theme dir first, base last.
        $view->addResolver('theme', function (string $v) use ($s): ?string {
            $active = (string) $s->settings->get('theme.active', 'base');
            foreach ([$active, 'base'] as $theme) {
                $p = $s->root . '/themes/' . $theme . '/templates/' . $v . '.php';
                if (is_file($p)) {
                    return $p;
                }
            }
            return null;
        });
        $s->registry->discover();
        $map = [
            Connection::class => $s->db,
            SettingsRepository::class => $s->settings,
            View::class => $view,
            \Core\Security\Csrf::class => new \Core\Security\Csrf(),
            \Core\Mail\SystemMail::class => new \Core\Mail\SystemMail($s->db, $s->settings),
            \Core\Admin\Nav::class => new \Core\Admin\Nav($s->registry, null),
            \Core\Content\ContentItems::class => new \Core\Content\ContentItems($s->db, $s->registry),
        ];
        $services = fn (string $id) => $map[$id] ?? throw new \RuntimeException("unbound {$id}");
        $adminMw = fn (Request $r, callable $next) =>
            \Core\Http\Response::text('ADMIN-AUTH-REQUIRED', 302);
        $s->registry->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            fn (string $id) => new \Core\Modules\ModuleContext(
                $id, $s->router, new \Core\Events\Dispatcher(), new \Core\Auth\Permissions(),
                $adminMw, $view, $services,
                fn () => (string) $s->settings->get('theme.active', 'base'),
            )
        );
    }

    /** Recursive sha256 manifest of a directory's file contents. */
    private function treeHash(string $dir): string
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
                $files[$rel] = hash_file('sha256', $f->getPathname());
            }
        }
        ksort($files);
        return hash('sha256', json_encode($files));
    }

    private function coreTables(Connection $db): array
    {
        $names = array_column(
            $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table'"),
            'name'
        );
        $core = array_values(array_filter($names, fn (string $n) =>
            str_starts_with($n, 'wc_')
            && !preg_match('/^wc_(inquiry|restaurant|salon)_/', $n)));
        sort($core);
        return $core;
    }

    public function testTwoDistinctSitesIdenticalCoreSharedAddon(): void
    {
        $scratch = \WcProcessTmpDirs::make('wc-parity-');
        @mkdir($scratch, 0777, true);
        // ONE zip build = byte-identical shared add-on on both sites.
        $inquiryZip = $this->zipDir(
            WC_ROOT . '/../examples/addon-inquiry', 'inquiry', $scratch . '/inquiry.zip'
        );
        $restaurantZip = $this->zipDir(
            WC_ROOT . '/../examples/industry-restaurant', 'restaurant', $scratch . '/restaurant.zip'
        );
        $salonZip = $this->zipDir(
            WC_ROOT . '/../examples/industry-salon', 'salon', $scratch . '/salon.zip'
        );

        $bistro = $this->site('bistro');
        $spa = $this->site('spa');

        $this->installEnable($bistro, $inquiryZip, 'wcref/inquiry');
        $this->installEnable($bistro, $restaurantZip, 'wcref/restaurant');
        $this->installEnable($spa, $inquiryZip, 'wcref/inquiry');
        $this->installEnable($spa, $salonZip, 'wcref/salon');

        // Identical core schema on both sites.
        $this->assertSame($this->coreTables($bistro->db), $this->coreTables($spa->db));

        // The shared add-on is byte-identical after install on both sites.
        $this->assertSame(
            $this->treeHash($bistro->root . '/addons/inquiry'),
            $this->treeHash($spa->root . '/addons/inquiry')
        );

        // Distinct ownership boundaries: each site has only its own
        // industry tables.
        $this->assertTrue($bistro->db->tableExists('restaurant_items'));
        $this->assertFalse($bistro->db->tableExists('salon_services'));
        $this->assertTrue($spa->db->tableExists('salon_services'));
        $this->assertFalse($spa->db->tableExists('restaurant_items'));

        $this->boot($bistro);
        $this->boot($spa);

        // Distinct data on each site.
        $_SESSION['_wc_csrf'] = 'tok';
        $bistro->router->dispatch(new Request('POST', '/inquiry', '', [], [
            '_csrf' => 'tok', 'name' => 'Pat', 'email' => 'pat@bistro.test', 'message' => 'Table for two?',
        ], [], [], []));
        $spa->router->dispatch(new Request('POST', '/inquiry', '', [], [
            '_csrf' => 'tok', 'name' => 'Sam', 'email' => 'sam@spa.test', 'message' => 'Book a massage?',
        ], [], [], []));
        $this->assertSame('pat@bistro.test', $bistro->db->fetchColumn(
            'SELECT email FROM `' . $bistro->db->table('inquiry_submissions') . '` LIMIT 1'));
        $this->assertSame('sam@spa.test', $spa->db->fetchColumn(
            'SELECT email FROM `' . $spa->db->table('inquiry_submissions') . '` LIMIT 1'));

        // Each site serves its own industry surface and 404s the other's.
        $bistro->db->insert('restaurant_categories', ['name' => 'Mains', 'sort_order' => 1, 'created_at' => date('c')]);
        $bistro->db->insert('restaurant_items', [
            'category_id' => 1, 'name' => 'Gumbo', 'price_cents' => 1450, 'visible' => 1,
            'created_at' => date('c'),
        ]);
        $this->assertSame(200, $bistro->router->dispatch(
            new Request('GET', '/menu', '', [], [], [], [], []))->status());
        $this->assertSame(404, $bistro->router->dispatch(
            new Request('GET', '/services', '', [], [], [], [], []))->status());
        $this->assertSame(404, $spa->router->dispatch(
            new Request('GET', '/menu', '', [], [], [], [], []))->status());
    }

    public function testThemeSwitchPreservesBusinessData(): void
    {
        $s = $this->site('switch');
        $scratch = $s->root . '/scratch';
        @mkdir($scratch);
        $terraZip = $this->zipDir(WC_ROOT . '/../examples/theme-terra', 'terra', $scratch . '/terra.zip');
        $noirZip = $this->zipDir(WC_ROOT . '/../examples/theme-noir', 'noir', $scratch . '/noir.zip');
        $inquiryZip = $this->zipDir(WC_ROOT . '/../examples/addon-inquiry', 'inquiry', $scratch . '/inquiry.zip');

        $this->installEnable($s, $inquiryZip, 'wcref/inquiry');
        foreach ([[$terraZip, 'wcref/terra'], [$noirZip, 'wcref/noir']] as [$z, $id]) {
            [, $err] = $s->pm->stage($z);
            $this->assertNull($err);
            [$ok, $err] = $s->pm->install($id);
            $this->assertTrue($ok, "install {$id}: {$err}");
        }
        $s->settings->set('theme.active', 'terra');

        // Business data before the switch.
        $s->db->insert('pages', [
            'slug' => 'about', 'title' => 'About us', 'body' => '<p>Family run since 1998.</p>',
            'status' => 'published', 'created_at' => date('c'), 'updated_at' => date('c'),
        ]);
        $s->db->insert('inquiry_submissions', [
            'name' => 'Pat', 'email' => 'pat@x.test', 'message' => 'Hello',
            'status' => 'new', 'created_at' => date('c'),
        ]);
        $pagesBefore = $s->db->fetchAll('SELECT * FROM `' . $s->db->table('pages') . '`');
        $inquiriesBefore = $s->db->fetchAll('SELECT * FROM `' . $s->db->table('inquiry_submissions') . '`');

        $this->boot($s);
        $terraHtml = $s->router->dispatch(
            new Request('GET', '/inquiry', '', [], [], [], [], []))->body();
        $this->assertStringContainsString('masthead', $terraHtml, 'terra layout expected');
        $this->assertStringNotContainsString('rail-brand', $terraHtml);

        // Flip presentation only.
        $s->settings->set('theme.active', 'noir');
        $noirHtml = $s->router->dispatch(
            new Request('GET', '/inquiry', '', [], [], [], [], []))->body();
        $this->assertStringContainsString('rail-brand', $noirHtml, 'noir layout expected');
        $this->assertStringNotContainsString('masthead', $noirHtml);
        // Same form functionality under the new presentation.
        $this->assertStringContainsString('_csrf', $noirHtml);

        // Nothing but the setting changed.
        $this->assertSame($pagesBefore, $s->db->fetchAll('SELECT * FROM `' . $s->db->table('pages') . '`'));
        $this->assertSame($inquiriesBefore, $s->db->fetchAll(
            'SELECT * FROM `' . $s->db->table('inquiry_submissions') . '`'));
    }

    public function testUninstallPreservesDataPurgeRemoves(): void
    {
        $s = $this->site('cycle');
        $zip = $this->zipDir(
            WC_ROOT . '/../examples/addon-inquiry', 'inquiry', $s->root . '/inquiry.zip'
        );
        $this->installEnable($s, $zip, 'wcref/inquiry');
        $s->db->insert('inquiry_submissions', [
            'name' => 'Keep Me', 'email' => 'keep@x.test', 'message' => 'data',
            'status' => 'new', 'created_at' => date('c'),
        ]);

        // Disable → boot still safe; uninstall → files gone, DATA stays.
        [$ok] = $s->pm->disable('wcref/inquiry');
        $this->assertTrue($ok);
        [$ok, $err] = $s->pm->uninstall('wcref/inquiry');
        $this->assertTrue($ok, (string) $err);
        $this->assertDirectoryDoesNotExist($s->root . '/addons/inquiry');
        $this->assertTrue($s->db->tableExists('inquiry_submissions'));
        $this->assertSame(1, (int) $s->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $s->db->table('inquiry_submissions') . '`'));

        // Explicit purge is the only destructive path.
        [$ok, $err] = $s->pm->purgeData('wcref/inquiry');
        $this->assertTrue($ok, (string) $err);
        $this->assertFalse($s->db->tableExists('inquiry_submissions'));
    }
}

/** Value object bundling one isolated site's pieces for the test. */
final class RefSite
{
    public Router $router;

    public function __construct(
        public string $root,
        public Connection $db,
        public SettingsRepository $settings,
        public ModuleRegistry $registry,
        public PackageManager $pm,
    ) {
        $this->router = new Router();
    }
}
