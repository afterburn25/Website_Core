<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/** Preserve form presentation choices and connect submissions to queued mail. */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_25_0025';
    }

    public function description(): string
    {
        return 'Complete forms and submissions workflow metadata';
    }

    public function up(Connection $db): void
    {
        $this->addColumn($db, 'forms', 'submit_label', "VARCHAR(80) NOT NULL DEFAULT 'Submit'");
        $this->addColumn($db, 'form_submissions', 'notification_message_id', 'BIGINT NULL DEFAULT NULL');
        $this->addColumn(
            $db,
            'form_submissions',
            'updated_at',
            $db->driver() === 'sqlite' ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL'
        );
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
