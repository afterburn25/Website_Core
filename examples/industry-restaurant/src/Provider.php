<?php
declare(strict_types=1);

namespace Wcref\Restaurant;

use Core\Modules\ModuleContext;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new MenuController($ctx, $ns);

        $ctx->route('GET', '/menu', fn ($r) => $ctl()->publicMenu($r));

        $ctx->adminRoute('GET', '/admin/restaurant', fn ($r) => $ctl()->adminIndex($r));
        $ctx->adminRoute('POST', '/admin/restaurant', fn ($r) => $ctl()->adminSave($r));

        $ctx->permission('restaurant.manage', 'Manage restaurant menus');
        $ctx->adminPage('Restaurant', '/admin/restaurant', 'restaurant.manage');

        // Daily specials use the generic content-type surface rather than a
        // custom table — core validates the declared fields and generates
        // the admin CRUD; this package just reads items back out.
        $ctx->contentType('special', 'Daily Specials', [
            ['name' => 'dish', 'label' => 'Dish', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Price', 'type' => 'number'],
            ['name' => 'available', 'label' => 'Available today', 'type' => 'checkbox'],
        ]);
    }
}
