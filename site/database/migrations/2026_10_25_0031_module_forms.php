<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0031'; }
    public function description(): string { return 'Package forms, submission links, validation and notification templates'; }

    public function up(Connection $db): void
    {
        $this->addColumn($db, 'forms', 'owner_package', 'VARCHAR(190) NULL DEFAULT NULL');
        $this->addColumn($db, 'forms', 'package_key', 'VARCHAR(80) NULL DEFAULT NULL');
        $this->addColumn($db, 'forms', 'notification_template', 'TEXT NULL');
        $this->addColumn($db, 'form_submissions', 'owner_package', 'VARCHAR(190) NULL DEFAULT NULL');
        $this->addColumn($db, 'form_submissions', 'dedupe_key', 'CHAR(64) NULL DEFAULT NULL');

        $forms = $db->table('forms');
        $submissions = $db->table('form_submissions');
        $links = $db->table('form_submission_links');
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $big = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $time = $sqlite ? 'TEXT NOT NULL' : 'DATETIME NOT NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$links}` (
            id {$auto}, submission_id {$big} NOT NULL, owner_package VARCHAR(190) NOT NULL,
            record_type VARCHAR(80) NOT NULL, record_id VARCHAR(190) NOT NULL, created_at {$time},
            UNIQUE (submission_id, owner_package, record_type, record_id)
        ){$engine}");

        foreach ([
            "CREATE UNIQUE INDEX forms_package_key ON `{$forms}` (owner_package, package_key)",
            "CREATE UNIQUE INDEX form_submissions_dedupe ON `{$submissions}` (dedupe_key)",
            "CREATE INDEX form_submissions_owner ON `{$submissions}` (owner_package, created_at)",
            "CREATE INDEX form_submission_links_owner ON `{$links}` (owner_package, record_type, record_id)",
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent */ }
        }
    }

    private function addColumn(Connection $db, string $logicalTable, string $column, string $definition): void
    {
        $table = $db->table($logicalTable);
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, $column]
            );
        if (!$exists) {
            $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
};
