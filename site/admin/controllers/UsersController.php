<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Auth\Permissions;
use Core\Http\Request;
use Core\Http\Response;

final class UsersController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'admins.view')) {
            return $this->forbidden();
        }
        $users = $this->c->get(\Core\Auth\AdminRepository::class)->all();
        return $this->page($r, 'users/index', [
            'admins' => $users,
            'roles'  => $this->c->get(Permissions::class)->roles(),
        ], 'Administrators');
    }

    public function changeRole(Request $r): Response
    {
        $actor = $this->user();
        $target = (int) ($r->attributes['id'] ?? 0);
        $newRole = $r->string('role');
        $repo = $this->c->get(\Core\Auth\AdminRepository::class);
        $perms = $this->c->get(Permissions::class);

        if (!$actor || !$perms->roleExists($newRole)) {
            return $this->forbidden();
        }
        $targetUser = $repo->find($target);
        if (!$targetUser) {
            return Response::notFound();
        }

        // Capability: owners manage everyone; admins manage non-owners only.
        if ($targetUser->isOwner() && !$actor->isOwner()) {
            return $this->forbidden();
        }
        if (!$actor->isOwner() && !$this->auth()->has($actor, 'admins.manage_nonowner')) {
            return $this->forbidden();
        }
        // Last-owner protection (atomic check inside transaction).
        if ($targetUser->isOwner() && $newRole !== 'owner') {
            $guard = $this->db()->transaction(function () use ($repo, $target) {
                if ($repo->enabledOwnerCount() <= 1) {
                    return false;
                }
                return true;
            });
            if (!$guard) {
                $this->flash('error', 'Cannot demote the last enabled owner.');
                return $this->redirect($r, '/admin/users');
            }
        }

        $repo->update($target, ['role' => $newRole]);
        $this->c->get(\Core\Audit\AuditLog::class)->record('admin.role_change', $actor->id, [
            'target' => $target, 'role' => $newRole,
        ]);
        $this->flash('ok', 'Role updated.');
        return $this->redirect($r, '/admin/users');
    }

    public function toggleDisable(Request $r): Response
    {
        $actor = $this->user();
        $target = (int) ($r->attributes['id'] ?? 0);
        $repo = $this->c->get(\Core\Auth\AdminRepository::class);
        $targetUser = $repo->find($target);

        if (!$actor || !$targetUser) {
            return Response::notFound();
        }
        if ($targetUser->isOwner() && !$actor->isOwner()) {
            return $this->forbidden();
        }
        if ($target === $actor->id) {
            $this->flash('error', 'You cannot disable your own account.');
            return $this->redirect($r, '/admin/users');
        }
        if ($targetUser->isOwner() && !$targetUser->disabled && $repo->enabledOwnerCount() <= 1) {
            $this->flash('error', 'Cannot disable the last enabled owner.');
            return $this->redirect($r, '/admin/users');
        }

        $repo->update($target, ['disabled' => $targetUser->disabled ? 0 : 1]);
        if (!$targetUser->disabled) {
            // Revoke all sessions immediately on disable.
            $this->db()->run(
                'UPDATE `' . $this->db()->table('admin_sessions')
                . '` SET revoked_at = ? WHERE admin_id = ? AND revoked_at IS NULL',
                [date('Y-m-d H:i:s'), $target]
            );
        }
        $this->c->get(\Core\Audit\AuditLog::class)->record(
            $targetUser->disabled ? 'admin.enabled' : 'admin.disabled',
            $actor->id,
            ['target' => $target]
        );
        $this->flash('ok', $targetUser->disabled ? 'Account enabled.' : 'Account disabled and sessions revoked.');
        return $this->redirect($r, '/admin/users');
    }
}
