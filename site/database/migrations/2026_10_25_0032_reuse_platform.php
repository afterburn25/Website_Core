<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0032'; }
    public function description(): string
    {
        return 'Reusable quote workflows, service areas, module themes and checkout validation';
    }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $big = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $time = $sqlite ? 'TEXT NOT NULL' : 'DATETIME NOT NULL';
        $nullableTime = $sqlite ? 'TEXT NULL' : 'DATETIME NULL';
        $real = $sqlite ? 'REAL' : 'DECIMAL(10,7)';
        $longText = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $attachments = $db->table('crm_quote_attachments');
        $reminders = $db->table('crm_quote_reminders');
        $documents = $db->table('crm_quote_documents');
        $cache = $db->table('service_area_cache');
        $overrides = $db->table('service_area_overrides');
        $themes = $db->table('module_theme_state');
        $validations = $db->table('commerce_validation_snapshots');

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$attachments}` (
            id {$auto}, quote_id {$big} NOT NULL, owner_package VARCHAR(190) NOT NULL,
            media_id {$big} NOT NULL, label VARCHAR(190) NOT NULL, added_by {$big} NULL,
            created_at {$time}, UNIQUE (quote_id, media_id)
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$reminders}` (
            id {$auto}, quote_id {$big} NOT NULL, owner_package VARCHAR(190) NOT NULL,
            due_at {$time}, channel VARCHAR(20) NOT NULL DEFAULT 'email',
            recipient VARCHAR(190) NOT NULL, message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', dedupe_key CHAR(64) NOT NULL UNIQUE,
            sent_at {$nullableTime}, created_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$documents}` (
            id {$auto}, quote_id {$big} NOT NULL, owner_package VARCHAR(190) NOT NULL,
            kind VARCHAR(30) NOT NULL, title VARCHAR(190) NOT NULL,
            html_snapshot {$longText} NOT NULL, sha256 CHAR(64) NOT NULL,
            created_by {$big} NULL, created_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$cache}` (
            address_hash CHAR(64) NOT NULL PRIMARY KEY, provider VARCHAR(80) NOT NULL,
            query_text VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL,
            normalized_address VARCHAR(500) NULL, latitude {$real} NULL, longitude {$real} NULL,
            precision_name VARCHAR(40) NULL, cached_at {$time}, expires_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$overrides}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, address_hash CHAR(64) NOT NULL,
            decision VARCHAR(20) NOT NULL, reason VARCHAR(500) NOT NULL,
            admin_id {$big} NOT NULL, expires_at {$nullableTime}, created_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$themes}` (
            owner_package VARCHAR(190) NOT NULL PRIMARY KEY,
            draft_variant VARCHAR(80) NULL, draft_palette VARCHAR(80) NULL,
            draft_media_json TEXT NULL,
            published_variant VARCHAR(80) NULL, published_palette VARCHAR(80) NULL,
            published_media_json TEXT NULL,
            previous_variant VARCHAR(80) NULL, previous_palette VARCHAR(80) NULL,
            previous_media_json TEXT NULL, updated_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$validations}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, order_id {$big} NULL,
            input_hash CHAR(64) NOT NULL, outcome VARCHAR(30) NOT NULL,
            errors_json TEXT NOT NULL, snapshot_json TEXT NOT NULL, final_at {$time}
        ){$engine}");

        foreach ([
            "CREATE INDEX crm_quote_attachments_owner ON `{$attachments}` (owner_package, quote_id)",
            "CREATE INDEX crm_quote_reminders_due ON `{$reminders}` (status, due_at)",
            "CREATE INDEX crm_quote_documents_owner ON `{$documents}` (owner_package, quote_id, kind)",
            "CREATE INDEX service_area_cache_expiry ON `{$cache}` (expires_at)",
            "CREATE INDEX service_area_overrides_lookup ON `{$overrides}` (owner_package, address_hash, created_at)",
            "CREATE INDEX commerce_validation_order ON `{$validations}` (owner_package, order_id, final_at)",
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent */ }
        }
    }
};
