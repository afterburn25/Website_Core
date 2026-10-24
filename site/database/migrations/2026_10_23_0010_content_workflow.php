<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0010'; }
    public function description(): string { return 'Content workflow: page hierarchy, scheduling, and trash'; }

    public function up(Connection $db): void
    {
        $table = $db->table('pages');
        $sqlite = $db->driver() === 'sqlite';
        $has = function (string $column) use ($db, $table, $sqlite): bool {
            if ($sqlite) {
                foreach ($db->fetchAll("PRAGMA table_info(`{$table}`)") as $row) {
                    if (($row['name'] ?? '') === $column) return true;
                }
                return false;
            }
            return (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
        };
        $definitions = [
            'parent_id' => $sqlite ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
            'publish_at' => $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL',
            'unpublish_at' => $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL',
            'deleted_at' => $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL',
        ];
        foreach ($definitions as $column => $definition) {
            if (!$has($column)) {
                $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
};
