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
            'canCreate' => $user->isOwner()
                || $this->auth()->has($user, 'admins.manage_nonowner'),
            'createRoles' => $user->isOwner()
                ? $this->c->get(Permissions::class)->roles()
                : array_values(array_diff(
                    $this->c->get(Permissions::class)->roles(), ['owner']
                )),
        ], 'Administrators');
    }

    public function create(Request $r): Response
    {
        $actor = $this->user();
        if (!$actor) {
            return $this->forbidden();
        }
        $repo = $this->c->get(\Core\Auth\AdminRepository::class);
        $perms = $this->c->get(Permissions::class);

        $username = $r->string('username');
        $email = $r->string('email');
        $role = $r->string('role');
        $password = (string) ($r->post['password'] ?? '');

        // Capability: owners create anyone; manage_nonowner holders create
        // non-owner accounts only — an admin must never mint a new owner.
        if (!$actor->isOwner()) {
            if (!$this->auth()->has($actor, 'admins.manage_nonowner') || $role === 'owner') {
                return $this->forbidden();
            }
        }
        if (!$perms->roleExists($role)) {
            return $this->forbidden();
        }

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
            $errors[] = 'Username: 3-60 chars, letters/digits/._-';
        } elseif ($repo->findByUsername($username) !== null) {
            $errors[] = 'That username is already taken.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if ($errors !== []) {
            $this->flash('error', implode(' ', $errors));
            return $this->redirect($r, '/admin/users');
        }

        $id = $repo->create(
            $username, $email, password_hash($password, PASSWORD_DEFAULT), $role
        );
        $this->c->get(\Core\Audit\AuditLog::class)->record('admin.created', $actor->id, [
            'target' => $id, 'username' => $username, 'role' => $role,
        ]);
        $this->flash('ok', 'Administrator created. They can enable MFA on first sign-in.');
        return $this->redirect($r, '/admin/users');
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

    public function resetPassword(Request $r): Response
    {
        $actor = $this->user();
        $target = (int) ($r->attributes['id'] ?? 0);
        $repo = $this->c->get(\Core\Auth\AdminRepository::class);
        $targetUser = $repo->find($target);

        if (!$actor || !$targetUser) {
            return Response::notFound();
        }
        // Same authority boundary as role changes: only owners touch owners,
        // others need admins.manage_nonowner.
        if ($targetUser->isOwner() && !$actor->isOwner()) {
            return $this->forbidden();
        }
        if (!$actor->isOwner() && !$this->auth()->has($actor, 'admins.manage_nonowner')) {
            return $this->forbidden();
        }

        $new = (string) ($r->post['new_password'] ?? '');
        if (strlen($new) < 12) {
            $this->flash('error', 'Temporary password must be at least 12 characters.');
            return $this->redirect($r, '/admin/users');
        }

        $repo->update($target, ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        // Every existing session dies — the old credentials must stop working
        // the moment the reset lands.
        $this->db()->run(
            'UPDATE `' . $this->db()->table('admin_sessions')
            . '` SET revoked_at = ? WHERE admin_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), $target]
        );
        $this->c->get(\Core\Audit\AuditLog::class)->record('admin.password_reset', $actor->id, [
            'target' => $target,
        ]);
        $this->flash('ok', 'Password reset; all sessions revoked. Share the new password out-of-band.');
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
