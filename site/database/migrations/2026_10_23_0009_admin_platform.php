<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0009'; }

    public function description(): string { return 'Admin platform: persistent operator notices'; }

    public function up(Connection $db): void
    {
        $table = $db->table('admin_notices');
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $tsNull = $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            id {$auto}, admin_id {$int} NULL,
            kind VARCHAR(20) NOT NULL DEFAULT 'info',
            title VARCHAR(190) NOT NULL, message TEXT NULL,
            action_url VARCHAR(500) NULL, dismissed_at {$tsNull},
            expires_at {$tsNull}, created_at {$tsNow}
        ){$engine}");

        if ($sqlite) {
            $db->pdo()->exec("CREATE INDEX IF NOT EXISTS idx_admin_notices_open ON `{$table}` (admin_id, dismissed_at, expires_at)");
        } else {
            try {
                $db->pdo()->exec("CREATE INDEX idx_admin_notices_open ON `{$table}` (admin_id, dismissed_at, expires_at)");
            } catch (\Throwable) {
                // Idempotent after an interrupted migration retry.
            }
        }
    }
};
