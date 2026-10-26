<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/**
 * Navigation menus: named locations containing ordered, nestable items.
 * URLs are validated — internal paths, anchors, web, email and telephone
 * destinations only, never javascript: or scheme-relative tricks.
 */
final class MenuService
{
    public function __construct(private Connection $db) {}

    /** @return list<array> items in display order for a location */
    public function itemsFor(string $location, bool $includeDisabled = false): array
    {
        $items = $this->db->fetchAll(
            'SELECT i.*,p.slug AS page_slug,p.title AS page_title FROM `' . $this->db->table('menu_items') . '` i
             JOIN `' . $this->db->table('menus') . '` m ON m.id = i.menu_id
             LEFT JOIN `' . $this->db->table('pages') . '` p ON p.id = i.page_id AND p.deleted_at IS NULL
             WHERE m.`location` = ?' . ($includeDisabled ? '' : ' AND i.is_enabled = 1')
             . ' ORDER BY i.parent_id, i.sort_order, i.id',
            [$location]
        );
        foreach ($items as &$item) {
            if (!empty($item['page_id']) && !empty($item['page_slug'])) $item['url'] = '/' . $item['page_slug'];
        }
        unset($item);
        return $items;
    }

    /** @return list<array> nested tree for theme rendering */
    public function tree(string $location, bool $includeDisabled = false): array
    {
        $items = $this->itemsFor($location, $includeDisabled);
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
    public function addItem(int $menuId, string $label, string $url, ?int $parentId, int $order, string $target = 'self', ?int $pageId = null, bool $enabled = true): array
    {
        if (($error = $this->labelError($label)) !== null) return [null, $error];
        [$url, $pageError] = $this->resolveLink($pageId, $url);
        if ($pageError !== null) return [null, $pageError];
        if (!$this->urlOk($url)) {
            return [null, 'URL must be a safe internal path, anchor, web, email, or telephone link.'];
        }
        if ($parentId !== null && !$this->validParent($menuId, $parentId)) {
            return [null, 'Parent item does not belong to this menu.'];
        }
        if ($parentId !== null && $this->itemDepth($parentId) >= 5) {
            return [null, 'That parent is already at the deepest supported menu level.'];
        }
        return [(int) $this->db->insert('menu_items', [
            'menu_id' => $menuId, 'parent_id' => $parentId,
            'label' => trim($label), 'url' => $url, 'sort_order' => max(0, $order),
            'page_id' => $pageId, 'target' => $this->target($target),
            'is_enabled' => $enabled ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]), null];
    }

    /** @return array{0:bool,1:?string} */
    public function updateItem(int $id, string $label, string $url, ?int $parentId, int $order, string $target = 'self', ?int $pageId = null, bool $enabled = true): array
    {
        $item = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('menu_items') . '` WHERE id = ?', [$id]
        );
        if (!$item) return [false, 'Menu item not found.'];
        if (($error = $this->labelError($label)) !== null) return [false, $error];
        [$url, $pageError] = $this->resolveLink($pageId, $url);
        if ($pageError !== null) return [false, $pageError];
        if (!$this->urlOk($url)) return [false, 'URL must be a safe internal path, anchor, web, email, or telephone link.'];
        if ($parentId === $id) return [false, 'An item cannot be its own parent.'];
        if ($parentId !== null) {
            if (!$this->validParent((int) $item['menu_id'], $parentId)) {
                return [false, 'Parent item does not belong to this menu.'];
            }
            if ($this->itemDepth($parentId) >= 5) {
                return [false, 'That parent is already at the deepest supported menu level.'];
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
            'parent_id' => $parentId, 'sort_order' => max(0, $order),
            'page_id' => $pageId, 'target' => $this->target($target),
            'is_enabled' => $enabled ? 1 : 0,
        ], 'id = ?', [$id]);
        return [true, null];
    }

    public function deleteItem(int $id): void
    {
        $table = $this->db->table('menu_items');
        $item = $this->db->fetch("SELECT menu_id,parent_id FROM `{$table}` WHERE id = ?", [$id]);
        if (!$item) return;
        $parentId = (int) ($item['parent_id'] ?? 0);
        $parent = $parentId > 0 ? $parentId : null;
        $siblings = array_map('intval', array_column($this->db->fetchAll(
            "SELECT id FROM `{$table}` WHERE menu_id = ? AND "
            . ($parent === null ? 'parent_id IS NULL' : 'parent_id = ?')
            . ' ORDER BY sort_order,id',
            $parent === null ? [(int) $item['menu_id']] : [(int) $item['menu_id'], $parent]
        ), 'id'));
        $children = array_map('intval', array_column($this->db->fetchAll(
            "SELECT id FROM `{$table}` WHERE parent_id = ? ORDER BY sort_order,id", [$id]
        ), 'id'));

        $this->db->transaction(function () use ($table, $id, $parent, $siblings, $children): void {
            if ($children !== []) {
                $this->db->run(
                    "UPDATE `{$table}` SET parent_id = ? WHERE parent_id = ?",
                    [$parent, $id]
                );
            }
            $this->db->run("DELETE FROM `{$table}` WHERE id = ?", [$id]);
            $order = [];
            foreach ($siblings as $siblingId) {
                if ($siblingId === $id) {
                    array_push($order, ...$children);
                } else {
                    $order[] = $siblingId;
                }
            }
            foreach ($order as $position => $siblingId) {
                $this->db->update('menu_items', ['sort_order' => $position], 'id = ?', [$siblingId]);
            }
        });
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
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F]/', $url)) return false;
        if (str_starts_with($url, '#')) return preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url) === 1;
        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//'); // internal path, not scheme-relative
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }
        if ($scheme === 'mailto') {
            $address = explode('?', substr($url, 7), 2)[0];
            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }
        return $scheme === 'tel' && preg_match('/^tel:\+?[0-9().,; x-]{3,40}$/i', $url) === 1;
    }

    private function labelError(string $label): ?string
    {
        $label = trim($label);
        if ($label === '') return 'Label is required.';
        return mb_strlen($label) > 190 ? 'Label must be 190 characters or fewer.' : null;
    }

    private function validParent(int $menuId, int $parentId): bool
    {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('menu_items') . '` WHERE id = ? AND menu_id = ?',
            [$parentId, $menuId]
        );
    }

    private function itemDepth(int $id): int
    {
        $depth = 0;
        $seen = [];
        while ($id > 0 && !isset($seen[$id]) && $depth <= 6) {
            $seen[$id] = true;
            $id = (int) ($this->db->fetchColumn(
                'SELECT COALESCE(parent_id, 0) FROM `' . $this->db->table('menu_items') . '` WHERE id = ?',
                [$id]
            ) ?: 0);
            if ($id > 0) $depth++;
        }
        return $depth;
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
