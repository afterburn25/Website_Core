<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Modules\ModuleContext;
use Core\Database\Connection;
use Core\Settings\SettingsRepository;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new CateringController($ctx, $ns);

        // Core owns the reusable business identity and sales workspace. Seed
        // only blank profile values from the module's legacy settings; later
        // administrator edits remain authoritative.
        $settings = $ctx->service(SettingsRepository::class);
        $ctx->businessProfile([
            'name' => (string) $settings->get('site.name', 'Catering Co.'),
            'email' => (string) $settings->get('site.contact_email', ''),
            'phone' => (string) $settings->get('catering.phone', ''),
            'service_area' => (string) $settings->get('catering.service_area', ''),
        ]);
        $ctx->leadManagement(['quotes' => true, 'label' => 'Catering sales']);
        $ctx->serviceArea();
        $profile = $ctx->service(\Core\Business\BusinessProfile::class)->get();
        $ctx->geocodingProvider(
            'openstreetmap-nominatim',
            new Geocoder((string) ($profile['email'] ?? '')),
            'OpenStreetMap contributors',
        );
        $ctx->themeCollection('Catering Themes', ThemeCollection::variants());
        $ctx->structuredData('catering-public', fn ($request): array => (new StructuredData($ctx))->forRequest($request));

        $commerce = new CommerceIntegration($ctx, $ctx->service(Connection::class), $settings);
        if ($commerce->commerceEnabled()) {
            $ctx->commerce([
                'store_name' => ($profile['name'] ?: 'Catering Co.') . ' Online Ordering',
                'currency' => 'USD',
                'fulfillment' => ['pickup', 'delivery'],
            ]);
            $ctx->customerAccounts(['registration' => true, 'account_label' => 'My catering account']);
        }
        $ctx->checkoutValidator(fn (array $checkout): array => $commerce->validate($checkout), ['timeout_seconds' => 5.0]);

        $ctx->route('GET', '/', fn ($r) => $ctl()->home($r));
        $ctx->route('GET', '/services', fn ($r) => $ctl()->services($r));
        $ctx->route('GET', '/services/{slug:weddings|corporate-events|private-celebrations|drop-off-catering|school-catering|nonprofit-events|holiday-catering|memorial-gatherings}',
            fn ($r) => $ctl()->serviceDetail($r));
        $ctx->route('GET', '/menus', fn ($r) => $ctl()->menus($r));
        $ctx->route('GET', '/menus/{slug:[a-z0-9-]+}', fn ($r) => $ctl()->menuDetail($r));
        $ctx->route('GET', '/packages', fn ($r) => $ctl()->packages($r));
        $ctx->route('GET', '/packages/{slug:[a-z0-9-]+}', fn ($r) => $ctl()->packageDetail($r));
        $ctx->route('GET', '/gallery', fn ($r) => $ctl()->gallery($r));
        $ctx->route('GET', '/venues', fn ($r) => $ctl()->venues($r));
        $ctx->route('GET', '/event-stories', fn ($r) => $ctl()->eventStories($r));
        $ctx->route('GET', '/about', fn ($r) => $ctl()->about($r));
        $ctx->route('GET', '/service-area', fn ($r) => $ctl()->serviceArea($r));
        $ctx->route('GET', '/faq', fn ($r) => $ctl()->faq($r));
        $ctx->route('GET', '/contact', fn ($r) => $ctl()->contact($r));
        $ctx->route('POST', '/contact', fn ($r) => $ctl()->submitContact($r));
        $ctx->route('GET', '/request-a-quote', fn ($r) => $ctl()->quote($r));
        $ctx->route('POST', '/request-a-quote', fn ($r) => $ctl()->submitQuote($r));
        $ctx->route('POST', '/catering/estimate', fn ($r) => $ctl()->estimate($r));

        $ctx->adminRoute('GET', '/admin/catering/menus', fn ($r) => $ctl()->adminMenus($r));
        $ctx->adminRoute('POST', '/admin/catering/menus', fn ($r) => $ctl()->adminMenusSave($r));
        $ctx->adminRoute('GET', '/admin/catering/packages', fn ($r) => $ctl()->adminPackages($r));
        $ctx->adminRoute('POST', '/admin/catering/packages', fn ($r) => $ctl()->adminPackagesSave($r));
        $ctx->adminRoute('GET', '/admin/catering/menu-groups', fn ($r) => $ctl()->adminMenuGroups($r));
        $ctx->adminRoute('POST', '/admin/catering/menu-groups', fn ($r) => $ctl()->adminMenuGroupsSave($r));
        $ctx->adminRoute('GET', '/admin/catering/events', fn ($r) => $ctl()->adminEvents($r));
        $ctx->adminRoute('POST', '/admin/catering/events', fn ($r) => $ctl()->adminEventsSave($r));
        $ctx->adminRoute('GET', '/admin/catering/events/{id:[0-9]+}', fn ($r) => $ctl()->adminEvent($r));
        $ctx->adminRoute('POST', '/admin/catering/events/{id:[0-9]+}', fn ($r) => $ctl()->adminEventsSave($r));
        $ctx->adminRoute('POST', '/admin/catering/tastings', fn ($r) => $ctl()->adminTastingsSave($r));
        $ctx->adminRoute('GET', '/admin/catering/production', fn ($r) => $ctl()->adminProduction($r));
        $ctx->adminRoute('POST', '/admin/catering/production', fn ($r) => $ctl()->adminProductionSave($r));
        // Preserve old bookmarks while Core takes ownership of the shared
        // inquiry workspace.
        $ctx->adminRoute('GET', '/admin/catering/inquiries', fn ($r) => $ctl()->legacyInquiryRedirect($r));
        $ctx->adminRoute('POST', '/admin/catering/inquiries', fn ($r) => $ctl()->legacyInquiryRedirect($r));
        $ctx->adminRoute('GET', '/admin/catering/settings', fn ($r) => $ctl()->adminSettings($r));
        $ctx->adminRoute('POST', '/admin/catering/settings', fn ($r) => $ctl()->adminSettingsSave($r));

        $ctx->permission('catering.manage', 'Manage catering menus, packages, and settings');
        $ctx->contentType('catering_venue', 'Catering Venue Partners', [
            ['name'=>'name','label'=>'Venue name','type'=>'text','required'=>true],
            ['name'=>'city','label'=>'City / region','type'=>'text','required'=>true],
            ['name'=>'website','label'=>'Venue website','type'=>'url'],
            ['name'=>'description','label'=>'Description','type'=>'textarea','required'=>true],
            ['name'=>'service_notes','label'=>'Catering and access notes','type'=>'textarea'],
            ['name'=>'hero_image','label'=>'Venue image','type'=>'media'],
        ]);
        $ctx->contentType('catering_case_study', 'Catering Event Case Studies', [
            ['name'=>'event_name','label'=>'Event name','type'=>'text','required'=>true],
            ['name'=>'event_type','label'=>'Event type','type'=>'text','required'=>true],
            ['name'=>'summary','label'=>'Event summary','type'=>'textarea','required'=>true],
            ['name'=>'menu','label'=>'Menu highlights','type'=>'textarea'],
            ['name'=>'result','label'=>'Outcome','type'=>'textarea'],
            ['name'=>'gallery','label'=>'Event gallery','type'=>'media_list'],
        ]);
        $ctx->contentType('catering_review', 'Verified Catering Reviews', [
            ['name'=>'customer_name','label'=>'Customer display name','type'=>'text','required'=>true],
            ['name'=>'event_type','label'=>'Event type','type'=>'text'],
            ['name'=>'rating','label'=>'Rating (1–5)','type'=>'number','required'=>true],
            ['name'=>'review','label'=>'Review','type'=>'textarea','required'=>true],
            ['name'=>'verified','label'=>'Verified event','type'=>'checkbox'],
            ['name'=>'portrait','label'=>'Customer or event image','type'=>'media'],
        ]);
        $ctx->adminPage('Menu details & pricing', '/admin/catering/menus', 'catering.manage', 'Catering', 'content');
        $ctx->adminPage('Package details & pricing', '/admin/catering/packages', 'catering.manage', 'Catering', 'products');
        $ctx->adminPage('Menu collections', '/admin/catering/menu-groups', 'catering.manage', 'Catering', 'patterns');
        $ctx->adminPage('Events & tastings', '/admin/catering/events', 'catering.manage', 'Catering', 'orders');
        $ctx->adminPage('Production catalog', '/admin/catering/production', 'catering.manage', 'Catering', 'operations');
        $ctx->adminPage('Catering settings', '/admin/catering/settings', 'catering.manage', 'Catering', 'settings');
        (new CatalogResources($ctx->service(Connection::class)))->register($ctx);

        $ctx->dashboardWidget('upcoming-events', 'Upcoming catering events', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            $count = $db->tableExists('catering_events') ? (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $db->table('catering_events') . '` WHERE state IN (?,?) AND (event_date IS NULL OR event_date>=?)',
                ['tentative','confirmed',date('Y-m-d')]
            ) : 0;
            $tasks = $db->tableExists('catering_event_tasks') ? (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM `' . $db->table('catering_event_tasks') . '` WHERE status IN (?,?)', ['open','in_progress']
            ) : 0;
            return ['value'=>$count,'detail'=>$tasks . ' open production task(s)'];
        }, 'catering.manage', '/admin/catering/events');

        $ctx->adminSearchProvider(function (string $query) use ($ctx): array {
            $db = $ctx->service(Connection::class);
            if (!$db->tableExists('catering_events')) return [];
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], trim($query)) . '%';
            return array_map(static fn(array $row): array => [
                'type'=>'Catering event','title'=>(string)$row['title'],
                'detail'=>trim((string)($row['event_date']??'') . ' · ' . ucfirst((string)$row['state'])),
                'url'=>'/admin/catering/events/' . (int)$row['id'],
            ], $db->fetchAll('SELECT id,title,event_date,state FROM `' . $db->table('catering_events') . '` WHERE title LIKE ? ESCAPE \'\\\\\' ORDER BY event_date,id LIMIT 10', [$like]));
        }, 'catering.manage');

        $ctx->healthCheck('inquiries-table', 'Inquiry storage', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            if (!$db->tableExists('catering_inquiries')) {
                return ['status' => 'error', 'detail' => 'Required catering questionnaire table is missing'];
            }
            $pending = CateringController::pendingCoreLinks($db);
            return $pending === 0
                ? ['status' => 'ok', 'detail' => 'Questionnaires linked to the Core inquiry workspace']
                : ['status' => 'warn', 'detail' => $pending . ' questionnaire(s) are waiting to link to Core'];
        });

        $ctx->navigationMenu([
            ['label' => 'Our Story', 'url' => '/about'],
            ['label' => 'Menus', 'url' => '/menus'],
            ['label' => 'Services', 'url' => '/services'],
            ['label' => 'Packages', 'url' => '/packages'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Venues', 'url' => '/venues'],
            ['label' => 'Event Stories', 'url' => '/event-stories'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);
        $ctx->navigationMenu([
            ['label' => 'Menus', 'url' => '/menus'],
            ['label' => 'Services', 'url' => '/services'],
            ['label' => 'Packages', 'url' => '/packages'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Venue Partners', 'url' => '/venues'],
            ['label' => 'Event Stories', 'url' => '/event-stories'],
            ['label' => 'Service Area', 'url' => '/service-area'],
            ['label' => 'FAQ', 'url' => '/faq'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Request a Quote', 'url' => '/request-a-quote'],
        ], 'footer', 'Footer navigation');
        $ctx->brandAssets([
            'logo' => 'assets/images/catering-co-logo.png',
        ]);

        // Import legacy inquiries in bounded batches through the supported
        // Core service. The local questionnaire remains intact for detailed
        // event fields and makes retries lossless.
        $ctl()->synchronizeLegacyInquiries();
    }
}
