<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class OperationsCommercialCompletionTest extends TestCase
{
    public function testOperationalRecoveryContractsRemainWired(): void
    {
        $backups = (string) file_get_contents(WC_ROOT . '/admin/controllers/BackupsController.php');
        $operations = (string) file_get_contents(WC_ROOT . '/admin/controllers/OperationsController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/backups/index.php');
        $this->assertStringContainsString('->inspect(', $backups);
        $this->assertStringContainsString('pre-restore safety backup', $backups);
        $this->assertStringContainsString('LOCK_EX', $operations);
        $this->assertStringContainsString('recoverExpiredLeases', $operations);
        $this->assertStringContainsString('Verified recovery points', $view);
    }
}
