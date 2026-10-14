<?php
declare(strict_types=1);

namespace Wcref\Salon;

use Core\Modules\ModuleContext;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new SalonController($ctx, $ns);

        $ctx->route('GET', '/services', fn ($r) => $ctl()->publicServices($r));

        $ctx->adminRoute('GET', '/admin/salon', fn ($r) => $ctl()->adminIndex($r));
        $ctx->adminRoute('POST', '/admin/salon', fn ($r) => $ctl()->adminSave($r));

        $ctx->permission('salon.manage', 'Manage salon services and staff');
        $ctx->adminPage('Salon', '/admin/salon', 'salon.manage');
    }
}
