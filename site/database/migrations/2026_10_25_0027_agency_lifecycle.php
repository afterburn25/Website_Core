<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0027'; }
    public function description(): string { return 'Agency credential lifecycle and health telemetry'; }

    public function up(Connection $db): void
    {
        $table = $db->table('agency_sites');
        $sqlite = $db->driver() === 'sqlite';
        $columns = [
            'active' => 'TINYINT NOT NULL DEFAULT 1',
            'consecutive_failures' => 'INTEGER NOT NULL DEFAULT 0',
            'last_latency_ms' => 'INTEGER NULL',
            'token_updated_at' => $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL',
            'updated_at' => $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL',
        ];
        foreach ($columns as $column => $type) {
            $exists = $sqlite
                ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
                : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$table, $column]);
            if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$type}");
        }
    }
};
