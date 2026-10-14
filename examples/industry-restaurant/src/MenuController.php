<?php
declare(strict_types=1);

namespace Wcref\Restaurant;

use Core\Admin\Nav;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class MenuController
{
    public function __construct(
        private ModuleContext $ctx,
        private string $ns,
    ) {}

    private function db(): Connection
    {
        return $this->ctx->service(Connection::class);
    }

    public function publicMenu(Request $r): Response
    {
        $db = $this->db();
        $cats = $db->fetchAll(
            'SELECT * FROM `' . $db->table('restaurant_categories') . '` ORDER BY sort_order, name'
        );
        $items = $db->fetchAll(
            'SELECT * FROM `' . $db->table('restaurant_items') . '` WHERE visible = 1 ORDER BY name'
        );
        $v = $this->ctx->service(View::class);
        $settings = $this->ctx->service(SettingsRepository::class);
        $v->share('basePath', $r->basePath);
        $v->share('siteName', $settings->get('site.name', 'Website Core'));
        $v->share('tagline', $settings->get('site.tagline', ''));
        $v->share('pages', []);
        return Response::html($v->renderInLayout($this->ns . ':menu', 'theme:layout', [
            'cats' => $cats, 'items' => $items,
            'page' => ['title' => 'Menu', 'meta_title' => 'Menu'],
        ]));
    }

    public function adminIndex(Request $r): Response
    {
        $forbidden = $this->guard();
        if ($forbidden) {
            return $forbidden;
        }
        $db = $this->db();
        return $this->adminPage($r, [
            'cats' => $db->fetchAll('SELECT * FROM `' . $db->table('restaurant_categories') . '` ORDER BY sort_order, name'),
            'items' => $db->fetchAll('SELECT * FROM `' . $db->table('restaurant_items') . '` ORDER BY category_id, name'),
        ]);
    }

    public function adminSave(Request $r): Response
    {
        $forbidden = $this->guard();
        if ($forbidden) {
            return $forbidden;
        }
        $db = $this->db();
        $action = $r->string('action');
        if ($action === 'add_category') {
            $name = trim($r->string('name'));
            if ($name !== '') {
                $db->insert('restaurant_categories', [
                    'name' => $name, 'sort_order' => (int) $r->string('sort_order', '0'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } elseif ($action === 'add_item') {
            $db->insert('restaurant_items', [
                'category_id' => (int) $r->string('category_id'),
                'name' => trim($r->string('name')),
                'description' => trim((string) ($r->post['description'] ?? '')),
                'price_cents' => (int) round(((float) $r->string('price', '0')) * 100),
                'visible' => $r->string('visible') !== '' ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } elseif ($action === 'delete_item') {
            $db->run('DELETE FROM `' . $db->table('restaurant_items') . '` WHERE id = ?', [(int) $r->string('id')]);
        } elseif ($action === 'delete_category') {
            $db->run('DELETE FROM `' . $db->table('restaurant_items') . '` WHERE category_id = ?', [(int) $r->string('id')]);
            $db->run('DELETE FROM `' . $db->table('restaurant_categories') . '` WHERE id = ?', [(int) $r->string('id')]);
        }
        return Response::redirect($r->basePath . '/admin/restaurant');
    }

    private function guard(): ?Response
    {
        $auth = $this->ctx->service(\Core\Auth\AuthService::class);
        $user = $auth->user();
        if ($user === null || !$auth->has($user, 'restaurant.manage')) {
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
        $v->share('pageTitle', 'Restaurant');
        $v->share('flash', null);
        return Response::html($v->renderInLayout($this->ns . ':admin-index', 'admin:layouts/admin', $data));
    }
}
