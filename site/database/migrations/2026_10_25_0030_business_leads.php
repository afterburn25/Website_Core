<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0030'; }
    public function description(): string { return 'Shared business profile, inquiries, and quotations'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $big = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $time = $sqlite ? 'TEXT NOT NULL' : 'DATETIME NOT NULL';
        $nullableTime = $sqlite ? 'TEXT NULL' : 'DATETIME NULL';
        $date = $sqlite ? 'TEXT NULL' : 'DATE NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $inquiries = $db->table('crm_inquiries');
        $notes = $db->table('crm_inquiry_notes');
        $quotes = $db->table('crm_quotes');
        $items = $db->table('crm_quote_items');

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$inquiries}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, source_submission_id {$big} NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'new', priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            customer_name VARCHAR(190) NOT NULL, customer_email VARCHAR(190) NULL,
            customer_phone VARCHAR(60) NULL, subject VARCHAR(190) NOT NULL, message TEXT NOT NULL,
            assigned_admin_id {$big} NULL, created_at {$time}, updated_at {$time},
            UNIQUE (owner_package, source_submission_id)
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$notes}` (
            id {$auto}, inquiry_id {$big} NOT NULL, admin_id {$big} NOT NULL,
            body TEXT NOT NULL, created_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$quotes}` (
            id {$auto}, owner_package VARCHAR(190) NOT NULL, inquiry_id {$big} NULL,
            quote_number VARCHAR(40) NOT NULL UNIQUE, status VARCHAR(30) NOT NULL DEFAULT 'draft',
            customer_name VARCHAR(190) NOT NULL, customer_email VARCHAR(190) NOT NULL,
            customer_phone VARCHAR(60) NULL, currency CHAR(3) NOT NULL DEFAULT 'USD',
            subtotal_minor INTEGER NOT NULL, discount_minor INTEGER NOT NULL DEFAULT 0,
            tax_minor INTEGER NOT NULL DEFAULT 0, total_minor INTEGER NOT NULL,
            valid_until {$date}, customer_notes TEXT NULL, internal_notes TEXT NULL,
            acceptance_token_hash CHAR(64) NULL UNIQUE, accepted_at {$nullableTime},
            created_at {$time}, updated_at {$time}
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$items}` (
            id {$auto}, quote_id {$big} NOT NULL, description VARCHAR(500) NOT NULL,
            quantity INTEGER NOT NULL, unit_price_minor INTEGER NOT NULL,
            line_total_minor INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0
        ){$engine}");
        foreach ([
            "CREATE INDEX crm_inquiries_queue ON `{$inquiries}` (owner_package, status, priority, created_at)",
            "CREATE INDEX crm_inquiry_notes_parent ON `{$notes}` (inquiry_id, created_at)",
            "CREATE INDEX crm_quotes_owner ON `{$quotes}` (owner_package, status, created_at)",
            "CREATE INDEX crm_quotes_inquiry ON `{$quotes}` (inquiry_id)",
            "CREATE INDEX crm_quote_items_parent ON `{$items}` (quote_id, sort_order)",
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent */ }
        }
    }
};
