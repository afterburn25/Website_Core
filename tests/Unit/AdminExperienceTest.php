<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Admin\AdminIcon;
use Core\Admin\PageCatalog;
use PHPUnit\Framework\TestCase;

final class AdminExperienceTest extends TestCase
{
    public function testEveryCoreDestinationHasPurposefulPageMetadata(): void
    {
        foreach (['Dashboard','Content','Media','Navigation Menu','Forms','SEO','Appearance','Geo service area','Audience locations','Privacy','Integrations','Administrators','Security','Modules','Backups','Operations','System health','Core updates','Audit log','Settings'] as $title) {
            $meta = PageCatalog::for($title);
            $this->assertNotSame('Manage this part of your website.', $meta['description'], $title);
            $this->assertStringContainsString('<svg', AdminIcon::svg($meta['icon']));
        }
    }

    public function testGeoServiceAreaUsesLocationAndMileageFieldsInsteadOfDateRange(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/business/service-area.php');
        $nav = (string) file_get_contents(WC_ROOT . '/core/Admin/Nav.php');
        $application = (string) file_get_contents(WC_ROOT . '/core/Application.php');
        $analytics = (string) file_get_contents(WC_ROOT . '/admin/views/analytics/geography.php');

        $this->assertStringContainsString('name="service_area_center_city"', $view);
        $this->assertStringContainsString('name="service_area_center_region"', $view);
        $this->assertStringContainsString('name="service_area_radius_miles"', $view);
        $this->assertStringContainsString('Range from city (miles)', $view);
        $this->assertStringNotContainsString('type="date"', $view);
        $this->assertStringContainsString("'/admin/service-area', 'Geo service area'", $nav);
        $this->assertStringContainsString("'/service-area'", $application);
        $this->assertStringContainsString('Audience locations', $analytics);
    }

    public function testSharedAdminInteractionsCoverSafetyAndSubfolderNavigation(): void
    {
        $layout = (string) file_get_contents(WC_ROOT . '/admin/views/layouts/admin.php');
        $css = (string) file_get_contents(WC_ROOT . '/assets/css/admin.css');
        $script = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');
        $this->assertStringContainsString("rtrim(\$siteBasePath, '/')", $layout);
        $this->assertStringContainsString('Website Administration</span>', $layout);
        $this->assertStringNotContainsString('class="brand-name"', $layout);
        $this->assertStringContainsString('width: min(100%, 10.5rem)', $css);
        $this->assertStringContainsString('background: transparent', (string) file_get_contents(
            dirname(WC_ROOT) . '/examples/industry-catering/assets/css/catering.css'
        ));
        $this->assertStringContainsString('event.submitter?.dataset?.confirm', $script);
        $this->assertStringContainsString('[data-copy-previous]', $script);
        $this->assertStringContainsString('responsive-table', $script);

        $mediaView = (string) file_get_contents(WC_ROOT . '/admin/views/media/index.php');
        $mediaController = (string) file_get_contents(WC_ROOT . '/admin/controllers/MediaController.php');
        $this->assertStringContainsString('data-file-selection', $mediaView);
        $this->assertStringContainsString('data-selection-action', $mediaView);
        $this->assertStringContainsString('data-focal-editor', $mediaView);
        $this->assertStringContainsString('media-usage-list', $mediaView);
        $this->assertStringContainsString("'private, no-store'", $mediaController);
        $this->assertStringContainsString("has(\$user, 'media.manage')", $mediaController);
        $this->assertStringContainsString('[data-focal-editor]', $script);
        $this->assertStringContainsString('.focal-preview', $css);

        $seo = (string) file_get_contents(WC_ROOT . '/admin/views/seo/index.php');
        $this->assertStringContainsString('Dismiss without redirect', $seo);
        $this->assertStringContainsString('Core assets and administration routes are excluded', $seo);
        $this->assertStringContainsString("e(\$issue['help'])", $seo);
        $this->assertStringContainsString('.seo-resolution-form', $css);

        $modules = (string) file_get_contents(WC_ROOT . '/admin/views/modules/index.php');
        $this->assertStringContainsString('Files changed outside Module Manager.', $modules);
        $this->assertStringContainsString('Finalize update', $modules);

        $updates = (string) file_get_contents(WC_ROOT . '/admin/views/system/updates.php');
        $updatesController = (string) file_get_contents(WC_ROOT . '/admin/controllers/UpdatesController.php');
        $this->assertStringContainsString('Update workspace needs attention.', $updates);
        $this->assertStringContainsString('GET never opens or parses a staged ZIP', $updatesController);
        $this->assertStringContainsString('UpdatesController', (string) file_get_contents(
            WC_ROOT . '/core/Application.php'
        ));
        $bootstrap = (string) file_get_contents(WC_ROOT . '/includes/bootstrap.php');
        $this->assertStringContainsString('runtime-code-cache.php', $bootstrap);
        $this->assertStringContainsString('wc_refresh_runtime_code_cache', $bootstrap);
        $this->assertStringContainsString('runtime-code-loader.php', $bootstrap);
        $this->assertStringContainsString('wc_runtime_code_mirror', $bootstrap);
        $this->assertStringContainsString('wc_runtime_code_mirror', (string) file_get_contents(
            WC_ROOT . '/includes/autoload.php'
        ));
        $this->assertStringContainsString("defined('WC_ROOT') ? WC_ROOT", (string) file_get_contents(
            WC_ROOT . '/includes/autoload.php'
        ));
        $this->assertStringContainsString('$root . \'/core/\'', (string) file_get_contents(
            WC_ROOT . '/includes/autoload.php'
        ));
        $this->assertStringContainsString('$root . \'/admin/\'', (string) file_get_contents(
            WC_ROOT . '/includes/autoload.php'
        ));
        $this->assertFileExists(WC_ROOT . '/errors/500.html');
        $this->assertStringContainsString('ErrorDocument 500', (string) file_get_contents(
            WC_ROOT . '/includes/server-error-pages.php'
        ));
    }

    public function testIndustryThemesUseVisualCardsPaletteDropdownsAndDirectActivation(): void
    {
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/appearance/index.php');
        $script = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');

        $this->assertStringContainsString('Choose your website design', $view);
        $this->assertStringContainsString('module-theme-thumbnail', $view);
        $this->assertStringContainsString('data-theme-palette', $view);
        $this->assertStringContainsString('name="operation" value="activate"', $view);
        $this->assertStringContainsString('Activate theme', $view);
        $this->assertStringNotContainsString('Module theme collections', $view);
        $this->assertStringContainsString('[data-theme-palette]', $script);
    }
}
