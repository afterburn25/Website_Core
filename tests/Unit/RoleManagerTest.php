<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Auth\AdminRepository;
use Core\Auth\Permissions;
use Core\Auth\RoleManager;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class RoleManagerTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    public function testCustomRolesExposeOnlyKnownCapabilities(): void
    {
        $permissions = new Permissions($this->db);
        $roles = new RoleManager($this->db, $permissions);
        $this->assertNull($roles->save('publisher', 'Publisher', [
            'content.manage', 'content.publish', 'not.a.capability',
        ]));
        $this->assertTrue($permissions->roleExists('publisher'));
        $this->assertTrue($permissions->allows('publisher', 'content.publish'));
        $this->assertFalse($permissions->allows('publisher', 'not.a.capability'));
        $this->assertContains('publisher', $permissions->roles());
    }

    public function testBuiltInRolesCannotBeReplacedAndInUseRoleCannotBeDeleted(): void
    {
        $permissions = new Permissions($this->db);
        $roles = new RoleManager($this->db, $permissions);
        $this->assertNotNull($roles->save('admin', 'Replacement', []));
        $this->assertNull($roles->save('reviewer', 'Reviewer', ['content.manage']));
        (new AdminRepository($this->db))->create(
            'reviewer1', 'reviewer@example.test', password_hash('long-password-1', PASSWORD_DEFAULT), 'reviewer'
        );
        $this->assertNotNull($roles->delete('reviewer'));
    }

    public function testEditorialColumnsAreInstalled(): void
    {
        $columns = array_column($this->db->fetchAll('PRAGMA table_info(`wc_pages`)'), 'name');
        $this->assertContains('review_status', $columns);
        $this->assertContains('review_note', $columns);
        $this->assertContains('reviewed_by', $columns);
        $this->assertContains('reviewed_at', $columns);
    }
}
