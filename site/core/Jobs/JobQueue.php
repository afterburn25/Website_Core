<?php
declare(strict_types=1);

namespace Core\Jobs;

use Core\Database\Connection;

/**
 * Durable job/outbox service. Ordinary host cron runs tools/cron.php (or
 * cron.php at the site root) to drain bounded batches; jobs are leased to a
 * worker so concurrent runners cannot double-execute, and handlers must be
 * idempotent — at-least-once delivery, never exactly-once external effects.
 */
final class JobQueue
{
    public function __construct(private Connection $db) {}

    public function dispatch(string $type, array $payload = [], ?int $runAfter = null): int
    {
        return (int) $this->db->insert('jobs', [
            'type'        => $type,
            'payload'     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'status'      => 'pending',
            'attempts'    => 0,
            'run_after'   => $runAfter ?? time(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array> leased jobs */
    public function claim(string $workerId, int $limit = 10, int $leaseSeconds = 120): array
    {
        $table = $this->db->table('jobs');
        $now = time();
        $expiry = $now + $leaseSeconds;

        // Reap dead leases first: a worker that crashed mid-job leaves the
        // row 'running' forever without this — invisible, never retried.
        // It returns to 'uncertain' (retryable under at-least-once).
        $this->db->run(
            "UPDATE `{$table}` SET status = 'uncertain', leased_by = NULL
             WHERE status = 'running' AND leased_until IS NOT NULL AND leased_until < ?",
            [$now]
        );

        $candidates = $this->db->fetchAll(
            "SELECT * FROM `{$table}`
             WHERE status IN ('pending','uncertain')
               AND run_after <= ?
               AND (leased_until IS NULL OR leased_until < ?)
             ORDER BY id LIMIT " . (int) $limit,
            [$now, $now]
        );
        // Atomic claim: the UPDATE only transitions unclaimed states, so a
        // second concurrent worker can never lease the same job — an
        // already-'running' row matches zero rows and is skipped.
        $rows = [];
        foreach ($candidates as $row) {
            $claimed = $this->db->run(
                "UPDATE `{$table}` SET leased_by = ?, leased_until = ?, status = 'running',
                    attempts = attempts + 1
                 WHERE id = ?
                   AND status IN ('pending','uncertain')
                   AND (leased_until IS NULL OR leased_until < ?)",
                [$workerId, $expiry, (int) $row['id'], $now]
            );
            if ($claimed->rowCount() > 0) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function complete(int $id): void
    {
        $this->db->update('jobs', [
            'status' => 'done', 'leased_by' => null, 'leased_until' => null,
            'finished_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public function fail(int $id, string $error, int $maxAttempts = 5): void
    {
        $row = $this->db->fetch('SELECT attempts FROM `' . $this->db->table('jobs') . '` WHERE id = ?', [$id]);
        $attempts = (int) ($row['attempts'] ?? 0);
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
        $backoff = time() + (60 * $attempts); // linear backoff
        $this->db->update('jobs', [
            'status' => $status, 'last_error' => substr($error, 0, 2000),
            'leased_by' => null, 'leased_until' => null,
            'run_after' => $status === 'pending' ? $backoff : time(),
        ], 'id = ?', [$id]);
    }

    /** Crash/interruption semantics: lease expired mid-work = uncertain. */
    public function markUncertain(int $id): void
    {
        $this->db->update('jobs', [
            'status' => 'uncertain', 'leased_by' => null, 'leased_until' => null,
        ], 'id = ?', [$id]);
    }

    public function counts(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) c FROM `' . $this->db->table('jobs') . '` GROUP BY status'
        );
        $out = ['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'uncertain' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
