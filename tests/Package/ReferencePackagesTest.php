<?php
declare(strict_types=1);

namespace Tests\Package;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ArchiveInspector;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Installs the shipped reference packages (examples/) through the real
 * Module Manager path and dispatches real requests against their routes —
 * proves the examples work, not just that they parse.
 */
final class ReferencePackagesTest extends TestCase
{
    private string $tmp;
    private Connection $db;
    private PackageManager $pm;
    private ModuleRegistry $registry;
    private Router $router;
    private \Core\Settings\SettingsRepository $serviceSettings;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('zip')) {
            $this->markTestSkipped('needs pdo_sqlite + zip');
        }
        // Process-lifetime dir: provider classes resolve __DIR__-relative
        // views long after a given test finishes (see WcProcessTmpDirs).
        $this->tmp = \WcProcessTmpDirs::make('wc-ref-');
        foreach (['staging', 'industries', 'addons', 'themes'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        // The test theme root needs a 'base' fallback for theme:layout.
        $this->linkBaseTheme();

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
        \Core\I18n\Translator::setInstance(new \Core\I18n\Translator('en'));
    }

    private function linkBaseTheme(): void
    {
        // Views resolve theme:* against WC_THEMES — that's the real constant;
        // for module view overrides the resolver also uses WC_THEMES, so the
        // test relies on the package's own views (no theme override needed).
        // Nothing to do here beyond noting the layout comes from real base.
    }

    protected function tearDown(): void
    {
        // Temp dirs cleaned at process shutdown by WcProcessTmpDirs.
    }

    private function zipDir(string $srcDir, string $innerName, string $zipName): string
    {
        $path = $this->tmp . '/' . $zipName;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
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
        return $path;
    }

    private function installEnable(string $exampleDir, string $inner, string $id): void
    {
        $zip = $this->zipDir(WC_ROOT . '/../examples/' . $exampleDir, $inner, $inner . '.zip');
        [, $err] = $this->pm->stage($zip);
        $this->assertNull($err, "stage {$id}");
        [$ok, $err] = $this->pm->install($id);
        $this->assertTrue($ok, "install {$id}: {$err}");
        [$ok, $err] = $this->pm->enable($id);
        $this->assertTrue($ok, "enable {$id}: {$err}");
    }

    private function services(): callable
    {
        $settings = new \Core\Settings\SettingsRepository($this->db);
        $this->serviceSettings = $settings;
        $configFile = $this->tmp . '/test-config.php';
        file_put_contents($configFile, '<?php return ' . var_export([
            'paths' => ['storage' => $this->tmp . '/storage'],
        ], true) . ';');
        $media = new \Core\Media\MediaService($this->db, new \Core\Config($configFile));
        $view = new \Core\View\View();
        $view->addResolver('admin', fn (string $v): ?string =>
            is_file(WC_ROOT . '/admin/views/' . $v . '.php') ? WC_ROOT . '/admin/views/' . $v . '.php' : null);
        $view->addResolver('theme', fn (string $v): ?string =>
            is_file(WC_THEMES . '/base/templates/' . $v . '.php') ? WC_THEMES . '/base/templates/' . $v . '.php' : null);
        $map = [
            Connection::class => $this->db,
            \Core\Settings\SettingsRepository::class => $settings,
            \Core\View\View::class => $view,
            \Core\Security\Csrf::class => new \Core\Security\Csrf(),
            \Core\Mail\SystemMail::class => new \Core\Mail\SystemMail($this->db, $settings),
            \Core\Security\RateLimiter::class => new \Core\Security\RateLimiter($this->db),
            \Core\Admin\Nav::class => new \Core\Admin\Nav($this->registry, null),
            \Core\Content\ContentItems::class => new \Core\Content\ContentItems($this->db, $this->registry),
            \Core\Media\MediaService::class => $media,
            \Core\Branding\BrandAssets::class => new \Core\Branding\BrandAssets($this->db, $settings, $media),
        ];
        return fn (string $id) => $map[$id] ?? throw new \RuntimeException("unbound {$id}");
    }

    private function boot(array $extraCtxArgs = []): void
    {
        $this->registry->discover();
        $services = $this->services();
        $adminMw = fn (Request $r, callable $next) => Response::text('ADMIN-AUTH-REQUIRED', 302);
        $this->registry->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            function (string $id) use ($adminMw, $services): \Core\Modules\ModuleContext {
                $info = $this->registry->all()[$id];
                return new \Core\Modules\ModuleContext(
                    $id, $this->router, new \Core\Events\Dispatcher(), new \Core\Auth\Permissions(),
                    $adminMw, $services(\Core\View\View::class), $services, fn () => 'base',
                    WC_THEMES, basename((string) $info['dir']), (string) $info['dir'],
                );
            }
        );
    }

    private function req(string $method, string $path, array $post = []): Response
    {
        return $this->router->dispatch(new Request($method, $path, '', [], $post, [], [], []));
    }

    public function testInquiryAddonEndToEnd(): void
    {
        $this->installEnable('addon-inquiry', 'inquiry', 'wcref/inquiry');
        $this->boot();

        // Migration ran on install.
        $this->assertTrue($this->db->tableExists('inquiry_submissions'));

        // Public form renders inside the theme layout.
        $resp = $this->req('GET', '/inquiry');
        $this->assertSame(200, $resp->status());
        $this->assertStringContainsString('Contact us', $resp->body());
        $this->assertStringContainsString('_csrf', $resp->body());

        // POST without CSRF → 419.
        $resp = $this->req('POST', '/inquiry', ['name' => 'A', 'email' => 'a@x.com', 'message' => 'hi']);
        $this->assertSame(419, $resp->status());

        // Honeypot → silent redirect, nothing stored.
        $_SESSION['_wc_csrf'] = 'tok';
        $resp = $this->req('POST', '/inquiry', [
            '_csrf' => 'tok', 'name' => 'Bot', 'email' => 'b@x.com',
            'message' => 'spam', 'website' => 'http://spam',
        ]);
        $this->assertSame(302, $resp->status());
        $this->assertSame(0, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('inquiry_submissions') . '`'
        ));

        // Valid submit → stored + redirected.
        $resp = $this->req('POST', '/inquiry', [
            '_csrf' => 'tok', 'name' => 'Pat', 'email' => 'pat@x.com', 'message' => 'Interested!',
        ]);
        $this->assertSame(302, $resp->status());
        $this->assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('inquiry_submissions') . '`'
        ));

        // Rate limit: 5 submissions per IP per window — the 6th is refused.
        for ($i = 0; $i < 4; $i++) {
            $this->req('POST', '/inquiry', [
                '_csrf' => 'tok', 'name' => 'Pat', 'email' => 'pat@x.com', 'message' => 'More ' . $i,
            ]);
        }
        $resp = $this->req('POST', '/inquiry', [
            '_csrf' => 'tok', 'name' => 'Pat', 'email' => 'pat@x.com', 'message' => 'Once more',
        ]);
        $this->assertSame(200, $resp->status());
        $this->assertStringContainsString('Too many submissions', $resp->body());
        $this->assertSame(5, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('inquiry_submissions') . '`'
        ));

        // Admin page requires the admin pipeline (auth+CSRF) — not reachable bare.
        $resp = $this->req('GET', '/admin/inquiries');
        $this->assertSame(302, $resp->status());
        $this->assertStringContainsString('ADMIN-AUTH-REQUIRED', $resp->body());
    }

    public function testRestaurantAndSalonInstallAndBoot(): void
    {
        $this->installEnable('industry-restaurant', 'restaurant', 'wcref/restaurant');
        $this->installEnable('industry-salon', 'salon', 'wcref/salon');
        $this->boot();

        $this->assertTrue($this->db->tableExists('restaurant_categories'));
        $this->assertTrue($this->db->tableExists('restaurant_items'));
        $this->assertTrue($this->db->tableExists('salon_services'));
        $this->assertTrue($this->db->tableExists('salon_staff'));

        // Seed via module-owned tables, then public pages render.
        $this->db->insert('restaurant_categories', ['name' => 'Mains', 'sort_order' => 1, 'created_at' => date('c')]);
        $this->db->insert('restaurant_items', [
            'category_id' => 1, 'name' => 'Gumbo', 'price_cents' => 1450, 'visible' => 1,
            'created_at' => date('c'),
        ]);
        $resp = $this->req('GET', '/menu');
        $this->assertSame(200, $resp->status());
        $this->assertStringContainsString('Gumbo', $resp->body());
        $this->assertStringContainsString('14.50', $resp->body());

        // Generic content-type loop: the 'special' type the package declared
        // is validated + stored by core and rendered by the package's view.
        $items = new \Core\Content\ContentItems($this->db, $this->registry);
        [$ok, $err] = $items->save(null, 'wcref/restaurant', 'special', [
            'dish' => 'Crawfish Etouffee', 'price' => '12.75', 'available' => '1',
        ], null);
        $this->assertTrue($ok, (string) $err);
        [$ok] = $items->save(null, 'wcref/restaurant', 'special', [
            'dish' => 'Hidden Gumbo', 'available' => '0',
        ], null);
        $this->assertTrue($ok);
        $resp = $this->req('GET', '/menu');
        $this->assertStringContainsString('Crawfish Etouffee', $resp->body());
        $this->assertStringContainsString('12.75', $resp->body());
        $this->assertStringNotContainsString('Hidden Gumbo', $resp->body());

        $this->db->insert('salon_services', [
            'name' => 'Cut', 'duration_minutes' => 45, 'price_cents' => 6000,
            'visible' => 1, 'created_at' => date('c'),
        ]);
        $resp = $this->req('GET', '/services');
        $this->assertSame(200, $resp->status());
        $this->assertStringContainsString('Cut', $resp->body());
        $this->assertStringContainsString('60.00', $resp->body());
    }

    public function testCateringIndustryInstallsRendersAndCapturesQuote(): void
    {
        $this->installEnable('industry-catering', 'catering', 'cajunwebpro/catering');
        $this->boot();

        $this->assertTrue($this->db->tableExists('catering_menu_categories'));
        $this->assertTrue($this->db->tableExists('catering_menu_items'));
        $this->assertTrue($this->db->tableExists('catering_packages'));
        $this->assertTrue($this->db->tableExists('catering_inquiries'));
        $this->assertGreaterThan(0, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '`'
        ));
        $settings = $this->serviceSettings;
        $logoId = (int) $settings->get('brand.logo_media_id', 0);
        $this->assertGreaterThan(0, $logoId);
        $this->assertSame('catering-co-logo.png', $this->db->fetchColumn(
            'SELECT original_name FROM `' . $this->db->table('media') . '` WHERE id = ?', [$logoId]
        ));
        $this->assertTrue((bool) $settings->get('branding.seeded.cajunwebpro.catering', false));
        $navigation = new \Core\Content\MenuService($this->db);
        $tree = $navigation->tree('primary');
        $this->assertSame(
            ['Our Story', 'Menus', 'Services', 'Packages', 'Gallery', 'Contact'],
            array_column($tree, 'label')
        );
        $footerTree = $navigation->tree('footer');
        $this->assertSame(
            ['Menus', 'Services', 'Packages', 'Gallery', 'Service Area', 'FAQ', 'Contact', 'Request a Quote'],
            array_column($footerTree, 'label')
        );
        $cateringNav = array_values(array_filter(
            $this->registry->adminNavItems(),
            fn (array $item): bool => $item['package'] === 'cajunwebpro/catering'
        ));
        $this->assertNotContains('Dashboard', array_column($cateringNav, 'label'));
        $widgets = $this->registry->dashboardWidgets();
        $this->assertSame(['new-inquiries'], array_column($widgets, 'key'));
        $this->assertSame('/admin/catering/inquiries', $widgets[0]['url']);

        [$updated, $error] = $navigation->updateItem(
            (int) $tree[0]['id'], 'Our Story', '/about', null, 0, 'tab'
        );
        $this->assertTrue($updated, (string) $error);
        [$updated, $error] = $navigation->updateItem(
            (int) $tree[2]['id'], 'Services', '/services', (int) $tree[0]['id'], 0, 'self'
        );
        $this->assertTrue($updated, (string) $error);
        [$updated, $error] = $navigation->updateItem(
            (int) $tree[4]['id'], 'Gallery', '/gallery', null, 4, 'window'
        );
        $this->assertTrue($updated, (string) $error);

        $home = $this->req('GET', '/');
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Gather', $home->body());
        $this->assertStringContainsString('Request a Quote', $home->body());
        $this->assertStringContainsString('class="c-nav-children"', $home->body());
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $home->body());
        $this->assertStringContainsString('data-open-window', $home->body());
        // Package asset URLs use the installed directory namespace. The
        // staged archive installs as modules/industries/catering, while the
        // manifest id remains cajunwebpro/catering.
        $this->assertStringContainsString('/index.php?p=/_a/modules/catering/assets/css/catering.css', $home->body());
        $this->assertStringContainsString('class="c-brand-logo"', $home->body());
        $this->assertStringContainsString('/media/' . $logoId . '/w/480/catering-co-logo.png', $home->body());
        $this->assertStringContainsString('class="c-footer-nav"', $home->body());
        $this->assertStringContainsString('All rights reserved.', $home->body());
        $this->assertStringNotContainsString('class="c-footer-social"', $home->body());
        $settings->set('social.instagram.url', 'https://instagram.com/cateringco');
        $settings->set('social.placement', 'both');
        $homeWithSocial = $this->req('GET', '/');
        $this->assertStringContainsString('class="c-header-social"', $homeWithSocial->body());
        $this->assertStringContainsString('class="c-footer-social"', $homeWithSocial->body());
        $this->assertSame(2, substr_count($homeWithSocial->body(), 'aria-label="Instagram"'));
        $this->assertLessThan(
            strpos($homeWithSocial->body(), 'class="c-header-social"'),
            strpos($homeWithSocial->body(), 'class="c-header-cta"')
        );

        foreach (['/services', '/menus', '/packages', '/gallery', '/about', '/service-area', '/faq', '/contact', '/request-a-quote'] as $route) {
            $response = $this->req('GET', $route);
            $this->assertSame(200, $response->status(), $route);
            $this->assertStringContainsString('<main id="main-content">', $response->body(), $route);
        }

        $menu = $this->req('GET', '/menus/plated-entrees');
        $this->assertSame(200, $menu->status());
        $this->assertStringContainsString('Herb-Crusted Salmon', $menu->body());

        $_SESSION['_wc_csrf'] = 'catering-test-token';
        $quote = $this->req('POST', '/request-a-quote', [
            '_csrf' => 'catering-test-token',
            'name' => 'Taylor Client',
            'email' => 'taylor@example.com',
            'phone' => '555-0100',
            'event_type' => 'Wedding',
            'event_date' => '2027-05-15',
            'event_time' => '17:30',
            'guest_count' => '120',
            'venue' => 'Garden Hall',
            'service_style' => 'Plated dinner',
            'menu_interest' => 'Plated Entrées',
            'budget' => '$10,000–$20,000',
            'dietary' => 'Two gluten-free meals',
            'message' => 'Outdoor cocktail hour.',
        ]);
        $this->assertSame(302, $quote->status());
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('catering_inquiries') . '` WHERE email = ?',
            ['taylor@example.com']
        );
        $this->assertNotNull($row);
        $this->assertSame('new', $row['status']);
        $this->assertSame(120, (int) $row['guest_count']);
    }

    public function testCateringNavigationInitializationPreservesExistingMenu(): void
    {
        $this->installEnable('industry-catering', 'catering', 'cajunwebpro/catering');
        $navigation = new \Core\Content\MenuService($this->db);
        $menuId = $navigation->ensureLocation('primary', 'Primary navigation');
        [$id, $error] = $navigation->addItem($menuId, 'Custom Home', '/', null, 0);
        $this->assertNotNull($id, (string) $error);

        $this->boot();

        $tree = $navigation->tree('primary');
        $this->assertCount(1, $tree);
        $this->assertSame('Custom Home', $tree[0]['label']);
        $home = $this->req('GET', '/');
        $this->assertStringContainsString('Custom Home', $home->body());
        $this->assertStringNotContainsString('Our Story', $home->body());
    }

    public function testThemesValidateAsPackages(): void
    {
        foreach (['theme-terra' => 'terra', 'theme-noir' => 'noir'] as $dir => $inner) {
            $manifest = json_decode((string) file_get_contents(
                WC_ROOT . '/../examples/' . $dir . '/theme.json'
            ), true);
            $this->assertSame([], \Core\Modules\Manifest::validate($manifest), $dir);
            $zip = $this->zipDir(WC_ROOT . '/../examples/' . $dir, $inner, $inner . '.zip');
            [, $err] = $this->pm->stage($zip);
            $this->assertNull($err, "stage {$dir}");
            $id = $manifest['id'];
            [$ok, $err] = $this->pm->install($id);
            $this->assertTrue($ok, "install {$id}: {$err}");
            $this->assertFileExists($this->tmp . '/themes/' . $inner . '/theme.json');
            $this->assertFileExists($this->tmp . '/themes/' . $inner . '/templates/layout.php');
        }
    }
}
