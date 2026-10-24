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
        foreach (['Dashboard','Content','Media','Navigation Menu','Forms','SEO','Appearance','Privacy','Integrations','Administrators','Security','Modules','Backups','Operations','System health','Core updates','Audit log','Settings'] as $title) {
            $meta = PageCatalog::for($title);
            $this->assertNotSame('Manage this part of your website.', $meta['description'], $title);
            $this->assertStringContainsString('<svg', AdminIcon::svg($meta['icon']));
        }
    }

    public function testSharedAdminInteractionsCoverSafetyAndSubfolderNavigation(): void
    {
        $layout = (string) file_get_contents(WC_ROOT . '/admin/views/layouts/admin.php');
        $script = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');
        $this->assertStringContainsString("rtrim(\$siteBasePath, '/')", $layout);
        $this->assertStringContainsString('event.submitter?.dataset?.confirm', $script);
        $this->assertStringContainsString('[data-copy-previous]', $script);
        $this->assertStringContainsString('responsive-table', $script);
    }
}
