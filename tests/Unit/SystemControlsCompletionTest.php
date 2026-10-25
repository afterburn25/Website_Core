<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class SystemControlsCompletionTest extends TestCase
{
    public function testAuditUsesCanonicalSchemaAndSafeExport(): void
    {
        $controller = (string) file_get_contents(WC_ROOT . '/admin/controllers/AuditController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/system/audit.php');
        $this->assertStringContainsString('context LIKE', $controller);
        $this->assertStringContainsString('actor_id', $controller);
        $this->assertStringNotContainsString('detail LIKE', $controller);
        $this->assertStringContainsString('audit.exported', $controller);
        $this->assertStringContainsString("\$row['context']", $view);
    }

    public function testReviewedUpdateAndSettingsRecoveryControlsRemainWired(): void
    {
        $updates = (string) file_get_contents(WC_ROOT . '/admin/controllers/UpdatesController.php');
        $settings = (string) file_get_contents(WC_ROOT . '/admin/controllers/SettingsController.php');
        $this->assertStringContainsString("hash_file('sha256'", $updates);
        $this->assertStringContainsString('restoreFiles', $updates);
        $this->assertStringContainsString('update_discarded', $updates);
        $this->assertStringContainsString('rotate_cron', $settings);
        $this->assertStringContainsString('_wc_settings_input', $settings);
        $this->assertStringContainsString('complete HTTPS URL', $settings);
    }
}
