<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * M2 remainder: URL redirects (with loop protection enforced at write
 * time), reusable structured content sections, and per-page indexing /
 * canonical / OG-image controls.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_14_0003';
    }

    public function description(): string
    {
        return 'Redirects, reusable content sections, page indexing/OG columns';
    }

    public function up(Connection $db): void
    {
        $t = fn (string $n) => $db->table($n);
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('redirects')}` (
            id {$auto},
            source VARCHAR(500) NOT NULL,
            target VARCHAR(500) NOT NULL,
            status_code INT NOT NULL DEFAULT 301,
            hits {$int} NOT NULL DEFAULT 0,
            created_by {$int} NULL,
            created_at {$tsNow}
        ){$engine}");
        if ($sqlite) {
            $db->pdo()->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_redirects_source ON `{$t('redirects')}` (source)");
        } else {
            try {
                $db->pdo()->exec("CREATE UNIQUE INDEX idx_redirects_source ON `{$t('redirects')}` (source(191))");
            } catch (\Throwable) {
                // index may already exist on re-run
            }
        }

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('sections')}` (
            id {$auto},
            `key` VARCHAR(80) NOT NULL UNIQUE,
            label VARCHAR(190) NOT NULL,
            body TEXT NULL,
            updated_by {$int} NULL,
            updated_at {$tsNow}
        ){$engine}");

        // Per-page indexing controls — additive columns, safe re-run via
        // information_schema check on MySQL and sqlite pragma.
        foreach ([
            'noindex'      => 'TINYINT NOT NULL DEFAULT 0',
            'canonical'    => 'VARCHAR(500) NULL',
            'og_media_id'  => $int . ' NULL',
        ] as $col => $def) {
            if (!$this->hasColumn($db, 'pages', $col)) {
                $db->pdo()->exec("ALTER TABLE `{$t('pages')}` ADD COLUMN `{$col}` {$def}");
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
        return (bool) $db->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$db->table($table), $col]
        );
    }
};
