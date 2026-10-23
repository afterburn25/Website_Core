<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Branding\Attribution;
use PHPUnit\Framework\TestCase;

final class AttributionTest extends TestCase
{
    public function testRequiredAttributionIsInjectedOnceWithBothLinks(): void
    {
        $service = new Attribution();
        $html = $service->apply('<html><body><main>Site</main></body></html>');
        $this->assertSame(2, substr_count($html, 'href="https://cajunwebpro.com"'));
        $this->assertStringContainsString('Cajun Web Pro LLC', $html);
        $this->assertStringContainsString('Let’s build yours.', $html);
        $this->assertSame($html, $service->apply($html));
    }
}
