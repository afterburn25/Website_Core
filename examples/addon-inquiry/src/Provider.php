<?php
declare(strict_types=1);

namespace Wcref\Inquiry;

use Core\Modules\ModuleContext;

final class Provider
{
    public function register(ModuleContext $ctx): void
    {
        $ns = $ctx->views(dirname(__DIR__) . '/views');
        $ctl = fn () => new InquiryController($ctx, $ns);

        // Public form + submit (CSRF validated inside the handler).
        $ctx->route('GET', '/inquiry', fn ($r) => $ctl()->form($r));
        $ctx->route('POST', '/inquiry', fn ($r) => $ctl()->submit($r));

        // Admin list, wrapped in the core auth+CSRF pipeline.
        $ctx->adminRoute('GET', '/admin/inquiries', fn ($r) => $ctl()->adminList($r));

        $ctx->permission('inquiry.view', 'View inquiry submissions');
        $ctx->adminPage('Inquiries', '/admin/inquiries', 'inquiry.view');
    }
}
