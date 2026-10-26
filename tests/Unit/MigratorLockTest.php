<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorLockTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('needs pdo_sqlite');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    private function holdLock(string $appliedAt): void
    {
        $this->db->insert('migrations', [
            'migration_id' => '__migrate_lock__',
            'description'  => 'migration lock sentinel',
            'batch'        => 0,
            'checksum'     => str_repeat('0', 64),
            'applied_at'   => $appliedAt,
        ]);
    }

    public function testFreshLockRefusesConcurrentMigrate(): void
    {
        $this->holdLock(date('Y-m-d H:i:s'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('in progress');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    public function testStaleLockIsReclaimed(): void
    {
        $this->holdLock(date('Y-m-d H:i:s', time() - 300));
        $applied = (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->assertSame([], $applied); // everything already applied in setUp
        // And the lock is released afterwards.
        $this->assertSame(0, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('migrations')
            . "` WHERE migration_id = '__migrate_lock__'"
        ));
    }
}
