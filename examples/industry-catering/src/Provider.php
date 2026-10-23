<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

use Core\Modules\ModuleContext;

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

        $ctx->adminRoute('GET', '/admin/catering', fn ($r) => $ctl()->adminDashboard($r));
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
        $ctx->adminPage('Catering', '/admin/catering', 'catering.manage');
        $ctx->adminPage('Catering Leads', '/admin/catering/inquiries', 'catering.inquiries');
    }
}
