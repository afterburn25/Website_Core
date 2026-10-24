<?php
declare(strict_types=1);

namespace Tests\Unit;

use Admin\Controllers\AssetController;
use Core\Container;
use Core\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AssetControllerTest extends TestCase
{
    public function testRelativeCssResourcesUseRewriteFreeAssetRoute(): void
    {
        $method = new ReflectionMethod(AssetController::class, 'rewriteCssUrls');
        $css = '.hero{background:url(\'../images/hero.webp\')}'
            . '.remote{background:url("https://example.test/image.webp")}'
            . '.inline{background:url(data:image/png;base64,abc)}';

        $rewritten = $method->invoke(
            null,
            $css,
            'modules/industry-catering/assets/css/catering.css'
        );

        self::assertStringContainsString(
            'url("?p=/_a/modules/industry-catering/assets/images/hero.webp&v=' . WC_VERSION . '")',
            $rewritten
        );
        self::assertStringContainsString('url("https://example.test/image.webp")', $rewritten);
        self::assertStringContainsString('url(data:image/png;base64,abc)', $rewritten);
    }

    public function testCoreAdminAssetsUseTheConstrainedAssetRoute(): void
    {
        $request = new Request('GET', '/_a/core/css/admin.css', '', [], [], [], [], []);
        $request->attributes['path'] = 'core/css/admin.css';

        $response = (new AssetController(new Container()))->serve($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('--admin-sidebar', $response->body());
        self::assertSame('text/css', $response->headers()['Content-Type'] ?? null);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options'] ?? null);
    }
}
