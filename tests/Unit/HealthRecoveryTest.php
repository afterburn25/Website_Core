<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HealthRecoveryTest extends TestCase
{
    public function testHealthUsesAnIsolatedFailSafeController(): void
    {
        $application = (string) file_get_contents(WC_ROOT . '/core/Application.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/HealthController.php');
        $legacy = (string) file_get_contents(WC_ROOT . '/admin/controllers/SystemToolsController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/system/health.php');

        self::assertStringContainsString('new \\Admin\\Controllers\\HealthController', $application);
        self::assertStringContainsString('health collector', $controller);
        self::assertStringContainsString('catch (\\Throwable $e)', $controller);
        self::assertStringContainsString('Some health checks could not run.', $view);
        self::assertStringNotContainsString('function health(', $legacy);
    }

    public function testAuditUsesAnIsolatedFailSafeController(): void
    {
        $application = (string) file_get_contents(WC_ROOT . '/core/Application.php');
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/AuditController.php');
        $legacy = (string) file_get_contents(WC_ROOT . '/admin/controllers/SystemToolsController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/system/audit.php');

        self::assertStringContainsString('new \\Admin\\Controllers\\AuditController', $application);
        self::assertStringContainsString('audit workspace recovery', $controller);
        self::assertStringContainsString('catch (\\Throwable $e)', $controller);
        self::assertStringContainsString('Audit log needs attention.', $view);
        self::assertStringNotContainsString('function audit(', $legacy);
    }

    public function testFrontControllerCanRenderBrandedFatalPageWithoutHtaccess(): void
    {
        $front = (string) file_get_contents(WC_ROOT . '/index.php');
        self::assertStringContainsString('register_shutdown_function', $front);
        self::assertStringContainsString('E_PARSE', $front);
        self::assertStringContainsString("'/errors/500.html'", $front);
        self::assertStringContainsString("\$_SERVER['SCRIPT_NAME']", $front);
        self::assertStringContainsString("ini_set('display_errors', '0')", $front);
        self::assertFileExists(WC_ROOT . '/errors/500.html');
    }
}
