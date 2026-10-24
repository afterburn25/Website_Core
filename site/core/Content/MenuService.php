<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/**
 * Navigation menus: named locations containing ordered, nestable items.
 * URLs are validated — internal paths and http(s)/mailto only, never
 * javascript: or scheme-relative tricks.
 */
final class MenuService
{
    public function __construct(private Connection $db) {}

    /** @return list<array> items in display order for a location */
    public function itemsFor(string $location): array
    {
        $items = $this->db->fetchAll(
            'SELECT i.*,p.slug AS page_slug,p.title AS page_title FROM `' . $this->db->table('menu_items') . '` i
             JOIN `' . $this->db->table('menus') . '` m ON m.id = i.menu_id
             LEFT JOIN `' . $this->db->table('pages') . '` p ON p.id = i.page_id AND p.deleted_at IS NULL
             WHERE m.`location` = ? ORDER BY i.parent_id, i.sort_order, i.id',
            [$location]
        );
        foreach ($items as &$item) {
            if (!empty($item['page_id']) && !empty($item['page_slug'])) $item['url'] = '/' . $item['page_slug'];
        }
        unset($item);
        return $items;
    }

    /** @return list<array> nested tree for theme rendering */
    public function tree(string $location): array
    {
        $items = $this->itemsFor($location);
        $byParent = [];
        foreach ($items as $item) {
            $byParent[(int) ($item['parent_id'] ?? 0)][] = $item;
        }
        $build = function (int $parent) use (&$build, &$byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $item) {
                $item['children'] = $build((int) $item['id']);
                $out[] = $item;
            }
            return $out;
        };
        return $build(0);
    }

    public function menuId(string $location): ?int
    {
        $id = $this->db->fetchColumn(
            'SELECT id FROM `' . $this->db->table('menus') . '` WHERE `location` = ?',
            [$location]
        );
        return $id === false ? null : (int) $id;
    }

    public function itemBelongsToMenu(int $itemId, int $menuId): bool
    {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('menu_items') . '` WHERE id = ? AND menu_id = ?',
            [$itemId, $menuId]
        );
    }

    public function ensureLocation(string $location, string $label): int
    {
        $id = $this->menuId($location);
        if ($id !== null) {
            return $id;
        }
        return (int) $this->db->insert('menus', [
            'location' => $location, 'label' => $label,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{0:?int,1:?string} */
    public function addItem(int $menuId, string $label, string $url, ?int $parentId, int $order, string $target = 'self', ?int $pageId = null): array
    {
        if (trim($label) === '') {
            return [null, 'Label is required.'];
        }
        [$url, $pageError] = $this->resolveLink($pageId, $url);
        if ($pageError !== null) return [null, $pageError];
        if (!$this->urlOk($url)) {
            return [null, 'URL must be an internal path or http(s)/mailto link.'];
        }
        if ($parentId !== null && !$this->validParent($menuId, $parentId)) {
            return [null, 'Parent item does not belong to this menu.'];
        }
        return [(int) $this->db->insert('menu_items', [
            'menu_id' => $menuId, 'parent_id' => $parentId,
            'label' => trim($label), 'url' => $url, 'sort_order' => $order,
            'page_id' => $pageId, 'target' => $this->target($target),
            'created_at' => date('Y-m-d H:i:s'),
        ]), null];
    }

    /** @return array{0:bool,1:?string} */
    public function updateItem(int $id, string $label, string $url, ?int $parentId, int $order, string $target = 'self', ?int $pageId = null): array
    {
        $item = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('menu_items') . '` WHERE id = ?', [$id]
        );
        if (!$item) return [false, 'Menu item not found.'];
        if (trim($label) === '') return [false, 'Label is required.'];
        [$url, $pageError] = $this->resolveLink($pageId, $url);
        if ($pageError !== null) return [false, $pageError];
        if (!$this->urlOk($url)) return [false, 'URL must be an internal path or http(s)/mailto link.'];
        if ($parentId === $id) return [false, 'An item cannot be its own parent.'];
        if ($parentId !== null) {
            if (!$this->validParent((int) $item['menu_id'], $parentId)) {
                return [false, 'Parent item does not belong to this menu.'];
            }
            $cursor = $parentId;
            while ($cursor > 0) {
                if ($cursor === $id) return [false, 'That submenu choice would create a loop.'];
                $cursor = (int) ($this->db->fetchColumn(
                    'SELECT COALESCE(parent_id, 0) FROM `' . $this->db->table('menu_items') . '` WHERE id = ?',
                    [$cursor]
                ) ?: 0);
            }
        }
        $this->db->update('menu_items', [
            'label' => trim($label), 'url' => trim($url),
            'parent_id' => $parentId, 'sort_order' => $order,
            'page_id' => $pageId, 'target' => $this->target($target),
        ], 'id = ?', [$id]);
        return [true, null];
    }

    public function deleteItem(int $id): void
    {
        // Re-parent children rather than orphaning them.
        $this->db->run(
            'UPDATE `' . $this->db->table('menu_items') . '` SET parent_id = NULL WHERE parent_id = ?',
            [$id]
        );
        $this->db->run('DELETE FROM `' . $this->db->table('menu_items') . '` WHERE id = ?', [$id]);
    }

    /** @return array{0:bool,1:?string} */
    public function reorder(int $menuId, array $tree): array
    {
        $valid = array_map('intval', array_column($this->db->fetchAll(
            'SELECT id FROM `' . $this->db->table('menu_items') . '` WHERE menu_id = ?', [$menuId]
        ), 'id'));
        $seen = [];
        $updates = [];
        $walk = function (array $nodes, ?int $parent, int $depth) use (&$walk, &$seen, &$updates, $valid): bool {
            if ($depth > 5) return false;
            foreach (array_values($nodes) as $order => $node) {
                if (!is_array($node) || !isset($node['id'])) return false;
                $id = (int) $node['id'];
                if (!in_array($id, $valid, true) || isset($seen[$id])) return false;
                $seen[$id] = true; $updates[] = [$id, $parent, $order];
                if (!$walk(is_array($node['children'] ?? null) ? $node['children'] : [], $id, $depth + 1)) return false;
            }
            return true;
        };
        if (!$walk($tree, null, 0) || count($seen) !== count($valid)) return [false, 'Menu tree is incomplete or invalid. Reload and try again.'];
        $this->db->transaction(function () use ($updates): void {
            foreach ($updates as [$id, $parent, $order]) $this->db->update('menu_items', ['parent_id' => $parent, 'sort_order' => $order], 'id = ?', [$id]);
        });
        return [true, null];
    }

    public function urlOk(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//'); // internal path, not scheme-relative
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private function validParent(int $menuId, int $parentId): bool
    {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('menu_items') . '` WHERE id = ? AND menu_id = ?',
            [$parentId, $menuId]
        );
    }

    /** @return array{0:string,1:?string} */
    private function resolveLink(?int $pageId, string $url): array
    {
        if ($pageId === null) return [trim($url), null];
        $page = $this->db->fetch('SELECT slug FROM `' . $this->db->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [$pageId]);
        return $page ? ['/' . $page['slug'], null] : ['', 'The selected page is no longer available.'];
    }

    private function target(string $target): string
    {
        return in_array($target, ['self', 'tab', 'window'], true) ? $target : 'self';
    }
}
