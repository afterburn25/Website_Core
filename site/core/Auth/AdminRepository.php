<?php
declare(strict_types=1);

namespace Core\Auth;

use Core\Database\Connection;

final class AdminRepository
{
    public function __construct(private Connection $db) {}

    public function find(int $id): ?AdminUser
    {
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('admins') . '` WHERE id = ?',
            [$id]
        );
        return $row ? AdminUser::fromRow($row) : null;
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('admins') . '` WHERE username = ?',
            [$username]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('admins') . '` WHERE email = ?',
            [$email]
        );
    }

    /** @return list<AdminUser> */
    public function all(): array
    {
        return array_map(
            fn (array $r) => AdminUser::fromRow($r),
            $this->db->fetchAll('SELECT * FROM `' . $this->db->table('admins') . '` ORDER BY id')
        );
    }

    public function create(
        string $username,
        string $email,
        string $passwordHash,
        string $role,
    ): int {
        return (int) $this->db->insert('admins', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'mfa_enabled'   => 0,
            'disabled'      => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $this->db->update('admins', $data, 'id = ?', [$id]);
    }

    /** Count enabled owners — used to enforce last-owner protection. */
    public function enabledOwnerCount(): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('admins')
            . "` WHERE role = 'owner' AND disabled = 0"
        );
    }

    public function sessionCount(int $adminId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('admin_sessions') . '` WHERE admin_id = ?',
            [$adminId]
        );
    }
}
