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
        $location = in_array($r->string('location', 'primary'), ['primary', 'footer'], true)
            ? $r->string('location', 'primary') : 'primary';
        $locationMeta = [
            'primary' => ['label' => 'Header menu', 'menu_label' => 'Primary navigation'],
            'footer' => ['label' => 'Footer menu', 'menu_label' => 'Footer navigation'],
        ];
        $menuId = $menus->ensureLocation($location, $locationMeta[$location]['menu_label']);

        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'add') {
                [$id, $err] = $menus->addItem(
                    $menuId,
                    $r->string('label'),
                    $r->string('url'),
                    ($p = $r->string('parent_id')) !== '' ? (int) $p : null,
                    (int) $r->string('sort_order', '0'), $r->string('target', 'self'),
                    ($pageId = $r->string('page_id')) !== '' ? (int) $pageId : null,
                    $r->string('is_enabled') === '1'
                );
                if (!$err) $this->c->get(\Core\Audit\AuditLog::class)->record('navigation.item_added', $u->id, ['menu' => $location, 'item' => $id]);
                $this->flash($err ? 'error' : 'ok', $err ?? 'Menu item added.');
            } elseif ($action === 'save') {
                $itemId = (int) $r->string('id');
                if (!$menus->itemBelongsToMenu($itemId, $menuId)) {
                    $this->flash('error', 'That link does not belong to the selected menu.');
                    return $this->redirect($r, '/admin/navigation?location=' . rawurlencode($location));
                }
                [$ok, $err] = $menus->updateItem(
                    $itemId,
                    $r->string('label'),
                    $r->string('url'),
                    ($p = $r->string('parent_id')) !== '' ? (int) $p : null,
                    (int) $r->string('sort_order', '0'), $r->string('target', 'self'),
                    ($pageId = $r->string('page_id')) !== '' ? (int) $pageId : null,
                    $r->string('is_enabled') === '1'
                );
                if ($ok) $this->c->get(\Core\Audit\AuditLog::class)->record('navigation.item_updated', $u->id, ['menu' => $location, 'item' => $itemId]);
                $this->flash($ok ? 'ok' : 'error', $err ?? 'Menu item updated.');
            } elseif ($action === 'reorder') {
                try {
                    $tree = json_decode((string) ($r->post['tree_json'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
                    [$ok, $err] = $menus->reorder($menuId, is_array($tree) ? $tree : []);
                    if ($ok) $this->c->get(\Core\Audit\AuditLog::class)->record('navigation.reordered', $u->id, ['menu' => $location]);
                    $this->flash($ok ? 'ok' : 'error', $err ?? 'Menu tree saved.');
                } catch (\JsonException) {
                    $this->flash('error', 'Menu tree data was invalid. Reload and try again.');
                }
            } elseif ($action === 'delete') {
                $itemId = (int) $r->string('id');
                if ($menus->itemBelongsToMenu($itemId, $menuId)) {
                    $menus->deleteItem($itemId);
                    $this->c->get(\Core\Audit\AuditLog::class)->record('navigation.item_deleted', $u->id, ['menu' => $location, 'item' => $itemId]);
                    $this->flash('ok', 'Menu item removed.');
                } else {
                    $this->flash('error', 'That link does not belong to the selected menu.');
                }
            } elseif (in_array($action, ['redirect_add', 'redirect_save'], true)) {
                $redirectId = (int) $r->string('id');
                [$ok, $err] = $redirects->save(
                    $redirectId > 0 ? $redirectId : null,
                    $r->string('source'),
                    $r->string('target'),
                    (int) $r->string('status_code', '301'),
                    $u->id
                );
                if ($ok) $this->c->get(\Core\Audit\AuditLog::class)->record(
                    $redirectId > 0 ? 'redirect.updated' : 'redirect.created',
                    $u->id,
                    ['redirect' => $redirectId ?: null, 'source' => $r->string('source')]
                );
                $this->flash($err ? 'error' : 'ok', $err ?? ($redirectId > 0 ? 'Redirect updated.' : 'Redirect added.'));
            } elseif ($action === 'redirect_delete') {
                $redirectId = (int) $r->string('id');
                $redirects->delete($redirectId);
                $this->c->get(\Core\Audit\AuditLog::class)->record('redirect.deleted', $u->id, ['redirect' => $redirectId]);
                $this->flash('ok', 'Redirect removed.');
            }
            return $this->redirect($r, '/admin/navigation?location=' . rawurlencode($location));
        }

        $items = $menus->itemsFor($location, true);
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
        foreach ($locationMeta as $key => &$meta) {
            $locationItems = $key === $location ? $items : $menus->itemsFor($key, true);
            $meta['count'] = count($locationItems);
            $meta['hidden'] = count(array_filter(
                $locationItems,
                static fn (array $item): bool => (int) ($item['is_enabled'] ?? 1) !== 1
            ));
        }
        unset($meta);
        return $this->page($r, 'navigation/index', [
            'items'     => $items,
            'tree'      => $menus->tree($location, true),
            'depths'    => $depths,
            'menuId'    => $menuId,
            'location'  => $location,
            'locationMeta' => $locationMeta,
            'pages'     => $this->db()->fetchAll('SELECT id,title,slug,status FROM `' . $this->db()->table('pages') . '` WHERE deleted_at IS NULL ORDER BY title'),
            'redirects' => $redirects->all(),
        ], 'Navigation Menu');
    }
}
