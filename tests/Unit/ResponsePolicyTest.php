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

    public function testFileResponseKeepsPayloadOutOfMemoryUntilRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wc-response-');
        file_put_contents($path, 'archive-bytes');
        try {
            $response = Response::file($path)->header('Content-Type', 'application/zip');
            $this->assertSame('archive-bytes', $response->body());
            $this->assertSame('application/zip', $response->headers()['Content-Type']);
        } finally {
            @unlink($path);
        }
    }
}
