<?php
declare(strict_types=1);

namespace Core\Commerce;

use Core\Database\Connection;

final class ProductRepository
{
    public function __construct(private Connection $db) {}

    public function all(?string $ownerPackage = null, bool $publicOnly = false): array
    {
        $where = [];
        $params = [];
        if ($ownerPackage !== null) { $where[] = 'owner_package = ?'; $params[] = $ownerPackage; }
        if ($publicOnly) $where[] = 'active = 1';
        $sql = 'SELECT * FROM `' . $this->db->table('commerce_products') . '`'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY featured DESC, sort_order ASC, name ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('commerce_products') . '` WHERE id = ?', [$id]);
    }

    public function findActive(int $id, ?string $ownerPackage = null): ?array
    {
        $sql = 'SELECT * FROM `' . $this->db->table('commerce_products') . '` WHERE id = ? AND active = 1';
        $params = [$id];
        if ($ownerPackage !== null) { $sql .= ' AND owner_package = ?'; $params[] = $ownerPackage; }
        return $this->db->fetch($sql, $params);
    }

    public function findActiveBySlug(string $slug, string $ownerPackage): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('commerce_products')
            . '` WHERE slug=? AND owner_package=? AND active=1', [$slug,$ownerPackage]);
    }

    /** @return array{0:?int,1:?string} */
    public function save(array $input, string $ownerPackage, ?int $id = null): array
    {
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug !== '' ? $slug : $name), '-');
        $price = trim((string) ($input['price'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) return [null, 'Enter a product name up to 190 characters.'];
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/', $sku)) return [null, 'SKU must use letters, numbers, periods, dashes, or underscores.'];
        if ($slug === '' || mb_strlen($slug) > 190) return [null, 'Enter a valid product URL slug.'];
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $price)) return [null, 'Enter a valid non-negative price with no more than two decimals.'];
        $priceMinor = (int) round(((float) $price) * 100);
        $track = !empty($input['track_inventory']) ? 1 : 0;
        $stockRaw = trim((string) ($input['stock_quantity'] ?? ''));
        if ($track && !preg_match('/^\d{1,9}$/', $stockRaw)) return [null, 'Tracked inventory requires a whole-number stock quantity.'];
        $image = trim((string) ($input['image_url'] ?? ''));
        if ($image !== '' && (!$this->safeImageUrl($image) || mb_strlen($image) > 500)) return [null, 'Image URL must be an internal path or a valid HTTPS URL.'];
        $now = date('Y-m-d H:i:s');
        $currency = strtoupper((string) ($input['currency'] ?? 'USD'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) return [null, 'Currency must be a three-letter ISO code.'];
        $data = [
            'owner_package' => $ownerPackage, 'sku' => $sku, 'name' => $name, 'slug' => $slug,
            'description' => mb_substr(trim(strip_tags((string) ($input['description'] ?? ''))), 0, 5000),
            'price_minor' => $priceMinor, 'currency' => $currency,
            'image_url' => $image !== '' ? $image : null, 'track_inventory' => $track,
            'stock_quantity' => $track ? (int) $stockRaw : null, 'active' => !empty($input['active']) ? 1 : 0,
            'featured' => !empty($input['featured']) ? 1 : 0,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)), 'updated_at' => $now,
        ];
        try {
            if ($id !== null && $this->find($id)) {
                $this->db->update('commerce_products', $data, 'id = ? AND owner_package = ?', [$id, $ownerPackage]);
                return [$id, null];
            }
            $id = (int) $this->db->insert('commerce_products', $data + ['created_at' => $now]);
            return [$id, null];
        } catch (\PDOException $e) {
            return [null, str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate')
                ? 'That SKU or URL slug is already in use.' : 'The product could not be saved.'];
        }
    }

    public function setActive(int $id, string $ownerPackage, bool $active): bool
    {
        return $this->db->update('commerce_products', ['active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND owner_package = ?', [$id, $ownerPackage]) > 0;
    }

    private function safeImageUrl(string $url): bool
    {
        if (preg_match('#^index\.php\?p=/_a/modules/[A-Za-z0-9._%-]+/[A-Za-z0-9._~/%-]+$#', $url)) return true;
        if (str_starts_with($url, '/')) return !str_starts_with($url, '//');
        return str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
