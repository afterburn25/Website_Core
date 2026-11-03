<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return 'catering_0007_event_operations'; }
    public function description(): string { return 'Fulfillment details and immutable catering operations snapshots'; }

    public function up(Connection $db): void
    {
        foreach ([
            'fulfillment_method' => 'VARCHAR(20) NULL',
            'fulfillment_window' => 'VARCHAR(120) NULL',
            'postal_code' => 'VARCHAR(20) NULL',
            'operations_plan' => 'MEDIUMTEXT NULL',
        ] as $column => $definition) $this->addColumn($db, $column, $definition);
    }

    private function addColumn(Connection $db, string $column, string $definition): void
    {
        $table = $db->table('catering_inquiries');
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, $column]
            );
        if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
};
