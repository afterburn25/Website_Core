<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_25_0028'; }
    public function description(): string { return 'Privacy-first first-party analytics'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $big = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $time = $sqlite ? 'TEXT NOT NULL' : 'DATETIME NOT NULL';
        $nullableTime = $sqlite ? 'TEXT NULL' : 'DATETIME NULL';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $visits = $db->table('analytics_visits');
        $views = $db->table('analytics_pageviews');
        $bots = $db->table('analytics_crawlers');
        $search = $db->table('analytics_search_console');

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$visits}` (
            id {$auto}, visit_key CHAR(64) NOT NULL UNIQUE, anonymous_hash CHAR(64) NOT NULL,
            started_at {$time}, last_seen_at {$time}, completed_at {$nullableTime},
            page_count INTEGER NOT NULL DEFAULT 0, active_seconds INTEGER NOT NULL DEFAULT 0,
            channel VARCHAR(40) NOT NULL DEFAULT 'Direct', referrer_domain VARCHAR(255) NULL,
            search_term VARCHAR(255) NULL, utm_source VARCHAR(120) NULL, utm_medium VARCHAR(120) NULL,
            utm_campaign VARCHAR(180) NULL, browser_family VARCHAR(80) NOT NULL DEFAULT 'Unknown',
            os_family VARCHAR(80) NOT NULL DEFAULT 'Unknown', device_family VARCHAR(40) NOT NULL DEFAULT 'Desktop',
            country_code CHAR(2) NULL, country_name VARCHAR(120) NULL, region_name VARCHAR(160) NULL,
            city_name VARCHAR(160) NULL
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$views}` (
            id {$auto}, visit_id {$big} NOT NULL, page_path VARCHAR(500) NOT NULL,
            page_title VARCHAR(255) NULL, token_hash CHAR(64) NOT NULL UNIQUE,
            viewed_at {$time}, last_seen_at {$time}, active_seconds INTEGER NOT NULL DEFAULT 0
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$bots}` (
            id {$auto}, crawler_family VARCHAR(100) NOT NULL, page_path VARCHAR(500) NOT NULL,
            hit_date VARCHAR(10) NOT NULL, hits INTEGER NOT NULL DEFAULT 1, last_seen_at {$time},
            UNIQUE (crawler_family, page_path, hit_date)
        ){$engine}");
        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$search}` (
            id {$auto}, query_text VARCHAR(500) NOT NULL, clicks INTEGER NOT NULL DEFAULT 0,
            impressions INTEGER NOT NULL DEFAULT 0, ctr DECIMAL(9,6) NOT NULL DEFAULT 0,
            average_position DECIMAL(9,3) NOT NULL DEFAULT 0, range_start VARCHAR(10) NULL,
            range_end VARCHAR(10) NULL, imported_at {$time}
        ){$engine}");

        foreach ([
            "CREATE INDEX analytics_visits_started ON `{$visits}` (started_at)",
            "CREATE INDEX analytics_visits_live ON `{$visits}` (last_seen_at)",
            "CREATE INDEX analytics_views_visit ON `{$views}` (visit_id, viewed_at)",
            "CREATE INDEX analytics_views_path ON `{$views}` (page_path, viewed_at)",
            "CREATE INDEX analytics_crawlers_date ON `{$bots}` (hit_date)",
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent */ }
        }
    }
};
