<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string { return 'landscaping_0001_commercial_foundation'; }
    public function description(): string { return 'Landscaping services, projects, plans, service areas, seasonal offers, and estimate leads'; }

    public function up(Connection $db): void
    {
        $sqlite = $db->driver() === 'sqlite';
        $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $timestamp = $sqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $nullableTimestamp = $sqlite ? 'TEXT NULL' : 'TIMESTAMP NULL DEFAULT NULL';

        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_services') . "` (
            id {$auto}, slug VARCHAR(120) NOT NULL, name VARCHAR(190) NOT NULL,
            category VARCHAR(120) NULL, summary TEXT NULL, details TEXT NULL, audience VARCHAR(30) NOT NULL DEFAULT 'both',
            image_url VARCHAR(1000) NULL, featured TINYINT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp}, updated_at {$nullableTimestamp}, UNIQUE (slug)
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_projects') . "` (
            id {$auto}, slug VARCHAR(120) NOT NULL, name VARCHAR(190) NOT NULL,
            location_label VARCHAR(190) NULL, summary TEXT NULL, services_used TEXT NULL,
            before_image_url VARCHAR(1000) NULL, after_image_url VARCHAR(1000) NULL,
            completed_on VARCHAR(20) NULL, featured TINYINT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp}, updated_at {$nullableTimestamp}, UNIQUE (slug)
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_plans') . "` (
            id {$auto}, slug VARCHAR(120) NOT NULL, name VARCHAR(190) NOT NULL,
            description TEXT NULL, cadence VARCHAR(120) NULL, seasonal_notes TEXT NULL,
            price_cents INT NOT NULL DEFAULT 0, price_note VARCHAR(120) NULL,
            inclusions TEXT NULL, featured TINYINT NOT NULL DEFAULT 0,
            visible TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
            created_at {$timestamp}, updated_at {$nullableTimestamp}, UNIQUE (slug)
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_service_areas') . "` (
            id {$auto}, name VARCHAR(190) NOT NULL, zip_codes TEXT NULL,
            travel_note TEXT NULL, map_copy TEXT NULL, visible TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_seasonal_services') . "` (
            id {$auto}, name VARCHAR(190) NOT NULL, season VARCHAR(80) NOT NULL,
            description TEXT NULL, availability_note VARCHAR(255) NULL,
            cta_label VARCHAR(120) NULL, visible TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS `' . $db->table('landscaping_estimates') . "` (
            id {$auto}, status VARCHAR(40) NOT NULL DEFAULT 'new', name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL, phone VARCHAR(80) NULL, address VARCHAR(255) NULL,
            city VARCHAR(120) NULL, state VARCHAR(80) NULL, zip VARCHAR(20) NULL,
            property_type VARCHAR(80) NULL, lot_size VARCHAR(80) NULL, service_interest TEXT NULL,
            schedule_preference VARCHAR(120) NULL, budget VARCHAR(100) NULL, message TEXT NULL,
            consent TINYINT NOT NULL DEFAULT 0, photo_media_ids TEXT NULL, admin_notes TEXT NULL,
            source_ip VARCHAR(64) NULL, created_at {$timestamp}, updated_at {$nullableTimestamp}
        ){$engine}");

        if ((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('landscaping_services') . '`') === 0) {
            $this->seed($db);
        }
    }

    private function seed(Connection $db): void
    {
        $now = date('Y-m-d H:i:s');
        $services = [
            ['lawn-maintenance','Lawn Maintenance','Lawn Care','Reliable mowing, edging, blowing, and routine turf care.','Build a recurring lawn-care schedule around the property, growing season, and finish level expected.','both',1,10],
            ['landscape-installation','Landscape Installation','Landscape Design & Installation','Planting, bed construction, drainage-aware grading, and landscape improvements.','Turn a design direction into a practical installation plan with appropriate plants, materials, and site preparation.','both',1,20],
            ['beds-and-mulch','Beds & Mulch','Beds & Planting','Fresh beds, defined edges, seasonal color, and protective mulch.','Refresh curb appeal while helping soil retain moisture and suppress weeds.','residential',1,30],
            ['shrub-trimming','Shrub & Hedge Trimming','Plant Care','Careful shaping and selective pruning for healthier, cleaner growth.','Maintain form, visibility, clearance, and plant health with timing appropriate to each species.','both',0,40],
            ['property-cleanup','Property Cleanup','Cleanup & Seasonal','Leaf removal, storm debris, overgrowth clearing, and seasonal resets.','Restore an untidy property and prepare beds and lawn areas for the next season.','both',1,50],
            ['commercial-grounds','Commercial Grounds Care','Commercial','Consistent exterior maintenance for offices, retail, associations, and facilities.','Coordinate service schedules and property standards across commercial sites.','commercial',0,60],
        ];
        foreach ($services as [$slug,$name,$category,$summary,$details,$audience,$featured,$sort]) {
            $db->insert('landscaping_services', compact('slug','name','category','summary','details','audience','featured') + ['image_url'=>'','visible'=>1,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>null]);
        }
        $projects = [
            ['front-yard-renewal','Front Yard Renewal','Sample neighborhood','A cleaner entry sequence with renewed turf, defined beds, and layered planting.','Lawn Maintenance, Beds & Mulch, Landscape Installation','', '', '',1,10],
            ['backyard-garden-reset','Backyard Garden Reset','Sample residence','Overgrown planting areas reorganized into a comfortable, lower-maintenance garden.','Property Cleanup, Shrub Trimming, Landscape Installation','', '', '',1,20],
            ['commercial-curb-appeal','Commercial Curb Appeal','Sample business property','A dependable grounds plan with cleaner edges, stronger entrances, and seasonal color.','Commercial Grounds Care, Beds & Mulch','', '', '',0,30],
        ];
        foreach ($projects as [$slug,$name,$location_label,$summary,$services_used,$before,$after,$completed,$featured,$sort]) {
            $db->insert('landscaping_projects', ['slug'=>$slug,'name'=>$name,'location_label'=>$location_label,'summary'=>$summary,'services_used'=>$services_used,'before_image_url'=>$before,'after_image_url'=>$after,'completed_on'=>$completed,'featured'=>$featured,'visible'=>1,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>null]);
        }
        $plans = [
            ['essential-care','Essential Care','Routine mowing and cleanup for a consistently neat property.','Weekly or biweekly','Frequency adjusts with local growth and weather.',0,'Custom estimate',"Mowing and edging\nHard-surface blowing\nRoutine visual inspection",0,10],
            ['complete-yard-care','Complete Yard Care','Recurring lawn, bed, and shrub attention throughout the growing season.','Scheduled year-round','Seasonal cleanup and pruning windows are planned in advance.',0,'Custom estimate',"Everything in Essential Care\nBed touchups\nShrub trimming schedule\nSeasonal cleanup priority",1,20],
            ['commercial-property-care','Commercial Property Care','A documented service plan for professional, dependable grounds.','Property-specific schedule','Seasonal color and storm response can be scoped separately.',0,'Custom proposal',"Site-specific checklist\nScheduled service windows\nPriority issue reporting\nSeasonal planning",0,30],
        ];
        foreach ($plans as [$slug,$name,$description,$cadence,$seasonal,$price,$note,$inclusions,$featured,$sort]) {
            $db->insert('landscaping_plans', ['slug'=>$slug,'name'=>$name,'description'=>$description,'cadence'=>$cadence,'seasonal_notes'=>$seasonal,'price_cents'=>$price,'price_note'=>$note,'inclusions'=>$inclusions,'featured'=>$featured,'visible'=>1,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>null]);
        }
        foreach ([['Primary Service Area','', 'Add nearby cities and ZIP codes in administration.','Local residential and commercial properties.',10],['Extended Service Area','', 'Travel or minimum-service requirements may apply.','Larger projects may be considered beyond the primary route.',20]] as [$name,$zips,$travel,$map,$sort]) {
            $db->insert('landscaping_service_areas',['name'=>$name,'zip_codes'=>$zips,'travel_note'=>$travel,'map_copy'=>$map,'visible'=>1,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>null]);
        }
        foreach ([['Spring Yard Reset','Spring','Cleanup, bed preparation, pruning review, and fresh mulch.','Book before the seasonal route fills.','Plan a spring cleanup',10],['Summer Lawn Care','Summer','Recurring mowing and detail work during peak growth.','Schedules depend on route availability.','Request lawn care',20],['Fall Leaf Cleanup','Fall','Leaf removal, final bed cleanup, and cold-season preparation.','Available during the local leaf season.','Reserve fall cleanup',30],['Storm Cleanup','As needed','Priority clearing of manageable landscape debris after severe weather.','Subject to safe access and crew availability.','Check availability',40]] as [$name,$season,$description,$availability,$cta,$sort]) {
            $db->insert('landscaping_seasonal_services',['name'=>$name,'season'=>$season,'description'=>$description,'availability_note'=>$availability,'cta_label'=>$cta,'visible'=>1,'sort_order'=>$sort,'created_at'=>$now,'updated_at'=>null]);
        }
    }
};
