<?php
declare(strict_types=1);
namespace Tests\Unit;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;
final class PrivacyWorkflowTest extends TestCase
{
    public function testResolutionEvidenceColumnsAndControlsExist(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $columns = array_column($db->fetchAll('PRAGMA table_info(`wc_privacy_requests`)'), 'name');
        foreach (['verified_at','completed_by','resolution_note','updated_at'] as $column) $this->assertContains($column, $columns);
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/PrivacyController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/privacy/index.php');
        $this->assertStringContainsString("status' => 'verified'", $controller);
        $this->assertStringContainsString('Verify the requester', $controller);
        $this->assertStringContainsString('resolution_note', $view);
        $this->assertStringContainsString('Reopen', $view);
    }
}
