<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0013'; }
    public function description(): string { return 'Universal forms, submissions and consent evidence'; }

    public function up(Connection $db): void
    {
        $t = fn(string $name): string => $db->table($name);
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $tsNull = $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('forms')}` (
            id {$auto}, name VARCHAR(190) NOT NULL, fields_json TEXT NOT NULL,
            recipient VARCHAR(190) NULL, subject VARCHAR(190) NULL,
            success_message VARCHAR(500) NULL, active TINYINT NOT NULL DEFAULT 1,
            created_by {$int} NULL, created_at {$tsNow}, updated_at {$tsNull}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('form_submissions')}` (
            id {$auto}, form_id {$int} NOT NULL, data_json TEXT NOT NULL,
            consent_json TEXT NULL, ip_hash CHAR(64) NULL, status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at {$tsNow}
            " . ($sqlite ? '' : ', KEY `idx_form_submission` (form_id, status, created_at)') . "
        ){$engine}");
        if ($sqlite) $db->pdo()->exec("CREATE INDEX IF NOT EXISTS idx_form_submission ON `{$t('form_submissions')}` (form_id,status,created_at)");
    }
};
