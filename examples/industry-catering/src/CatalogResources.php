<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Database\Connection;
use Core\Modules\ModuleContext;

/**
 * Adapts Catering's catalog records to Core 2.3's shared resource workspace.
 * Advanced pricing, dietary and package-choice fields remain in the module's
 * detailed editors; quick saves deliberately update only common catalog data.
 */
final class CatalogResources
{
    public function __construct(private Connection $db) {}

    public function register(ModuleContext $ctx): void
    {
        $categoryOptions = [];
        // Core registers providers before it runs a module's migrations on a
        // clean install. Keep registration side-effect free until the catalog
        // tables exist; the next request will expose the migrated categories.
        if ($this->db->tableExists('catering_menu_categories')) {
            foreach ($this->db->fetchAll(
                'SELECT id, name FROM `' . $this->db->table('catering_menu_categories') . '` ORDER BY sort_order, name'
            ) as $category) {
                $categoryOptions[(string) $category['id']] = (string) $category['name'];
            }
        }

        $ctx->adminResource(
            'menu-items', 'Menu quick manager', 'catering.manage',
            [
                ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => $categoryOptions],
                ['name' => 'name', 'label' => 'Dish name', 'required' => true],
                ['name' => 'visible', 'label' => 'Visible', 'type' => 'checkbox'],
            ],
            [
                ['name' => 'name', 'label' => 'Dish'],
                ['name' => 'category', 'label' => 'Category'],
                ['name' => 'status', 'label' => 'Status'],
                ['name' => 'order', 'label' => 'Order'],
            ],
            fn (string $search, int $page, int $perPage): array => $this->menuList($search, $page, $perPage),
            fn (int $id): ?array => $this->menuFind($id),
            fn (?int $id, array $data): array => $this->menuSave($id, $data),
            fn (int $id): true|string => $this->delete('catering_menu_items', $id),
            'Catering',
            [
                'icon' => 'forms',
                'reorder' => fn (array $ids): true|string => $this->reorder('catering_menu_items', $ids),
                'bulk' => fn (array $ids, string $action): true|string => $this->bulk('catering_menu_items', $ids, $action, true),
                'bulk_actions' => [
                    'show' => 'Show', 'hide' => 'Hide',
                    'feature' => 'Feature', 'unfeature' => 'Remove featured status',
                ],
            ],
        );

        $ctx->adminResource(
            'packages', 'Package quick manager', 'catering.manage',
            [
                ['name' => 'name', 'label' => 'Package name', 'required' => true],
                ['name' => 'visible', 'label' => 'Visible', 'type' => 'checkbox'],
            ],
            [
                ['name' => 'name', 'label' => 'Package'],
                ['name' => 'status', 'label' => 'Status'],
                ['name' => 'pricing', 'label' => 'Pricing'],
                ['name' => 'order', 'label' => 'Order'],
            ],
            fn (string $search, int $page, int $perPage): array => $this->packageList($search, $page, $perPage),
            fn (int $id): ?array => $this->packageFind($id),
            fn (?int $id, array $data): array => $this->packageSave($id, $data),
            fn (int $id): true|string => $this->delete('catering_packages', $id),
            'Catering',
            [
                'icon' => 'marketplace',
                'reorder' => fn (array $ids): true|string => $this->reorder('catering_packages', $ids),
                'bulk' => fn (array $ids, string $action): true|string => $this->bulk('catering_packages', $ids, $action, true),
                'bulk_actions' => [
                    'show' => 'Show', 'hide' => 'Hide',
                    'feature' => 'Feature', 'unfeature' => 'Remove featured status',
                ],
            ],
        );
    }

    public function menuList(string $search, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE i.name LIKE ? OR c.name LIKE ?';
            $params = ['%' . $search . '%', '%' . $search . '%'];
        }
        $total = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_menu_items') . '` i INNER JOIN `'
            . $this->db->table('catering_menu_categories') . '` c ON c.id = i.category_id' . $where,
            $params
        );
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            'SELECT i.id, i.name, i.category_id, i.visible, i.featured, i.sort_order, i.available_from, i.available_until, c.name AS category FROM `'
            . $this->db->table('catering_menu_items') . '` i INNER JOIN `'
            . $this->db->table('catering_menu_categories') . '` c ON c.id = i.category_id' . $where
            . ' ORDER BY i.sort_order, i.name, i.id LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        foreach ($rows as &$row) {
            $row['status'] = ((int) $row['visible'] === 1 ? 'Visible' : 'Hidden')
                . ((int) $row['featured'] === 1 ? ' · Featured' : '') . $this->availabilityStatus($row);
            $row['order'] = (string) (int) $row['sort_order'];
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total];
    }

    public function menuFind(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT id, category_id, name, visible FROM `' . $this->db->table('catering_menu_items') . '` WHERE id = ?',
            [$id]
        );
    }

    public function menuSave(?int $id, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) return [null, 'Dish name must be 1–190 characters.'];
        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($categoryId === false || !$this->exists('catering_menu_categories', (int) $categoryId)) {
            return [null, 'Choose an existing menu category.'];
        }
        if ($id !== null && !$this->exists('catering_menu_items', $id)) return [null, 'Dish not found.'];
        $values = ['category_id' => (int) $categoryId, 'name' => $name, 'visible' => !empty($data['visible']) ? 1 : 0];
        if ($id !== null) {
            $this->db->update('catering_menu_items', $values, 'id = ?', [$id]);
            return [$id, null];
        }
        $id = (int) $this->db->insert('catering_menu_items', $values + [
            'slug' => $this->uniqueSlug('catering_menu_items', $name),
            'description' => '', 'price_cents' => 0, 'price_unit' => 'team confirmation required',
            'dietary' => '', 'featured' => 0, 'sort_order' => $this->nextOrder('catering_menu_items'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function packageList(string $search, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = $search === '' ? '' : ' WHERE name LIKE ?';
        $params = $search === '' ? [] : ['%' . $search . '%'];
        $total = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('catering_packages') . '`' . $where,
            $params
        );
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            'SELECT id, name, visible, featured, pricing_basis, sort_order, available_from, available_until FROM `'
            . $this->db->table('catering_packages') . '`' . $where
            . ' ORDER BY sort_order, name, id LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        foreach ($rows as &$row) {
            $row['status'] = ((int) $row['visible'] === 1 ? 'Visible' : 'Hidden')
                . ((int) $row['featured'] === 1 ? ' · Featured' : '') . $this->availabilityStatus($row);
            $row['pricing'] = $row['pricing_basis'] === 'manual' ? 'Team confirmation' : str_replace('_', ' ', (string) $row['pricing_basis']);
            $row['order'] = (string) (int) $row['sort_order'];
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total];
    }

    public function packageFind(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT id, name, visible FROM `' . $this->db->table('catering_packages') . '` WHERE id = ?',
            [$id]
        );
    }

    public function packageSave(?int $id, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) return [null, 'Package name must be 1–190 characters.'];
        if ($id !== null && !$this->exists('catering_packages', $id)) return [null, 'Package not found.'];
        $values = ['name' => $name, 'visible' => !empty($data['visible']) ? 1 : 0];
        if ($id !== null) {
            $this->db->update('catering_packages', $values, 'id = ?', [$id]);
            return [$id, null];
        }
        $id = (int) $this->db->insert('catering_packages', $values + [
            'slug' => $this->uniqueSlug('catering_packages', $name),
            'eyebrow' => '', 'description' => '', 'price_cents' => 0,
            'price_note' => 'team confirmation required', 'guest_min' => 1,
            'guest_max' => null, 'inclusions' => '', 'featured' => 0,
            'sort_order' => $this->nextOrder('catering_packages'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function reorder(string $table, array $submitted): true|string
    {
        if (!in_array($table, ['catering_menu_items', 'catering_packages'], true)) return 'Unsupported catalog resource.';
        [$ids, $error] = $this->ids($table, $submitted);
        if ($error !== null) return $error;
        $all = array_map('intval', array_column($this->db->fetchAll(
            'SELECT id FROM `' . $this->db->table($table) . '` ORDER BY sort_order, name, id'
        ), 'id'));
        $selected = array_fill_keys($ids, true);
        $slots = [];
        foreach ($all as $index => $current) if (isset($selected[$current])) $slots[] = $index;
        foreach ($slots as $index => $slot) $all[$slot] = $ids[$index];
        $this->db->transaction(function () use ($table, $all): void {
            foreach ($all as $index => $id) $this->db->update($table, ['sort_order' => ($index + 1) * 10], 'id = ?', [$id]);
        });
        return true;
    }

    public function bulk(string $table, array $submitted, string $action, bool $featured): true|string
    {
        if (!in_array($table, ['catering_menu_items', 'catering_packages'], true)) return 'Unsupported catalog resource.';
        $actions = ['show' => ['visible', 1], 'hide' => ['visible', 0]];
        if ($featured) $actions += ['feature' => ['featured', 1], 'unfeature' => ['featured', 0]];
        if (!isset($actions[$action])) return 'Choose a supported bulk action.';
        [$ids, $error] = $this->ids($table, $submitted);
        if ($error !== null) return $error;
        [$column, $value] = $actions[$action];
        $this->db->transaction(function () use ($table, $ids, $column, $value): void {
            foreach ($ids as $id) $this->db->update($table, [$column => $value], 'id = ?', [$id]);
        });
        return true;
    }

    public function delete(string $table, int $id): true|string
    {
        if (!in_array($table, ['catering_menu_items', 'catering_packages'], true)) return 'Unsupported catalog resource.';
        if (!$this->exists($table, $id)) return 'Record not found.';
        $this->db->transaction(function () use ($table, $id): void {
            if ($table === 'catering_menu_items' && $this->db->tableExists('catering_menu_group_items')) {
                $this->db->run('DELETE FROM `' . $this->db->table('catering_menu_group_items') . '` WHERE item_id = ?', [$id]);
            }
            $this->db->run('DELETE FROM `' . $this->db->table($table) . '` WHERE id = ?', [$id]);
        });
        return true;
    }

    private function ids(string $table, array $submitted): array
    {
        if ($submitted === [] || count($submitted) > 100) return [[], 'Choose 1–100 catalog records.'];
        $ids = [];
        foreach ($submitted as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || isset($ids[(int) $id])) return [[], 'Choose unique existing catalog records.'];
            $ids[(int) $id] = (int) $id;
        }
        foreach ($ids as $id) if (!$this->exists($table, $id)) return [[], 'Choose unique existing catalog records.'];
        return [array_values($ids), null];
    }

    private function exists(string $table, int $id): bool
    {
        return (bool) $this->db->fetchColumn('SELECT id FROM `' . $this->db->table($table) . '` WHERE id = ?', [$id]);
    }

    private function nextOrder(string $table): int
    {
        return (int) $this->db->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `' . $this->db->table($table) . '`');
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'item';
        $candidate = $slug;
        for ($suffix = 2; $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table($table) . '` WHERE slug = ?', [$candidate]
        ); $suffix++) $candidate = $slug . '-' . $suffix;
        return $candidate;
    }

    private function availabilityStatus(array $row): string
    {
        $today = date('Y-m-d');
        if (!empty($row['available_from']) && (string) $row['available_from'] > $today) return ' · Scheduled';
        if (!empty($row['available_until']) && (string) $row['available_until'] < $today) return ' · Expired';
        return '';
    }
}
