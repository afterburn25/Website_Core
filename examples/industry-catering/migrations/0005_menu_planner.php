<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return 'catering_0005_menu_planner'; }
    public function description(): string { return 'Menu planning, package choices, food pricing and allergen information'; }

    public function up(Connection $db): void
    {
        foreach (['catering_menu_items', 'catering_packages'] as $logical) {
            foreach ([
                'pricing_basis' => "VARCHAR(20) NOT NULL DEFAULT 'manual'",
                'servings_per_tray' => 'INT NOT NULL DEFAULT 1',
                'minimum_quantity' => 'INT NOT NULL DEFAULT 1',
                'guest_tiers' => 'TEXT NULL',
            ] as $column => $definition) $this->addColumn($db, $logical, $column, $definition);
        }
        foreach (['dietary_tags' => 'TEXT NULL', 'allergens' => 'TEXT NULL', 'allergen_notes' => 'TEXT NULL'] as $column => $definition) {
            $this->addColumn($db, 'catering_menu_items', $column, $definition);
        }
        $this->addColumn($db, 'catering_packages', 'choice_rules', 'TEXT NULL');
        $this->addColumn($db, 'catering_inquiries', 'menu_plan', 'MEDIUMTEXT NULL');
    }

    private function addColumn(Connection $db, string $logical, string $column, string $definition): void
    {
        $table = $db->table($logical);
        $exists = $db->driver() === 'sqlite'
            ? in_array($column, array_column($db->fetchAll("PRAGMA table_info(`{$table}`)"), 'name'), true)
            : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$table, $column]);
        if ($exists) return;
        $db->pdo()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        // Infer only unambiguous existing labels, and only when the new column is created.
        // A repeated migration must never change an administrator's later pricing choice.
        if ($column === 'pricing_basis') {
            $label = $logical === 'catering_packages' ? 'price_note' : 'price_unit';
            $db->run("UPDATE `{$table}` SET pricing_basis = ? WHERE LOWER(TRIM(`{$label}`)) IN (?, ?, ?, ?)",
                ['per_guest', 'per guest', 'per person', 'starting per guest', 'starting per person']);
        }
    }
};
