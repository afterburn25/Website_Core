<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Content\MenuService;
use Core\Http\Request;
use Core\Http\Response;

final class NavigationController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'navigation.manage')) {
            return $this->forbidden();
        }
        $menus = new MenuService($this->db());
        $redirects = new \Core\Content\RedirectService($this->db());
        $location = 'primary';
        $menuId = $menus->ensureLocation($location, 'Primary navigation');

        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'add') {
                [$id, $err] = $menus->addItem(
                    $menuId,
                    $r->string('label'),
                    $r->string('url'),
                    ($p = $r->string('parent_id')) !== '' ? (int) $p : null,
                    (int) $r->string('sort_order', '0'), $r->string('target', 'self'),
                    ($pageId = $r->string('page_id')) !== '' ? (int) $pageId : null
                );
                $this->flash($err ? 'error' : 'ok', $err ?? 'Menu item added.');
            } elseif ($action === 'save') {
                [$ok, $err] = $menus->updateItem(
                    (int) $r->string('id'),
                    $r->string('label'),
                    $r->string('url'),
                    ($p = $r->string('parent_id')) !== '' ? (int) $p : null,
                    (int) $r->string('sort_order', '0'), $r->string('target', 'self'),
                    ($pageId = $r->string('page_id')) !== '' ? (int) $pageId : null
                );
                $this->flash($ok ? 'ok' : 'error', $err ?? 'Menu item updated.');
            } elseif ($action === 'reorder') {
                try {
                    $tree = json_decode((string) ($r->post['tree_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
                    [$ok, $err] = $menus->reorder($menuId, is_array($tree) ? $tree : []);
                    $this->flash($ok ? 'ok' : 'error', $err ?? 'Menu tree saved.');
                } catch (\JsonException) {
                    $this->flash('error', 'Menu tree data was invalid. Reload and try again.');
                }
            } elseif ($action === 'delete') {
                $menus->deleteItem((int) $r->string('id'));
                $this->flash('ok', 'Menu item removed.');
            } elseif ($action === 'redirect_add') {
                [$ok, $err] = $redirects->save(
                    null,
                    $r->string('source'),
                    $r->string('target'),
                    (int) $r->string('status_code', '301'),
                    $u->id
                );
                $this->flash($err ? 'error' : 'ok', $err ?? 'Redirect added.');
            } elseif ($action === 'redirect_delete') {
                $redirects->delete((int) $r->string('id'));
                $this->flash('ok', 'Redirect removed.');
            }
            return $this->redirect($r, '/admin/navigation');
        }

        $items = $menus->itemsFor($location);
        $depths = [];
        $byId = array_column($items, null, 'id');
        foreach ($items as $item) {
            $depth = 0;
            $parent = (int) ($item['parent_id'] ?? 0);
            while ($parent > 0 && isset($byId[$parent]) && $depth < 5) {
                $depth++;
                $parent = (int) ($byId[$parent]['parent_id'] ?? 0);
            }
            $depths[(int) $item['id']] = $depth;
        }
        return $this->page($r, 'navigation/index', [
            'items'     => $items,
            'tree'      => $menus->tree($location),
            'depths'    => $depths,
            'menuId'    => $menuId,
            'pages'     => $this->db()->fetchAll('SELECT id,title,slug,status FROM `' . $this->db()->table('pages') . '` WHERE deleted_at IS NULL ORDER BY title'),
            'redirects' => $redirects->all(),
        ], 'Navigation');
    }
}
