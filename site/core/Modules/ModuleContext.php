<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Auth\Permissions;
use Core\Events\Dispatcher;
use Core\Routing\Router;

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

    public function __construct(
        private string $packageId,
        private Router $router,
        private Dispatcher $events,
        private ?Permissions $permissions = null,
    ) {}

    public function packageId(): string
    {
        return $this->packageId;
    }

    /** Register a public/admin route. Prefix keeps module routes namespaced. */
    public function route(string $methods, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->router->add($methods, $pattern, $handler, $middleware);
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
}
