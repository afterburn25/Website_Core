<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponsePolicyTest extends TestCase
{
    public function testInternalCacheMarkerCanBeRemovedCaseInsensitively(): void
    {
        $response = Response::html('<p>safe</p>')
            ->header('X-WC-Cacheable', 'public')
            ->removeHeader('x-wc-cacheable');

        $this->assertArrayNotHasKey('X-WC-Cacheable', $response->headers());
        $this->assertSame('text/html; charset=utf-8', $response->headers()['Content-Type']);
    }

    public function testPublicCachePolicyRequiresControllerOptIn(): void
    {
        $application = (string) file_get_contents(WC_CORE . '/Application.php');
        $pageController = (string) file_get_contents(WC_ROOT . '/admin/controllers/PublicPageController.php');

        $this->assertStringContainsString("['X-WC-Cacheable']", $application);
        $this->assertStringContainsString("header('Cache-Control', 'private, no-store')", $application);
        $this->assertStringContainsString("header('X-WC-Cacheable', 'public')", $pageController);
    }
}
