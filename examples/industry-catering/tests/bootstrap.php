<?php
declare(strict_types=1);

$core = getenv('CATERING_CORE_ROOT');
if (!$core || !is_file($core . '/site/includes/autoload.php')) {
    throw new RuntimeException('Set CATERING_CORE_ROOT to a read-only Website Core checkout.');
}
define('WC_ROOT', $core . '/site');
define('WC_CORE', WC_ROOT . '/core');
define('WC_THEMES', WC_ROOT . '/themes');
$bootstrapSource = (string) file_get_contents(WC_ROOT . '/includes/bootstrap.php');
preg_match("/define\\('WC_VERSION',\\s*'([^']+)'\\)/", $bootstrapSource, $coreVersion);
define('WC_VERSION', $coreVersion[1] ?? '2.0.5');
define('WC_API_VERSION', 1);
$vendorAutoload = dirname(WC_ROOT) . '/vendor/autoload.php';
if (is_file($vendorAutoload) && PHP_VERSION_ID >= 80400) {
    require $vendorAutoload;
} elseif (is_file(dirname(WC_ROOT) . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php')) {
    require dirname(WC_ROOT) . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';
}
require WC_ROOT . '/includes/autoload.php';
require WC_ROOT . '/includes/helpers.php';
$moduleRoot = getenv('CATERING_MODULE_ROOT') ?: dirname(__DIR__);
CoreAutoload::register('CajunWebPro\\Catering', $moduleRoot . '/src');

function cateringFixture(?string $sqlitePath = null, bool $withModule = true): array
{
    global $moduleRoot;
    $dsn = getenv('CATERING_TEST_MYSQL_DSN');
    $prefix = $dsn ? 'ct_' . bin2hex(random_bytes(4)) . '_' : 'ct_';
    $pdo = new PDO($dsn ?: 'sqlite:' . ($sqlitePath ?: ':memory:'), getenv('CATERING_TEST_MYSQL_USER') ?: null, getenv('CATERING_TEST_MYSQL_PASS') ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = new Core\Database\Connection($pdo, $prefix);
    (new Core\Database\Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
    if ($withModule) (new Core\Database\Migrator($db, $moduleRoot . '/migrations'))->migrate();
    $settings = new Core\Settings\SettingsRepository($db);
    $settings->set('site.name', 'Catering Co.');
    $settings->set('site.contact_email', 'owner@example.test');
    $settings->set('catering.minimum_guests', 20);
    $settings->set('branding.seeded.cajunwebpro.catering', true);
    $configPath = tempnam(sys_get_temp_dir(), 'ct-config-');
    $testStorage = sys_get_temp_dir() . '/ct-media-' . bin2hex(random_bytes(4));
    file_put_contents($configPath, '<?php return ' . var_export(['paths' => ['storage' => $testStorage]], true) . ';');
    $config = new Core\Config($configPath);
    unlink($configPath);
    $csrf = new Core\Security\Csrf();
    $limiter = new Core\Security\RateLimiter($db);
    $permissions = new Core\Auth\Permissions();
    $admins = new Core\Auth\AdminRepository($db);
    foreach (['owner', 'editor'] as $role) {
        if (!$admins->findByUsername($role)) $admins->create($role, $role . '@example.test', password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), $role);
    }
    $audit = new Core\Audit\AuditLog($db);
    $auth = new Core\Auth\AuthService($db, $admins, $permissions, new Core\Security\SessionManager($config), $limiter, $audit);
    $view = new Core\View\View();
    $view->share('coreVersion', WC_VERSION);
    $moduleManifest = json_decode((string) file_get_contents($moduleRoot . '/addon.json'), true, 512, JSON_THROW_ON_ERROR);
    $view->share('industryVersions', [['name' => 'Catering Website', 'version' => (string) $moduleManifest['version']]]);
    $view->share('noticeCount', 0);
    $view->share('pageMeta', []);
    $view->addResolver('admin', static fn (string $name) => WC_ROOT . '/admin/views/' . $name . '.php');
    $media = new Core\Media\MediaService($db, $config);
    $services = [
        Core\Database\Connection::class => $db, Core\Settings\SettingsRepository::class => $settings,
        Core\Security\Csrf::class => $csrf, Core\Security\RateLimiter::class => $limiter,
        Core\View\View::class => $view, Core\Auth\AuthService::class => $auth,
        Core\Audit\AuditLog::class => $audit, Core\Admin\Nav::class => new Core\Admin\Nav(null, $auth),
        Core\Mail\SystemMail::class => new Core\Mail\SystemMail($db, $settings),
        Core\Branding\BrandAssets::class => new Core\Branding\BrandAssets($db, $settings, $media),
        Core\Media\MediaService::class => $media,
        Core\Config::class => $config,
    ];
    $container = new Core\Container();
    foreach ($services as $id => $service) $container->instance($id, $service);
    $registry = new Core\Modules\ModuleRegistry($db, $testStorage . '/industries', $testStorage . '/addons', $testStorage . '/themes');
    $services[Core\Modules\ModuleRegistry::class] = $registry;
    $services[Core\Business\BusinessProfile::class] = new Core\Business\BusinessProfile($settings);
    $services[Core\Leads\InquiryService::class] = new Core\Leads\InquiryService($db);
    $services[Core\Leads\QuoteService::class] = new Core\Leads\QuoteService($db);
    $services[Core\Business\ServiceAreaService::class] = new Core\Business\ServiceAreaService($db, $settings, $audit);
    $services[Core\Appearance\ModuleThemeCollections::class] = new Core\Appearance\ModuleThemeCollections($db, $audit);
    $services[Core\Commerce\CheckoutValidation::class] = new Core\Commerce\CheckoutValidation($db);
    $services[Core\Commerce\ProductRepository::class] = new Core\Commerce\ProductRepository($db);
    $services[Core\Content\ContentItems::class] = new Core\Content\ContentItems($db, $registry);
    $services[Core\System\Version::class] = new Core\System\Version($registry);
    $services[Core\Admin\NoticeCenter::class] = new Core\Admin\NoticeCenter($db);
    foreach ($services as $id => $service) $container->instance($id, $service);
    $services[Core\Modules\ModuleAdminRenderer::class] = new Core\Modules\ModuleAdminRenderer($container);
    $router = new Core\Routing\Router();
    $middleware = static function ($request, $next) use ($auth, $csrf) {
        if ($auth->user() === null) return Core\Http\Response::text('Sign in required', 401);
        if ($request->isPost() && !$csrf->validate($request)) return Core\Http\Response::text('Invalid CSRF token', 419);
        return $next($request);
    };
    $ctx = new Core\Modules\ModuleContext('cajunwebpro/catering', $router, new Core\Events\Dispatcher(), $permissions,
        $middleware, $view, static fn ($id) => $services[$id] ?? throw new RuntimeException('Missing service ' . $id),
        static fn () => 'base', WC_THEMES, 'industry-catering', $moduleRoot);
    (new CajunWebPro\Catering\Provider())->register($ctx);
    $controller = new CajunWebPro\Catering\CateringController($ctx, $ctx->views($moduleRoot . '/views'));
    return compact('db', 'settings', 'csrf', 'auth', 'admins', 'router', 'controller', 'ctx', 'limiter');
}

function cateringRequest(string $method, string $path, array $post = [], array $query = []): Core\Http\Request
{
    return new Core\Http\Request($method, $path, '/catering', $query, $post, [], [], ['REMOTE_ADDR' => '127.0.0.1']);
}

function cateringTestIdentity(array $fixture, string $role): void
{
    $admin = $fixture['admins']->findByUsername($role);
    $_SESSION[Core\Auth\AuthService::ADMIN_KEY] = (int) $admin['id'];
    $db = $fixture['db'];
    $db->run('DELETE FROM `' . $db->table('admin_sessions') . '` WHERE session_hash = ?', [hash('sha256', session_id())]);
    $db->insert('admin_sessions', ['admin_id' => (int) $admin['id'], 'session_hash' => hash('sha256', session_id()),
        'ip' => '127.0.0.1', 'user_agent' => 'Catering module test', 'created_at' => date('Y-m-d H:i:s'), 'last_seen' => date('Y-m-d H:i:s')]);
}
