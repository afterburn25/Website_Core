<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Modules\ModuleContext;
use Core\Database\Connection;
use Core\Commerce\ProductRepository;
use Core\Settings\SettingsRepository;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new CateringController($ctx, $ns);

        $ctx->route('GET', '/', fn ($r) => $ctl()->home($r));
        $ctx->route('GET', '/services', fn ($r) => $ctl()->services($r));
        $ctx->route('GET', '/services/{slug:weddings|corporate-events|private-celebrations|drop-off-catering}',
            fn ($r) => $ctl()->serviceDetail($r));
        $ctx->route('GET', '/menus', fn ($r) => $ctl()->menus($r));
        $ctx->route('GET', '/menus/{slug:[a-z0-9-]+}', fn ($r) => $ctl()->menuDetail($r));
        $ctx->route('GET', '/packages', fn ($r) => $ctl()->packages($r));
        $ctx->route('GET', '/packages/{slug:[a-z0-9-]+}', fn ($r) => $ctl()->packageDetail($r));
        $ctx->route('GET', '/gallery', fn ($r) => $ctl()->gallery($r));
        $ctx->route('GET', '/about', fn ($r) => $ctl()->about($r));
        $ctx->route('GET', '/service-area', fn ($r) => $ctl()->serviceArea($r));
        $ctx->route('GET', '/faq', fn ($r) => $ctl()->faq($r));
        $ctx->route('GET', '/contact', fn ($r) => $ctl()->contact($r));
        $ctx->route('POST', '/contact', fn ($r) => $ctl()->submitContact($r));
        $ctx->route('GET', '/request-a-quote', fn ($r) => $ctl()->quote($r));
        $ctx->route('POST', '/request-a-quote', fn ($r) => $ctl()->submitQuote($r));

        $ctx->adminRoute('GET', '/admin/catering/menus', fn ($r) => $ctl()->adminMenus($r));
        $ctx->adminRoute('POST', '/admin/catering/menus', fn ($r) => $ctl()->adminMenusSave($r));
        $ctx->adminRoute('GET', '/admin/catering/packages', fn ($r) => $ctl()->adminPackages($r));
        $ctx->adminRoute('POST', '/admin/catering/packages', fn ($r) => $ctl()->adminPackagesSave($r));
        $ctx->adminRoute('GET', '/admin/catering/inquiries', fn ($r) => $ctl()->adminInquiries($r));
        $ctx->adminRoute('POST', '/admin/catering/inquiries', fn ($r) => $ctl()->adminInquirySave($r));
        $ctx->adminRoute('GET', '/admin/catering/settings', fn ($r) => $ctl()->adminSettings($r));
        $ctx->adminRoute('POST', '/admin/catering/settings', fn ($r) => $ctl()->adminSettingsSave($r));

        $ctx->permission('catering.manage', 'Manage catering menus, packages, and settings');
        $ctx->permission('catering.inquiries', 'View and manage catering inquiries');
        $ctx->commerce([
            'store_name' => 'Catering Co. Store',
            'currency' => 'USD',
            'fulfillment' => ['pickup', 'delivery'],
        ]);
        $ctx->customerAccounts([
            'registration' => true,
            'account_label' => 'My catering account',
        ]);
        $this->seedCommerceProducts($ctx);
        $ctx->adminPage('Menus', '/admin/catering/menus', 'catering.manage', 'Catering');
        $ctx->adminPage('Packages', '/admin/catering/packages', 'catering.manage', 'Catering');
        $ctx->adminPage('Leads & inquiries', '/admin/catering/inquiries', 'catering.inquiries', 'Catering');
        $ctx->adminPage('Catering settings', '/admin/catering/settings', 'catering.manage', 'Catering');

        // Package data joins shared Core admin surfaces through stable API
        // hooks; the industry module never patches Core controllers/views.
        $ctx->dashboardWidget('new-inquiries', 'New catering inquiries', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            return [
                'value' => (int) $db->fetchColumn(
                    'SELECT COUNT(*) FROM `' . $db->table('catering_inquiries') . '` WHERE status = ?', ['new']
                ),
                'detail' => 'Awaiting review',
            ];
        }, 'catering.inquiries', '/admin/catering/inquiries');
        $ctx->adminSearchProvider(function (string $query) use ($ctx): array {
            $db = $ctx->service(Connection::class);
            $like = '%' . $query . '%';
            return array_map(fn (array $row): array => [
                'type' => 'Catering lead', 'title' => $row['name'],
                'detail' => $row['email'] . ' · ' . $row['status'],
                'url' => '/admin/catering/inquiries',
            ], $db->fetchAll(
                'SELECT name, email, status FROM `' . $db->table('catering_inquiries')
                . '` WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 10', [$like, $like]
            ));
        }, 'catering.inquiries');
        $ctx->healthCheck('inquiries-table', 'Inquiry storage', function () use ($ctx): array {
            $db = $ctx->service(Connection::class);
            return $db->tableExists('catering_inquiries')
                ? ['status' => 'ok', 'detail' => 'Ready']
                : ['status' => 'error', 'detail' => 'Required table is missing'];
        });

        $ctx->navigationMenu([
            ['label' => 'Our Story', 'url' => '/about'],
            ['label' => 'Menus', 'url' => '/menus'],
            ['label' => 'Services', 'url' => '/services'],
            ['label' => 'Packages', 'url' => '/packages'],
            ['label' => 'Order Online', 'url' => '/store'],
            ['label' => 'My Account', 'url' => '/account'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);
        $ctx->navigationMenu([
            ['label' => 'Menus', 'url' => '/menus'],
            ['label' => 'Services', 'url' => '/services'],
            ['label' => 'Packages', 'url' => '/packages'],
            ['label' => 'Order Online', 'url' => '/store'],
            ['label' => 'My Account', 'url' => '/account'],
            ['label' => 'Gallery', 'url' => '/gallery'],
            ['label' => 'Service Area', 'url' => '/service-area'],
            ['label' => 'FAQ', 'url' => '/faq'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Request a Quote', 'url' => '/request-a-quote'],
        ], 'footer', 'Footer navigation');
        $ctx->brandAssets([
            'logo' => 'assets/images/catering-co-logo.png',
        ]);
    }

    /** Import the module's Cajun demo menu once; later Core edits always win. */
    private function seedCommerceProducts(ModuleContext $ctx): void
    {
        $settings=$ctx->service(SettingsRepository::class);
        $marker='commerce.products_seeded.'.str_replace('/','.',$ctx->packageId());
        if ((bool)$settings->get($marker,false)) return;
        $products=$ctx->service(ProductRepository::class);
        if ($products->all($ctx->packageId())===[]) {
            $db=$ctx->service(Connection::class);
            foreach($db->fetchAll('SELECT slug,name,description,price_cents,image,featured,visible,sort_order FROM `'.$db->table('catering_menu_items').'` ORDER BY sort_order,name') as $item) {
                [$id,$error]=$products->save([
                    'name'=>$item['name'],
                    'sku'=>'CAT-'.strtoupper(substr(preg_replace('/[^a-z0-9]+/i','-',(string)$item['slug']),0,90)),
                    'slug'=>$item['slug'],
                    'description'=>$item['description'],
                    'price'=>number_format((int)$item['price_cents']/100,2,'.',''),
                    'currency'=>'USD',
                    'image_url'=>!empty($item['image'])?'index.php?p=/_a/modules/industry-catering/assets/images/menu/'.rawurlencode((string)$item['image']):'',
                    'active'=>(int)$item['visible']===1?'1':'',
                    'featured'=>(int)$item['featured']===1?'1':'',
                    'sort_order'=>(int)$item['sort_order'],
                ],$ctx->packageId());
                if ($id===null || $error!==null) throw new \RuntimeException('Unable to seed Core commerce product: '.($error??'unknown error'));
            }
        }
        $settings->set($marker,true);
    }
}
