<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return 'catering_0008_event_workspace'; }
    public function description(): string { return 'Confirmed events, tastings, production, staffing, equipment, and recipe costing'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $decimal = $sqlite ? 'NUMERIC' : 'DECIMAL(12,4)';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timestamp = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $nullableTimestamp = $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL';

        foreach ([
            'address_line_1' => 'VARCHAR(255) NULL',
            'address_line_2' => 'VARCHAR(255) NULL',
            'city' => 'VARCHAR(120) NULL',
            'region' => 'VARCHAR(120) NULL',
            'tasting_requested' => 'TINYINT NOT NULL DEFAULT 0',
        ] as $column => $definition) $this->addColumn($db, 'catering_inquiries', $column, $definition);

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_events') . "` (
            id {$auto},
            inquiry_id {$int} NOT NULL,
            core_quote_id {$int} NULL,
            state VARCHAR(24) NOT NULL DEFAULT 'tentative',
            title VARCHAR(190) NOT NULL,
            event_date VARCHAR(20) NULL,
            event_time VARCHAR(20) NULL,
            customer_name VARCHAR(190) NOT NULL,
            customer_email VARCHAR(190) NOT NULL,
            customer_phone VARCHAR(80) NULL,
            guest_count INT NULL,
            final_guest_count INT NULL,
            final_headcount_due VARCHAR(20) NULL,
            venue VARCHAR(255) NULL,
            address_line_1 VARCHAR(255) NULL,
            address_line_2 VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            region VARCHAR(120) NULL,
            postal_code VARCHAR(20) NULL,
            fulfillment_method VARCHAR(20) NULL,
            fulfillment_window VARCHAR(120) NULL,
            menu_plan MEDIUMTEXT NULL,
            operations_plan MEDIUMTEXT NULL,
            run_of_show MEDIUMTEXT NULL,
            internal_notes MEDIUMTEXT NULL,
            delivery_status VARCHAR(24) NOT NULL DEFAULT 'unassigned',
            driver_name VARCHAR(190) NULL,
            route_order INT NULL,
            setup_instructions TEXT NULL,
            proof_reference VARCHAR(500) NULL,
            revision INT NOT NULL DEFAULT 1,
            created_at {$timestamp},
            updated_at {$nullableTimestamp},
            UNIQUE (inquiry_id),
            UNIQUE (core_quote_id)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_event_tasks') . "` (
            id {$auto}, event_id {$int} NOT NULL, category VARCHAR(40) NOT NULL DEFAULT 'general',
            title VARCHAR(190) NOT NULL, due_date VARCHAR(20) NULL, assignee VARCHAR(190) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'open', notes TEXT NULL, sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_event_staff') . "` (
            id {$auto}, event_id {$int} NOT NULL, position VARCHAR(120) NOT NULL, staff_name VARCHAR(190) NULL,
            starts_at VARCHAR(30) NULL, ends_at VARCHAR(30) NULL, status VARCHAR(24) NOT NULL DEFAULT 'needed',
            hourly_cost_minor INT NOT NULL DEFAULT 0, notes TEXT NULL, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_event_equipment') . "` (
            id {$auto}, event_id {$int} NOT NULL, item_name VARCHAR(190) NOT NULL, quantity INT NOT NULL DEFAULT 1,
            packed_quantity INT NOT NULL DEFAULT 0, returned_quantity INT NOT NULL DEFAULT 0,
            replacement_cost_minor INT NOT NULL DEFAULT 0, status VARCHAR(24) NOT NULL DEFAULT 'needed',
            notes TEXT NULL, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_tastings') . "` (
            id {$auto}, inquiry_id {$int} NULL, event_id {$int} NULL, customer_name VARCHAR(190) NOT NULL,
            customer_email VARCHAR(190) NOT NULL, preferred_date VARCHAR(20) NULL, guest_count INT NOT NULL DEFAULT 2,
            status VARCHAR(24) NOT NULL DEFAULT 'requested', eligibility VARCHAR(24) NOT NULL DEFAULT 'review',
            dietary TEXT NULL, notes TEXT NULL, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_ingredients') . "` (
            id {$auto}, name VARCHAR(190) NOT NULL, unit VARCHAR(40) NOT NULL, cost_minor_per_unit INT NOT NULL DEFAULT 0,
            supplier VARCHAR(190) NULL, allergens TEXT NULL, active TINYINT NOT NULL DEFAULT 1,
            created_at {$timestamp}, updated_at {$nullableTimestamp}, UNIQUE (name, unit)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_recipe_ingredients') . "` (
            id {$auto}, menu_item_id {$int} NOT NULL, ingredient_id {$int} NOT NULL,
            quantity_per_guest {$decimal} NOT NULL DEFAULT 0, waste_percent {$decimal} NOT NULL DEFAULT 0,
            station VARCHAR(80) NOT NULL DEFAULT 'Kitchen', prep_lead_days INT NOT NULL DEFAULT 0,
            label_notes VARCHAR(500) NULL, prep_notes TEXT NULL, created_at {$timestamp}, updated_at {$nullableTimestamp},
            UNIQUE (menu_item_id, ingredient_id)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_event_costs') . "` (
            id {$auto}, event_id {$int} NOT NULL, category VARCHAR(24) NOT NULL,
            description VARCHAR(190) NOT NULL, estimated_minor INT NOT NULL DEFAULT 0,
            actual_minor INT NULL, notes TEXT NULL, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_event_revisions') . "` (
            id {$auto}, event_id {$int} NOT NULL, revision INT NOT NULL, summary TEXT NOT NULL,
            acknowledged_at {$nullableTimestamp}, created_at {$timestamp}
        ){$engine}");

        foreach ([
            'CREATE INDEX ' . $db->table('idx_catering_events_date') . ' ON `' . $db->table('catering_events') . '` (event_date)',
            'CREATE INDEX ' . $db->table('idx_catering_events_state') . ' ON `' . $db->table('catering_events') . '` (state)',
            'CREATE INDEX ' . $db->table('idx_catering_tasks_event') . ' ON `' . $db->table('catering_event_tasks') . '` (event_id)',
            'CREATE INDEX ' . $db->table('idx_catering_staff_event') . ' ON `' . $db->table('catering_event_staff') . '` (event_id)',
            'CREATE INDEX ' . $db->table('idx_catering_equipment_event') . ' ON `' . $db->table('catering_event_equipment') . '` (event_id)',
            'CREATE INDEX ' . $db->table('idx_catering_costs_event') . ' ON `' . $db->table('catering_event_costs') . '` (event_id)',
        ] as $sql) {
            try { $db->pdo()->exec($sql); } catch (\Throwable) { /* idempotent index creation */ }
        }
    }

    private function addColumn(Connection $db, string $logicalTable, string $column, string $definition): void
    {
        $table = $db->table($logicalTable);
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$table, $column]
            );
        if (!$exists) $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
};
