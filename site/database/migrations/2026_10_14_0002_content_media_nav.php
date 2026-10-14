<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * M2 schema: media library, navigation menus, preview tokens.
 * sqlite branch is dev/test-only (COMPATIBILITY.md).
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_14_0002';
    }

    public function description(): string
    {
        return 'Content/media/navigation: media, media_folders, menus, menu_items, preview_tokens';
    }

    public function up(Connection $db): void
    {
        $t = fn (string $n) => $db->table($n);
        $sqlite = $db->driver() === 'sqlite';

        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tsNull = $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('media')}` (
            id {$auto},
            folder VARCHAR(190) NOT NULL DEFAULT '',
            filename VARCHAR(190) NOT NULL,
            original_name VARCHAR(190) NOT NULL,
            mime VARCHAR(100) NOT NULL,
            size_bytes {$int} NOT NULL,
            width INT NULL,
            height INT NULL,
            alt_text VARCHAR(300) NULL,
            caption VARCHAR(300) NULL,
            is_private TINYINT NOT NULL DEFAULT 0,
            uploaded_by {$int} NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('media_usage')}` (
            id {$auto},
            media_id {$int} NOT NULL,
            context VARCHAR(80) NOT NULL,
            context_id {$int} NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('menus')}` (
            id {$auto},
            `location` VARCHAR(60) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('menu_items')}` (
            id {$auto},
            menu_id {$int} NOT NULL,
            parent_id {$int} NULL,
            label VARCHAR(190) NOT NULL,
            url VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('preview_tokens')}` (
            id {$auto},
            token_hash CHAR(64) NOT NULL UNIQUE,
            page_id {$int} NOT NULL,
            created_by {$int} NOT NULL,
            expires_at {$tsNull},
            used_at {$tsNull},
            created_at {$tsNow}
        ){$engine}");

        // Seed the primary menu location.
        $db->pdo()->exec(
            'INSERT INTO `' . $t('menus') . "` (`location`, label, created_at)
             SELECT 'primary', 'Primary navigation', " . ($sqlite ? "datetime('now')" : 'NOW()')
            . ($sqlite ? " WHERE NOT EXISTS (SELECT 1 FROM `{$t('menus')}` WHERE `location`='primary')"
                       : " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `{$t('menus')}` WHERE `location`='primary')")
        );
    }
};
