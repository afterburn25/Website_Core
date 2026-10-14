<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Security\Totp;

final class ProfileController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        $error = null;

        if ($r->isPost()) {
            $action = $r->string('action');
            if ($action === 'password') {
                $error = $this->changePassword($r, $user->id);
            }
        }

        $db = $this->db();
        $sessions = $db->fetchAll(
            'SELECT * FROM `' . $db->table('admin_sessions')
            . '` WHERE admin_id = ? AND revoked_at IS NULL ORDER BY last_seen DESC',
            [$user->id]
        );
        $pending = $_SESSION['_wc_mfa_enroll'] ?? null;

        return $this->page($r, 'profile/index', [
            'sessions'    => $sessions,
            'error'       => $error,
            'mfaEnroll'   => $pending,
            'otpauthUri'  => $pending
                ? Totp::otpauthUri('Website Core', $user->username, $pending)
                : null,
            'recoveryCodes' => $_SESSION['_wc_show_recovery'] ?? null,
        ], 'My Profile');
    }

    private function changePassword(Request $r, int $adminId): ?string
    {
        $current = (string) ($r->post['current_password'] ?? '');
        $new = (string) ($r->post['new_password'] ?? '');
        $confirm = (string) ($r->post['new_password2'] ?? '');

        $row = $this->db()->fetch(
            'SELECT password_hash FROM `' . $this->db()->table('admins') . '` WHERE id = ?',
            [$adminId]
        );
        if (!$row || !password_verify($current, (string) $row['password_hash'])) {
            return 'Current password is incorrect.';
        }
        if (strlen($new) < 12) {
            return 'New password must be at least 12 characters.';
        }
        if ($new !== $confirm) {
            return 'New passwords do not match.';
        }

        $this->c->get(\Core\Auth\AdminRepository::class)->update($adminId, [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
        ]);
        $this->auth()->revokeOtherSessions($adminId);
        $this->c->get(\Core\Audit\AuditLog::class)->record('admin.password_change', $adminId);
        $this->flash('ok', 'Password changed; other sessions were signed out.');
        return null;
    }

    /** Begin MFA enrollment: stash a fresh secret in session for confirm step. */
    public function enableMfa(Request $r): Response
    {
        $user = $this->user();
        $step = $r->string('step');

        if ($step === 'start') {
            $_SESSION['_wc_mfa_enroll'] = Totp::generateSecret();
            return $this->redirect($r, '/admin/profile');
        }

        if ($step === 'confirm') {
            $secret = $_SESSION['_wc_mfa_enroll'] ?? null;
            if (!is_string($secret)) {
                $this->flash('error', 'Enrollment expired — start again.');
                return $this->redirect($r, '/admin/profile');
            }
            $counter = Totp::verify($secret, $r->string('code'));
            if ($counter === null) {
                $this->flash('error', 'That code did not match. Check the time on your device and try again.');
                return $this->redirect($r, '/admin/profile');
            }
            $codes = $this->auth()->generateRecoveryCodes($user->id);
            $this->c->get(\Core\Auth\AdminRepository::class)->update($user->id, [
                'mfa_enabled'       => 1,
                'mfa_secret'        => $secret,
                'mfa_last_counter'  => $counter,
            ]);
            unset($_SESSION['_wc_mfa_enroll']);
            $_SESSION['_wc_show_recovery'] = $codes;
            $this->c->get(\Core\Audit\AuditLog::class)->record('admin.mfa_enabled', $user->id);
            $this->flash('ok', 'Two-factor authentication enabled. Save your recovery codes now.');
            return $this->redirect($r, '/admin/profile');
        }

        return $this->redirect($r, '/admin/profile');
    }

    public function disableMfa(Request $r): Response
    {
        $user = $this->user();
        $row = $this->db()->fetch(
            'SELECT password_hash FROM `' . $this->db()->table('admins') . '` WHERE id = ?',
            [$user->id]
        );
        if (!password_verify((string) ($r->post['password'] ?? ''), (string) ($row['password_hash'] ?? ''))) {
            $this->flash('error', 'Password confirmation failed — MFA unchanged.');
            return $this->redirect($r, '/admin/profile');
        }
        $this->c->get(\Core\Auth\AdminRepository::class)->update($user->id, [
            'mfa_enabled' => 0, 'mfa_secret' => null, 'mfa_last_counter' => -1,
        ]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('admin.mfa_disabled', $user->id);
        $this->flash('ok', 'Two-factor authentication disabled.');
        return $this->redirect($r, '/admin/profile');
    }
}
