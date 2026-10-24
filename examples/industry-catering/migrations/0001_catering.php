<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'catering_0001_foundation';
    }

    public function description(): string
    {
        return 'Catering menus, packages, and event inquiries';
    }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $int = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timestamp = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $nullableTimestamp = $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_menu_categories') . "` (
            id {$auto},
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1,
            created_at {$timestamp},
            UNIQUE (slug)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_menu_items') . "` (
            id {$auto},
            category_id {$int} NOT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            description TEXT NULL,
            price_cents INT NOT NULL DEFAULT 0,
            price_unit VARCHAR(80) NOT NULL DEFAULT 'per person',
            dietary VARCHAR(255) NULL,
            featured TINYINT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp},
            UNIQUE (slug)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_packages') . "` (
            id {$auto},
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(190) NOT NULL,
            eyebrow VARCHAR(120) NULL,
            description TEXT NULL,
            price_cents INT NOT NULL DEFAULT 0,
            price_note VARCHAR(120) NOT NULL DEFAULT 'per person',
            guest_min INT NOT NULL DEFAULT 10,
            guest_max INT NULL,
            inclusions TEXT NULL,
            featured TINYINT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp},
            UNIQUE (slug)
        ){$engine}");

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('catering_inquiries') . "` (
            id {$auto},
            kind VARCHAR(40) NOT NULL DEFAULT 'quote',
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(80) NULL,
            company VARCHAR(190) NULL,
            event_type VARCHAR(100) NULL,
            event_date VARCHAR(20) NULL,
            event_time VARCHAR(20) NULL,
            guest_count INT NULL,
            venue VARCHAR(255) NULL,
            service_style VARCHAR(100) NULL,
            menu_interest VARCHAR(190) NULL,
            budget VARCHAR(100) NULL,
            dietary TEXT NULL,
            message TEXT NULL,
            source_ip VARCHAR(64) NULL,
            created_at {$timestamp},
            updated_at {$nullableTimestamp}
        ){$engine}");

        if ((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_menu_categories') . '`') === 0) {
            $this->seed($db);
        }
    }

    private function seed(Connection $db): void
    {
        $now = date('Y-m-d H:i:s');
        $categories = [
            ['seasonal-small-plates', 'Seasonal Small Plates', 'Passed and displayed bites designed to begin the gathering beautifully.', 10],
            ['plated-entrees', 'Plated Entrées', 'Refined main courses finished with seasonal accompaniments.', 20],
            ['buffet-favorites', 'Buffet Favorites', 'Generous crowd-pleasing selections for relaxed service.', 30],
            ['desserts', 'Desserts', 'A sweet finish, from petite pastries to celebration cakes.', 40],
        ];
        $categoryIds = [];
        foreach ($categories as [$slug, $name, $description, $sort]) {
            $categoryIds[$slug] = (int) $db->insert('catering_menu_categories', [
                'slug' => $slug, 'name' => $name, 'description' => $description,
                'sort_order' => $sort, 'visible' => 1, 'created_at' => $now,
            ]);
        }

        $items = [
            ['seasonal-small-plates', 'garden-crostini', 'Garden Crostini', 'Whipped chèvre, roasted tomato, basil oil, and garden herbs.', 650, 'per guest', 'Vegetarian', 1, 10],
            ['seasonal-small-plates', 'gulf-shrimp-spoons', 'Gulf Shrimp Spoons', 'Citrus-poached shrimp, cucumber, avocado, and pepper relish.', 850, 'per guest', 'Gluten Free', 1, 20],
            ['seasonal-small-plates', 'wild-mushroom-tartlet', 'Wild Mushroom Tartlet', 'Caramelized shallot, thyme, and aged parmesan.', 725, 'per guest', 'Vegetarian', 0, 30],
            ['plated-entrees', 'herb-crusted-salmon', 'Herb-Crusted Salmon', 'Seasonal grains, charred vegetables, lemon beurre blanc.', 3600, 'per guest', 'Gluten Free', 1, 10],
            ['plated-entrees', 'filet-mignon', 'Filet Mignon', 'Truffle potatoes, roasted heirloom carrots, red-wine reduction.', 4800, 'per guest', 'Gluten Free', 1, 20],
            ['plated-entrees', 'garden-harvest', 'Garden Harvest', 'Roasted squash, creamy polenta, greens, and smoked tomato jus.', 3000, 'per guest', 'Vegan, Gluten Free', 0, 30],
            ['buffet-favorites', 'southern-celebration', 'Southern Celebration', 'Herb chicken, braised greens, whipped potatoes, and warm rolls.', 2800, 'per guest', '', 0, 10],
            ['buffet-favorites', 'mediterranean-table', 'Mediterranean Table', 'Grilled chicken, lemon rice, seasonal vegetables, hummus, and flatbread.', 2700, 'per guest', 'Vegetarian options', 0, 20],
            ['desserts', 'petite-dessert-table', 'Petite Dessert Table', 'Chef-selected tartlets, truffles, macarons, and seasonal sweets.', 1200, 'per guest', 'Vegetarian', 0, 10],
        ];
        foreach ($items as [$category, $slug, $name, $description, $price, $unit, $dietary, $featured, $sort]) {
            $db->insert('catering_menu_items', [
                'category_id' => $categoryIds[$category], 'slug' => $slug, 'name' => $name,
                'description' => $description, 'price_cents' => $price, 'price_unit' => $unit,
                'dietary' => $dietary, 'featured' => $featured, 'visible' => 1,
                'sort_order' => $sort, 'created_at' => $now,
            ]);
        }

        $packages = [
            ['the-signature', 'The Signature', 'Effortless hospitality', 'A polished buffet experience with thoughtful menu choices and seamless service.', 3900, 'starting per guest', 25, 150, "Two passed appetizers\nOne salad\nTwo entrées\nTwo seasonal sides\nArtisan rolls\nStandard service ware", 0, 10],
            ['the-elevated', 'The Elevated', 'Our most popular', 'A composed celebration with premium ingredients, expanded service, and custom touches.', 5900, 'starting per guest', 40, 250, "Four passed appetizers\nChoice of plated or chef-attended buffet\nTwo entrées\nThree seasonal sides\nDessert display\nService staff and event captain", 1, 20],
            ['the-bespoke', 'The Bespoke', 'Designed around you', 'A fully tailored culinary experience created around your venue, story, and vision.', 7900, 'starting per guest', 50, null, "Custom tasting and menu design\nCocktail-hour experience\nMulti-course dinner\nPremium staffing\nRental coordination\nDedicated event captain", 0, 30],
        ];
        foreach ($packages as [$slug, $name, $eyebrow, $description, $price, $note, $min, $max, $inclusions, $featured, $sort]) {
            $db->insert('catering_packages', [
                'slug' => $slug, 'name' => $name, 'eyebrow' => $eyebrow,
                'description' => $description, 'price_cents' => $price, 'price_note' => $note,
                'guest_min' => $min, 'guest_max' => $max, 'inclusions' => $inclusions,
                'featured' => $featured, 'visible' => 1, 'sort_order' => $sort, 'created_at' => $now,
            ]);
        }
    }
};
