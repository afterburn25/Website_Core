<?php
declare(strict_types=1);

namespace Core\Seo;

use Core\Database\Connection;

final class NotFoundLog
{
    public function __construct(private Connection $db) {}

    public function record(string $path, string $referrer = ''): void
    {
        $path = '/' . ltrim(substr($path, 0, 500), '/');
        $hash = hash('sha256', strtolower(rtrim($path, '/') ?: '/'));
        $now = date('Y-m-d H:i:s');
        $table = $this->db->table('not_found_log');
        if ($this->db->driver() === 'sqlite') {
            $this->db->run(
                "INSERT INTO `{$table}` (path_hash,path,hits,last_referrer,first_seen,last_seen) VALUES (?,?,?,?,?,?)"
                . ' ON CONFLICT(path_hash) DO UPDATE SET hits=hits+1,last_referrer=excluded.last_referrer,last_seen=excluded.last_seen',
                [$hash, $path, 1, substr($referrer, 0, 500), $now, $now]
            );
        } else {
            $this->db->run(
                "INSERT INTO `{$table}` (path_hash,path,hits,last_referrer,first_seen,last_seen) VALUES (?,?,?,?,?,?)"
                . ' ON DUPLICATE KEY UPDATE hits=hits+1,last_referrer=VALUES(last_referrer),last_seen=VALUES(last_seen)',
                [$hash, $path, 1, substr($referrer, 0, 500), $now, $now]
            );
        }
    }

    public function top(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('not_found_log') . '` ORDER BY hits DESC, last_seen DESC LIMIT '
            . max(1, min(500, $limit))
        );
    }

    public function clear(): int
    {
        return $this->db->run('DELETE FROM `' . $this->db->table('not_found_log') . '`')->rowCount();
    }
}
