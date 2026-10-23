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
        return $this->db->fetchAll(
            'SELECT i.* FROM `' . $this->db->table('menu_items') . '` i
             JOIN `' . $this->db->table('menus') . '` m ON m.id = i.menu_id
             WHERE m.`location` = ? ORDER BY i.parent_id, i.sort_order, i.id',
            [$location]
        );
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
    public function addItem(int $menuId, string $label, string $url, ?int $parentId, int $order): array
    {
        if (trim($label) === '') {
            return [null, 'Label is required.'];
        }
        if (!$this->urlOk($url)) {
            return [null, 'URL must be an internal path or http(s)/mailto link.'];
        }
        if ($parentId !== null && !$this->validParent($menuId, $parentId)) {
            return [null, 'Parent item does not belong to this menu.'];
        }
        return [(int) $this->db->insert('menu_items', [
            'menu_id' => $menuId, 'parent_id' => $parentId,
            'label' => trim($label), 'url' => $url, 'sort_order' => $order,
            'created_at' => date('Y-m-d H:i:s'),
        ]), null];
    }

    /** @return array{0:bool,1:?string} */
    public function updateItem(int $id, string $label, string $url, ?int $parentId, int $order): array
    {
        $item = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('menu_items') . '` WHERE id = ?', [$id]
        );
        if (!$item) return [false, 'Menu item not found.'];
        if (trim($label) === '') return [false, 'Label is required.'];
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
}
