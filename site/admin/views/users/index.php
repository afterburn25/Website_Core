<?php /** @var array $admins @var array $roles @var \Core\Security\Csrf $csrf
  * @var string $basePath @var \Core\Auth\AdminUser $user */ ?>
<div class="card">
<table>
<tr><th>Username</th><th>Email</th><th>Role</th><th>MFA</th><th>Status</th><th>Last sign-in</th><th></th></tr>
<?php foreach ($admins as $a): ?>
<tr>
<td><?= e($a->username) ?><?= $a->id === $user->id ? ' <span class="muted">(you)</span>' : '' ?></td>
<td><?= e($a->email) ?></td>
<td>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/role" style="display:inline">
<?= $csrf->field() ?>
<select name="role" onchange="this.form.submit()" <?= $a->id === $user->id ? 'disabled' : '' ?>>
<?php foreach ($roles as $role): ?>
<option value="<?= e_attr($role) ?>" <?= $a->role === $role ? 'selected' : '' ?>><?= e($role) ?></option>
<?php endforeach; ?>
</select>
</form>
</td>
<td><?= $a->mfaEnabled ? '<span class="badge ok">on</span>' : '<span class="badge">off</span>' ?></td>
<td><?= $a->disabled ? '<span class="badge bad">disabled</span>' : '<span class="badge ok">active</span>' ?></td>
<td><?= e($a->lastLoginAt ?? '—') ?></td>
<td>
<?php if ($a->id !== $user->id && (!$a->isOwner() || $user->isOwner())): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/disable" style="display:inline">
<?= $csrf->field() ?>
<button class="btn small <?= $a->disabled ? '' : 'danger' ?>" type="submit">
<?= $a->disabled ? 'Enable' : 'Disable' ?></button>
</form>
<details style="display:inline-block;margin-left:.5rem">
<summary class="muted" style="cursor:pointer">Reset password</summary>
<form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $a->id ?>/reset-password" style="margin:.25rem 0">
<?= $csrf->field() ?>
<input name="new_password" type="password" required minlength="12" autocomplete="new-password" placeholder="new temporary password">
<button class="btn small" type="submit">Reset</button>
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
