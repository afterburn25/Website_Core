<?php
declare(strict_types=1);

namespace Core\Auth;

use Core\Audit\AuditLog;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Security\Totp;

/**
 * Administrator authentication: password verify + rehash, staged MFA
 * challenge with replay protection, persistent session records for
 * revocation, and role/capability checks.
 *
 * Password flow: POST credentials -> if MFA enabled, a pending marker is
 * stored and a code challenge is required before identity is committed.
 */
final class AuthService
{
    public const PENDING_KEY = '_wc_mfa_pending';
    public const ADMIN_KEY = '_wc_admin_id';

    public function __construct(
        private Connection $db,
        private AdminRepository $admins,
        private Permissions $permissions,
        private SessionManager $sessions,
        private RateLimiter $limiter,
        private ?AuditLog $audit = null,
    ) {}

    /**
     * Step 1: verify credentials. Returns AdminUser when fully authenticated,
     * or null when an MFA challenge is still required. Throws on failure.
     */
    public function attemptPassword(Request $request, string $username, string $password): ?AdminUser
    {
        $bucket = 'login:' . strtolower($username) . ':' . $request->ip();
        if (!$this->limiter->attempt($bucket, 8, 600)) {
            throw new \RuntimeException('Too many attempts. Try again later.');
        }

        $row = $this->admins->findByUsername($username);
        if (!$row || (bool) $row['disabled']
            || !password_verify($password, (string) $row['password_hash'])) {
            throw new \RuntimeException('Invalid credentials.');
        }

        if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
            $this->admins->update((int) $row['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        if ((bool) $row['mfa_enabled']) {
            $_SESSION[self::PENDING_KEY] = [
                'admin_id' => (int) $row['id'],
                'at'       => time(),
            ];
            return null; // MFA challenge required
        }

        $this->limiter->clear($bucket);
        return $this->commitLogin($request, (int) $row['id']);
    }

    /**
     * Step 2: verify a TOTP code or single-use recovery code for the pending
     * login. Replay is blocked via stored last-accepted counter / burned codes.
     */
    public function completeMfa(Request $request, string $code): AdminUser
    {
        $pending = $_SESSION[self::PENDING_KEY] ?? null;
        if (!is_array($pending) || time() - (int) ($pending['at'] ?? 0) > 300) {
            throw new \RuntimeException('No pending sign-in or challenge expired.');
        }
        $adminId = (int) $pending['admin_id'];

        $bucket = 'mfa:' . $adminId . ':' . $request->ip();
        if (!$this->limiter->attempt($bucket, 6, 300)) {
            throw new \RuntimeException('Too many attempts. Try again later.');
        }

        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('admins') . '` WHERE id = ? AND disabled = 0',
            [$adminId]
        );
        if (!$row) {
            throw new \RuntimeException('Account unavailable.');
        }

        $accepted = Totp::verify(
            (string) $row['mfa_secret'],
            $code,
            (int) $row['mfa_last_counter']
        );
        if ($accepted !== null) {
            $this->admins->update($adminId, ['mfa_last_counter' => $accepted]);
        } elseif ($this->burnRecoveryCode($adminId, $code)) {
            // accepted as recovery code
        } else {
            throw new \RuntimeException('Invalid verification code.');
        }

        unset($_SESSION[self::PENDING_KEY]);
        $this->limiter->clear($bucket);
        return $this->commitLogin($request, $adminId);
    }

    private function burnRecoveryCode(int $adminId, string $code): bool
    {
        $codes = $this->db->fetchAll(
            'SELECT id, code_hash FROM `' . $this->db->table('recovery_codes')
            . '` WHERE admin_id = ? AND used_at IS NULL',
            [$adminId]
        );
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $code));
        foreach ($codes as $row) {
            if (hash_equals((string) $row['code_hash'], hash('sha256', $normalized))) {
                $this->db->update('recovery_codes', [
                    'used_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int) $row['id']]);
                return true;
            }
        }
        return false;
    }

    private function commitLogin(Request $request, int $adminId): AdminUser
    {
        $this->sessions->rotate();
        $_SESSION[self::ADMIN_KEY] = $adminId;

        $this->db->insert('admin_sessions', [
            'admin_id'     => $adminId,
            'session_hash' => hash('sha256', session_id()),
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'created_at'   => date('Y-m-d H:i:s'),
            'last_seen'    => date('Y-m-d H:i:s'),
        ]);
        $this->admins->update($adminId, ['last_login_at' => date('Y-m-d H:i:s')]);
        $this->audit?->record('auth.login', $adminId, ['ip' => $request->ip()]);

        $user = $this->admins->find($adminId);
        if (!$user) {
            throw new \RuntimeException('Account unavailable.');
        }
        return $user;
    }

    public function user(): ?AdminUser
    {
        $id = $_SESSION[self::ADMIN_KEY] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $user = $this->admins->find($id);
        if (!$user || $user->disabled || !$this->sessionValid($id)) {
            $this->logout();
            return null;
        }
        return $user;
    }

    private function sessionValid(int $adminId): bool
    {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('admin_sessions')
            . '` WHERE admin_id = ? AND session_hash = ? AND revoked_at IS NULL',
            [$adminId, hash('sha256', session_id())]
        );
    }

    public function has(AdminUser $user, string $capability): bool
    {
        return $this->permissions->allows($user->role, $capability);
    }

    public function logout(): void
    {
        $id = $_SESSION[self::ADMIN_KEY] ?? null;
        if (is_int($id)) {
            $this->db->run(
                'UPDATE `' . $this->db->table('admin_sessions')
                . '` SET revoked_at = ? WHERE admin_id = ? AND session_hash = ?',
                [date('Y-m-d H:i:s'), $id, hash('sha256', session_id())]
            );
            $this->audit?->record('auth.logout', $id);
        }
        unset($_SESSION[self::ADMIN_KEY], $_SESSION[self::PENDING_KEY]);
        $this->sessions->destroy();
    }

    /** Revoke every other session for an admin (e.g. on password change). */
    public function revokeOtherSessions(int $adminId): void
    {
        $this->db->run(
            'UPDATE `' . $this->db->table('admin_sessions')
            . '` SET revoked_at = ? WHERE admin_id = ? AND session_hash != ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $adminId, hash('sha256', session_id())]
        );
    }

    /**
     * Generate single-use recovery codes. Returns plaintext codes to show
     * once; only hashes are persisted.
     * @return list<string>
     */
    public function generateRecoveryCodes(int $adminId, int $count = 8): array
    {
        $this->db->run(
            'DELETE FROM `' . $this->db->table('recovery_codes') . '` WHERE admin_id = ?',
            [$adminId]
        );
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $code = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
            $plain[] = $code;
            $this->db->insert('recovery_codes', [
                'admin_id'   => $adminId,
                'code_hash'  => hash('sha256', strtolower(str_replace('-', '', $code))),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $plain;
    }
}
