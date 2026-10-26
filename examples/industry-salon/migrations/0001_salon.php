<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'salon_0001_schema';
    }

    public function description(): string
    {
        return 'Salon services + staff tables';
    }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('salon_services') . "` (
            id {$auto},
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            duration_minutes INT NOT NULL DEFAULT 30,
            price_cents INT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('salon_staff') . "` (
            id {$auto},
            name VARCHAR(190) NOT NULL,
            title VARCHAR(190) NULL,
            bio TEXT NULL,
            created_at {$tsNow}
        ){$engine}");
    }
};
