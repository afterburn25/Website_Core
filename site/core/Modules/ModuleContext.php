<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Auth\Permissions;
use Core\Branding\BrandAssets;
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
        $this->router->add($methods, $pattern, $handler, $middleware);
    }

    /**
     * Register an ADMIN route — wrapped in the same authenticated-session +
     * CSRF pipeline as every core admin screen. The handler must ALSO check
     * its own declared capability; hidden nav items are not access control.
     */
    public function adminRoute(string $methods, string $pattern, callable $handler): void
    {
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
        $this->adminNav[] = [
            'label' => $label, 'route' => $route,
            'capability' => $capability, 'package' => $this->packageId,
            'category' => $category === null || trim($category) === '' ? null : trim($category),
        ];
    }

    public function scheduledJob(string $type, callable $handler): void
    {
        $this->jobHandlers[$type] = $handler;
    }

    public function contentType(string $type, string $label, array $fields): void
    {
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
        $this->healthChecks[] = compact('key', 'label', 'provider');
    }

    /** Register an industry-specific block for the core visual composer. */
    public function pageBlock(string $type, string $label, array $fields, callable $renderer): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $type)) {
            throw new \InvalidArgumentException('Block type must use lowercase letters, numbers and underscores.');
        }
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
            if (!isset($allowed[$role]) || !is_string($path) || trim($path) === '') {
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
}
