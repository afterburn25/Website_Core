<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ModuleCommercialCompletionTest extends TestCase
{
    public function testProtectedCoreRecoveryAndPackageFiltersRemainVisible(): void
    {
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/ModulesController.php');
        $manager = (string) file_get_contents(WC_ROOT . '/core/Modules/PackageManager.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/modules/index.php');
        $this->assertStringContainsString('LOCK_EX', $controller);
        $this->assertStringContainsString('Recovery mode flag could not be removed', $controller);
        $this->assertStringContainsString('Package id is invalid', $manager);
        $this->assertStringContainsString('Required system component', $view);
        $this->assertStringContainsString('data-package-filter', $view);
    }
}
