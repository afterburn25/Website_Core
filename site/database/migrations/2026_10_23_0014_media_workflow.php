<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return '2026_10_23_0014'; }
    public function description(): string { return 'Media organization, tags, focal points and searchable metadata'; }

    public function up(Connection $db): void
    {
        $table = $db->table('media');
        $sqlite = $db->driver() === 'sqlite';
        $columns = [
            'tags_json' => "TEXT NULL",
            'focal_x' => $sqlite ? "REAL NOT NULL DEFAULT 50" : "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
            'focal_y' => $sqlite ? "REAL NOT NULL DEFAULT 50" : "DECIMAL(5,2) NOT NULL DEFAULT 50.00",
        ];
        foreach ($columns as $name => $type) {
            $exists = $sqlite
                ? in_array($name, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
                : (bool) $db->fetchColumn(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                    [$table, $name]
                );
            if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$type}");
        }
        if ($sqlite) {
            $db->pdo()->exec("CREATE INDEX IF NOT EXISTS idx_media_folder ON `{$table}` (folder)");
        } else {
            $hasIndex = (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$table, 'idx_media_folder']
            );
            if (!$hasIndex) $db->pdo()->exec("ALTER TABLE `{$table}` ADD KEY `idx_media_folder` (folder)");
        }
    }
};
