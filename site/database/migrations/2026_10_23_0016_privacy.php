<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0016'; }
    public function description(): string { return 'Privacy requests and consent-ready analytics settings'; }
    public function up(Connection $db): void
    {
        $t=$db->table('privacy_requests'); $s=$db->driver()==='sqlite';
        $auto=$s?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $now=$s?"TEXT NOT NULL DEFAULT (datetime('now'))":'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $null=$s?'TEXT NULL DEFAULT NULL':'TIMESTAMP NULL DEFAULT NULL';
        $engine=$s?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t}` (id {$auto}, request_type VARCHAR(20) NOT NULL,
            email VARCHAR(190) NOT NULL, details TEXT NULL, ip_hash CHAR(64) NULL, status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at {$now}, completed_at {$null}){$engine}");
    }
};
