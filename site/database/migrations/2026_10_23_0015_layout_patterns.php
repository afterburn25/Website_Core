<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0015'; }
    public function description(): string { return 'Reusable page layout patterns'; }
    public function up(Connection $db): void
    {
        $t = $db->table('page_patterns'); $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $now = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t}` (
            id {$auto}, name VARCHAR(190) NOT NULL, blocks_json TEXT NOT NULL,
            created_by {$int} NULL, created_at {$now}, updated_at {$now}
        ){$engine}");
    }
};
