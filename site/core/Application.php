<?php
declare(strict_types=1);

namespace Core;

use Core\Audit\AuditLog;
use Core\Auth\AdminRepository;
use Core\Auth\AuthService;
use Core\Auth\Permissions;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Http\Request;
use Core\Http\Response;
use Core\Jobs\JobQueue;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Settings\SettingsRepository;
use Core\View\View;

/**
 * Application kernel. Wires the container, boots services, builds routes,
 * loads enabled packages, and dispatches the request.
 */
final class Application
{
    private Container $container;
    private Router $router;
    private ?Request $request = null;

    public function __construct(private Config $config)
    {
        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->instance(Container::class, $this->container);
        $this->router = new Router();
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Dispatcher::class, new Dispatcher());
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function installed(): bool
    {
        return $this->config->loaded() && is_file(WC_INSTALLED_LOCK);
    }

    public function run(): void
    {
        $this->request = $this->makeRequest();
        try {
            if (!$this->installed()) {
                Response::redirect($this->request->basePath . '/install.php')->send();
                return;
            }
            $response = $this->dispatch($this->request);
        } catch (\Throwable $e) {
            $response = $this->handleException($e);
        }
        $response->send();
    }

    public function makeRequest(): Request
    {
        $request = Request::capture();
        $trusted = (array) $this->config->get('security.trusted_proxies', []);
        if ($trusted !== []) {
            $ip = $request->ip();
            foreach ($trusted as $cidr) {
                if (self::ipInCidr($ip, (string) $cidr)) {
                    $request->trustProxy(true);
                    break;
                }
            }
        }
        return $request;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$net, $bits] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $bytes = intdiv((int) $bits, 8);
        $rem = (int) $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($netBin[$bytes]) & ord($mask));
    }

    private function bootServices(): void
    {
        $driver = (string) $this->config->get('database.driver', 'mysql');
        $db = $driver === 'sqlite'
            // Dev/test only — production is MySQL/MariaDB (COMPATIBILITY.md).
            ? Connection::sqlite(
                (string) $this->config->get('database.name', ':memory:'),
                (string) $this->config->get('database.prefix', ''),
            )
            : Connection::mysql(
                (string) $this->config->get('database.host'),
                (int) $this->config->get('database.port', 3306),
                (string) $this->config->get('database.name'),
                (string) $this->config->get('database.user'),
                (string) $this->config->get('database.password'),
                (string) $this->config->get('database.charset', 'utf8mb4'),
                (string) $this->config->get('database.prefix', ''),
            );
        $this->container->instance(Connection::class, $db);

        $session = new SessionManager($this->config);
        $session->start($this->request);
        $this->container->instance(SessionManager::class, $session);

        $this->container->instance(Csrf::class, new Csrf());
        $this->container->instance(SettingsRepository::class, new SettingsRepository($db));
        $this->container->instance(AuditLog::class, new AuditLog($db));
        $this->container->instance(JobQueue::class, new JobQueue($db));
        $this->container->instance(RateLimiter::class, new RateLimiter($db));
        $this->container->instance(Permissions::class, new Permissions());
        $this->container->instance(AdminRepository::class, new AdminRepository($db));
        $this->container->instance(AuthService::class, new AuthService(
            $db,
            $this->container->get(AdminRepository::class),
            $this->container->get(Permissions::class),
            $session,
            $this->container->get(RateLimiter::class),
            $this->container->get(AuditLog::class),
        ));
        $this->container->instance(\Core\Media\MediaService::class, new \Core\Media\MediaService($db, $this->config));
        $this->container->instance(\Core\Content\MenuService::class, new \Core\Content\MenuService($db));

        $view = new View();
        $view->addResolver('admin', fn (string $v): ?string =>
            is_file(WC_ROOT . '/admin/views/' . $v . '.php')
                ? WC_ROOT . '/admin/views/' . $v . '.php' : null);
        $view->addResolver('install', fn (string $v): ?string =>
            is_file(WC_ROOT . '/install/views/' . $v . '.php')
                ? WC_ROOT . '/install/views/' . $v . '.php' : null);
        $themeDir = (string) ($this->container->get(SettingsRepository::class)->get('theme.active', 'base'));
        $view->addResolver('theme', function (string $v) use ($themeDir): ?string {
            foreach ([$themeDir, 'base'] as $theme) {
                $path = WC_THEMES . '/' . $theme . '/templates/' . $v . '.php';
                if (is_file($path)) {
                    return $path;
                }
            }
            return null;
        });
        $this->container->instance(View::class, $view);

        // Localization: core catalog, then active theme, then enabled
        // modules (prepended in bootModules so package strings win).
        $translator = new \Core\I18n\Translator(
            (string) ($this->container->get(SettingsRepository::class)->get('site.locale', 'en') ?: 'en')
        );
        $translator->addDir(WC_ROOT, false);
        $translator->addDir($themeDir !== '' ? WC_THEMES . '/' . $themeDir : '', false);
        $this->container->instance(\Core\I18n\Translator::class, $translator);
        \Core\I18n\Translator::setInstance($translator);
    }

    public function dispatch(Request $request): Response
    {
        $this->bootServices();
        $this->registerRoutes();
        $this->bootModules();
        $response = $this->router->dispatch($request);
        return $this->withSecurityHeaders($response);
    }

    private function bootModules(): void
    {
        $db = $this->container->get(Connection::class);
        $registry = new ModuleRegistry($db, WC_MODULES . '/industries', WC_MODULES . '/addons', WC_THEMES);
        $registry->discover();
        $this->container->instance(ModuleRegistry::class, $registry);
        $this->container->instance(\Core\Modules\PackageManager::class, new \Core\Modules\PackageManager(
            $db, $registry, new \Core\Modules\ArchiveInspector(),
            (string) $this->config->get('paths.upload', WC_ROOT . '/upload'),
            WC_MODULES . '/industries', WC_MODULES . '/addons', WC_THEMES,
            (string) $this->config->get('paths.storage', WC_ROOT . '/storage') . '/package-op.lock',
            new \Core\Update\SignatureVerifier(
                (array) $this->config->get('trust.public_keys', []),
                (bool) $this->config->get('trust.allow_unsigned', true)
            ),
        ));

        $events = $this->container->get(Dispatcher::class);
        $permissions = $this->container->get(Permissions::class);
        $contextFactory = fn (string $packageId): ModuleContext =>
            new ModuleContext($packageId, $this->router, $events, $permissions);

        // Recovery mode: a flag file in storage (created from the Modules
        // screen or by hand on a broken host) boots trusted core ONLY —
        // no package code runs, so a boot-fatal extension can never lock
        // the administrator out.
        $recoveryFlag = (string) $this->config->get('paths.storage', WC_ROOT . '/storage')
            . '/recovery.flag';
        if (is_file($recoveryFlag)) {
            return;
        }

        $registry->bootEnabled(
            fn (string $prefix, string $dir) => \CoreAutoload::register($prefix, $dir),
            $contextFactory,
        );

        // Package catalogs layer on top of core/theme strings.
        $translator = $this->container->get(\Core\I18n\Translator::class);
        foreach ($registry->contexts() as $ctx) {
            $pkgDir = $registry->all()[$ctx->packageId()]['dir'] ?? null;
            if (is_string($pkgDir)) {
                $translator->addDir($pkgDir);
            }
        }
    }

    private function registerRoutes(): void
    {
        $app = $this;
        $c = $this->container;

        // Admin routes must be registered before the public slug catch-all.
        $auth = function (Request $r, callable $next) use ($c): Response {
            $auth = $c->get(AuthService::class);
            if ($auth->user() === null) {
                return Response::redirect($r->basePath . '/admin/login');
            }
            return $next($r);
        };
        $csrfCheck = function (Request $r, callable $next) use ($c): Response {
            if ($r->isPost() && !$c->get(Csrf::class)->validate($r)) {
                return Response::text('Invalid CSRF token.', 419);
            }
            return $next($r);
        };

        $this->router->group('/admin', function (Router $r) use ($c, $auth, $csrfCheck) {
            $r->any('/login', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->login($req));
            $r->any('/mfa', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->mfa($req));
            $r->post('/logout', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->logout($req), [$csrfCheck]);

            $r->get('/', fn (Request $req) => (new \Admin\Controllers\DashboardController($c))->index($req), [$auth]);
            $r->any('/settings', fn (Request $req) => (new \Admin\Controllers\SettingsController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/profile', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->index($req), [$auth, $csrfCheck]);
            $r->post('/profile/mfa/enable', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->enableMfa($req), [$auth, $csrfCheck]);
            $r->post('/profile/mfa/disable', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->disableMfa($req), [$auth, $csrfCheck]);
            $r->get('/users', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->index($req), [$auth]);
            $r->post('/users/{id:\d+}/role', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->changeRole($req), [$auth, $csrfCheck]);
            $r->post('/users/{id:\d+}/disable', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->toggleDisable($req), [$auth, $csrfCheck]);
            $r->get('/modules', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->index($req), [$auth]);
            $r->any('/pages', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/pages/edit', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->edit($req), [$auth, $csrfCheck]);
            $r->any('/pages/edit/{id:\d+}', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->edit($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/publish', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->publish($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/preview', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->previewLink($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/restore/{rid:\d+}', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->restoreRevision($req), [$auth, $csrfCheck]);
            $r->any('/navigation', fn (Request $req) => (new \Admin\Controllers\NavigationController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/media', fn (Request $req) => (new \Admin\Controllers\MediaController($c))->index($req), [$auth, $csrfCheck]);
            $r->post('/modules/upload', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->upload($req), [$auth, $csrfCheck]);
            $r->post('/modules/recovery', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->recovery($req), [$auth, $csrfCheck]);
            $r->post('/modules/{id:.+}/{op:[a-z]+}', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->action($req), [$auth, $csrfCheck]);
            $r->get('/backups', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->index($req), [$auth]);
            $r->post('/backups/create', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->create($req), [$auth, $csrfCheck]);
            $r->post('/backups/restore', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->restore($req), [$auth, $csrfCheck]);
            $r->post('/backups/delete', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->delete($req), [$auth, $csrfCheck]);
            $r->get('/health', fn (Request $req) => (new \Admin\Controllers\SystemController($c))->health($req), [$auth]);
            $r->get('/audit', fn (Request $req) => (new \Admin\Controllers\SystemController($c))->audit($req), [$auth]);
        });

        // Sitemap, robots, media streams, and draft previews — before the
        // page catch-all so they always win.
        $this->router->get('/sitemap.xml', fn (Request $r) => (new \Admin\Controllers\SitemapController($c))->sitemap($r));
        $this->router->get('/robots.txt', fn (Request $r) => (new \Admin\Controllers\SitemapController($c))->robots($r));
        $this->router->get('/media/{id:\d+}/{name:.*}', fn (Request $r) => (new \Admin\Controllers\MediaController($c))->serve($r));
        $this->router->get('/preview/{token:[a-f0-9]{48}}', fn (Request $r) => (new \Admin\Controllers\PublicPageController($c))->preview($r));

        // Namespaced asset resolver (theme/module assets, allowlisted types).
        $this->router->get('/_a/{path:.*}', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\AssetController($c))->serve($r);
        });

        // Public routes — the slug catch-all stays last so /admin and any
        // module routes always win. Reserved prefixes are excluded anyway.
        $this->router->get('/', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\PublicPageController($c))->home($r);
        });
        $this->router->get('/{slug:(?!admin\b|_a\b|media\b|preview\b|sitemap\.xml|robots\.txt|install\.php|cron\.php|api\b|storage\b|upload\b)[a-z0-9][a-z0-9\-\/]*}', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\PublicPageController($c))->show($r);
        });
    }

    private function withSecurityHeaders(Response $response): Response
    {
        return $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    private function handleException(\Throwable $e): Response
    {
        error_log('[website-core] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $debug = (bool) $this->config->get('app.debug', false);
        if ($debug) {
            return Response::html(
                '<h1>Application error</h1><pre>' . htmlspecialchars((string) $e) . '</pre>',
                500
            );
        }
        return Response::html(
            '<!doctype html><html><body><h1>Something went wrong</h1>'
            . '<p>The error has been logged. Reference: ' . substr(sha1($e->getMessage() . microtime()), 0, 10) . '</p></body></html>',
            500
        );
    }
}
