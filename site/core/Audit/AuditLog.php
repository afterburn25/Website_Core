<?php
declare(strict_types=1);

namespace Core\Audit;

use Core\Database\Connection;

/**
 * Durable audit trail for sensitive mutations. Callers pass an event name
 * and a small context array; secrets must never be included — the recorder
 * strips keys that look sensitive as a safety net.
 */
final class AuditLog
{
    private const SENSITIVE = '/pass|secret|token|key|hash|csrf|password|credential/i';

    public function __construct(private Connection $db) {}

    public function record(string $event, ?int $actorId, array $context = []): void
    {
        $context = $this->redact($context);
        $this->db->insert('audit_log', [
            'event'       => $event,
            'actor_id'    => $actorId,
            'context'     => json_encode($context, JSON_UNESCAPED_SLASHES),
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array> */
    public function recent(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('audit_log') . '` ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }

    private function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match(self::SENSITIVE, $k)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = $this->redact($v);
            }
        }
        return $data;
    }
}
