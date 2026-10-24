<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Events\Dispatcher;
use Core\Modules\ModuleContext;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;

final class ModuleContextTest extends TestCase
{
    public function testAssetBaseUsesInstalledDirectoryNamespace(): void
    {
        $context = new ModuleContext(
            'cajunwebpro/catering',
            new Router(),
            new Dispatcher(),
            assetNamespace: 'industry-catering',
        );

        $this->assertSame(
            '/catering/index.php?p=/_a/modules/industry-catering',
            $context->assetBase('/catering')
        );
        $this->assertSame('/catering/index.php?p=', $context->routeBase('/catering'));
        $this->assertSame(
            '/catering/index.php?p=/menus/plated&guest_count=40',
            $context->url('/catering', '/menus/plated', ['guest_count' => 40])
        );
    }

    public function testAdminPagesCarryTheirPackageCategory(): void
    {
        $context = new ModuleContext(
            'cajunwebpro/catering',
            new Router(),
            new Dispatcher(),
        );
        $context->adminPage('Menus', '/admin/catering/menus', 'catering.manage', 'Catering');

        $items = $context->adminNavItems();
        $this->assertSame('Catering', $items[0]['category']);
        $this->assertSame('cajunwebpro/catering', $items[0]['package']);
    }

    public function testSharedAdminHooksRemainPackageAttributed(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $context->dashboardWidget('open', 'Open records', fn () => 3, 'demo.view', '/admin/demo');
        $context->adminSearchProvider(fn (string $q) => [['title' => $q, 'url' => '/admin/demo']], 'demo.view');
        $context->healthCheck('tables', 'Tables', fn () => ['status' => 'ok']);

        $this->assertSame('open', $context->dashboardWidgets()[0]['key']);
        $this->assertSame('demo.view', $context->searchProviders()[0]['capability']);
        $this->assertSame('tables', $context->healthChecks()[0]['key']);
    }

    public function testModuleCanDeclareItsDefaultNavigationWithoutCoreServices(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $context->navigationMenu([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Services', 'url' => '/services', 'children' => [
                ['label' => 'Events', 'url' => '/services/events', 'target' => 'tab'],
            ]],
        ]);

        $tree = $context->navigationTree();
        $this->assertSame(['Home', 'Services'], array_column($tree, 'label'));
        $this->assertSame('Events', $tree[1]['children'][0]['label']);
        $this->assertSame('acme/demo', $context->packageId());

        $context->navigationMenu([
            ['label' => 'Privacy', 'url' => '/privacy'],
        ], 'footer', 'Footer navigation');
        $this->assertSame(['Privacy'], array_column($context->navigationTree('footer'), 'label'));
        $this->assertArrayHasKey('footer', $context->navigationMenus());
    }

    public function testModuleCanDeclareBrandDefaultsWithoutOwningCoreSettings(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $context->brandAssets([
            'logo' => 'assets/images/logo.png',
            'favicon' => 'assets/images/favicon.ico',
        ]);

        $this->assertSame([
            'logo' => 'assets/images/logo.png',
            'favicon' => 'assets/images/favicon.ico',
        ], $context->declaredBrandAssets());
    }
}
