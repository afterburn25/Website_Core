<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * License tracking table for DRM system.
 * Stores the license key, bound domain, and validation state.
 */
return new class extends Migration
{
    public function id(): string
    {
        return '2026_10_15_0008_license_tracking';
    }

    public function description(): string
    {
        return 'License tracking table for DRM system';
    }

    public function up(Connection $db): void
    {
        $table = $db->table('license');
        if ($db->driver() === 'sqlite') {
            $db->run("
                CREATE TABLE IF NOT EXISTS `{$table}` (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    license_key TEXT NOT NULL,
                    license_domain TEXT NOT NULL,
                    license_valid INTEGER NOT NULL DEFAULT 0,
                    license_validated_at TEXT,
                    license_deactivated INTEGER NOT NULL DEFAULT 0,
                    license_error TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(license_key)
                )
            ");
        } else {
            $db->run("
                CREATE TABLE IF NOT EXISTS `{$table}` (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    license_key VARCHAR(255) NOT NULL,
                    license_domain VARCHAR(255) NOT NULL,
                    license_valid TINYINT(1) NOT NULL DEFAULT 0,
                    license_validated_at TIMESTAMP NULL,
                    license_deactivated TINYINT(1) NOT NULL DEFAULT 0,
                    license_error TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_license_key (license_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    public function down(Connection $db): void
    {
        $db->run("DROP TABLE IF EXISTS `" . $db->table('license') . "`");
    }
};
