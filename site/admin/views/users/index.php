<?php /** @var array $admins @var array $roles @var \Core\Security\Csrf $csrf
  * @var string $basePath @var \Core\Auth\AdminUser $user */ ?>
<section class="status-strip"><div class="status-chip"><strong><?= count($admins) ?></strong><span>Administrators</span></div><div class="status-chip ok"><strong><?= count(array_filter($admins,static fn($a)=>!$a->disabled)) ?></strong><span>Active accounts</span></div><a class="status-chip" href="<?= e_attr($basePath) ?>/admin/security"><strong>Review</strong><span>Security posture</span></a></section>
<div class="card">
<div class="card-heading"><div><h2>Administrator accounts</h2><p class="card-subtitle">Control access without sharing credentials. The last enabled owner is always protected.</p></div></div>
<table>
<tr><th>Username</th><th>Email</th><th>Role</th><th>MFA</th><th>Status</th><th>Last sign-in</th><th></th></tr>
<?php foreach ($admins as $a): ?>
<tr>
<td><?= e($a->username) ?><?= $a->id === $user->id ? ' <span class="muted">(you)</span>' : '' ?></td>
<td><?= e($a->email) ?></td>
<td>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/role" style="display:inline">
<?= $csrf->field() ?>
<div class="button-row compact"><select name="role" <?= $a->id === $user->id ? 'disabled' : '' ?>>
<?php foreach ($roles as $role): ?>
<option value="<?= e_attr($role) ?>" <?= $a->role === $role ? 'selected' : '' ?>><?= e($role) ?></option>
<?php endforeach; ?>
</select><?php if($a->id!==$user->id): ?><button class="btn small secondary" data-confirm="Change this administrator’s role and permissions?">Save</button><?php endif; ?></div>
</form>
</td>
<td><?= $a->mfaEnabled ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td>
<td><?= $a->disabled ? '<span class="badge bad">disabled</span>' : '<span class="badge ok">active</span>' ?></td>
<td><?= e($a->lastLoginAt ?? '—') ?></td>
<td>
<?php if ($a->id !== $user->id && (!$a->isOwner() || $user->isOwner())): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/disable" class="inline-toggle-form" data-confirm="<?= $a->disabled?'Enable this administrator account?':'Disable this administrator and revoke access?' ?>">
<?= $csrf->field() ?>
<label class="toggle-control compact"><span class="sr-only"><?= $a->disabled ? 'Enable' : 'Disable' ?> <?= e($a->username) ?></span><input type="checkbox" <?= $a->disabled ? '' : 'checked' ?> data-auto-submit><span class="toggle-track" aria-hidden="true"></span></label>
</form>
<details style="display:inline-block;margin-left:.5rem">
<summary class="muted" style="cursor:pointer">Reset password</summary>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/reset-password" style="margin:.25rem 0">
<?= $csrf->field() ?>
<input name="new_password" type="password" required minlength="12" autocomplete="new-password" placeholder="new temporary password">
<button class="btn small" type="submit" data-confirm="Reset this administrator’s password and revoke all existing sessions?">Reset</button>
</form>
</details>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<p class="muted">The last enabled owner cannot be demoted or disabled.</p>
</div>
<?php if (!empty($canCreate)): ?>
<div class="card">
<h2>Add administrator</h2>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/create">
<?= $csrf->field() ?>
<div class="grid2">
<label>Username <input name="username" required minlength="3" maxlength="60"></label>
<label>Email <input name="email" type="email" required></label>
<label>Role <select name="role">
<?php foreach (($createRoles ?? $roles) as $role): ?>
<option value="<?= e_attr($role) ?>" <?= $role === 'editor' ? 'selected' : '' ?>><?= e($role) ?></option>
<?php endforeach; ?>
</select></label>
<label>Temporary password <input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
</div>
<button class="btn" type="submit">Create</button>
<p class="muted">Share the temporary password out-of-band; the new admin should change it and enable MFA on first sign-in. Only owners can assign the owner role.</p>
</form>
</div>
<?php endif; ?>
