<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'catering_0002_cajun_menu_images';
    }

    public function description(): string
    {
        return 'Cajun demo menu and dish-specific photography';
    }

    public function up(Connection $db): void
    {
        $itemsTable = $db->table('catering_menu_items');
        $categoriesTable = $db->table('catering_menu_categories');
        $hasImage = $db->driver() === 'sqlite'
            ? in_array('image', array_column($db->fetchAll("PRAGMA table_info(`{$itemsTable}`)"), 'name'), true)
            : (bool) $db->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
                [$itemsTable, 'image']
            );
        if (!$hasImage) {
            $db->pdo()->exec("ALTER TABLE `{$itemsTable}` ADD COLUMN image VARCHAR(190) NULL");
        }

        $categories = [
            'seasonal-small-plates' => ['Cajun Starters', 'Louisiana-inspired bites made for cocktail hours, receptions, and passing around the table.'],
            'plated-entrees' => ['Louisiana Classics', 'Beloved Cajun dishes prepared with deep flavor and polished catering presentation.'],
            'buffet-favorites' => ['Bayou Favorites', 'Generous crowd-pleasing selections for family-style service and festive buffets.'],
            'desserts' => ['Louisiana Desserts', 'A sweet Southern finish with warm spice, caramel, pecans, and celebration-ready presentation.'],
        ];
        foreach ($categories as $slug => [$name, $description]) {
            $db->run(
                "UPDATE `{$categoriesTable}` SET name = ?, description = ? WHERE slug = ?",
                [$name, $description, $slug]
            );
        }

        $replacements = [
            'garden-crostini' => ['boudin-balls', 'Boudin Balls', 'Crisp Louisiana boudin with seasoned pork and rice, served with Creole remoulade.', 800, 'per guest', '', 0, 10, 'boudin-balls.webp'],
            'gulf-shrimp-spoons' => ['crawfish-bread', 'Crawfish Bread', 'Toasted French bread layered with Louisiana crawfish, Cajun cream, melted cheese, and green onion.', 900, 'per guest', '', 0, 20, 'crawfish-bread.webp'],
            'wild-mushroom-tartlet' => ['cajun-shrimp-grits-bites', 'Cajun Shrimp & Grits Bites', 'Blackened Gulf shrimp over seared grit cakes with Cajun cream and green onion.', 950, 'per guest', 'Gluten Free', 1, 30, 'cajun-shrimp-grits-bites.webp'],
            'herb-crusted-salmon' => ['chicken-andouille-gumbo', 'Chicken & Andouille Gumbo', 'Dark-roux gumbo with tender chicken, smoked andouille, Louisiana rice, and green onion.', 1200, 'per guest', '', 1, 10, 'chicken-andouille-gumbo.webp'],
            'filet-mignon' => ['crawfish-etouffee', 'Crawfish Étouffée', 'Louisiana crawfish tails smothered in a rich, seasoned sauce and served over steamed rice.', 2800, 'per guest', '', 1, 20, 'crawfish-etouffee.webp'],
            'garden-harvest' => ['crawfish-bisque', 'Crawfish Bisque', 'Velvety crawfish bisque with traditional stuffed heads, Louisiana rice, and fresh herbs.', 1400, 'per guest', '', 0, 30, 'crawfish-bisque.webp'],
            'southern-celebration' => ['shrimp-fettuccine', 'Shrimp Fettuccine', 'Gulf shrimp and fettuccine tossed in a silky Cajun cream sauce with parmesan and herbs.', 2600, 'per guest', '', 0, 10, 'shrimp-fettuccine.webp'],
            'mediterranean-table' => ['cajun-jambalaya', 'Cajun Jambalaya', 'Louisiana brown jambalaya with Gulf shrimp, chicken, and smoked andouille sausage.', 2400, 'per guest', 'Gluten Free', 0, 20, 'cajun-jambalaya.webp'],
            'petite-dessert-table' => ['bananas-foster-bread-pudding', 'Bananas Foster Bread Pudding', 'Warm bread pudding with caramelized bananas, rum caramel, toasted pecans, and vanilla cream.', 1100, 'per guest', 'Vegetarian', 0, 10, 'bananas-foster-bread-pudding.webp'],
        ];
        foreach ($replacements as $oldSlug => $item) {
            [$slug, $name, $description, $price, $unit, $dietary, $featured, $sort, $image] = $item;
            if ((int) $db->fetchColumn("SELECT COUNT(*) FROM `{$itemsTable}` WHERE slug = ?", [$slug]) > 0) {
                continue;
            }
            $db->run(
                "UPDATE `{$itemsTable}` SET slug = ?, name = ?, description = ?, price_cents = ?, price_unit = ?, dietary = ?, featured = ?, sort_order = ?, image = ? WHERE slug = ?",
                [$slug, $name, $description, $price, $unit, $dietary, $featured, $sort, $image, $oldSlug]
            );
        }

        if ((int) $db->fetchColumn("SELECT COUNT(*) FROM `{$itemsTable}` WHERE slug = ?", ['blackened-redfish']) === 0) {
            $categoryId = $db->fetchColumn("SELECT id FROM `{$categoriesTable}` WHERE slug = ?", ['plated-entrees']);
            if ($categoryId !== false) {
                $db->insert('catering_menu_items', [
                    'category_id' => (int) $categoryId,
                    'slug' => 'blackened-redfish',
                    'name' => 'Blackened Redfish',
                    'description' => 'Cajun-crusted redfish with crawfish cream, dirty rice, and roasted seasonal vegetables.',
                    'price_cents' => 3400,
                    'price_unit' => 'per guest',
                    'dietary' => 'Gluten Free',
                    'featured' => 0,
                    'visible' => 1,
                    'sort_order' => 40,
                    'created_at' => date('Y-m-d H:i:s'),
                    'image' => 'blackened-redfish.webp',
                ]);
            }
        }
    }
};
