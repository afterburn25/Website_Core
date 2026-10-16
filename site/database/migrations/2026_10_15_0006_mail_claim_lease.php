<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * system_messages gains claimed_at so SystemMail::flush can claim a row
 * atomically ('queued' → 'sending') before attempting transport and
 * reclaim claims stranded by a crashed worker.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_15_0006';
    }

    public function description(): string
    {
        return 'Add claimed_at lease column to system_messages for atomic mail claims';
    }

    public function up(Connection $db): void
    {
        if (!$this->hasColumn($db, 'system_messages', 'claimed_at')) {
            $ts = $db->driver() === 'sqlite' ? 'TEXT' : 'TIMESTAMP';
            $db->pdo()->exec(
                'ALTER TABLE `' . $db->table('system_messages')
                . "` ADD COLUMN `claimed_at` {$ts} NULL"
            );
        }
    }

    private function hasColumn(Connection $db, string $table, string $col): bool
    {
        if ($db->driver() === 'sqlite') {
            $rows = $db->pdo()->query("PRAGMA table_info(`{$db->table($table)}`)")->fetchAll();
            foreach ($rows as $r) {
                if ($r['name'] === $col) {
                    return true;
                }
            }
            return false;
        }
        $row = $db->fetch(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$db->table($table), $col]
        );
        return (int) ($row['n'] ?? 0) > 0;
    }
};
