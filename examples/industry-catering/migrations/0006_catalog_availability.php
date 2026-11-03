<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return 'catering_0006_catalog_availability'; }
    public function description(): string { return 'Seasonal catalog windows and reusable package menu groups'; }

    public function up(Connection $db): void
    {
        foreach (['catering_menu_items', 'catering_packages'] as $table) {
            $this->addColumn($db, $table, 'available_from', 'VARCHAR(10) NULL');
            $this->addColumn($db, $table, 'available_until', 'VARCHAR(10) NULL');
        }
        $this->addColumn($db, 'catering_packages', 'menu_group_id', 'BIGINT UNSIGNED NULL');

        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timestamp = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_menu_groups') . "` (
            id {$auto},
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            available_from VARCHAR(10) NULL,
            available_until VARCHAR(10) NULL,
            visible TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp},
            UNIQUE (slug)
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_menu_group_items') . "` (
            group_id {$int} NOT NULL,
            item_id {$int} NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (group_id, item_id)
        ){$engine}");
    }

    private function addColumn(Connection $db, string $logical, string $column, string $definition): void
    {
        $table = $db->table($logical);
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, $column]
            );
        if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
};
