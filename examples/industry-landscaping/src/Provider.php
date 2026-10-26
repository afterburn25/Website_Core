<?php
declare(strict_types=1);

namespace CajunWebPro\Landscaping;

use Core\Database\Connection;
use Core\Modules\ModuleContext;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new LandscapingController($ctx, $ns);

        $ctx->route('GET', '/', fn ($r) => $ctl()->home($r));
        $ctx->route('GET', '/services', fn ($r) => $ctl()->services($r));
        $ctx->route('GET', '/services/{slug:[a-z0-9-]+}', fn ($r) => $ctl()->serviceDetail($r));
        $ctx->route('GET', '/projects', fn ($r) => $ctl()->projects($r));
        $ctx->route('GET', '/maintenance-plans', fn ($r) => $ctl()->plans($r));
        $ctx->route('GET', '/service-areas', fn ($r) => $ctl()->serviceAreas($r));
        $ctx->route('GET', '/seasonal-services', fn ($r) => $ctl()->seasonal($r));
        $ctx->route('GET', '/about', fn ($r) => $ctl()->about($r));
        $ctx->route('GET', '/contact', fn ($r) => $ctl()->contact($r));
        $ctx->route('GET', '/request-estimate', fn ($r) => $ctl()->estimate($r));
        $ctx->route('POST', '/request-estimate', fn ($r) => $ctl()->submitEstimate($r));

        foreach (['services', 'projects', 'plans', 'areas', 'seasonal', 'estimates', 'settings'] as $screen) {
            $ctx->adminRoute('GET', '/admin/landscaping/' . $screen, fn ($r) => $ctl()->admin($r));
            $ctx->adminRoute('POST', '/admin/landscaping/' . $screen, fn ($r) => $ctl()->adminSave($r));
        }
        $ctx->adminRoute('GET', '/admin/landscaping/estimate-photo/{id:\\d+}', fn ($r) => $ctl()->estimatePhoto($r));

        $ctx->permission('landscaping.manage', 'Manage landscaping services, projects, plans, areas, and settings');
        $ctx->permission('landscaping.estimates', 'View and manage landscaping estimate requests');
        $ctx->adminPage('Services', '/admin/landscaping/services', 'landscaping.manage', 'Landscaping');
        $ctx->adminPage('Projects', '/admin/landscaping/projects', 'landscaping.manage', 'Landscaping');
        $ctx->adminPage('Maintenance plans', '/admin/landscaping/plans', 'landscaping.manage', 'Landscaping');
        $ctx->adminPage('Service areas', '/admin/landscaping/areas', 'landscaping.manage', 'Landscaping');
        $ctx->adminPage('Seasonal services', '/admin/landscaping/seasonal', 'landscaping.manage', 'Landscaping');
        $ctx->adminPage('Estimate leads', '/admin/landscaping/estimates', 'landscaping.estimates', 'Landscaping');
        $ctx->adminPage('Settings', '/admin/landscaping/settings', 'landscaping.manage', 'Landscaping');

        $ctx->dashboardWidget('new-estimates', 'New landscaping estimates', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            return ['value'=>(int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('landscaping_estimates') . '` WHERE status = ?', ['new']), 'detail'=>'Awaiting review'];
        }, 'landscaping.estimates', '/admin/landscaping/estimates');
        $ctx->adminSearchProvider(function (string $query) use ($ctx): array {
            $db = $ctx->service(Connection::class);
            $like = '%' . $query . '%';
            return array_map(fn (array $row): array => ['type'=>'Landscaping lead','title'=>$row['name'],'detail'=>$row['email'].' · '.$row['status'],'url'=>'/admin/landscaping/estimates'], $db->fetchAll('SELECT name,email,status FROM `' . $db->table('landscaping_estimates') . '` WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 10', [$like,$like]));
        }, 'landscaping.estimates');
        $ctx->healthCheck('landscaping-tables', 'Landscaping data storage', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            return $db->tableExists('landscaping_services') && $db->tableExists('landscaping_estimates') ? ['status'=>'ok','detail'=>'Ready'] : ['status'=>'error','detail'=>'Required tables are missing'];
        });

        $ctx->navigationMenu([
            ['label'=>'Home','url'=>'/'],
            ['label'=>'Services','url'=>'/services','children'=>[
                ['label'=>'Lawn Maintenance','url'=>'/services/lawn-maintenance'],
                ['label'=>'Landscape Installation','url'=>'/services/landscape-installation'],
                ['label'=>'Beds & Mulch','url'=>'/services/beds-and-mulch'],
                ['label'=>'Property Cleanup','url'=>'/services/property-cleanup'],
            ]],
            ['label'=>'Projects','url'=>'/projects'],
            ['label'=>'Maintenance Plans','url'=>'/maintenance-plans'],
            ['label'=>'Service Areas','url'=>'/service-areas'],
            ['label'=>'Contact','url'=>'/contact'],
        ]);
        $ctx->navigationMenu([
            ['label'=>'Services','url'=>'/services'],['label'=>'Projects','url'=>'/projects'],
            ['label'=>'Maintenance Plans','url'=>'/maintenance-plans'],['label'=>'Seasonal Services','url'=>'/seasonal-services'],
            ['label'=>'Service Areas','url'=>'/service-areas'],['label'=>'About','url'=>'/about'],
            ['label'=>'Contact','url'=>'/contact'],['label'=>'Request an Estimate','url'=>'/request-estimate'],
        ], 'footer', 'Footer navigation');
        $ctx->brandAssets(['logo'=>'assets/images/landscaping-mark.png']);
    }
}
