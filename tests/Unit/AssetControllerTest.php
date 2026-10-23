<?php
declare(strict_types=1);

namespace Tests\Unit;

use Admin\Controllers\AssetController;
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
}
