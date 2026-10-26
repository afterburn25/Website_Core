<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NavigationEditorTest extends TestCase
{
    public function testEditorExposesHeaderAndFooterLocationsAndPreservesSelection(): void
    {
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/NavigationController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/navigation/index.php');

        $this->assertStringContainsString("['primary', 'footer']", $controller);
        $this->assertStringContainsString('itemBelongsToMenu', $controller);
        $this->assertStringContainsString("/admin/navigation?location=", $controller);
        $this->assertStringContainsString("'label' => 'Header menu'", $controller);
        $this->assertStringContainsString("'label' => 'Footer menu'", $controller);
        $this->assertStringContainsString('$locationMeta', $view);
        $this->assertStringContainsString('name="location"', $view);
        $this->assertStringContainsString('menu-location-tabs', $view);
        $this->assertStringContainsString('data-menu-editor', $view);
        $this->assertStringContainsString('data-root-drop', $view);
        $this->assertStringContainsString('Visible on website', $view);
        $this->assertStringContainsString('data-reset-tree', $view);
        $this->assertStringContainsString('/admin/seo', $view);
        $this->assertStringNotContainsString('name="action" value="redirect_save"', $view);

        $script = (string) file_get_contents(WC_ROOT . '/assets/js/admin.js');
        $this->assertStringContainsString("ratio < .28 ? 'before'", $script);
        $this->assertStringContainsString("ratio > .72 ? 'after'", $script);
        $this->assertStringContainsString('Save or discard the menu structure', $script);

        $public = (string) file_get_contents(WC_ROOT . '/admin/controllers/PublicPageController.php');
        $this->assertStringContainsString("'target' => \$i['target']", $public);
        $this->assertStringContainsString("itemsFor('primary', true)", $public);
    }
}
