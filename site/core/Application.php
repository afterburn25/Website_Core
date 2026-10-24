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
    private bool $servicesBooted = false;

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
        return $this->config->loaded() && wc_completed_installation(WC_CONFIG);
    }

    public function run(): void
    {
        $this->request = $this->makeRequest();
        try {
            if (!$this->installed()) {
                (new \Core\Install\Installer($this))->run();
                return;
            }
            $response = $this->dispatch($this->request);

            // License warning on the rendered page. Only reachable for
            // /admin requests — public paths were 503'd by licenseBlocked.
            // A license-free install has no row and must never see this.
            $validator = $this->container->get(\Core\License\LicenseValidation::class);
            $licenseStatus = $validator->status();
            if ($licenseStatus['license_key'] !== null && !$validator->isValid()) {
                $body = $response->body();
                if (str_starts_with($body, '<!doctype html') || str_starts_with($body, '<html')) {
                    $warning = '<div style="background:#fee2e2;color:#b91c1c;padding:1rem;margin:1rem;border-radius:0.5rem">'
                        . '<strong>License Warning:</strong> '
                        . e($licenseStatus['license_error'] ?? 'License is not valid.')
                        . ' Please contact Cajunwebpro.com support.</div>';
                    $body = str_replace('</body>', $warning . '</body>', $body);
                    $response->setBody($body);
                }
            }
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

    /**
     * Bind the service layer (DB, sessions, security, view, i18n). Called by
     * dispatch() with the real request; non-HTTP entry points (cron.php)
     * call it with no request — sessions are HTTP-only and skipped.
     * Idempotent: a second call is a no-op.
     */
    public function bootServices(?Request $request = null): void
    {
        if ($this->servicesBooted) {
            return;
        }
        $this->servicesBooted = true;
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

        // Keep the database schema synchronized even when an owner updates
        // files directly through a shared-hosting file manager instead of the
        // built-in updater. Without this gate, the standalone login screen can
        // succeed while the authenticated dashboard crashes as soon as it
        // queries a table introduced by newer core code.
        $this->ensureCoreSchema($db);

        $session = new SessionManager($this->config);
        if ($request !== null) {
            $session->start($request);
        }
        $this->container->instance(SessionManager::class, $session);

        $this->container->instance(Csrf::class, new Csrf());
        $this->container->instance(SettingsRepository::class, new SettingsRepository($db));
        $this->container->instance(\Core\Privacy\ConsentManager::class,
            new \Core\Privacy\ConsentManager($this->container->get(SettingsRepository::class)));
        $this->container->instance(AuditLog::class, new AuditLog($db));
        $this->container->instance(\Core\Admin\NoticeCenter::class, new \Core\Admin\NoticeCenter($db));
        $this->container->instance(\Core\Seo\NotFoundLog::class, new \Core\Seo\NotFoundLog($db));
        $this->container->instance(JobQueue::class, new JobQueue($db));
        $this->container->instance(\Core\Api\ApiKeyService::class, new \Core\Api\ApiKeyService($db));
        $webhooks = new \Core\Api\WebhookService($db, $this->container->get(JobQueue::class), $this->config);
        $this->container->instance(\Core\Api\WebhookService::class, $webhooks);
        foreach (\Core\Api\WebhookService::EVENTS as $event) {
            $this->container->get(Dispatcher::class)->listen($event, fn (array $payload) => $webhooks->queue($event, $payload));
        }
        $this->container->instance(RateLimiter::class, new RateLimiter($db));
        $this->container->instance(Permissions::class, new Permissions($db));
        $this->container->instance(AdminRepository::class, new AdminRepository($db));
        $systemMail = new \Core\Mail\SystemMail($db, $this->container->get(SettingsRepository::class));
        $this->container->instance(\Core\Mail\SystemMail::class, $systemMail);
        $this->container->instance(AuthService::class, new AuthService(
            $db,
            $this->container->get(AdminRepository::class),
            $this->container->get(Permissions::class),
            $session,
            $this->container->get(RateLimiter::class),
            $this->container->get(AuditLog::class),
            $this->config,
            $systemMail,
        ));
        $mediaService = new \Core\Media\MediaService($db, $this->config);
        $this->container->instance(\Core\Media\MediaService::class, $mediaService);
        $this->container->instance(\Core\Branding\BrandAssets::class, new \Core\Branding\BrandAssets(
            $db, $this->container->get(SettingsRepository::class), $mediaService
        ));
        $this->container->instance(\Core\Content\MenuService::class, new \Core\Content\MenuService($db));
        $this->container->instance(\Core\Content\PublishingService::class, new \Core\Content\PublishingService($db));
        $this->container->instance(\Core\Agency\AgencyService::class, new \Core\Agency\AgencyService($db, $this->config));
        $marketplace = new \Core\Marketplace\MarketplaceService($db, new \Core\Update\SignatureVerifier(
            (array) $this->config->get('trust.public_keys', []), false
        ));
        $this->container->instance(\Core\Marketplace\MarketplaceService::class, $marketplace);
        $managedUpdates = new \Core\Update\ManagedUpdateService(
            $db, $this->config, $this->container->get(SettingsRepository::class), $marketplace
        );
        $this->container->instance(\Core\Update\ManagedUpdateService::class, $managedUpdates);
        try {
            $policy = (string) $this->container->get(SettingsRepository::class)->get('updates.automation', 'off');
            $lastCheck = $db->fetchColumn('SELECT MAX(created_at) FROM `' . $db->table('managed_update_checks') . '`');
            $pending = $db->fetchColumn("SELECT COUNT(*) FROM `" . $db->table('jobs') . "` WHERE type = 'core.managed_update' AND status IN ('pending','uncertain','running')");
            $sources = $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('marketplace_sources') . '` WHERE active = 1');
            if ($policy !== 'off' && $sources && !$pending && (!$lastCheck || strtotime((string) $lastCheck) < time() - 43200)) {
                $this->container->get(JobQueue::class)->dispatch('core.managed_update');
            }
        } catch (\Throwable) {
            // The 2.0 migration may not exist yet during an in-place update.
        }

        $view = new View();
        $view->addResolver('admin', fn (string $v): ?string =>
            is_file(WC_ROOT . '/admin/views/' . $v . '.php')
                ? WC_ROOT . '/admin/views/' . $v . '.php' : null);
        $themeDir = (string) ($this->container->get(SettingsRepository::class)->get('theme.active', 'base'));
        $themesRoot = (string) $this->config->get('paths.themes', WC_THEMES);
        $view->addResolver('theme', function (string $v) use ($themeDir, $themesRoot): ?string {
            $manifest = is_file($themesRoot . '/' . $themeDir . '/theme.json')
                ? json_decode((string) file_get_contents($themesRoot . '/' . $themeDir . '/theme.json'), true) : [];
            $parent = is_array($manifest) ? basename((string) ($manifest['extends'] ?? '')) : '';
            foreach (array_values(array_unique(array_filter([$themeDir, $parent, 'base']))) as $theme) {
                $path = $themesRoot . '/' . $theme . '/templates/' . $v . '.php';
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
        $translator->addDir($themeDir !== '' ? $themesRoot . '/' . $themeDir : '', false);
        $this->container->instance(\Core\I18n\Translator::class, $translator);
        \Core\I18n\Translator::setInstance($translator);

        // License validation service
        $this->container->instance(\Core\License\LicenseValidation::class, new \Core\License\LicenseValidation($db, $this->config));
        $this->container->instance(\Core\Jobs\LicenseValidationJob::class, new \Core\Jobs\LicenseValidationJob($db, $this->config));

        // Enqueue license validation job if it hasn't run recently.
        // Licensed installs only; wrapped so a missing jobs/license table can
        // never break request handling. Deactivated sites re-check hourly —
        // an owner who re-registers their domain should see the site return
        // within an hour, not a day.
        try {
            $licenseTable = $db->table('license');
            $lastRun = $db->fetch(
                "SELECT MAX(license_validated_at) AS last_run FROM `{$licenseTable}` WHERE license_validated_at IS NOT NULL"
            );
            $licenseRow = $db->fetch("SELECT license_key, license_deactivated FROM `{$licenseTable}` WHERE license_key IS NOT NULL AND license_key <> '' LIMIT 1");
            $deactivated = (bool) ($licenseRow['license_deactivated'] ?? 0);
            $interval = $deactivated ? '-1 hour' : '-24 hours';
            $needsValidation = (bool) $licenseRow && (!$lastRun || strtotime((string) $lastRun['last_run']) < strtotime($interval));
            if ($needsValidation) {
                $jobsTable = $db->table('jobs');
                $pending = $db->fetch(
                    "SELECT id FROM `{$jobsTable}` WHERE type = 'license.validate' AND status IN ('pending','uncertain','running') LIMIT 1"
                );
                if (!$pending) {
                    (new \Core\Jobs\JobQueue($db))->dispatch('license.validate');
                }
            }
        } catch (\Throwable) {
            // licensing/job tables absent on this schema — skip silently
        }

        $modulesRoot = (string) $this->config->get('paths.modules', WC_MODULES);
        $themesRoot = (string) $this->config->get('paths.themes', WC_THEMES);
        $registry = new ModuleRegistry($db, $modulesRoot . '/industries', $modulesRoot . '/addons', $themesRoot);
        $registry->discover();
        $this->container->instance(ModuleRegistry::class, $registry);
        $this->container->instance(\Core\System\Version::class, new \Core\System\Version($registry));
        $this->container->instance(\Core\System\Integrity::class, new \Core\System\Integrity(
            $db, $this->config, $registry
        ));
        $this->container->instance(\Core\Content\ContentItems::class,
            new \Core\Content\ContentItems($db, $registry));
        $this->container->instance(\Core\Modules\PackageManager::class, new \Core\Modules\PackageManager(
            $db, $registry, new \Core\Modules\ArchiveInspector(),
            (string) $this->config->get('paths.upload', WC_ROOT . '/upload'),
            $modulesRoot . '/industries', $modulesRoot . '/addons', $themesRoot,
            (string) $this->config->get('paths.storage', WC_ROOT . '/storage') . '/package-op.lock',
            new \Core\Update\SignatureVerifier(
                (array) $this->config->get('trust.public_keys', []),
                (bool) $this->config->get('trust.allow_unsigned', false)
            ),
            $this->container->get(Dispatcher::class),
            $this->container->get(\Core\License\LicenseValidation::class),
        ));
    }

    /**
     * Boot enough for the job runner: services + enabled packages so
     * scheduledJob() handlers exist. Routes register as usual but are
     * never dispatched (no HTTP), and module admin routes get a null
     * pipeline — unreachable in this context by construction.
     */
    public function bootJobs(): void
    {
        $this->bootServices();
        $this->bootModules();
    }

    public function dispatch(Request $request): Response
    {
        $this->bootServices($request);
        try {
            $this->container->get(\Core\Content\PublishingService::class)->runDue();
        } catch (\Throwable) {
            // An upgrade request may reach this code before its migration runs.
        }
        if ($this->inMaintenance($request)) {
            return $this->withSecurityHeaders(
                Response::html(
                    (new \Core\Branding\Attribution())->apply(\Core\Branding\StatusPage::render(503, 'We’re making things better.',
                        'A little work is happening behind the scenes. Please check back shortly.',
                        $request->routeBase(), $this->siteName(), null, 'Check again', '')),
                    503
                )->header('Retry-After', '120')
            );
        }
        if ($this->licenseBlocked($request)) {
            return $this->withSecurityHeaders(
                Response::html(
                    (new \Core\Branding\Attribution())->apply(\Core\Branding\StatusPage::render(503, 'This site is taking a pause.',
                        'The owner can sign in to review the site status or contact website support.',
                        $request->routeBase(), $this->siteName(), null, 'Check again', '')),
                    503
                )->header('Retry-After', '300')
            );
        }
        $this->registerRoutes();
        $this->bootModules();
        $this->registerPublicRoutes();
        $response = $this->router->dispatch($request);
        $publicRequest = !str_starts_with(rtrim($request->path, '/') ?: '/', '/admin');
        if ($publicRequest && $response->status() === 404) {
            try {
                $this->container->get(\Core\Seo\NotFoundLog::class)->record(
                    $request->path, $request->header('Referer')
                );
            } catch (\Throwable) {
                // Upgrade request may run before the 404-log migration.
            }
        }
        if ($publicRequest) {
            $contentType = strtolower((string) ($response->headers()['Content-Type'] ?? 'text/html'));
            if (str_contains($contentType, 'text/html')) {
                // Public HTML is private by default because an industry
                // module may render a session-bound CSRF token. Controllers
                // opt safe, anonymous pages into shared caching with this
                // internal marker; it is removed before the response leaves.
                $cacheable = ($response->headers()['X-WC-Cacheable'] ?? '') === 'public';
                $response->removeHeader('X-WC-Cacheable');
                $response->setBody((new \Core\Branding\Attribution())->apply($response->body()));
                $response->setBody($this->container->get(\Core\Privacy\ConsentManager::class)
                    ->inject($response->body(), $request->routeBase()));
                if ($cacheable && $request->method === 'GET' && $response->status() === 200) {
                    $etag = '"' . hash('sha256', $response->body()) . '"';
                    $response->header('ETag', $etag)
                        ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
                    if (hash_equals($etag, trim($request->header('If-None-Match')))) {
                        $response = (new Response('', 304))->header('ETag', $etag)
                            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
                    }
                } elseif ($response->status() !== 304) {
                    $response->header('Cache-Control', 'private, no-store');
                }
            }
        }
        return $this->withSecurityHeaders($response);
    }

    /**
     * storage/maintenance.flag blocks public traffic while leaving the whole
     * admin namespace reachable. Admin authentication still runs normally:
     * logged-out requests such as /admin are redirected to /admin/login, and
     * protected screens still require a valid administrator session. Keeping
     * only the literal login route open would intercept /admin before its
     * authentication redirect and lock a logged-out operator outside.
     */
    private function inMaintenance(Request $request): bool
    {
        $flag = (string) $this->config->get('paths.storage', WC_ROOT . '/storage')
            . '/maintenance.flag';
        if (!is_file($flag)) {
            return false;
        }
        $path = rtrim($request->path, '/') ?: '/';
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return false;
        }
        return $this->container->get(AuthService::class)->user() === null;
    }

    /**
     * An installed license that failed validation or was remotely deactivated
     * blocks public pages. Admin routes stay open — the owner needs them to
     * re-register the domain or contact support. cron.php remains a standalone
     * entry point, and setup runs only before installation, so revalidation can
     * still reactivate the site. License-free installs are unaffected.
     */
    private function licenseBlocked(Request $request): bool
    {
        if (str_starts_with(rtrim($request->path, '/') ?: '/', '/admin')) {
            return false;
        }
        try {
            $row = $this->container->get(\Core\License\LicenseValidation::class)->status();
            return $row['license_key'] !== null
                && !((bool) ($row['license_valid'] ?? 0) && !(bool) ($row['license_deactivated'] ?? 0));
        } catch (\Throwable) {
            return false; // Licensing unavailable (e.g. license table missing) — do not block
        }
    }

    /** Combined auth+CSRF middleware for module admin routes. */
    private $adminPipeline = null;

    private function bootModules(): void
    {
        $db = $this->container->get(Connection::class);
        $modulesRoot = (string) $this->config->get('paths.modules', WC_MODULES);
        $themesRoot = (string) $this->config->get('paths.themes', WC_THEMES);
        $registry = new ModuleRegistry($db, $modulesRoot . '/industries', $modulesRoot . '/addons', $themesRoot);
        $registry->discover();
        $this->container->instance(ModuleRegistry::class, $registry);
        $this->container->instance(\Core\System\Version::class, new \Core\System\Version($registry));
        $this->container->instance(\Core\System\Integrity::class, new \Core\System\Integrity(
            $db, $this->config, $registry
        ));
        $this->container->instance(\Core\Content\ContentItems::class,
            new \Core\Content\ContentItems($db, $registry));
        $this->container->instance(\Core\Modules\PackageManager::class, new \Core\Modules\PackageManager(
            $db, $registry, new \Core\Modules\ArchiveInspector(),
            (string) $this->config->get('paths.upload', WC_ROOT . '/upload'),
            $modulesRoot . '/industries', $modulesRoot . '/addons', $themesRoot,
            (string) $this->config->get('paths.storage', WC_ROOT . '/storage') . '/package-op.lock',
            new \Core\Update\SignatureVerifier(
                (array) $this->config->get('trust.public_keys', []),
                (bool) $this->config->get('trust.allow_unsigned', false)
            ),
            $this->container->get(Dispatcher::class),
            $this->container->get(\Core\License\LicenseValidation::class),
        ));

        $events = $this->container->get(Dispatcher::class);
        $permissions = $this->container->get(Permissions::class);
        $contextFactory = fn (string $packageId): ModuleContext => new ModuleContext(
            $packageId, $this->router, $events, $permissions,
            $this->adminPipeline,
            $this->container->get(\Core\View\View::class),
            fn (string $id) => $this->container->get($id),
            fn () => (string) $this->container->get(SettingsRepository::class)->get('theme.active', 'base'),
            $themesRoot,
            basename((string) ($registry->all()[$packageId]['dir'] ?? '')),
            (string) ($registry->all()[$packageId]['dir'] ?? ''),
        );

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
            $user = $auth->user();
            if ($user === null) {
                return Response::redirect($r->routeUrl('/admin/login'));
            }
            $settings = $c->get(SettingsRepository::class);
            $path = rtrim($r->path, '/') ?: '/';
            if ((bool) $settings->get('security.require_mfa', false) && !$user->mfaEnabled
                && !str_starts_with($path, '/admin/profile') && $path !== '/admin/logout') {
                $_SESSION['_wc_flash'] = ['type' => 'error', 'message' => 'MFA enrollment is required before continuing.'];
                return Response::redirect($r->routeUrl('/admin/profile'));
            }
            return $next($r);
        };
        $csrfCheck = function (Request $r, callable $next) use ($c): Response {
            if ($r->isPost() && !$c->get(Csrf::class)->validate($r)) {
                return Response::text('Invalid CSRF token.', 419);
            }
            return $next($r);
        };

        // Combined pipeline for module adminRoute() registrations.
        $this->adminPipeline = function (Request $r, callable $next) use ($auth, $csrfCheck): Response {
            return $auth($r, fn (Request $r2) => $csrfCheck($r2, $next));
        };

        $this->router->group('/admin', function (Router $r) use ($c, $auth, $csrfCheck) {
            $r->any('/login', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->login($req));
            $r->any('/forgot', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->forgot($req));
            $r->any('/reset', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->reset($req));
            $r->any('/mfa', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->mfa($req));
            $r->post('/logout', fn (Request $req) => (new \Admin\Controllers\AuthController($c))->logout($req), [$csrfCheck]);

            $r->get('/', fn (Request $req) => (new \Admin\Controllers\DashboardController($c))->index($req), [$auth]);
            $r->get('/search', fn (Request $req) => (new \Admin\Controllers\SearchController($c))->index($req), [$auth]);
            $r->get('/notices', fn (Request $req) => (new \Admin\Controllers\NoticesController($c))->index($req), [$auth]);
            $r->post('/notices/{id:\d+}/dismiss', fn (Request $req) => (new \Admin\Controllers\NoticesController($c))->dismiss($req), [$auth, $csrfCheck]);
            $r->any('/settings', fn (Request $req) => (new \Admin\Controllers\SettingsController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/privacy', fn (Request $req) => (new \Admin\Controllers\PrivacyController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/integrations', fn (Request $req) => (new \Admin\Controllers\IntegrationsController($c))->index($req), [$auth, $csrfCheck]);
            $r->get('/integrations/export', fn (Request $req) => (new \Admin\Controllers\IntegrationsController($c))->export($req), [$auth]);
            $r->any('/agency', fn (Request $req) => (new \Admin\Controllers\AgencyController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/marketplace', fn (Request $req) => (new \Admin\Controllers\MarketplaceController($c))->index($req), [$auth, $csrfCheck]);
            $r->get('/privacy/export', fn (Request $req) => (new \Admin\Controllers\PrivacyController($c))->export($req), [$auth]);
            $r->post('/settings/license-validate', fn (Request $req) => (new \Admin\Controllers\SettingsController($c))->validateLicense($req), [$auth, $csrfCheck]);
            $r->any('/profile', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->index($req), [$auth, $csrfCheck]);
            $r->post('/profile/mfa/enable', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->enableMfa($req), [$auth, $csrfCheck]);
            $r->post('/profile/mfa/disable', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->disableMfa($req), [$auth, $csrfCheck]);
            $r->post('/profile/sessions/revoke-others', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->revokeOtherSessions($req), [$auth, $csrfCheck]);
            $r->post('/profile/sessions/{id:\d+}/revoke', fn (Request $req) => (new \Admin\Controllers\ProfileController($c))->revokeSession($req), [$auth, $csrfCheck]);
            $r->get('/users', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->index($req), [$auth]);
            $r->any('/roles', fn (Request $req) => (new \Admin\Controllers\RolesController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/security', fn (Request $req) => (new \Admin\Controllers\SecurityController($c))->index($req), [$auth, $csrfCheck]);
            $r->post('/users/create', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->create($req), [$auth, $csrfCheck]);
            $r->post('/users/{id:\d+}/role', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->changeRole($req), [$auth, $csrfCheck]);
            $r->post('/users/{id:\d+}/disable', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->toggleDisable($req), [$auth, $csrfCheck]);
            $r->post('/users/{id:\d+}/reset-password', fn (Request $req) => (new \Admin\Controllers\UsersController($c))->resetPassword($req), [$auth, $csrfCheck]);
            $r->get('/modules', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->index($req), [$auth]);
            $r->any('/pages', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/pages/edit', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->edit($req), [$auth, $csrfCheck]);
            $r->any('/pages/edit/{id:\d+}', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->edit($req), [$auth, $csrfCheck]);
            $r->any('/patterns', fn (Request $req) => (new \Admin\Controllers\PatternsController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/translations', fn (Request $req) => (new \Admin\Controllers\TranslationsController($c))->index($req), [$auth, $csrfCheck]);
            $r->get('/forms', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->index($req), [$auth]);
            $r->post('/forms/save', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->save($req), [$auth, $csrfCheck]);
            $r->post('/forms/{id:\d+}/archive', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->archive($req), [$auth, $csrfCheck]);
            $r->get('/forms/submissions', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->submissions($req), [$auth]);
            $r->post('/forms/submissions/{id:\d+}/read', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->markRead($req), [$auth, $csrfCheck]);
            $r->post('/forms/submissions/{id:\d+}/{status:new|read|archived}', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->submissionStatus($req), [$auth, $csrfCheck]);
            $r->get('/forms/export', fn (Request $req) => (new \Admin\Controllers\FormsController($c))->export($req), [$auth]);
            $r->post('/pages/{id:\d+}/publish', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->publish($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/review', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->requestReview($req), [$auth, $csrfCheck]);
            $r->get('/pages/{id:\d+}/compare/{a:\d+}/{b:\d+}', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->compare($req), [$auth]);
            $r->post('/pages/{id:\d+}/schedule', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->schedule($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/duplicate', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->duplicate($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/trash', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->trash($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/restore', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->restore($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/purge', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->purge($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/preview', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->previewLink($req), [$auth, $csrfCheck]);
            $r->post('/pages/{id:\d+}/restore/{rid:\d+}', fn (Request $req) => (new \Admin\Controllers\PagesController($c))->restoreRevision($req), [$auth, $csrfCheck]);
            $r->any('/navigation', fn (Request $req) => (new \Admin\Controllers\NavigationController($c))->index($req), [$auth, $csrfCheck]);
            $r->any('/seo', fn (Request $req) => (new \Admin\Controllers\SeoController($c))->index($req), [$auth, $csrfCheck]);
            $r->get('/seo/redirects.csv', fn (Request $req) => (new \Admin\Controllers\SeoController($c))->export($req), [$auth]);
            $r->get('/content', fn (Request $req) => (new \Admin\Controllers\ContentTypesController($c))->index($req), [$auth]);
            $r->get('/content/list', fn (Request $req) => (new \Admin\Controllers\ContentTypesController($c))->list($req), [$auth]);
            $r->get('/content/edit', fn (Request $req) => (new \Admin\Controllers\ContentTypesController($c))->edit($req), [$auth]);
            $r->post('/content/save', fn (Request $req) => (new \Admin\Controllers\ContentTypesController($c))->save($req), [$auth, $csrfCheck]);
            $r->post('/content/delete', fn (Request $req) => (new \Admin\Controllers\ContentTypesController($c))->delete($req), [$auth, $csrfCheck]);
            $r->any('/media', fn (Request $req) => (new \Admin\Controllers\MediaController($c))->index($req), [$auth, $csrfCheck]);
            $r->get('/appearance', fn (Request $req) => (new \Admin\Controllers\AppearanceController($c))->index($req), [$auth]);
            $r->post('/appearance/activate', fn (Request $req) => (new \Admin\Controllers\AppearanceController($c))->activate($req), [$auth, $csrfCheck]);
            $r->post('/appearance/branding', fn (Request $req) => (new \Admin\Controllers\AppearanceController($c))->saveBranding($req), [$auth, $csrfCheck]);
            $r->post('/modules/upload', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->upload($req), [$auth, $csrfCheck]);
            $r->post('/modules/recovery', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->recovery($req), [$auth, $csrfCheck]);
            $r->post('/modules/{id:.+}/{op:[a-z]+}', fn (Request $req) => (new \Admin\Controllers\ModulesController($c))->action($req), [$auth, $csrfCheck]);
            $r->get('/backups', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->index($req), [$auth]);
            $r->post('/backups/create', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->create($req), [$auth, $csrfCheck]);
            $r->post('/backups/restore', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->restore($req), [$auth, $csrfCheck]);
            $r->post('/backups/delete', fn (Request $req) => (new \Admin\Controllers\BackupsController($c))->delete($req), [$auth, $csrfCheck]);
            $r->any('/operations', fn (Request $req) => (new \Admin\Controllers\OperationsController($c))->index($req), [$auth, $csrfCheck]);
            // A dedicated class path prevents stale shared-host OPcache from
            // retaining a pre-repair SystemController after files are
            // replaced through a hosting panel.
            $r->get('/health', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->health($req), [$auth]);
            $r->get('/system/diagnostics', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->diagnostics($req), [$auth]);
            $r->get('/audit', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->audit($req), [$auth]);
            $r->get('/updates', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->updates($req), [$auth]);
            $r->post('/updates/stage', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->stageUpdate($req), [$auth, $csrfCheck]);
            $r->post('/updates/apply', fn (Request $req) => (new \Admin\Controllers\SystemToolsController($c))->applyUpdate($req), [$auth, $csrfCheck]);
        });

        // Sitemap, robots, media streams, and draft previews — before the
        // page catch-all so they always win.
        $this->router->get('/sitemap.xml', fn (Request $r) => (new \Admin\Controllers\SitemapController($c))->sitemap($r));
        $this->router->get('/robots.txt', fn (Request $r) => (new \Admin\Controllers\SitemapController($c))->robots($r));
        $this->router->get('/media/{id:\d+}/w/{width:\d+}/{name:.*}', fn (Request $r) => (new \Admin\Controllers\MediaController($c))->serveVariant($r));
        $this->router->get('/media/{id:\d+}/crop/{width:\d+}x{height:\d+}/{name:.*}', fn (Request $r) => (new \Admin\Controllers\MediaController($c))->serveCrop($r));
        $this->router->get('/media/{id:\d+}/{name:.*}', fn (Request $r) => (new \Admin\Controllers\MediaController($c))->serve($r));
        $this->router->get('/preview/{token:[a-f0-9]{48}}', fn (Request $r) => (new \Admin\Controllers\PublicPageController($c))->preview($r));
        $this->router->post('/forms/{id:\d+}/submit', fn (Request $r) => (new \Admin\Controllers\FormsController($c))->submit($r));
        $this->router->any('/privacy/request', fn (Request $r) => (new \Admin\Controllers\PrivacyController($c))->request($r));
        $this->router->get('/api/v1/health', fn (Request $r) => (new \Admin\Controllers\ApiController($c))->health($r));
        $this->router->get('/api/v1/pages', fn (Request $r) => (new \Admin\Controllers\ApiController($c))->pages($r));
        $this->router->get('/api/v1/pages/{id:\d+}', fn (Request $r) => (new \Admin\Controllers\ApiController($c))->page($r));

        // Namespaced asset resolver (theme/module assets, allowlisted types).
        $this->router->get('/_a/{path:.*}', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\AssetController($c))->serve($r);
        });

    }

    /**
     * Public routes are registered LAST, after bootModules() — the router
     * is first-match-wins, so a module's public routes must exist before
     * the slug catch-all or the catch-all swallows them as 404s. Reserved
     * prefixes are still excluded inside the pattern.
     */
    /**
     * First path segments the page catch-all never serves — a page saved
     * under one of these is unreachable. PagesController blocks them via
     * this same list so the router and the validator share one source.
     */
    public const RESERVED_SLUGS = [
        'admin', '_a', 'media', 'preview', 'sitemap.xml', 'robots.txt',
        'install.php', 'cron.php', 'api', 'forms', 'privacy', 'l', 'storage', 'upload',
    ];

    private function registerPublicRoutes(): void
    {
        $c = $this->container;
        $reserved = implode('|', array_map(
            fn (string $s) => preg_quote($s) . '\b',
            self::RESERVED_SLUGS
        ));
        $this->router->get('/', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\PublicPageController($c))->home($r);
        });
        $this->router->get('/l/{locale:[a-z][a-z](?:-[a-z][a-z])?}/{slug:[a-z0-9][a-z0-9\-\/]*}', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\PublicPageController($c))->localized($r);
        });
        $this->router->get('/{slug:(?!' . $reserved . ')[a-z0-9][a-z0-9\-\/]*}', function (Request $r) use ($c): Response {
            return (new \Admin\Controllers\PublicPageController($c))->show($r);
        });
    }

    private function withSecurityHeaders(Response $response): Response
    {
        $response = $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->header('Cross-Origin-Opener-Policy', 'same-origin')
            ->header('X-Permitted-Cross-Domain-Policies', 'none');
        if ($this->request?->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($this->request && str_starts_with($this->request->path, '/admin')) {
            $response->header('Cache-Control', 'private, no-store');
        }
        return $response;
    }

    private function handleException(\Throwable $e): Response
    {
        try {
            $entropy = bin2hex(random_bytes(8));
        } catch (\Throwable) {
            $entropy = (string) microtime(true);
        }
        $reference = substr(hash(
            'sha256',
            get_class($e) . "\0" . $e->getMessage() . "\0" . $e->getFile()
                . ':' . $e->getLine() . "\0" . $entropy
        ), 0, 10);
        $logLine = sprintf(
            '[website-core] [ref %s] %s: %s @ %s:%d',
            $reference,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );
        error_log($logLine);
        $this->writeApplicationError($logLine);
        $debug = (bool) $this->config->get('app.debug', false);
        if ($debug) {
            return Response::html(
                '<h1>Application error</h1><pre>' . htmlspecialchars((string) $e) . '</pre>',
                500
            );
        }
        return Response::html((new \Core\Branding\Attribution())->apply(\Core\Branding\StatusPage::render(
            500, 'That wasn’t supposed to happen.',
            'The issue has been recorded. Try the page once more, or share the reference with the site administrator.',
            $this->request?->routeBase() ?? '', $this->siteName(), $reference, 'Try again', ''
        )), 500);
    }

    private function siteName(): string
    {
        try {
            return (string) $this->container->get(SettingsRepository::class)->get('site.name', 'Website');
        } catch (\Throwable) {
            return (string) $this->config->get('app.name', 'Website');
        }
    }

    /** Apply only when this build's latest core migration is absent. */
    private function ensureCoreSchema(Connection $db): void
    {
        $target = defined('WC_SCHEMA_VERSION') ? (string) WC_SCHEMA_VERSION : '';
        if ($target !== '' && $db->tableExists('migrations')) {
            $applied = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $db->table('migrations') . '` WHERE migration_id = ?',
                [$target]
            );
            if ($applied > 0) {
                return;
            }
        }
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
    }

    /** Best-effort local log that preserves the same reference shown on screen. */
    private function writeApplicationError(string $line): void
    {
        $storage = (string) $this->config->get('paths.storage', WC_ROOT . '/storage');
        $directory = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return;
        }
        @file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'application.log',
            '[' . gmdate('Y-m-d\TH:i:s\Z') . '] ' . $line . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }
}
