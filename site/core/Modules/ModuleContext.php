<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Auth\Permissions;
use Core\Events\Dispatcher;
use Core\Routing\Router;
use Core\View\View;

/**
 * The supported extension API surface (version WC_API_VERSION). Packages
 * receive this in their provider's register() and may ONLY interact with
 * core through it — no reaching into other modules' tables or core internals.
 */
final class ModuleContext
{
    /** @var list<array{label:string,route:string,capability:string,package:string}> */
    private array $adminNav = [];
    /** @var array<string, callable> */
    private array $jobHandlers = [];
    /** @var array<string, array{label:string,fields:list<array>}> */
    private array $contentTypes = [];

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
    ) {}

    private function themesRoot(): string
    {
        return $this->themesRoot ?? (defined('WC_THEMES') ? WC_THEMES : 'themes');
    }

    public function packageId(): string
    {
        return $this->packageId;
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

    public function adminPage(string $label, string $route, string $capability): void
    {
        $this->adminNav[] = [
            'label' => $label, 'route' => $route,
            'capability' => $capability, 'package' => $this->packageId,
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
}
