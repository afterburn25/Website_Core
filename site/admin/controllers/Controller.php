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
        return $this->nav()->all();
    }

    /** Navigation filtered by the current user's capabilities (server-side). */
    private function visibleNav(): array
    {
        return $this->nav()->visible($this->user());
    }

    private function nav(): \Core\Admin\Nav
    {
        return new \Core\Admin\Nav(
            $this->c->has(\Core\Modules\ModuleRegistry::class) ? $this->c->get(\Core\Modules\ModuleRegistry::class) : null,
            $this->c->has(AuthService::class) ? $this->auth() : null,
        );
    }

    /** 403 helper for capability failures. */
    protected function forbidden(): Response
    {
        return Response::html('<h1>403 — Forbidden</h1>', 403);
    }
}
