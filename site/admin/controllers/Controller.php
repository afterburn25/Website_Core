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
        $v->share('basePath', $r->routeBase());
        $v->share('currentPath', $r->path);
        $v->share('nav', $this->visibleNav());
        $v->share('pageTitle', $title);
        $v->share('siteName', $this->settings()->get('site.name', 'Website Core'));
        $v->share('coreVersion', defined('WC_VERSION') ? WC_VERSION : 'development');
        $v->share('industryVersions', $this->industryVersions());
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
        return Response::redirect($r->routeUrl($path));
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

    /** @return list<array{name:string,version:string}> */
    private function industryVersions(): array
    {
        if (!$this->c->has(\Core\Modules\ModuleRegistry::class)) {
            return [];
        }
        $versions = [];
        foreach ($this->c->get(\Core\Modules\ModuleRegistry::class)->all() as $id => $info) {
            $manifest = $info['manifest'] ?? [];
            if (($manifest['type'] ?? null) !== 'industry' || ($info['state'] ?? null) !== 'enabled') {
                continue;
            }
            $versions[] = [
                'name' => (string) ($manifest['name'] ?? $id),
                'version' => (string) ($manifest['version'] ?? 'unknown'),
            ];
        }
        return $versions;
    }

    /** 403 helper for capability failures. */
    protected function forbidden(): Response
    {
        return Response::html('<h1>403 — Forbidden</h1>', 403);
    }

    /**
     * Error message when PHP silently discarded the entire POST body
     * (CONTENT_LENGTH > post_max_size leaves $_POST and $_FILES empty).
     */
    protected function discardedPostError(Request $r): ?string
    {
        return $r->isPost()
            && (int) ($r->server['CONTENT_LENGTH'] ?? 0) > 0
            && $r->post === [] && $r->files === []
            ? 'Upload exceeds the server post size limit (' . ini_get('post_max_size') . ').'
            : null;
    }

    /** Human-readable upload failure for $field, or null on success. */
    protected function uploadError(Request $r, string $field): ?string
    {
        $file = $r->files[$field] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            return null;
        }
        return $this->discardedPostError($r) ?? match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload exceeds the server file size limit (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_PARTIAL => 'Upload was incomplete — try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Server upload error — check the host configuration.',
            default => 'Choose a file to upload.',
        };
    }
}
