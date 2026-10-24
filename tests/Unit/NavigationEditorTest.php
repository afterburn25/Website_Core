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
        $this->assertStringContainsString('Header menu', $view);
        $this->assertStringContainsString('Footer menu', $view);
        $this->assertStringContainsString('name="location"', $view);
        $this->assertStringContainsString('menu-location-tabs', $view);
    }
}
