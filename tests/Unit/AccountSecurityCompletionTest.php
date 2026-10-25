<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Auth\AdminRepository;
use Core\Auth\Permissions;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class AccountSecurityCompletionTest extends TestCase
{
    public function testCapabilityCatalogProvidesHumanGuidance(): void
    {
        $catalog = (new Permissions())->capabilityCatalog();
        $this->assertSame('Manage content', $catalog['content.manage']['label']);
        $this->assertSame('Access', $catalog['admins.view']['group']);
        $this->assertNotSame('', $catalog['settings.manage']['description']);
    }

    public function testAdministratorEmailLookupAndUniqueWorkflowContract(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $repo = new AdminRepository($db);
        $id = $repo->create('owner-one', 'owner@example.test', password_hash('long-password', PASSWORD_DEFAULT), 'owner');
        $this->assertSame($id, (int) $repo->findByEmail('owner@example.test')['id']);
    }

    public function testCommercialAccountControlsRemainWired(): void
    {
        $users = (string) file_get_contents(WC_ROOT . '/admin/controllers/UsersController.php');
        $profile = (string) file_get_contents(WC_ROOT . '/admin/controllers/ProfileController.php');
        $view = (string) file_get_contents(WC_ROOT . '/admin/views/profile/index.php');
        $this->assertStringContainsString("admins.manage_nonowner", $users);
        $this->assertStringContainsString('enabledOwnerCount()', $users);
        $this->assertStringContainsString('admin.recovery_codes_regenerated', $profile);
        $this->assertStringContainsString("DELETE FROM `", $profile);
        $this->assertStringContainsString('data-copy-target', $view);
        $this->assertStringContainsString('Cancel enrollment', $view);
    }
}
