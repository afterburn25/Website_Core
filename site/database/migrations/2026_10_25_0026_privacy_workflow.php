<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0026'; }
    public function description(): string { return 'Privacy request verification and resolution evidence'; }
    public function up(Connection $db): void
    {
        $time = $db->driver() === 'sqlite' ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $this->addColumn($db, 'verified_at', $time);
        $this->addColumn($db, 'completed_by', 'BIGINT NULL DEFAULT NULL');
        $this->addColumn($db, 'resolution_note', 'TEXT NULL');
        $this->addColumn($db, 'updated_at', $time);
    }
    private function addColumn(Connection $db, string $column, string $definition): void
    {
        $table = $db->table('privacy_requests');
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$table, $column]);
        if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
};
