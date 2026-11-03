<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'catering_0004_core_sales_workspace';
    }

    public function description(): string
    {
        return 'Link detailed catering questionnaires to the shared Core sales workspace';
    }

    public function up(Connection $db): void
    {
        $table = $db->table('catering_inquiries');
        $hasColumn = $db->driver() === 'sqlite'
            ? in_array('core_inquiry_id', array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, 'core_inquiry_id']
            );
        if (!$hasColumn) {
            $type = $db->driver() === 'sqlite' ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL';
            $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN core_inquiry_id {$type}");
        }
        if ($db->driver() !== 'sqlite') {
            $hasIndex = (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?',
                [$table, 'catering_inquiries_core_inquiry_idx']
            );
            if (!$hasIndex) {
                $db->pdo()->exec("CREATE INDEX catering_inquiries_core_inquiry_idx ON `{$table}` (core_inquiry_id)");
            }
        }
    }
};
