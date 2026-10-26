<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Commercial content-editor safety: optimistic locking prevents one editor
 * silently overwriting another, and revision snapshots restore the complete
 * editable page state instead of only the title/body pair.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_23_0022';
    }

    public function description(): string
    {
        return 'Conflict-safe page editing and complete revision snapshots';
    }

    public function up(Connection $db): void
    {
        if (!$this->hasColumn($db, 'pages', 'lock_version')) {
            $db->pdo()->exec(
                'ALTER TABLE `' . $db->table('pages')
                . '` ADD COLUMN lock_version INT NOT NULL DEFAULT 1'
            );
        }
        if (!$this->hasColumn($db, 'page_revisions', 'snapshot_json')) {
            $db->pdo()->exec(
                'ALTER TABLE `' . $db->table('page_revisions')
                . '` ADD COLUMN snapshot_json TEXT NULL'
            );
        }
    }

    private function hasColumn(Connection $db, string $table, string $column): bool
    {
        if ($db->driver() === 'sqlite') {
            return in_array(
                $column,
                array_column($db->fetchAll('PRAGMA table_info(`' . $db->table($table) . '`)'), 'name'),
                true
            );
        }
        return (bool) $db->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
            [$db->table($table), $column]
        );
    }
};
