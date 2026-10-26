<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'restaurant_0001_menus';
    }

    public function description(): string
    {
        return 'Restaurant menu categories + items';
    }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('restaurant_categories') . "` (
            id {$auto},
            name VARCHAR(190) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('restaurant_items') . "` (
            id {$auto},
            category_id {$int} NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            price_cents INT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1,
            created_at {$tsNow}
        ){$engine}");
    }
};
