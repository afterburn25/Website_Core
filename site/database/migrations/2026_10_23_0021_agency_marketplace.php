<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0021'; }
    public function description(): string { return 'Agency registry, marketplace catalogs and managed update history'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $now = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $time = $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $sites = $db->table('agency_sites'); $sources = $db->table('marketplace_sources'); $checks = $db->table('managed_update_checks');
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$sites}` (id {$auto},label VARCHAR(120) NOT NULL,base_url VARCHAR(500) NOT NULL UNIQUE,token_cipher TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'unknown',remote_site VARCHAR(190) NULL,core_version VARCHAR(40) NULL,industry_versions_json TEXT NULL,last_error VARCHAR(1000) NULL,last_checked_at {$time},created_at {$now}){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$sources}` (id {$auto},label VARCHAR(120) NOT NULL,catalog_url VARCHAR(500) NOT NULL UNIQUE,active TINYINT NOT NULL DEFAULT 1,catalog_json TEXT NULL,last_error VARCHAR(1000) NULL,last_checked_at {$time},created_at {$now}){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$checks}` (id {$auto},source_id {$int} NULL,version VARCHAR(40) NULL,action VARCHAR(40) NOT NULL,status VARCHAR(20) NOT NULL,detail VARCHAR(1000) NULL,created_at {$now}){$engine}");
        $items = $db->table('menu_items');
        foreach (['page_id' => $int . ' NULL', 'target' => "VARCHAR(20) NOT NULL DEFAULT 'self'"] as $column => $type) {
            $exists = $sqlite
                ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$items}`)"), 'name'), true)
                : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$items, $column]);
            if (!$exists) $db->pdo()->exec("ALTER TABLE `{$items}` ADD COLUMN `{$column}` {$type}");
        }
    }
};
