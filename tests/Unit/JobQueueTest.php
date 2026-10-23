<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Jobs\JobQueue;
use PHPUnit\Framework\TestCase;

final class JobQueueTest extends TestCase
{
    private Connection $db;
    private JobQueue $q;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->q = new JobQueue($this->db);
    }

    public function testSecondWorkerCannotStealALiveLease(): void
    {
        $this->q->dispatch('demo.work', ['n' => 1]);

        $first = $this->q->claim('worker-A', 10, 120);
        $this->assertCount(1, $first);

        // The lease is live — a second worker sees nothing, not even the
        // same row a previous buggy claim would have re-returned.
        $this->assertSame([], $this->q->claim('worker-B', 10, 120));
    }

    public function testExpiredLeaseIsReclaimable(): void
    {
        $this->q->dispatch('demo.work', ['n' => 1]);
        $this->q->claim('worker-A', 10, 1);

        // Force the lease into the past — crash-recovery path.
        $this->db->run(
            'UPDATE `' . $this->db->table('jobs') . '` SET leased_until = ?',
            [time() - 1]
        );
        $rows = $this->q->claim('worker-B', 10, 120);
        $this->assertCount(1, $rows);
    }

    public function testCompleteRemovesFromClaimableSet(): void
    {
        $id = $this->q->dispatch('demo.work');
        $rows = $this->q->claim('w', 10, 60);
        $this->assertCount(1, $rows);
        $this->q->complete((int) $rows[0]['id']);
        $this->assertSame([], $this->q->claim('w2', 10, 60));
        $row = $this->db->fetch(
            'SELECT status FROM `' . $this->db->table('jobs') . '` WHERE id = ?', [$id]
        );
        $this->assertSame('done', $row['status']);
    }

    public function testFailedJobCanBeRetriedAndOldCompletedJobsPruned(): void
    {
        $failed = $this->q->dispatch('demo.failed');
        $this->db->update('jobs', ['status' => 'failed', 'attempts' => 5], 'id = ?', [$failed]);
        $this->assertTrue($this->q->retry($failed));
        $row = $this->db->fetch('SELECT status, attempts FROM `' . $this->db->table('jobs') . '` WHERE id = ?', [$failed]);
        $this->assertSame('pending', $row['status']);
        $this->assertSame(0, (int) $row['attempts']);

        $done = $this->q->dispatch('demo.done');
        $this->db->update('jobs', [
            'status' => 'done', 'finished_at' => date('Y-m-d H:i:s', time() - 40 * 86400),
        ], 'id = ?', [$done]);
        $this->assertSame(1, $this->q->pruneCompleted(30));
    }
}
