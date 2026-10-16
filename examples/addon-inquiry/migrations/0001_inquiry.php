<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Inquiry add-on schema — module-owned table named after the package
 * (wcref/inquiry → wc_inquiry_*) so uninstall preserves it and purge can
 * drop exactly this package's data.
 */
return new class extends Migration {
    public function id(): string
    {
        return 'inquiry_0001_submissions';
    }

    public function description(): string
    {
        return 'Create inquiry_submissions table';
    }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('inquiry_submissions') . "` (
            id {$auto},
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            ip VARCHAR(45) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at {$tsNow}
        ){$engine}");
    }
};
