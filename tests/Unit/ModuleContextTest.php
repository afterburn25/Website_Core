<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Events\Dispatcher;
use Core\Auth\Permissions;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Forms\FormService;
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
        $context->adminPage('Menus', '/admin/catering/menus', 'catering.manage', 'Catering', 'products');

        $items = $context->adminNavItems();
        $this->assertSame('Catering', $items[0]['category']);
        $this->assertSame('cajunwebpro/catering', $items[0]['package']);
        $this->assertSame('products', $items[0]['icon']);

        $context->adminPage('Settings', '/admin/catering/settings', 'catering.manage');
        $this->assertSame('modules', $context->adminNavItems()[1]['icon']);
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

    public function testModuleRegistrationsRejectUnsafeOrDuplicateContributions(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $context->dashboardWidget('open-items', 'Open items', fn () => 2, 'demo.view', '/admin/demo');
        try {
            $context->dashboardWidget('open-items', 'Duplicate', fn () => 3);
            $this->fail('Duplicate dashboard key was accepted.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(\InvalidArgumentException::class);
        $context->navigationMenu([['label' => 'Unsafe', 'url' => 'javascript:alert(1)']]);
    }

    public function testBrandDefaultsMustRemainInsidePackage(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $this->expectException(\InvalidArgumentException::class);
        $context->brandAssets(['logo' => '../outside.png']);
    }

    public function testPackageCannotClaimAnotherCapabilityNamespace(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $this->expectException(\InvalidArgumentException::class);
        $context->permission('settings.manage', 'Attempt to claim a Core capability');
    }

    public function testModuleMustExplicitlyOptIntoOptionalCoreFeatures(): void
    {
        $permissions = new Permissions();
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher(), $permissions);
        $this->assertFalse($permissions->allows('admin', 'commerce.manage'));
        $this->assertFalse($permissions->allows('admin', 'customers.manage'));

        $context->commerce(['currency'=>'USD','fulfillment'=>['pickup','delivery']]);
        $context->customerAccounts(['registration'=>true,'account_label'=>'My account']);
        $context->businessProfile(['name'=>'Demo Company']);
        $context->leadManagement(['quotes'=>true,'label'=>'Sales']);

        $this->assertArrayHasKey('commerce.catalog', $context->coreFeatures());
        $this->assertArrayHasKey('commerce.orders', $context->coreFeatures());
        $this->assertArrayHasKey('accounts.customers', $context->coreFeatures());
        $this->assertArrayHasKey('business.profile', $context->coreFeatures());
        $this->assertArrayHasKey('crm.inquiries', $context->coreFeatures());
        $this->assertArrayHasKey('crm.quotes', $context->coreFeatures());
        $this->assertTrue($permissions->allows('admin', 'commerce.manage'));
        $this->assertTrue($permissions->allows('admin', 'orders.manage'));
        $this->assertTrue($permissions->allows('admin', 'customers.manage'));
        $this->assertTrue($permissions->allows('admin', 'inquiries.manage'));
        $this->assertTrue($permissions->allows('admin', 'quotes.manage'));
    }

    public function testModuleCanRegisterAReusableAdminResource(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $reorder = fn(array $ids) => null;
        $bulk = fn(array $ids, string $action) => true;
        $context->adminResource('services','Services','demo.manage',
            [['name'=>'name','label'=>'Name','required'=>true]],
            [['name'=>'name','label'=>'Name']],
            fn()=>[],fn(int $id)=>['id'=>$id,'name'=>'Example'],fn(?int $id,array $data)=>[$id??1,null],
            options: ['reorder'=>$reorder,'bulk'=>$bulk,'bulk_actions'=>['archive'=>'Archive']]
        );
        $resources=$context->adminResources();
        self::assertCount(1,$resources);
        $resource=array_values($resources)[0];
        self::assertSame('Services',$resource['label']);
        self::assertSame($reorder,$resource['reorder']);
        self::assertSame($bulk,$resource['bulk']);
        self::assertSame(['archive'=>'Archive'],$resource['bulk_actions']);
        self::assertSame('Services',$context->adminNavItems()[0]['label']);
    }

    public function testAdminResourceRejectsIncompleteBulkConfiguration(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $this->expectException(\InvalidArgumentException::class);
        $context->adminResource('services','Services','demo.manage',
            [['name'=>'name','label'=>'Name']], [['name'=>'name','label'=>'Name']],
            fn()=>[], fn(int $id)=>['id'=>$id], fn(?int $id,array $data)=>[$id??1,null],
            options: ['bulk_actions'=>['archive'=>'Archive']]
        );
    }

    public function testAdminResourceRejectsUnsafeBulkActionTokens(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $this->expectException(\InvalidArgumentException::class);
        $context->adminResource('services','Services','demo.manage',
            [['name'=>'name','label'=>'Name']], [['name'=>'name','label'=>'Name']],
            fn()=>[], fn(int $id)=>['id'=>$id], fn(?int $id,array $data)=>[$id??1,null],
            options: ['bulk'=>fn()=>true,'bulk_actions'=>['delete everything'=>'Delete']]
        );
    }

    public function testUnknownCoreFeatureIsRejected(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $this->expectException(\InvalidArgumentException::class);
        $context->coreFeature('commerce.imaginary');
    }

    public function testModuleCanDeclareAndLinkCoreOwnedStarterForm(): void
    {
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $events = new Dispatcher();
        $forms = new FormService($db, $events);
        $context = new ModuleContext(
            'acme/demo',
            new Router(),
            $events,
            serviceResolver: static fn (string $id): object => match ($id) {
                FormService::class => $forms,
                default => throw new \RuntimeException('Unexpected service ' . $id),
            },
        );
        $formId = $context->starterForm('contact_request', 'Contact request', [[
            'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
        ]]);
        self::assertIsInt($formId);
        self::assertSame($formId, $context->starterForms()['contact_request']);

        [$submissionId, $errors] = $forms->submit((int) $formId, ['email' => 'person@example.com'], 'hash');
        self::assertSame([], $errors);
        self::assertTrue($context->linkFormSubmission((int) $submissionId, 'lead', 'ABC-7'));
        self::assertSame('ABC-7', $context->formSubmission((int) $submissionId)['links'][0]['record_id']);
    }
}
