<?php
declare(strict_types=1);

namespace Core\Auth;

final class AdminUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $mfaEnabled,
        public readonly bool $disabled,
        public readonly ?string $lastLoginAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            username: (string) $row['username'],
            email: (string) $row['email'],
            role: (string) $row['role'],
            mfaEnabled: (bool) $row['mfa_enabled'],
            disabled: (bool) $row['disabled'],
            lastLoginAt: $row['last_login_at'] ?? null,
        );
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
