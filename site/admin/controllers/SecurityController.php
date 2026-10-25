<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class SecurityController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'admins.view')) return $this->forbidden();

        if ($r->isPost()) {
            if (!$this->auth()->has($u, 'settings.manage')) return $this->forbidden();
            $enable = $r->string('require_mfa') === '1';
            if ($enable && !$u->mfaEnabled) {
                $this->flash('error', 'Enable MFA on your own profile before requiring it for other administrators.');
            } else {
                $this->settings()->set('security.require_mfa', $enable);
                $this->c->get(\Core\Audit\AuditLog::class)->record('security.mfa_policy', $u->id, ['required' => $enable]);
                $this->flash('ok', $enable ? 'MFA is now required for administrator access.' : 'Mandatory MFA policy disabled.');
            }
            return $this->redirect($r, '/admin/security');
        }

        $admins = $this->db()->fetchAll(
            'SELECT a.id, a.username, a.role, a.mfa_enabled, a.disabled, a.last_login_at,'
            . ' (SELECT COUNT(*) FROM `' . $this->db()->table('admin_sessions')
            . '` s WHERE s.admin_id = a.id AND s.revoked_at IS NULL) active_sessions,'
            . ' (SELECT COUNT(*) FROM `' . $this->db()->table('recovery_codes')
            . '` rc WHERE rc.admin_id = a.id AND rc.used_at IS NULL) recovery_codes'
            . ' FROM `' . $this->db()->table('admins') . '` a ORDER BY a.role, a.username'
        );
        $enabled = array_filter($admins, fn (array $a): bool => !(bool) $a['disabled']);
        $mfaEnabled = array_filter($enabled, fn (array $a): bool => (bool) $a['mfa_enabled']);
        return $this->page($r, 'security/index', [
            'admins' => $admins,
            'required' => (bool) $this->settings()->get('security.require_mfa', false),
            'coverage' => ['protected' => count($mfaEnabled), 'enabled' => count($enabled)],
            'posture' => [
                'owners' => count(array_filter($enabled, fn (array $a): bool => $a['role'] === 'owner')),
                'sessions' => array_sum(array_map(fn (array $a): int => (int) $a['active_sessions'], $enabled)),
                'disabled' => count($admins) - count($enabled),
            ],
            'canManage' => $this->auth()->has($u, 'settings.manage'),
        ], 'Security');
    }
}
