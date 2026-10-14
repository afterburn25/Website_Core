<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Auth\AdminUser;
use Core\Auth\AuthService;
use Core\Container;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Core\Settings\SettingsRepository;
use Core\View\View;

abstract class Controller
{
    public function __construct(protected Container $c) {}

    protected function db(): Connection
    {
        return $this->c->get(Connection::class);
    }

    protected function settings(): SettingsRepository
    {
        return $this->c->get(SettingsRepository::class);
    }

    protected function auth(): AuthService
    {
        return $this->c->get(AuthService::class);
    }

    protected function user(): ?AdminUser
    {
        return $this->auth()->user();
    }

    protected function csrf(): Csrf
    {
        return $this->c->get(Csrf::class);
    }

    /** Render an admin page inside the admin layout. */
    protected function page(Request $r, string $view, array $data = [], string $title = ''): Response
    {
        $v = $this->c->get(View::class);
        $v->share('user', $this->user());
        $v->share('csrf', $this->csrf());
        $v->share('basePath', $r->basePath);
        $v->share('currentPath', $r->path);
        $v->share('nav', $this->visibleNav());
        $v->share('pageTitle', $title);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $v->share('flash', $_SESSION['_wc_flash'] ?? null);
        unset($_SESSION['_wc_flash']);
        return Response::html($v->renderInLayout('admin:' . $view, 'admin:layouts/admin', $data));
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_wc_flash'] = ['type' => $type, 'message' => $message];
    }

    protected function redirect(Request $r, string $path): Response
    {
        return Response::redirect($r->basePath . $path);
    }

    /** Core admin navigation; module pages are merged in by the registry. */
    protected function adminNav(): array
    {
        $nav = [
            ['route' => '/admin',          'label' => 'Dashboard',    'cap' => null],
            ['route' => '/admin/pages',    'label' => 'Content',      'cap' => 'content.manage'],
            ['route' => '/admin/navigation','label' => 'Navigation',  'cap' => 'navigation.manage'],
            ['route' => '/admin/media',    'label' => 'Media',        'cap' => 'media.manage'],
            ['route' => '/admin/modules',  'label' => 'Modules',      'cap' => 'modules.view'],
            ['route' => '/admin/users',    'label' => 'Administrators','cap' => 'admins.view'],
            ['route' => '/admin/settings', 'label' => 'Settings',     'cap' => 'settings.manage'],
        ];
        if ($this->c->has(\Core\Modules\ModuleContext::class)) {
            foreach ($this->c->get(\Core\Modules\ModuleContext::class)->adminNavItems() as $item) {
                $nav[] = ['route' => $item['route'], 'label' => $item['label'], 'cap' => $item['capability']];
            }
        }
        return $nav;
    }

    /** Navigation filtered by the current user's capabilities (server-side). */
    private function visibleNav(): array
    {
        $user = $this->user();
        return array_values(array_filter(
            $this->adminNav(),
            fn (array $item) => $item['cap'] === null
                || ($user !== null && $this->auth()->has($user, (string) $item['cap']))
        ));
    }

    /** 403 helper for capability failures. */
    protected function forbidden(): Response
    {
        return Response::html('<h1>403 — Forbidden</h1>', 403);
    }
}
