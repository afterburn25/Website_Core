<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Auth\Permissions;
use Core\Branding\BrandAssets;
use Core\Business\BusinessProfile;
use Core\Content\MenuService;
use Core\Database\Connection;
use Core\Events\Dispatcher;
use Core\Http\Request;
use Core\Routing\Router;
use Core\Settings\SettingsRepository;
use Core\View\View;

/**
 * The supported extension API surface (version WC_API_VERSION). Packages
 * receive this in their provider's register() and may ONLY interact with
 * core through it — no reaching into other modules' tables or core internals.
 */
final class ModuleContext
{
    /** @var list<array{label:string,route:string,capability:string,package:string,category:?string}> */
    private array $adminNav = [];
    /** @var array<string, callable> */
    private array $jobHandlers = [];
    /** @var array<string, array{label:string,fields:list<array>}> */
    private array $contentTypes = [];
    /** @var list<array{key:string,label:string,provider:callable,capability:?string,url:?string}> */
    private array $dashboardWidgets = [];
    /** @var list<array{provider:callable,capability:?string}> */
    private array $searchProviders = [];
    /** @var list<array{key:string,label:string,provider:callable}> */
    private array $healthChecks = [];
    /** @var array<string,array{label:string,fields:array,renderer:callable,package:string}> */
    private array $pageBlocks = [];
    /** @var array<string,array{label:string,items:list<array>}> */
    private array $navigationMenus = [];
    /** @var array<string,string> */
    private array $brandAssets = [];
    /** @var array<string,array> opt-in Core surfaces supplied by this package */
    private array $coreFeatures = [];
    /** @var array<string,array> declarative admin resources keyed by route owner/resource */
    private array $adminResources = [];
    private ?string $viewNamespace = null;

    /**
     * @param callable|null $adminMiddleware combined auth+CSRF pipeline for
     *        adminRoute(); null in isolated tests
     * @param callable|null $serviceResolver core service lookup (trusted code)
     * @param callable|null $activeTheme returns the active theme dir name
     */
    public function __construct(
        private string $packageId,
        private Router $router,
        private Dispatcher $events,
        private ?Permissions $permissions = null,
        private $adminMiddleware = null,
        private ?View $view = null,
        private $serviceResolver = null,
        private $activeTheme = null,
        private ?string $themesRoot = null,
        private ?string $assetNamespace = null,
        private ?string $packageRoot = null,
    ) {}

    private function themesRoot(): string
    {
        return $this->themesRoot ?? (defined('WC_THEMES') ? WC_THEMES : 'themes');
    }

    public function packageId(): string
    {
        return $this->packageId;
    }

    /**
     * Public URL prefix for this package's assets. The namespace is the
     * installed package directory, not the manifest id (which may contain a
     * slash and therefore cannot safely occupy one route segment).
     */
    public function assetBase(string $basePath = ''): string
    {
        $namespace = $this->assetNamespace;
        if ($namespace === null || $namespace === '') {
            $namespace = basename(str_replace('\\', '/', $this->packageId));
        }

        // Query routing is the portable form: it works on Apache/LiteSpeed
        // with rewrites and on nginx/shared hosts that ignore .htaccess.
        return rtrim($basePath, '/') . '/index.php?p=/_a/modules/' . rawurlencode($namespace);
    }

    /** Prefix to which a package may append an absolute application route. */
    public function routeBase(string $basePath = ''): string
    {
        return rtrim($basePath, '/') . '/index.php?p=';
    }

    /** Build a portable application URL without requiring server rewrites. */
    public function url(string $basePath, string $path = '/', array $query = []): string
    {
        $url = $this->routeBase($basePath) . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /** Register a public route. Module routes register before the slug catch-all. */
    public function route(string $methods, string $pattern, callable $handler, array $middleware = []): void
    {
        if (!str_starts_with($pattern, '/')) throw new \InvalidArgumentException('Module routes must begin with /.');
        $this->router->add($methods, $pattern, $handler, $middleware);
    }

    /**
     * Register an ADMIN route — wrapped in the same authenticated-session +
     * CSRF pipeline as every core admin screen. The handler must ALSO check
     * its own declared capability; hidden nav items are not access control.
     */
    public function adminRoute(string $methods, string $pattern, callable $handler): void
    {
        if (!str_starts_with($pattern, '/admin/')) throw new \InvalidArgumentException('Module admin routes must begin with /admin/.');
        $mw = $this->adminMiddleware ? [$this->adminMiddleware] : [];
        $this->router->add($methods, $pattern, $handler, $mw);
    }

    public function on(string $event, callable $listener): void
    {
        $this->events->listen($event, $listener);
    }

    public function adminPage(
        string $label,
        string $route,
        string $capability,
        ?string $category = null
    ): void
    {
        $label = trim($label);
        $route = trim($route);
        $capability = trim($capability);
        if ($label === '' || mb_strlen($label) > 120) throw new \InvalidArgumentException('Admin page labels must be 1–120 characters.');
        if (!str_starts_with($route, '/admin/') || mb_strlen($route) > 500) throw new \InvalidArgumentException('Admin page routes must be internal /admin/ paths.');
        if (!preg_match('/^[a-z0-9_.:-]+$/', $capability)) throw new \InvalidArgumentException('Admin pages require a valid package capability.');
        if ($category !== null && mb_strlen(trim($category)) > 80) throw new \InvalidArgumentException('Admin categories must be 80 characters or fewer.');
        $this->adminNav[] = [
            'label' => $label, 'route' => $route,
            'capability' => $capability, 'package' => $this->packageId,
            'category' => $category === null || trim($category) === '' ? null : trim($category),
        ];
    }

    public function scheduledJob(string $type, callable $handler): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.:-]{1,119}$/', $type)) throw new \InvalidArgumentException('Scheduled-job types must be stable lowercase tokens.');
        if (!str_starts_with($type, $this->packageSlug() . '.')) throw new \InvalidArgumentException('Scheduled-job types must be prefixed with the package slug.');
        if (isset($this->jobHandlers[$type])) throw new \InvalidArgumentException('Scheduled-job type is already registered by this package.');
        $this->jobHandlers[$type] = $handler;
    }

    public function contentType(string $type, string $label, array $fields): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $type)) throw new \InvalidArgumentException('Content types must use lowercase letters, numbers, and underscores.');
        if (trim($label) === '' || mb_strlen(trim($label)) > 120) throw new \InvalidArgumentException('Content type labels must be 1–120 characters.');
        if (isset($this->contentTypes[$type])) throw new \InvalidArgumentException('Content type is already registered by this package.');
        $this->contentTypes[$type] = ['label' => $label, 'fields' => $fields];
    }

    /** Add a small live metric to the core dashboard. Provider returns a scalar or {value,detail}. */
    public function dashboardWidget(
        string $key,
        string $label,
        callable $provider,
        ?string $capability = null,
        ?string $url = null,
    ): void {
        $this->assertContribution($key, $label, $capability, $url);
        foreach ($this->dashboardWidgets as $widget) {
            if ($widget['key'] === $key) throw new \InvalidArgumentException('Dashboard widget key is already registered by this package.');
        }
        $this->dashboardWidgets[] = compact('key', 'label', 'provider', 'capability', 'url');
    }

    /** Provider receives the query and returns standard search result arrays. */
    public function adminSearchProvider(callable $provider, ?string $capability = null): void
    {
        $this->searchProviders[] = compact('provider', 'capability');
    }

    /** Provider returns ok|warn|error or {status,detail}. */
    public function healthCheck(string $key, string $label, callable $provider): void
    {
        $this->assertContribution($key, $label);
        foreach ($this->healthChecks as $check) {
            if ($check['key'] === $key) throw new \InvalidArgumentException('Health-check key is already registered by this package.');
        }
        $this->healthChecks[] = compact('key', 'label', 'provider');
    }

    /** Register an industry-specific block for the core visual composer. */
    public function pageBlock(string $type, string $label, array $fields, callable $renderer): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $type)) {
            throw new \InvalidArgumentException('Block type must use lowercase letters, numbers and underscores.');
        }
        if (isset($this->pageBlocks[$type])) throw new \InvalidArgumentException('Page block type is already registered by this package.');
        $this->pageBlocks[$type] = compact('label', 'fields', 'renderer') + ['package' => $this->packageId];
    }

    /**
     * Declare the module's starting navigation. Core imports the declaration
     * into its editable menu once, without replacing an existing or
     * intentionally cleared menu.
     */
    public function navigationMenu(
        array $items,
        string $location = 'primary',
        string $label = 'Primary navigation',
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $location)) {
            throw new \InvalidArgumentException('Navigation location must use lowercase letters, numbers, hyphens, and underscores.');
        }
        $items = $this->normalizeNavigationItems($items);
        $this->navigationMenus[$location] = ['label' => trim($label), 'items' => $items];
        if ($this->serviceResolver === null) {
            return;
        }

        $settings = $this->service(SettingsRepository::class);
        $marker = 'navigation.seeded.' . str_replace('/', '.', $this->packageId) . '.' . $location;
        if ((bool) $settings->get($marker, false)) {
            return;
        }

        $menus = new MenuService($this->service(Connection::class));
        $menuId = $menus->ensureLocation($location, trim($label) ?: 'Navigation');
        if ($menus->itemsFor($location) === []) {
            $this->service(Connection::class)->transaction(function () use ($menus, $menuId, $items): void {
                $insert = function (array $nodes, ?int $parentId = null) use (&$insert, $menus, $menuId): void {
                    foreach ($nodes as $order => $item) {
                        [$id, $error] = $menus->addItem(
                            $menuId,
                            $item['label'],
                            $item['url'],
                            $parentId,
                            $order,
                            $item['target'],
                        );
                        if ($error !== null || $id === null) {
                            throw new \RuntimeException('Unable to initialize module navigation: ' . ($error ?? 'unknown error'));
                        }
                        $insert($item['children'], $id);
                    }
                };
                $insert($items);
            });
        }
        $settings->set($marker, true);
    }

    /** Return the Core-managed menu, or the module defaults before import. */
    public function navigationTree(string $location = 'primary'): array
    {
        $defaults = $this->navigationMenus[$location]['items'] ?? [];
        if ($this->serviceResolver === null) {
            return $defaults;
        }
        $tree = (new MenuService($this->service(Connection::class)))->tree($location);
        $marker = 'navigation.seeded.' . str_replace('/', '.', $this->packageId) . '.' . $location;
        if ($tree !== [] || (bool) $this->service(SettingsRepository::class)->get($marker, false)) {
            return $tree;
        }
        return $defaults;
    }

    /**
     * Declare starting brand images stored inside this package. Core copies
     * them into Media once, then owns the editable logo/favicon selections.
     * Later package boots and updates never overwrite an administrator's
     * selection or intentionally cleared value.
     *
     * @param array<string,string> $assets logo|favicon|social => relative path
     */
    public function brandAssets(array $assets): void
    {
        $allowed = ['logo' => true, 'favicon' => true, 'social' => true];
        foreach ($assets as $role => $path) {
            if (!isset($allowed[$role]) || !is_string($path) || !Manifest::relativePathOk($path)) {
                throw new \InvalidArgumentException('Brand assets support logo, favicon, and social image paths.');
            }
            $this->brandAssets[$role] = trim($path);
        }
        if ($this->serviceResolver !== null) {
            if ($this->packageRoot === null || $this->packageRoot === '') {
                throw new \RuntimeException('Package root is required to import brand assets.');
            }
            $this->service(BrandAssets::class)->seed($this->packageId, $this->packageRoot, $this->brandAssets);
        }
    }

    /** Resolve the current Core-owned brand asset for module layouts. */
    public function brandAssetUrl(Request $request, string $role, ?int $width = null): ?string
    {
        if ($this->serviceResolver === null) return null;
        return $this->service(BrandAssets::class)->url($request, $role, $width);
    }

    /** Resolve the Core-owned custom footer notice for an industry layout. */
    public function copyrightNotice(string $siteName): string
    {
        if ($this->serviceResolver === null) {
            return '© ' . date('Y') . ' ' . trim($siteName) . '. All rights reserved.';
        }
        return $this->service(BrandAssets::class)->copyrightNotice($siteName);
    }

    /** @return list<array{platform:string,label:string,url:string,placement:string}> */
    public function socialLinks(?string $location = null): array
    {
        if ($this->serviceResolver === null) return [];
        return $this->service(BrandAssets::class)->socialLinks($location);
    }

    /**
     * Opt an enabled package into a Core-owned feature. Core never shows or
     * serves an optional feature merely because its code is present.
     */
    public function coreFeature(string $feature, array $options = []): void
    {
        $allowed = [
            'commerce.catalog', 'commerce.cart', 'commerce.checkout', 'commerce.orders', 'accounts.customers',
            'business.profile', 'crm.inquiries', 'crm.quotes',
        ];
        if (!in_array($feature, $allowed, true)) {
            throw new \InvalidArgumentException('Unknown Core feature: ' . $feature);
        }
        if (isset($this->coreFeatures[$feature])) {
            throw new \InvalidArgumentException('Core feature is already declared by this package.');
        }
        $this->coreFeatures[$feature] = $this->normalizeFeatureOptions($options);
        $capability = match ($feature) {
            'commerce.catalog', 'commerce.cart', 'commerce.checkout' => 'commerce.manage',
            'commerce.orders' => 'orders.manage',
            'accounts.customers' => 'customers.manage',
            'crm.inquiries' => 'inquiries.manage',
            'crm.quotes' => 'quotes.manage',
            default => null,
        };
        if ($capability !== null) $this->permissions?->activateOptional($capability);
    }

    /** Declare a coherent store while retaining per-surface switches. */
    public function commerce(array $options = []): void
    {
        $features = $options['features'] ?? ['catalog', 'cart', 'checkout', 'orders'];
        if (!is_array($features) || $features === []) {
            throw new \InvalidArgumentException('Commerce requires at least one feature.');
        }
        unset($options['features']);
        $features=array_values(array_unique(array_map('strval',$features)));
        if (in_array('checkout',$features,true)) $features=array_values(array_unique([...$features,'catalog','cart','orders']));
        if (in_array('cart',$features,true) && !in_array('catalog',$features,true)) $features[]='catalog';
        foreach ($features as $feature) {
            if (!in_array($feature, ['catalog', 'cart', 'checkout', 'orders'], true)) {
                throw new \InvalidArgumentException('Unsupported commerce feature: ' . $feature);
            }
            $this->coreFeature('commerce.' . $feature, $options);
        }
    }

    /** Opt into public customer identities; omitted modules expose no account UI. */
    public function customerAccounts(array $options = []): void
    {
        $this->coreFeature('accounts.customers', $options);
    }

    /** Seed and expose the single Core-owned business identity. */
    public function businessProfile(array $defaults = []): void
    {
        $this->coreFeature('business.profile');
        if ($defaults !== [] && $this->serviceResolver !== null) {
            $this->service(BusinessProfile::class)->seed($this->packageId, $defaults);
        }
    }

    /** Enable the shared inquiry workspace and, optionally, quotations. */
    public function leadManagement(array $options = []): void
    {
        $quotes = !array_key_exists('quotes', $options) || (bool) $options['quotes'];
        unset($options['quotes']);
        $this->coreFeature('crm.inquiries', $options);
        if ($quotes) $this->coreFeature('crm.quotes', $options);
    }

    private function normalizeFeatureOptions(array $options): array
    {
        $allowed = ['store_name', 'currency', 'fulfillment', 'terms_url', 'registration', 'account_label', 'label'];
        $normalized = [];
        foreach ($options as $key => $value) {
            if (!in_array((string) $key, $allowed, true)) {
                throw new \InvalidArgumentException('Unsupported Core feature option: ' . $key);
            }
            if ($key === 'fulfillment') {
                if (!is_array($value)) throw new \InvalidArgumentException('Fulfillment must be a list.');
                $methods = array_values(array_intersect(['pickup', 'delivery', 'shipping'], array_map('strval', $value)));
                if ($methods === []) throw new \InvalidArgumentException('Select at least one fulfillment method.');
                $normalized[$key] = $methods;
                continue;
            }
            if ($key === 'registration') {
                $normalized[$key] = (bool) $value;
                continue;
            }
            $text = trim((string) $value);
            if (mb_strlen($text) > 200) throw new \InvalidArgumentException('Core feature options are limited to 200 characters.');
            if ($key === 'currency' && !preg_match('/^[A-Z]{3}$/', strtoupper($text))) {
                throw new \InvalidArgumentException('Commerce currency must be a three-letter ISO code.');
            }
            $normalized[$key] = $key === 'currency' ? strtoupper($text) : $text;
        }
        return $normalized;
    }

    /** @return list<array{label:string,url:string,target:string,children:list<array>}> */
    private function normalizeNavigationItems(array $items, int $depth = 0): array
    {
        if ($depth > 5) {
            throw new \InvalidArgumentException('Navigation defaults may be nested up to five levels.');
        }
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || trim((string) ($item['label'] ?? '')) === '' || trim((string) ($item['url'] ?? '')) === '') {
                throw new \InvalidArgumentException('Every navigation item requires a label and URL.');
            }
            if (mb_strlen(trim((string) $item['label'])) > 190 || !$this->navigationUrlOk(trim((string) $item['url']))) {
                throw new \InvalidArgumentException('Navigation defaults require labels up to 190 characters and safe internal, web, email, telephone, or anchor URLs.');
            }
            $target = (string) ($item['target'] ?? 'self');
            if (!in_array($target, ['self', 'tab', 'window'], true)) {
                throw new \InvalidArgumentException('Navigation targets must be self, tab, or window.');
            }
            $children = $item['children'] ?? [];
            if (!is_array($children)) {
                throw new \InvalidArgumentException('Navigation item children must be an array.');
            }
            $normalized[] = [
                'label' => trim((string) $item['label']),
                'url' => trim((string) $item['url']),
                'target' => $target,
                'children' => $this->normalizeNavigationItems($children, $depth + 1),
            ];
        }
        return $normalized;
    }

    /** Declare a capability this package adds; owner always holds it and
     * admin does by default — a package can never grant itself core caps. */
    public function permission(string $capability, string $description = ''): void
    {
        if (!preg_match('/^[a-z0-9_.:-]+$/', $capability)
            || !str_starts_with($capability, $this->packageSlug() . '.')) {
            throw new \InvalidArgumentException('Package capabilities must be prefixed with the package slug.');
        }
        if (mb_strlen($description) > 300) throw new \InvalidArgumentException('Capability descriptions must be 300 characters or fewer.');
        $this->permissions?->register($capability, $description);
    }

    /**
     * Register this package's view directory. Views resolve as
     * "mod-<slug>:<name>" with theme overrides at
     * themes/<active>/templates/modules/<package-id>/<name>.php falling
     * back to the package's own views/<name>.php — overrides never reach
     * module data.
     */
    public function views(string $dir): string
    {
        $ns = 'mod-' . str_replace(['/', '_'], '-', $this->packageId);
        $this->viewNamespace = $ns;
        $theme = $this->activeTheme;
        $this->view?->addResolver($ns, function (string $v) use ($dir, $theme): ?string {
            $active = $theme instanceof \Closure ? $theme() : (is_string($theme) ? $theme : '');
            if ($active !== '') {
                $override = $this->themesRoot() . '/' . $active . '/templates/modules/' . $this->packageId . '/' . $v . '.php';
                if (is_file($override)) {
                    return $override;
                }
            }
            $path = rtrim($dir, '/\\') . '/' . $v . '.php';
            return is_file($path) ? $path : null;
        });
        return $ns;
    }

    /** Render a package view in Core's complete admin shell with capability enforcement. */
    public function adminView(Request $request, string $view, array $data, string $title, string $capability): \Core\Http\Response
    {
        if ($this->viewNamespace === null || !str_starts_with($view, $this->viewNamespace . ':')) {
            throw new \InvalidArgumentException('Call views() and use its returned namespace before rendering an admin view.');
        }
        return $this->service(ModuleAdminRenderer::class)->render($request, $this->packageId, $view, $data, $title, $capability);
    }

    /**
     * Register a standard list/editor resource while the package retains its
     * persistence callbacks and business rules.
     *
     * @param list<array{name:string,label:string,type?:string,required?:bool,options?:array}> $fields
     * @param list<array{name:string,label:string}> $columns
     * @param array{reorder?:callable,bulk?:callable,bulk_actions?:array<string,string>} $options
     */
    public function adminResource(
        string $key,
        string $label,
        string $capability,
        array $fields,
        array $columns,
        callable $list,
        callable $find,
        callable $save,
        ?callable $delete = null,
        string $category = 'Industry',
        array $options = [],
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $key)) throw new \InvalidArgumentException('Resource keys must be stable lowercase tokens.');
        if (trim($label) === '' || mb_strlen(trim($label)) > 120) throw new \InvalidArgumentException('Resource labels must be 1–120 characters.');
        if (!preg_match('/^[a-z0-9_.:-]+$/', $capability)) throw new \InvalidArgumentException('Resources require a valid capability.');
        if ($fields === [] || $columns === []) throw new \InvalidArgumentException('Resources require fields and columns.');
        foreach (array_keys($options) as $option) {
            if (!in_array((string) $option, ['reorder', 'bulk', 'bulk_actions'], true)) {
                throw new \InvalidArgumentException('Unsupported admin resource option: ' . $option);
            }
        }
        $reorder = $options['reorder'] ?? null;
        $bulk = $options['bulk'] ?? null;
        $bulkActions = $options['bulk_actions'] ?? [];
        if ($reorder !== null && !is_callable($reorder)) throw new \InvalidArgumentException('Resource reorder must be callable.');
        if ($bulk !== null && !is_callable($bulk)) throw new \InvalidArgumentException('Resource bulk handler must be callable.');
        if (!is_array($bulkActions)) throw new \InvalidArgumentException('Resource bulk actions must be a map.');
        if (($bulk === null) !== ($bulkActions === [])) {
            throw new \InvalidArgumentException('Resource bulk handler and actions must be declared together.');
        }
        $normalizedBulkActions = [];
        foreach ($bulkActions as $action => $actionLabel) {
            $action = (string) $action;
            $actionLabel = trim((string) $actionLabel);
            if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $action) || $actionLabel === '' || mb_strlen($actionLabel) > 120) {
                throw new \InvalidArgumentException('Resource bulk actions require safe tokens and labels.');
            }
            $normalizedBulkActions[$action] = $actionLabel;
        }
        $allowedTypes = ['text', 'textarea', 'email', 'url', 'number', 'date', 'select', 'checkbox'];
        $normalize = static function (array $items, bool $field) use ($allowedTypes): array {
            $out = [];
            foreach ($items as $item) {
                if (!is_array($item) || !preg_match('/^[a-z][a-z0-9_]{0,79}$/', (string)($item['name']??'')) || trim((string)($item['label']??'')) === '') {
                    throw new \InvalidArgumentException('Resource fields and columns require safe names and labels.');
                }
                if ($field && !in_array((string)($item['type']??'text'), $allowedTypes, true)) throw new \InvalidArgumentException('Unsupported resource field type.');
                $out[] = $item;
            }
            return $out;
        };
        $routeOwner = $this->packageSlug() . '-' . substr(hash('sha256', $this->packageId), 0, 8);
        $route = '/admin/resources/' . $routeOwner . '/' . $key;
        $resourceKey = $routeOwner . '/' . $key;
        if (isset($this->adminResources[$resourceKey])) throw new \InvalidArgumentException('Admin resource is already registered.');
        $this->adminResources[$resourceKey] = [
            'key'=>$key,'label'=>trim($label),'capability'=>$capability,
            'fields'=>$normalize($fields,true),'columns'=>$normalize($columns,false),
            'list'=>$list,'find'=>$find,'save'=>$save,'delete'=>$delete,
            'reorder'=>$reorder,'bulk'=>$bulk,'bulk_actions'=>$normalizedBulkActions,
            'route'=>$route,'package'=>$this->packageId,
        ];
        $this->adminPage(trim($label), $route, $capability, $category);
    }

    /**
     * Resolve a core service (View, SettingsRepository, JobQueue, SystemMail,
     * …). Packages are trusted in-process code — this is the supported way
     * to reach shared infrastructure, not a sandbox boundary.
     */
    public function service(string $id): mixed
    {
        if ($this->serviceResolver === null) {
            throw new \RuntimeException('No service resolver bound to this context.');
        }
        return ($this->serviceResolver)($id);
    }

    private function assertContribution(string $key, string $label, ?string $capability = null, ?string $url = null): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $key)) throw new \InvalidArgumentException('Contribution keys must be stable lowercase tokens.');
        if (trim($label) === '' || mb_strlen(trim($label)) > 120) throw new \InvalidArgumentException('Contribution labels must be 1–120 characters.');
        if ($capability !== null && !preg_match('/^[a-z0-9_.:-]+$/', $capability)) throw new \InvalidArgumentException('Contribution capability is invalid.');
        if ($url !== null && (($url !== '/admin' && !str_starts_with($url, '/admin/'))
            || str_starts_with($url, '//') || mb_strlen($url) > 500)) {
            throw new \InvalidArgumentException('Contribution links must use an internal admin path.');
        }
    }

    private function packageSlug(): string
    {
        $parts = explode('/', $this->packageId, 2);
        return str_replace('-', '_', strtolower($parts[1] ?? $parts[0]));
    }

    private function navigationUrlOk(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F]/', $url)) return false;
        if (str_starts_with($url, '#')) return preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) === 1;
        if (str_starts_with($url, '/')) return !str_starts_with($url, '//');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) return filter_var($url, FILTER_VALIDATE_URL) !== false;
        if ($scheme === 'mailto') return filter_var(explode('?', substr($url, 7), 2)[0], FILTER_VALIDATE_EMAIL) !== false;
        return $scheme === 'tel' && preg_match('/^tel:\+?[0-9().,; x-]{3,40}$/i', $url) === 1;
    }

    // ---- read-only aggregations used by the core shell --------------------

    /** @return list<array> */
    public function adminNavItems(): array
    {
        return $this->adminNav;
    }

    /** @return array<string, callable> */
    public function jobHandlers(): array
    {
        return $this->jobHandlers;
    }

    /**
     * Content types declared via contentType(). Storage and the generated
     * admin CRUD live in Core\Content\ContentItems — packages read items
     * through $ctx->service(\Core\Content\ContentItems::class).
     * @return array<string, array{label:string,fields:list<array>}>
     */
    public function contentTypes(): array
    {
        return $this->contentTypes;
    }

    public function dashboardWidgets(): array { return $this->dashboardWidgets; }
    public function searchProviders(): array { return $this->searchProviders; }
    public function healthChecks(): array { return $this->healthChecks; }
    public function pageBlocks(): array { return $this->pageBlocks; }
    public function navigationMenus(): array { return $this->navigationMenus; }
    public function declaredBrandAssets(): array { return $this->brandAssets; }
    public function coreFeatures(): array { return $this->coreFeatures; }
    public function adminResources(): array { return $this->adminResources; }
}
