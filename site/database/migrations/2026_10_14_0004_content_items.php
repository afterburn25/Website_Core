<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Generic content items for package-registered content types
 * (ctx->contentType()). Rows are schema-less field data validated at the
 * service boundary against the type's declared field set — the declared
 * fields are the contract, this table is just durable storage.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_14_0004';
    }

    public function description(): string
    {
        return 'Generic content items for package content types';
    }

    public function up(Connection $db): void
    {
        $t = fn (string $n) => $db->table($n);
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('content_items')}` (
            id {$auto},
            package VARCHAR(120) NOT NULL,
            type VARCHAR(80) NOT NULL,
            title VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            data TEXT NULL,
            sort INT NOT NULL DEFAULT 0,
            created_by {$int} NULL,
            updated_by {$int} NULL,
            created_at {$tsNow},
            updated_at {$tsNow}
        ){$engine}");
        if ($sqlite) {
            $db->pdo()->exec('CREATE INDEX IF NOT EXISTS idx_items_type ON `' . $t('content_items') . '` (package, type)');
        } else {
            try {
                $db->pdo()->exec('CREATE INDEX idx_items_type ON `' . $t('content_items') . '` (package, type)');
            } catch (\Throwable) {
                // index may already exist on re-run
            }
        }
    }
};
