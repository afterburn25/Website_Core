<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0012'; }
    public function description(): string { return 'Visual page builder: versioned structured block layouts'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        foreach (['pages', 'page_revisions'] as $logical) {
            $table = $db->table($logical);
            $exists = false;
            if ($sqlite) {
                foreach ($db->fetchAll("PRAGMA table_info(`{$table}`)") as $row) {
                    if (($row['name'] ?? '') === 'blocks_json') $exists = true;
                }
            } else {
                $exists = (bool) $db->fetchColumn(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                    [$table, 'blocks_json']
                );
            }
            if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN blocks_json TEXT NULL");
        }
    }
};
