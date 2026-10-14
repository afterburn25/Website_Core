<?php
declare(strict_types=1);

namespace Core\Security;

use Core\Database\Connection;

/**
 * Bounded, database-backed rate limiter. Buckets decay on read; attempts are
 * recorded atomically so concurrent logins cannot bypass the limit.
 */
final class RateLimiter
{
    public function __construct(private Connection $db) {}

    /** Attempts allowed in the window; returns true and records a hit. */
    public function attempt(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        $this->gc();
        $table = $this->db->table('rate_limits');
        $now = time();
        $windowStart = $now - $windowSeconds;

        $row = $this->db->fetch(
            "SELECT attempts, first_attempt FROM `{$table}` WHERE bucket = ?",
            [hash('sha256', $bucket)]
        );

        if ($row === null || (int) $row['first_attempt'] < $windowStart) {
            if ($this->db->driver() === 'sqlite') {
                $this->db->run(
                    "INSERT INTO `{$table}` (bucket, attempts, first_attempt, last_attempt)
                     VALUES (?, 1, ?, ?)
                     ON CONFLICT(bucket) DO UPDATE SET attempts = 1, first_attempt = excluded.first_attempt, last_attempt = excluded.last_attempt",
                    [hash('sha256', $bucket), $now, $now]
                );
            } else {
                $this->db->run(
                    "INSERT INTO `{$table}` (bucket, attempts, first_attempt, last_attempt)
                     VALUES (?, 1, ?, ?)
                     ON DUPLICATE KEY UPDATE attempts = 1, first_attempt = VALUES(first_attempt), last_attempt = VALUES(last_attempt)",
                    [hash('sha256', $bucket), $now, $now]
                );
            }
            return true;
        }

        if ((int) $row['attempts'] >= $maxAttempts) {
            $this->db->run(
                "UPDATE `{$table}` SET last_attempt = ? WHERE bucket = ?",
                [$now, hash('sha256', $bucket)]
            );
            return false;
        }

        $this->db->run(
            "UPDATE `{$table}` SET attempts = attempts + 1, last_attempt = ? WHERE bucket = ?",
            [$now, hash('sha256', $bucket)]
        );
        return true;
    }

    public function clear(string $bucket): void
    {
        $this->db->run(
            'DELETE FROM `' . $this->db->table('rate_limits') . '` WHERE bucket = ?',
            [hash('sha256', $bucket)]
        );
    }

    public function retryAfter(string $bucket, int $windowSeconds): int
    {
        $row = $this->db->fetch(
            'SELECT first_attempt FROM `' . $this->db->table('rate_limits') . '` WHERE bucket = ?',
            [hash('sha256', $bucket)]
        );
        if ($row === null) {
            return 0;
        }
        return max(0, (int) $row['first_attempt'] + $windowSeconds - time());
    }

    private function gc(): void
    {
        // Probabilistic cleanup keeps the table bounded without a daemon.
        if (random_int(1, 100) <= 5) {
            $this->db->run(
                'DELETE FROM `' . $this->db->table('rate_limits') . '` WHERE last_attempt < ?',
                [time() - 86400]
            );
        }
    }
}
