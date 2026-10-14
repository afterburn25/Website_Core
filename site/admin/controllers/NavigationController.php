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
                    (int) $r->string('sort_order', '0')
                );
                $this->flash($err ? 'error' : 'ok', $err ?? 'Menu item added.');
            } elseif ($action === 'delete') {
                $menus->deleteItem((int) $r->string('id'));
                $this->flash('ok', 'Menu item removed.');
            }
            return $this->redirect($r, '/admin/navigation');
        }

        return $this->page($r, 'navigation/index', [
            'items'   => $menus->itemsFor($location),
            'menuId'  => $menuId,
        ], 'Navigation');
    }
}
