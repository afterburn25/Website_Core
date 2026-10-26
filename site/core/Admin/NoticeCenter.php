<?php
declare(strict_types=1);

namespace Core\Admin;

use Core\Database\Connection;

/** Persistent, dismissible notices shared by core and installed packages. */
final class NoticeCenter
{
    public function __construct(private Connection $db) {}

    public function publish(
        string $title,
        string $message = '',
        string $kind = 'info',
        ?string $actionUrl = null,
        ?int $adminId = null,
        ?string $expiresAt = null,
    ): int {
        $kind = in_array($kind, ['info', 'success', 'warning', 'error'], true) ? $kind : 'info';
        return (int) $this->db->insert('admin_notices', [
            'admin_id' => $adminId,
            'kind' => $kind,
            'title' => substr(trim($title), 0, 190),
            'message' => $message,
            'action_url' => $actionUrl,
            'dismissed_at' => null,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array> */
    public function openFor(int $adminId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('admin_notices') . '`'
            . ' WHERE dismissed_at IS NULL AND (admin_id IS NULL OR admin_id = ?)'
            . ' AND (expires_at IS NULL OR expires_at > ?) ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            [$adminId, date('Y-m-d H:i:s')]
        );
    }

    public function countFor(int $adminId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('admin_notices') . '`'
            . ' WHERE dismissed_at IS NULL AND (admin_id IS NULL OR admin_id = ?)'
            . ' AND (expires_at IS NULL OR expires_at > ?)',
            [$adminId, date('Y-m-d H:i:s')]
        );
    }

    public function dismiss(int $id, int $adminId): bool
    {
        return $this->db->run(
            'UPDATE `' . $this->db->table('admin_notices') . '` SET dismissed_at = ?'
            . ' WHERE id = ? AND dismissed_at IS NULL AND (admin_id IS NULL OR admin_id = ?)',
            [date('Y-m-d H:i:s'), $id, $adminId]
        )->rowCount() > 0;
    }
}
