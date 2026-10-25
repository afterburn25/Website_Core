<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0029'; }
    public function description(): string { return 'Opt-in commerce catalog and order foundation'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $big = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $time = $sqlite ? 'TEXT NOT NULL' : 'DATETIME NOT NULL';
        $nullableTime = $sqlite ? 'TEXT NULL' : 'DATETIME NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $products = $db->table('commerce_products');
        $orders = $db->table('commerce_orders');
        $items = $db->table('commerce_order_items');
        $customers = $db->table('customer_accounts');
        $resets = $db->table('customer_password_resets');

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$products}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, sku VARCHAR(100) NOT NULL,
            name VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL, description TEXT NULL,
            price_minor INTEGER NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'USD',
            image_url VARCHAR(500) NULL, track_inventory INTEGER NOT NULL DEFAULT 0,
            stock_quantity INTEGER NULL, active INTEGER NOT NULL DEFAULT 1,
            featured INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0,
            created_at {$time}, updated_at {$time}, UNIQUE (owner_package, sku), UNIQUE (owner_package, slug)
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$orders}` (
            id {$auto}, order_number VARCHAR(40) NOT NULL UNIQUE, source_package VARCHAR(190) NOT NULL,
            customer_id {$big} NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'new', payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(40) NOT NULL DEFAULT 'manual', payment_reference VARCHAR(190) NULL,
            fulfillment_method VARCHAR(30) NOT NULL, customer_name VARCHAR(190) NOT NULL,
            customer_email VARCHAR(190) NOT NULL, customer_phone VARCHAR(60) NULL,
            address_json TEXT NULL, customer_notes TEXT NULL, internal_notes TEXT NULL,
            inventory_restored INTEGER NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'USD', subtotal_minor INTEGER NOT NULL,
            shipping_minor INTEGER NOT NULL DEFAULT 0, tax_minor INTEGER NOT NULL DEFAULT 0,
            total_minor INTEGER NOT NULL, placed_at {$time}, updated_at {$time}, completed_at {$nullableTime}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$items}` (
            id {$auto}, order_id {$big} NOT NULL, product_id {$big} NULL,
            sku VARCHAR(100) NOT NULL, name VARCHAR(190) NOT NULL,
            unit_price_minor INTEGER NOT NULL, quantity INTEGER NOT NULL,
            line_total_minor INTEGER NOT NULL, product_snapshot_json TEXT NULL
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$customers}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL, name VARCHAR(190) NOT NULL, phone VARCHAR(60) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active', last_login_at {$nullableTime},
            created_at {$time}, updated_at {$time}, UNIQUE (owner_package, email)
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$resets}` (
            id {$auto}, customer_id {$big} NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at {$time}, used_at {$nullableTime}, created_at {$time}
        ){$engine}");
        foreach ([
            "CREATE INDEX commerce_products_state ON `{$products}` (owner_package, active, sort_order)",
            "CREATE INDEX commerce_orders_state ON `{$orders}` (status, placed_at)",
            "CREATE INDEX commerce_orders_customer ON `{$orders}` (customer_email)",
            "CREATE INDEX commerce_order_items_order ON `{$items}` (order_id)",
            "CREATE INDEX customer_accounts_state ON `{$customers}` (owner_package, status, created_at)",
            "CREATE INDEX customer_resets_customer ON `{$resets}` (customer_id, expires_at)",
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent */ }
        }
    }
};
