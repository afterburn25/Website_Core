<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Core schema for the universal core: identities, sessions, recovery codes,
 * settings, pages, package registry + journal, jobs/outbox, audit, rate
 * limits, and transactional system messages.
 *
 * The sqlite branch exists ONLY for the development/test harness; production
 * and CI acceptance run against MySQL/MariaDB. See COMPATIBILITY.md.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_14_0001';
    }

    public function description(): string
    {
        return 'Create universal core schema (identities, settings, pages, packages, jobs, audit)';
    }

    public function up(Connection $db): void
    {
        $t = fn (string $n) => $db->table($n);
        $sqlite = $db->driver() === 'sqlite';

        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $text = 'TEXT';
        $now = 'CURRENT_TIMESTAMP';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        // Nullable timestamp (revoked_at, used_at, ...) vs created-at default.
        $tsNull = $sqlite ? 'TEXT NULL DEFAULT NULL' : 'TIMESTAMP NULL DEFAULT NULL';
        $tsNow = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('admins')}` (
            id {$auto},
            username VARCHAR(60) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            mfa_enabled TINYINT NOT NULL DEFAULT 0,
            mfa_secret VARCHAR(64) NULL,
            mfa_last_counter BIGINT NOT NULL DEFAULT -1,
            disabled TINYINT NOT NULL DEFAULT 0,
            last_login_at {$tsNull},
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('admin_sessions')}` (
            id {$auto},
            admin_id {$int} NOT NULL,
            session_hash CHAR(64) NOT NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(300) NULL,
            created_at {$tsNow},
            last_seen {$tsNull},
            revoked_at {$tsNull}
            " . ($sqlite ? '' : ', KEY `idx_admin_session` (admin_id, session_hash)') . "
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('recovery_codes')}` (
            id {$auto},
            admin_id {$int} NOT NULL,
            code_hash CHAR(64) NOT NULL,
            used_at {$tsNull},
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('settings')}` (
            `key` VARCHAR(120) NOT NULL PRIMARY KEY,
            `value` {$text} NULL,
            updated_at {$tsNull}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('pages')}` (
            id {$auto},
            slug VARCHAR(190) NOT NULL UNIQUE,
            title VARCHAR(190) NOT NULL,
            body {$text} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            meta_title VARCHAR(190) NULL,
            meta_description VARCHAR(300) NULL,
            updated_by {$int} NULL,
            published_at {$tsNull},
            created_at {$tsNow},
            updated_at {$tsNull}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('page_revisions')}` (
            id {$auto},
            page_id {$int} NOT NULL,
            title VARCHAR(190) NOT NULL,
            body {$text} NULL,
            saved_by {$int} NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('packages')}` (
            package_id VARCHAR(190) NOT NULL PRIMARY KEY,
            state VARCHAR(20) NOT NULL DEFAULT 'staged',
            version VARCHAR(40) NULL,
            manifest {$text} NULL,
            updated_at {$tsNull}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('package_journal')}` (
            id {$auto},
            package_id VARCHAR(190) NOT NULL,
            operation VARCHAR(40) NOT NULL,
            result VARCHAR(20) NOT NULL,
            detail {$text} NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('jobs')}` (
            id {$auto},
            `type` VARCHAR(80) NOT NULL,
            payload {$text} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            run_after BIGINT NOT NULL DEFAULT 0,
            leased_by VARCHAR(120) NULL,
            leased_until BIGINT NULL,
            last_error {$text} NULL,
            created_at {$tsNow},
            finished_at {$tsNull}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('audit_log')}` (
            id {$auto},
            event VARCHAR(80) NOT NULL,
            actor_id {$int} NULL,
            context {$text} NULL,
            ip VARCHAR(45) NULL,
            created_at {$tsNow}
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('rate_limits')}` (
            bucket CHAR(64) NOT NULL PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 0,
            first_attempt BIGINT NOT NULL,
            last_attempt BIGINT NOT NULL
        ){$engine}");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t('system_messages')}` (
            id {$auto},
            `to` VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            body {$text} NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            attempts INT NOT NULL DEFAULT 0,
            last_error {$text} NULL,
            dedupe_key CHAR(64) NULL,
            created_at {$tsNow},
            sent_at {$tsNull}
        ){$engine}");
    }
};
