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
        $originalCategories = [
            'seasonal-small-plates' => ['Seasonal Small Plates', 'Passed and displayed bites designed to begin the gathering beautifully.', 10],
            'plated-entrees' => ['Plated Entrées', 'Refined main courses finished with seasonal accompaniments.', 20],
            'buffet-favorites' => ['Buffet Favorites', 'Generous crowd-pleasing selections for relaxed service.', 30],
            'desserts' => ['Desserts', 'A sweet finish, from petite pastries to celebration cakes.', 40],
        ];
        foreach ($categories as $slug => [$name, $description]) {
            [$originalName, $originalDescription, $originalSort] = $originalCategories[$slug];
            $db->run(
                "UPDATE `{$categoriesTable}` SET name = ?, description = ? WHERE slug = ? AND name = ? AND description = ? AND sort_order = ? AND visible = 1",
                [$name, $description, $slug, $originalName, $originalDescription, $originalSort]
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
        // This migration may still be pending on 0.1.21 sites. Match complete
        // original starter records before replacing them; a familiar slug alone
        // does not authorize overwriting the owner's edits.
        $originalItems = [
            'garden-crostini' => ['Garden Crostini', 'Whipped chèvre, roasted tomato, basil oil, and garden herbs.', 650, 'Vegetarian'],
            'gulf-shrimp-spoons' => ['Gulf Shrimp Spoons', 'Citrus-poached shrimp, cucumber, avocado, and pepper relish.', 850, 'Gluten Free'],
            'wild-mushroom-tartlet' => ['Wild Mushroom Tartlet', 'Caramelized shallot, thyme, and aged parmesan.', 725, 'Vegetarian'],
            'herb-crusted-salmon' => ['Herb-Crusted Salmon', 'Seasonal grains, charred vegetables, lemon beurre blanc.', 3600, 'Gluten Free'],
            'filet-mignon' => ['Filet Mignon', 'Truffle potatoes, roasted heirloom carrots, red-wine reduction.', 4800, 'Gluten Free'],
            'garden-harvest' => ['Garden Harvest', 'Roasted squash, creamy polenta, greens, and smoked tomato jus.', 3000, 'Vegan, Gluten Free'],
            'southern-celebration' => ['Southern Celebration', 'Herb chicken, braised greens, whipped potatoes, and warm rolls.', 2800, ''],
            'mediterranean-table' => ['Mediterranean Table', 'Grilled chicken, lemon rice, seasonal vegetables, hummus, and flatbread.', 2700, 'Vegetarian options'],
            'petite-dessert-table' => ['Petite Dessert Table', 'Chef-selected tartlets, truffles, macarons, and seasonal sweets.', 1200, 'Vegetarian'],
        ];
        $originalCategorySlugs = [
            'garden-crostini' => 'seasonal-small-plates', 'gulf-shrimp-spoons' => 'seasonal-small-plates', 'wild-mushroom-tartlet' => 'seasonal-small-plates',
            'herb-crusted-salmon' => 'plated-entrees', 'filet-mignon' => 'plated-entrees', 'garden-harvest' => 'plated-entrees',
            'southern-celebration' => 'buffet-favorites', 'mediterranean-table' => 'buffet-favorites', 'petite-dessert-table' => 'desserts',
        ];
        $originalFeatured = ['garden-crostini' => 1, 'gulf-shrimp-spoons' => 1, 'herb-crusted-salmon' => 1, 'filet-mignon' => 1];
        $upgradedStarter = false;
        foreach ($replacements as $oldSlug => $item) {
            [$originalName, $originalDescription, $originalPrice, $originalDietary] = $originalItems[$oldSlug];
            [$slug, $name, $description, $price, $unit, $dietary, $featured, $sort, $image] = $item;
            if ((int) $db->fetchColumn("SELECT COUNT(*) FROM `{$itemsTable}` WHERE slug = ?", [$slug]) > 0) {
                continue;
            }
            $updated = $db->run(
                "UPDATE `{$itemsTable}` SET slug = ?, name = ?, description = ?, price_cents = ?, price_unit = ?, dietary = ?, featured = ?, sort_order = ?, image = ? WHERE slug = ? AND name = ? AND description = ? AND price_cents = ? AND dietary = ? AND price_unit = ? AND featured = ? AND sort_order = ? AND visible = 1 AND (image IS NULL OR image = ?) AND category_id = (SELECT id FROM `{$categoriesTable}` WHERE slug = ?)",
                [$slug, $name, $description, $price, $unit, $dietary, $featured, $sort, $image, $oldSlug, $originalName, $originalDescription, $originalPrice, $originalDietary, 'per guest', $originalFeatured[$oldSlug] ?? 0, $sort, '', $originalCategorySlugs[$oldSlug]]
            );
            $upgradedStarter = $upgradedStarter || $updated->rowCount() > 0;
        }

        if ($upgradedStarter && (int) $db->fetchColumn("SELECT COUNT(*) FROM `{$itemsTable}` WHERE slug = ?", ['blackened-redfish']) === 0) {
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
