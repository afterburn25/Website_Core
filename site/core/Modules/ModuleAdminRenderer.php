<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Admin\Nav;
use Core\Auth\AuthService;
use Core\Branding\BrandAssets;
use Core\Container;
use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Csrf;
use Core\Settings\SettingsRepository;
use Core\System\Version;
use Core\View\View;

/** Shared authenticated admin-shell renderer for package controllers. */
final class ModuleAdminRenderer
{
    public function __construct(private Container $container) {}

    public function render(Request $request, string $packageId, string $view, array $data, string $title, string $capability): Response
    {
        $auth = $this->container->get(AuthService::class);
        $user = $auth->user();
        if ($user === null || !$auth->has($user, $capability)) {
            return Response::html(\Core\Branding\StatusPage::render(
                403, 'This area is not available to you.',
                'Your account does not have permission for this module action.',
                $request->routeBase(), (string) $this->container->get(SettingsRepository::class)->get('site.name', 'Website'),
                null, 'Back to dashboard', '/admin'
            ), 403);
        }
        $expected = 'mod-' . str_replace(['/', '_'], '-', $packageId) . ':';
        if (!str_starts_with($view, $expected)) throw new \InvalidArgumentException('Module admin views must use their registered namespace.');

        $v = $this->container->get(View::class);
        $settings = $this->container->get(SettingsRepository::class);
        $registry = $this->container->get(ModuleRegistry::class);
        $brand = $this->container->get(BrandAssets::class);
        $versions = $this->container->get(Version::class);
        foreach ([
            'user' => $user, 'csrf' => $this->container->get(Csrf::class),
            'basePath' => $request->routeBase(), 'siteBasePath' => rtrim($request->basePath, '/'),
            'currentPath' => $request->path, 'nav' => (new Nav($registry, $auth))->visible($user),
            'pageTitle' => $title, 'pageMeta' => \Core\Admin\PageCatalog::for($title),
            'siteName' => $settings->get('site.name', 'Website Core'),
            'brandLogoUrl' => $brand->url($request, 'logo', 480),
            'faviconUrl' => $brand->url($request, 'favicon'),
            'coreVersion' => $versions->core(), 'industryVersions' => $versions->industries(),
            'noticeCount' => $this->container->get(\Core\Admin\NoticeCenter::class)->countFor($user->id),
            'flash' => $_SESSION['_wc_flash'] ?? null,
        ] as $key => $value) $v->share($key, $value);
        unset($_SESSION['_wc_flash']);
        return Response::html($v->renderInLayout($view, 'admin:layouts/admin', $data));
    }
}
