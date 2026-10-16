<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/**
 * Indexes matching real query patterns:
 * - jobs(status, run_after): JobQueue::claim filters status + run_after
 *   every cron tick.
 * - system_messages(status): SystemMail::flush filters status='queued'.
 * - page_revisions(page_id): page edit view lists revisions by page.
 * - menu_items(menu_id): joined to menus on every public page render.
 * - media_usage(media_id): media delete/usage checks filter by media.
 */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_14_0005';
    }

    public function description(): string
    {
        return 'Indexes on hot query paths (jobs, mail, revisions, menus, media usage)';
    }

    public function up(Connection $db): void
    {
        $indexes = [
            'jobs' => 'idx_jobs_claim (status, run_after)',
            'system_messages' => 'idx_messages_status (status)',
            'page_revisions' => 'idx_revisions_page (page_id)',
            'menu_items' => 'idx_menu_items_menu (menu_id)',
            'media_usage' => 'idx_media_usage_media (media_id)',
        ];
        $sqlite = $db->driver() === 'sqlite';
        foreach ($indexes as $table => $def) {
            [$name, $cols] = explode(' ', $def, 2);
            $sql = 'CREATE ' . ($sqlite ? 'INDEX IF NOT EXISTS ' : 'INDEX ')
                . $name . ' ON `' . $db->table($table) . '` ' . $cols;
            if ($sqlite) {
                $db->pdo()->exec($sql);
            } else {
                try {
                    $db->pdo()->exec($sql);
                } catch (\Throwable) {
                    // index may already exist on re-run
                }
            }
        }
    }
};
