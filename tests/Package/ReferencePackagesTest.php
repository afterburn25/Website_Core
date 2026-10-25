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
        $this->boot();

        $this->assertTrue($this->db->tableExists('restaurant_categories'));
        $this->assertTrue($this->db->tableExists('restaurant_items'));

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

        // Core permits exactly one enabled industry. Exercise the supported
        // handoff instead of booting two competing industry route sets.
        [$ok, $err] = $this->pm->disable('wcref/restaurant');
        $this->assertTrue($ok, "disable wcref/restaurant: {$err}");
        $this->installEnable('industry-salon', 'salon', 'wcref/salon');
        $this->router = new Router();
        $this->boot();

        $this->assertTrue($this->db->tableExists('salon_services'));
        $this->assertTrue($this->db->tableExists('salon_staff'));
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
        $this->assertSame(10, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '`'
        ));
        $menuColumns = array_column(
            $this->db->fetchAll('PRAGMA table_info(`' . $this->db->table('catering_menu_items') . '`)'),
            'name'
        );
        $this->assertContains('image', $menuColumns);
        $this->assertSame(10, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '` WHERE image IS NOT NULL AND image <> ?',
            ['']
        ));
        $this->assertSame(0, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '` WHERE slug IN (?, ?)',
            ['garden-crostini', 'herb-crusted-salmon']
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
        $this->assertStringContainsString('Chicken &amp; Andouille Gumbo', $home->body());
        $this->assertStringContainsString('chicken-andouille-gumbo.webp', $home->body());
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
        $this->assertStringContainsString('Chicken &amp; Andouille Gumbo', $menu->body());
        $this->assertStringContainsString('Crawfish Étouffée', $menu->body());
        $this->assertStringContainsString('Crawfish Bisque', $menu->body());
        $this->assertStringContainsString('Blackened Redfish', $menu->body());
        $this->assertStringContainsString('chicken-andouille-gumbo.webp', $menu->body());
        $this->assertStringNotContainsString('Herb-Crusted Salmon', $menu->body());

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

    public function testLandscapingIndustryInstallsRendersAndCapturesEstimate(): void
    {
        $this->installEnable('industry-landscaping', 'landscaping', 'cajunwebpro/landscaping');
        $this->boot();

        foreach ([
            'landscaping_services', 'landscaping_projects', 'landscaping_plans',
            'landscaping_service_areas', 'landscaping_seasonal_services',
            'landscaping_estimates',
        ] as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
        $this->assertSame(6, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('landscaping_services') . '`'
        ));
        $this->assertSame(3, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('landscaping_plans') . '`'
        ));

        $settings = $this->serviceSettings;
        $logoId = (int) $settings->get('brand.logo_media_id', 0);
        $this->assertGreaterThan(0, $logoId);
        $this->assertSame('landscaping-mark.png', $this->db->fetchColumn(
            'SELECT original_name FROM `' . $this->db->table('media') . '` WHERE id = ?', [$logoId]
        ));
        $this->assertTrue((bool) $settings->get('branding.seeded.cajunwebpro.landscaping', false));
        $settings->set('site.contact_email', 'owner@example.com');

        $navigation = new \Core\Content\MenuService($this->db);
        $this->assertSame(
            ['Home', 'Services', 'Projects', 'Maintenance Plans', 'Service Areas', 'Contact'],
            array_column($navigation->tree('primary'), 'label')
        );
        $this->assertSame(
            ['Services', 'Projects', 'Maintenance Plans', 'Seasonal Services', 'Service Areas', 'About', 'Contact', 'Request an Estimate'],
            array_column($navigation->tree('footer'), 'label')
        );

        $home = $this->req('GET', '/');
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Your yard.', $home->body());
        $this->assertStringContainsString('Lawn Maintenance', $home->body());
        $this->assertStringContainsString('/index.php?p=/_a/modules/landscaping/assets/css/landscaping.css', $home->body());
        $this->assertStringContainsString('/media/' . $logoId . '/w/256/landscaping-mark.png', $home->body());

        foreach ([
            '/services', '/services/lawn-maintenance', '/projects', '/maintenance-plans',
            '/service-areas', '/seasonal-services', '/about', '/contact', '/request-estimate',
        ] as $route) {
            $response = $this->req('GET', $route);
            $this->assertSame(200, $response->status(), $route);
            $this->assertStringContainsString('<main id="main">', $response->body(), $route);
        }

        $_SESSION['_wc_csrf'] = 'landscaping-test-token';
        $estimate = $this->req('POST', '/request-estimate', [
            '_csrf' => 'landscaping-test-token',
            'name' => 'Jordan Client',
            'email' => 'jordan@example.com',
            'phone' => '555-0142',
            'address' => '100 Garden Lane',
            'city' => 'Lafayette',
            'state' => 'Louisiana',
            'zip' => '70501',
            'property_type' => 'Residential',
            'lot_size' => 'Quarter acre',
            'service_interest' => 'Landscape Installation',
            'schedule_preference' => 'Within one month',
            'budget' => '$1,500–$5,000',
            'message' => 'Refresh the front beds and improve drainage.',
            'consent' => '1',
        ]);
        $this->assertSame(302, $estimate->status());
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('landscaping_estimates') . '` WHERE email = ?',
            ['jordan@example.com']
        );
        $this->assertNotNull($row);
        $this->assertSame('new', $row['status']);
        $this->assertSame('Landscape Installation', $row['service_interest']);
        $this->assertSame(1, (int) $row['consent']);
        $message = $this->db->fetch(
            'SELECT `to`, subject, status FROM `' . $this->db->table('system_messages')
            . '` WHERE `to` = ? ORDER BY id DESC LIMIT 1',
            ['owner@example.com']
        );
        $this->assertNotNull($message);
        $this->assertSame('queued', $message['status']);
        $this->assertStringContainsString('Jordan Client', $message['subject']);

        $photo = $this->req('GET', '/admin/landscaping/estimate-photo/1');
        $this->assertSame(302, $photo->status());
        $this->assertStringContainsString('ADMIN-AUTH-REQUIRED', $photo->body());

        $landscapingNav = array_values(array_filter(
            $this->registry->adminNavItems(),
            fn (array $item): bool => $item['package'] === 'cajunwebpro/landscaping'
        ));
        $this->assertNotContains('Dashboard', array_column($landscapingNav, 'label'));
        $this->assertCount(7, $landscapingNav);
        $widgets = array_values(array_filter(
            $this->registry->dashboardWidgets(),
            fn (array $widget): bool => $widget['key'] === 'new-estimates'
        ));
        $this->assertCount(1, $widgets);
        $this->assertSame('/admin/landscaping/estimates', $widgets[0]['url']);
    }

    public function testLandscapingReleaseAndThemesFitSharedHostingAndInstall(): void
    {
        $moduleZip = $this->zipDir(
            WC_ROOT . '/../examples/industry-landscaping',
            'landscaping',
            'landscaping-size-check.zip'
        );
        $this->assertLessThanOrEqual(2 * 1024 * 1024, (int) filesize($moduleZip));

        $themes = [
            'theme-landscaping-bold-outdoors' => 'landscaping-bold-outdoors',
            'theme-landscaping-estate-green' => 'landscaping-estate-green',
            'theme-landscaping-neighborhood-lawn' => 'landscaping-neighborhood-lawn',
            'theme-landscaping-earth-stone' => 'landscaping-earth-stone',
        ];
        foreach ($themes as $dir => $inner) {
            $manifest = json_decode((string) file_get_contents(
                WC_ROOT . '/../examples/' . $dir . '/theme.json'
            ), true);
            $this->assertSame([], \Core\Modules\Manifest::validate($manifest), $dir);
            $zip = $this->zipDir(WC_ROOT . '/../examples/' . $dir, $inner, $inner . '.zip');
            [, $error] = $this->pm->stage($zip);
            $this->assertNull($error, "stage {$dir}");
            [$ok, $error] = $this->pm->install((string) $manifest['id']);
            $this->assertTrue($ok, "install {$dir}: {$error}");
            $this->assertFileExists($this->tmp . '/themes/' . $inner . '/theme.json');
            $this->assertFileExists($this->tmp . '/themes/' . $inner . '/assets/css/landscaping-theme.css');
        }

        $this->installEnable('industry-landscaping', 'landscaping', 'cajunwebpro/landscaping');
        $this->boot();
        $before = $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('landscaping_services') . '` ORDER BY id'
        );
        foreach ($themes as $inner) {
            $this->serviceSettings->set('theme.active', $inner);
            $home = $this->req('GET', '/');
            $this->assertSame(200, $home->status(), $inner);
            $this->assertStringContainsString('class="l-theme ' . $inner . '"', $home->body(), $inner);
            if ($inner === 'landscaping-bold-outdoors') {
                $this->assertStringNotContainsString('/_a/themes/' . $inner, $home->body());
            } else {
                $this->assertStringContainsString(
                    '/_a/themes/' . $inner . '/assets/css/landscaping-theme.css',
                    $home->body(),
                    $inner
                );
            }
            $this->assertSame($before, $this->db->fetchAll(
                'SELECT * FROM `' . $this->db->table('landscaping_services') . '` ORDER BY id'
            ), $inner . ' changed landscaping data');
        }
    }

    public function testLandscapingUpgradeAndUninstallPreserveCustomerData(): void
    {
        $this->installEnable('industry-landscaping', 'landscaping', 'cajunwebpro/landscaping');
        $this->db->insert('landscaping_services', [
            'slug' => 'client-custom-service',
            'name' => 'Client Custom Service',
            'category' => 'Custom',
            'summary' => 'Created by the site owner.',
            'details' => 'Must survive package lifecycle operations.',
            'audience' => 'both',
            'image_url' => '',
            'featured' => 0,
            'visible' => 1,
            'sort_order' => 999,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ]);

        $upgrade = $this->zipDir(
            WC_ROOT . '/../examples/industry-landscaping',
            'landscaping',
            'landscaping-upgrade.zip'
        );
        [, $error] = $this->pm->stage($upgrade);
        $this->assertNull($error);
        [$ok, $error] = $this->pm->install('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('enabled', $this->db->fetchColumn(
            'SELECT state FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
            ['cajunwebpro/landscaping']
        ));
        $this->assertSame('Client Custom Service', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('landscaping_services') . '` WHERE slug = ?',
            ['client-custom-service']
        ));

        [$ok, $error] = $this->pm->disable('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        [$ok, $error] = $this->pm->uninstall('cajunwebpro/landscaping');
        $this->assertTrue($ok, (string) $error);
        $this->assertDirectoryDoesNotExist($this->tmp . '/industries/landscaping');
        $this->assertTrue($this->db->tableExists('landscaping_services'));
        $this->assertSame('Client Custom Service', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('landscaping_services') . '` WHERE slug = ?',
            ['client-custom-service']
        ));
    }

    public function testCateringReferencePackageFitsSharedHostingUploadCeiling(): void
    {
        $zip = $this->zipDir(
            WC_ROOT . '/../examples/industry-catering',
            'catering',
            'catering-size-check.zip'
        );
        $size = filesize($zip);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(
            2 * 1024 * 1024,
            (int) $size,
            'Catering package must fit the common shared-hosting 2 MB upload ceiling.'
        );
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

    public function testCateringCajunMenuMigrationPreservesCustomItems(): void
    {
        $migrationDir = WC_ROOT . '/../examples/industry-catering/migrations';
        $foundation = require $migrationDir . '/0001_catering.php';
        $foundation->up($this->db);
        $categoryId = (int) $this->db->fetchColumn(
            'SELECT id FROM `' . $this->db->table('catering_menu_categories') . '` WHERE slug = ?',
            ['seasonal-small-plates']
        );
        $this->db->insert('catering_menu_items', [
            'category_id' => $categoryId,
            'slug' => 'client-family-recipe',
            'name' => 'Client Family Recipe',
            'description' => 'Administrator-created content.',
            'price_cents' => 1500,
            'price_unit' => 'per guest',
            'dietary' => '',
            'featured' => 0,
            'visible' => 1,
            'sort_order' => 90,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        (new Migrator($this->db, $migrationDir))->migrate();

        $custom = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('catering_menu_items') . '` WHERE slug = ?',
            ['client-family-recipe']
        );
        $this->assertNotNull($custom);
        $this->assertSame('Client Family Recipe', $custom['name']);
        $this->assertSame('', (string) ($custom['image'] ?? ''));
        $this->assertSame('Chicken & Andouille Gumbo', $this->db->fetchColumn(
            'SELECT name FROM `' . $this->db->table('catering_menu_items') . '` WHERE slug = ?',
            ['chicken-andouille-gumbo']
        ));
        $this->assertSame(11, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '`'
        ));
    }

    public function testCateringHomeMetadataMigrationRepairsOnlyMissingStarterFields(): void
    {
        $this->db->insert('settings', [
            'key' => 'site.name', 'value' => json_encode('Catering Co.'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $pageId = $this->db->insert('pages', [
            'slug' => 'home', 'title' => 'Welcome',
            'body' => '<p>Customized body remains.</p>', 'status' => 'published',
            'meta_title' => '', 'meta_description' => '',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $migration = require WC_ROOT . '/../examples/industry-catering/migrations/0003_home_search_metadata.php';
        $migration->up($this->db);
        $page = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('pages') . '` WHERE id = ?',
            [$pageId]
        );

        $this->assertSame('Welcome to Catering Co.', $page['title']);
        $this->assertSame('Catering Co. | Louisiana Catering & Events', $page['meta_title']);
        $this->assertStringContainsString('Louisiana-inspired catering', $page['meta_description']);
        $this->assertSame('<p>Customized body remains.</p>', $page['body']);

        $this->db->update('pages', [
            'title' => 'Our Custom Home', 'meta_title' => 'Custom search title',
            'meta_description' => 'Custom search description.',
        ], 'id = ?', [$pageId]);
        $migration->up($this->db);
        $customized = $this->db->fetch(
            'SELECT title,meta_title,meta_description FROM `' . $this->db->table('pages') . '` WHERE id = ?',
            [$pageId]
        );
        $this->assertSame('Our Custom Home', $customized['title']);
        $this->assertSame('Custom search title', $customized['meta_title']);
        $this->assertSame('Custom search description.', $customized['meta_description']);
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
