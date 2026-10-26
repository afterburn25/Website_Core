<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Branding\StatusPage;
use PHPUnit\Framework\TestCase;

final class StatusPageTest extends TestCase
{
    public function testRendersOfflineSafeBrandedMaintenancePageWithDiscreteAdminEntry(): void
    {
        $html = StatusPage::render(503, 'Updating', 'Back soon', '/catering/index.php?p=', 'Demo Site', null, 'Check again', '');
        self::assertStringContainsString('<svg viewBox="0 0 680 680"', $html);
        self::assertStringContainsString('/catering/index.php?p=/admin/login', $html);
        self::assertStringContainsString('aria-label="Administrator sign in"', $html);
        self::assertStringContainsString('/catering/assets/images/cajunwebpro-logo.png', $html);
        self::assertStringContainsString('prefers-reduced-motion', $html);
        self::assertStringContainsString('noindex,nofollow', $html);
    }

    public function testEscapesContentAndShowsSupportReference(): void
    {
        $html = StatusPage::render(500, '<broken>', 'Unsafe & detail', '', 'Site', 'abc123');
        self::assertStringNotContainsString('<broken>', $html);
        self::assertStringContainsString('&lt;broken&gt;', $html);
        self::assertStringContainsString('abc123', $html);
    }
}
