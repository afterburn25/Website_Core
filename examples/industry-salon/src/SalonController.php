<?php
declare(strict_types=1);

namespace Wcref\Salon;

use Core\Admin\Nav;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class SalonController
{
    public function __construct(
        private ModuleContext $ctx,
        private string $ns,
    ) {}

    private function db(): Connection
    {
        return $this->ctx->service(Connection::class);
    }

    public function publicServices(Request $r): Response
    {
        $db = $this->db();
        $v = $this->ctx->service(View::class);
        $settings = $this->ctx->service(SettingsRepository::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $settings->get('site.name', 'Website Core'));
        $v->share('tagline', $settings->get('site.tagline', ''));
        $v->share('pages', []);
        return Response::html($v->renderInLayout($this->ns . ':services', 'theme:layout', [
            'services' => $db->fetchAll('SELECT * FROM `' . $db->table('salon_services') . '` WHERE visible = 1 ORDER BY name'),
            'staff' => $db->fetchAll('SELECT * FROM `' . $db->table('salon_staff') . '` ORDER BY name'),
            'page' => ['title' => 'Services', 'meta_title' => 'Services'],
        ]));
    }

    public function adminIndex(Request $r): Response
    {
        if ($resp = $this->guard()) {
            return $resp;
        }
        $db = $this->db();
        return $this->adminPage($r, [
            'services' => $db->fetchAll('SELECT * FROM `' . $db->table('salon_services') . '` ORDER BY name'),
            'staff' => $db->fetchAll('SELECT * FROM `' . $db->table('salon_staff') . '` ORDER BY name'),
        ]);
    }

    public function adminSave(Request $r): Response
    {
        if ($resp = $this->guard()) {
            return $resp;
        }
        $db = $this->db();
        switch ($r->string('action')) {
            case 'add_service':
                $db->insert('salon_services', [
                    'name' => trim($r->string('name')),
                    'description' => trim((string) ($r->post['description'] ?? '')),
                    'duration_minutes' => (int) $r->string('duration', '30'),
                    'price_cents' => (int) round(((float) $r->string('price', '0')) * 100),
                    'visible' => $r->string('visible') !== '' ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                break;
            case 'add_staff':
                $db->insert('salon_staff', [
                    'name' => trim($r->string('name')),
                    'title' => trim($r->string('title')),
                    'bio' => trim((string) ($r->post['bio'] ?? '')),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                break;
            case 'delete_service':
                $db->run('DELETE FROM `' . $db->table('salon_services') . '` WHERE id = ?', [(int) $r->string('id')]);
                break;
            case 'delete_staff':
                $db->run('DELETE FROM `' . $db->table('salon_staff') . '` WHERE id = ?', [(int) $r->string('id')]);
                break;
        }
        return Response::redirect($r->basePath . '/admin/salon');
    }

    private function guard(): ?Response
    {
        $auth = $this->ctx->service(\Core\Auth\AuthService::class);
        $user = $auth->user();
        if ($user === null || !$auth->has($user, 'salon.manage')) {
            return Response::html('<h1>403 — Forbidden</h1>', 403);
        }
        return null;
    }

    private function adminPage(Request $r, array $data): Response
    {
        $auth = $this->ctx->service(\Core\Auth\AuthService::class);
        $v = $this->ctx->service(View::class);
        $v->share('user', $auth->user());
        $v->share('csrf', $this->ctx->service(Csrf::class));
        $v->share('basePath', $r->basePath);
        $v->share('currentPath', $r->path);
        $v->share('nav', $this->ctx->service(Nav::class)->visible($auth->user()));
        $v->share('siteName', $this->ctx->service(SettingsRepository::class)->get('site.name', 'Website Core'));
        $v->share('pageTitle', 'Salon');
        $v->share('flash', null);
        return Response::html($v->renderInLayout($this->ns . ':admin-index', 'admin:layouts/admin', $data));
    }
}
