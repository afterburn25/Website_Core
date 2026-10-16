<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Audit\AuditLog;
use Core\Auth\AdminRepository;
use Core\Auth\AuthService;
use Core\Auth\Permissions;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Security\Totp;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private Connection $db;
    private AuthService $auth;
    private AdminRepository $admins;
    private \Core\Mail\SystemMail $mail;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $this->admins = new AdminRepository($this->db);
        $config = new Config('/nonexistent');
        $this->auth = new AuthService(
            $this->db,
            $this->admins,
            new Permissions(),
            new SessionManager($config),
            new RateLimiter($this->db),
            new AuditLog($this->db),
            $config,
            $this->mail = new \Core\Mail\SystemMail($this->db, new \Core\Settings\SettingsRepository($this->db)),
        );
    }

    private function request(): Request
    {
        return new Request('POST', '/admin/login', '', [], [], [], [], []);
    }

    private function makeAdmin(string $role = 'owner'): int
    {
        return $this->admins->create(
            'owner1', 'owner@example.test',
            password_hash('correct-horse-12', PASSWORD_DEFAULT),
            $role
        );
    }

    public function testPasswordLogin(): void
    {
        $this->makeAdmin();
        $user = $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
        $this->assertNotNull($user);
        $this->assertSame('owner1', $user->username);
        $this->assertSame($_SESSION[AuthService::ADMIN_KEY], $user->id);
    }

    public function testWrongPasswordRejected(): void
    {
        $this->makeAdmin();
        $this->expectException(\RuntimeException::class);
        $this->auth->attemptPassword($this->request(), 'owner1', 'wrong-password');
    }

    public function testDisabledAccountRejected(): void
    {
        $id = $this->makeAdmin();
        $this->admins->update($id, ['disabled' => 1]);
        $this->expectException(\RuntimeException::class);
        $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
    }

    public function testMfaFlow(): void
    {
        $id = $this->makeAdmin();
        $secret = Totp::generateSecret();
        $this->admins->update($id, ['mfa_enabled' => 1, 'mfa_secret' => $secret]);

        // Password step returns null (challenge pending), no identity yet.
        $this->assertNull($this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12'));
        $this->assertArrayNotHasKey(AuthService::ADMIN_KEY, $_SESSION);
        $this->assertNotEmpty($_SESSION[AuthService::PENDING_KEY]);

        // Wrong code fails.
        $this->expectException(\RuntimeException::class);
        $this->auth->completeMfa($this->request(), '000000');
    }

    public function testMfaCompletionAndReplay(): void
    {
        $id = $this->makeAdmin();
        $secret = Totp::generateSecret();
        $this->admins->update($id, ['mfa_enabled' => 1, 'mfa_secret' => $secret]);
        $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');

        $code = Totp::code($secret);
        $user = $this->auth->completeMfa($this->request(), $code);
        $this->assertSame($id, $user->id);

        // The same TOTP counter must not be reusable in a later login.
        $this->auth->logout();
        $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
        $this->expectException(\RuntimeException::class);
        $this->auth->completeMfa($this->request(), $code);
    }

    public function testRecoveryCodeBurnsOnce(): void
    {
        $id = $this->makeAdmin();
        $secret = Totp::generateSecret();
        $this->admins->update($id, ['mfa_enabled' => 1, 'mfa_secret' => $secret]);
        $codes = $this->auth->generateRecoveryCodes($id, 4);
        $this->assertCount(4, $codes);

        $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
        $user = $this->auth->completeMfa($this->request(), $codes[0]);
        $this->assertSame($id, $user->id);

        // Reuse of the same recovery code fails.
        $this->auth->logout();
        $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
        $this->expectException(\RuntimeException::class);
        $this->auth->completeMfa($this->request(), $codes[0]);
    }

    public function testSessionRevocationLogsOut(): void
    {
        $this->makeAdmin();
        $user = $this->auth->attemptPassword($this->request(), 'owner1', 'correct-horse-12');
        $this->assertNotNull($this->auth->user());
        $this->db->run(
            'UPDATE `' . $this->db->table('admin_sessions') . '` SET revoked_at = datetime(\'now\')'
        );
        $this->assertNull($this->auth->user());
    }

    public function testLastOwnerProtectionData(): void
    {
        $this->makeAdmin();
        $this->assertSame(1, $this->admins->enabledOwnerCount());
        $this->admins->create('o2', 'o2@x.test', password_hash('password-12345', PASSWORD_DEFAULT), 'owner');
        $this->assertSame(2, $this->admins->enabledOwnerCount());
    }

    public function testPermissions(): void
    {
        $perms = new Permissions();
        $this->assertTrue($perms->allows('owner', 'anything.at.all'));
        $this->assertTrue($perms->allows('admin', 'settings.manage'));
        $this->assertFalse($perms->allows('admin', 'admins.manage_owners'));
        $this->assertTrue($perms->allows('editor', 'content.manage'));
        $this->assertFalse($perms->allows('editor', 'settings.manage'));
        $perms->register('custom.thing');
        $this->assertTrue($perms->allows('admin', 'custom.thing'));
        $this->assertFalse($perms->allows('editor', 'custom.thing'));
    }

    public function testPasswordResetFlowSingleUseAndRevokesSessions(): void
    {
        $id = $this->makeAdmin();
        $captured = [];
        \Core\Mail\SystemMail::captureWith(function (string $to, string $s, string $b) use (&$captured) {
            $captured[] = [$to, $b];
        });
        (new \Core\Settings\SettingsRepository($this->db))->set('mail.transport', 'capture');

        $this->auth->requestReset($this->request(), 'owner@example.test');
        $this->mail->flush(); // requestReset queues; delivery happens on flush
        $this->assertCount(1, $captured);
        preg_match('/token=([a-f0-9]{64})/', $captured[0][1], $m);
        $this->assertNotEmpty($m[1], 'reset link carries the raw token');
        // Only the hash is stored.
        $row = $this->db->fetch('SELECT reset_token_hash FROM `' . $this->db->table('admins') . '` WHERE id = ?', [$id]);
        $this->assertSame(hash('sha256', $m[1]), $row['reset_token_hash']);

        $this->assertTrue($this->auth->completeReset($this->request(), $m[1], 'brand-new-pass-99'));
        // Single-use: the same token cannot be replayed.
        $this->assertFalse($this->auth->completeReset($this->request(), $m[1], 'another-pass-99'));
        // New password actually works.
        $this->assertNotNull($this->auth->attemptPassword($this->request(), 'owner1', 'brand-new-pass-99'));
    }

    public function testPasswordResetUnknownIdentifierIsSilent(): void
    {
        $captured = 0;
        \Core\Mail\SystemMail::captureWith(function () use (&$captured) {
            $captured++;
        });
        $this->auth->requestReset($this->request(), 'nobody@example.test');
        $this->assertSame(0, $captured, 'unknown accounts must not receive mail or disclosure');
    }

    public function testPasswordResetExpiredTokenRefused(): void
    {
        $id = $this->makeAdmin();
        $token = bin2hex(random_bytes(32));
        $this->admins->update($id, [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);
        $this->assertFalse($this->auth->completeReset($this->request(), $token, 'brand-new-pass-99'));
    }

    public function testAuditRedaction(): void
    {
        $audit = new AuditLog($this->db);
        $audit->record('test.event', 1, ['password' => 'hunter2', 'safe' => 'yes', 'api_key' => 'x']);
        $row = $this->db->fetch('SELECT context FROM `' . $this->db->table('audit_log') . '` ORDER BY id DESC');
        $ctx = json_decode($row['context'], true);
        $this->assertSame('[redacted]', $ctx['password']);
        $this->assertSame('[redacted]', $ctx['api_key']);
        $this->assertSame('yes', $ctx['safe']);
    }
}
