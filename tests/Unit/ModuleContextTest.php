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
            '/catering/_a/modules/industry-catering',
            $context->assetBase('/catering')
        );
    }
}
