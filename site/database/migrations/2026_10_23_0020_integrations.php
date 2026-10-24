<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0020'; }
    public function description(): string { return 'API keys, webhooks and delivery history'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $now = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $nullableTime = $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $keys = $db->table('api_keys');
        $hooks = $db->table('webhooks');
        $deliveries = $db->table('webhook_deliveries');
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$keys}` (id {$auto},label VARCHAR(120) NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,token_prefix VARCHAR(20) NOT NULL,capabilities_json TEXT NOT NULL,last_used_at {$nullableTime},revoked_at {$nullableTime},created_at {$now}){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$hooks}` (id {$auto},label VARCHAR(120) NOT NULL,url VARCHAR(500) NOT NULL,events_json TEXT NOT NULL,secret_hash CHAR(64) NOT NULL,secret_cipher TEXT NOT NULL,active TINYINT NOT NULL DEFAULT 1,created_at {$now}){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$deliveries}` (id {$auto},webhook_id {$int} NOT NULL,event VARCHAR(120) NOT NULL,payload_json TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',http_status INTEGER NULL,last_error VARCHAR(1000) NULL,attempted_at {$nullableTime},created_at {$now}){$engine}");
    }
};
