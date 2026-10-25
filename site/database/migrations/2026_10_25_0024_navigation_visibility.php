<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/** Allow administrators to stage or temporarily hide individual navigation links. */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_25_0024';
    }

    public function description(): string
    {
        return 'Add per-item navigation visibility';
    }

    public function up(Connection $db): void
    {
        $table = $db->table('menu_items');
        $exists = $db->driver() === 'sqlite'
            ? in_array('is_enabled', array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, 'is_enabled']
            );
        if (!$exists) {
            $db->pdo()->exec(
                "ALTER TABLE `{$table}` ADD COLUMN is_enabled TINYINT NOT NULL DEFAULT 1"
            );
        }
    }
};
