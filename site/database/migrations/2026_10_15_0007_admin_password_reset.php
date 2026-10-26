<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Self-service admin password reset: a sha256 token hash + expiry on the
 * admin row. The raw token only ever travels in the emailed link —
 * the DB stores the hash so a leaked dump cannot mint resets.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_15_0007';
    }

    public function description(): string
    {
        return 'Password reset token columns on admins (hash + expiry)';
    }

    public function up(Connection $db): void
    {
        $ts = $db->driver() === 'sqlite' ? 'TEXT' : 'TIMESTAMP';
        foreach ([
            'reset_token_hash'  => 'CHAR(64) NULL',
            'reset_expires_at'  => "{$ts} NULL",
        ] as $col => $def) {
            if (!$this->hasColumn($db, 'admins', $col)) {
                $db->pdo()->exec(
                    'ALTER TABLE `' . $db->table('admins')
                    . "` ADD COLUMN `{$col}` {$def}"
                );
            }
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
