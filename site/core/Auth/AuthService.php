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
        private ?\Core\Config $config = null,
        private ?\Core\Mail\SystemMail $mail = null,
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
        $row = $this->db->fetch(
            'SELECT id, last_seen FROM `' . $this->db->table('admin_sessions')
            . '` WHERE admin_id = ? AND session_hash = ? AND revoked_at IS NULL',
            [$adminId, $this->sessionHash()]
        );
        if (!$row) return false;
        if (empty($row['last_seen']) || strtotime((string) $row['last_seen']) < time() - 300) {
            $this->db->update('admin_sessions', ['last_seen' => date('Y-m-d H:i:s')], 'id = ?', [(int) $row['id']]);
        }
        return true;
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
                [date('Y-m-d H:i:s'), $id, $this->sessionHash()]
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
            [date('Y-m-d H:i:s'), $adminId, $this->sessionHash()]
        );
    }

    /** @return list<array> */
    public function sessionsFor(int $adminId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, ip, user_agent, created_at, last_seen, session_hash FROM `'
            . $this->db->table('admin_sessions')
            . '` WHERE admin_id = ? AND revoked_at IS NULL ORDER BY last_seen DESC', [$adminId]
        );
        return array_map(function (array $row): array {
            $row['current'] = hash_equals((string) $row['session_hash'], $this->sessionHash());
            unset($row['session_hash']);
            return $row;
        }, $rows);
    }

    public function revokeSession(int $adminId, int $sessionId): bool
    {
        $row = $this->db->fetch(
            'SELECT session_hash FROM `' . $this->db->table('admin_sessions')
            . '` WHERE id = ? AND admin_id = ? AND revoked_at IS NULL', [$sessionId, $adminId]
        );
        if (!$row || hash_equals((string) $row['session_hash'], $this->sessionHash())) return false;
        $changed = $this->db->update('admin_sessions', ['revoked_at' => date('Y-m-d H:i:s')],
            'id = ? AND admin_id = ?', [$sessionId, $adminId]);
        if ($changed) $this->audit?->record('auth.session_revoked', $adminId, ['session_id' => $sessionId]);
        return $changed > 0;
    }

    private function sessionHash(): string
    {
        return hash('sha256', session_id());
    }

    /**
     * Self-service reset request: mints a single-use token, stores only its
     * sha256 hash + a 30-minute expiry, and queues the link via SystemMail.
     * Always returns null — existence of an account is never disclosed.
     * Throws RuntimeException only on rate limit (safe to surface — it
     * identifies the requesting IP's budget, not an account).
     */
    public function requestReset(Request $request, string $identifier): void
    {
        $bucket = 'pwreset:' . $request->ip();
        if (!$this->limiter->attempt($bucket, 5, 600)) {
            throw new \RuntimeException('Too many reset requests. Try again later.');
        }

        $identifier = trim($identifier);
        $row = str_contains($identifier, '@')
            ? $this->db->fetch(
                'SELECT * FROM `' . $this->db->table('admins') . '` WHERE email = ? AND disabled = 0',
                [$identifier]
            )
            : $this->admins->findByUsername($identifier);
        if (!$row || (bool) ($row['disabled'] ?? false)) {
            return; // silent — no account enumeration
        }

        $token = bin2hex(random_bytes(32));
        $this->admins->update((int) $row['id'], [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => date('Y-m-d H:i:s', time() + 1800),
        ]);

        // ?p=/… form — identical on rewrite and no-rewrite hosts.
        $base = rtrim((string) ($this->config?->get('app.url') ?? ''), '/');
        if ($base === '') {
            // Fallback: derive from the request (emails need absolute links).
            $host = $request->header('Host') ?: 'localhost';
            $base = ($request->isSecure() ? 'https' : 'http') . '://' . $host
                . rtrim($request->basePath, '/');
        }
        $link = $base . '/index.php?p=/admin/reset&token=' . $token;
        $this->mail?->passwordReset((string) $row['email'], $link);
        $this->audit?->record('admin.password_reset_request', (int) $row['id']);
    }

    /**
     * Consume a reset token: validates hash + expiry, writes the new
     * password, clears the token, and revokes every session — a stolen
     * session cannot outlive a completed reset.
     */
    public function completeReset(Request $request, string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $bucket = 'pwreset-token:' . $request->ip();
        if (!$this->limiter->attempt($bucket, 10, 600)) {
            throw new \RuntimeException('Too many attempts. Try again later.');
        }

        $row = $this->db->fetch(
            'SELECT id FROM `' . $this->db->table('admins')
            . '` WHERE reset_token_hash = ? AND reset_expires_at > ? AND disabled = 0',
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        );
        if (!$row) {
            return false;
        }

        $id = (int) $row['id'];
        $this->admins->update($id, [
            'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
            'reset_token_hash' => null,
            'reset_expires_at' => null,
        ]);
        $this->db->run(
            'UPDATE `' . $this->db->table('admin_sessions')
            . '` SET revoked_at = ? WHERE admin_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $id]
        );
        $this->audit?->record('admin.password_reset_done', $id);
        return true;
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
