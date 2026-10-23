<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0011'; }
    public function description(): string { return 'SEO insights: aggregate public 404 paths'; }

    public function up(Connection $db): void
    {
        $table = $db->table('not_found_log');
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            id {$auto}, path_hash CHAR(64) NOT NULL UNIQUE, path VARCHAR(500) NOT NULL,
            hits BIGINT NOT NULL DEFAULT 1, last_referrer VARCHAR(500) NULL,
            first_seen {$tsNow}, last_seen {$tsNow}
        ){$engine}");
    }
};
