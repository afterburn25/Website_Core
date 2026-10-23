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
}
