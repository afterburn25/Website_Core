<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppearanceControllerTest extends TestCase
{
    public function testCoreBrandingContractIsWiredIntoAppearanceAndPublicTheme(): void
    {
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/AppearanceController.php');
        $public = (string) file_get_contents(WC_ROOT . '/admin/controllers/PublicPageController.php');
        $theme = (string) file_get_contents(WC_ROOT . '/themes/base/templates/layout.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/appearance/index.php');
        $admin = (string) file_get_contents(WC_ROOT . '/admin/views/layouts/admin.php');
        foreach (['brand.logo_media_id', 'brand.favicon_media_id', 'brand.social_media_id'] as $key) {
            $this->assertStringContainsString($key, $controller);
            $this->assertStringContainsString($key, $public);
        }
        $this->assertStringContainsString('brandLogoUrl', $theme);
        $this->assertStringContainsString('designTokens', $theme);
        $this->assertStringContainsString('logo_upload', $view);
        $this->assertStringContainsString('favicon_upload', $view);
        $this->assertStringContainsString('enctype="multipart/form-data"', $view);
        $this->assertStringContainsString('brand-logo', $admin);
        $this->assertStringContainsString('rel="icon"', $admin);
        $this->assertStringContainsString('footer.copyright', $controller);
        $this->assertStringContainsString('name="copyright"', $view);
        $this->assertStringContainsString('footerNavigation', $public);
        $this->assertStringContainsString('aria-label="Footer"', $theme);
    }
}
