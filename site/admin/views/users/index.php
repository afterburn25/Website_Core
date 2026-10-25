<?php
/** @var array $admins @var array $roles @var array $assignRoles @var array $createRoles @var array $createInput
 * @var bool $canCreate @var \Core\Security\Csrf $csrf @var string $basePath @var \Core\Auth\AdminUser $user */
$active = count(array_filter($admins, static fn ($admin): bool => !$admin->disabled));
$protected = count(array_filter($admins, static fn ($admin): bool => !$admin->disabled && $admin->mfaEnabled));
?>
<section class="status-strip" aria-label="Administrator overview">
  <div class="status-chip"><strong><?= count($admins) ?></strong><span>Administrators</span></div>
  <div class="status-chip ok"><strong><?= $active ?></strong><span>Active accounts</span></div>
  <div class="status-chip <?= $protected === $active && $active > 0 ? 'ok' : 'warn' ?>"><strong><?= $protected ?>/<?= $active ?></strong><span>MFA coverage</span></div>
  <a class="status-chip" href="<?= e_attr($basePath) ?>/admin/security"><strong>Review →</strong><span>Security posture</span></a>
</section>

<section class="card">
  <div class="card-heading"><div><p class="section-kicker">Access directory</p><h2>Administrator accounts</h2><p class="card-subtitle">Give each person their own account. Role, status, MFA, and last access stay visible in one place.</p></div><?php if ($user->isOwner()): ?><a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/roles">Manage roles</a><?php endif; ?></div>
  <div class="responsive-table"><table class="admin-account-table">
    <thead><tr><th>Administrator</th><th>Role</th><th>Protection</th><th>Last sign-in</th><th>Account actions</th></tr></thead>
    <tbody><?php foreach ($admins as $admin): ?>
      <tr class="<?= $admin->disabled ? 'is-muted' : '' ?>">
        <td><div class="account-identity"><span class="account-avatar" aria-hidden="true"><?= e(strtoupper(substr($admin->username, 0, 1))) ?></span><div><strong><?= e($admin->username) ?></strong><?= $admin->id === $user->id ? ' <span class="badge neutral">You</span>' : '' ?><small><?= e($admin->email) ?></small></div></div></td>
        <td>
          <?php if ($admin->id === $user->id): ?><span class="role-label"><?= e(ucfirst($admin->role)) ?></span>
          <?php else: ?><form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $admin->id ?>/role" class="compact-role-form"><?= $csrf->field() ?><select name="role" aria-label="Role for <?= e_attr($admin->username) ?>"><?php foreach ($assignRoles as $role): ?><option value="<?= e_attr($role) ?>" <?= $admin->role === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option><?php endforeach; ?></select><button class="btn small secondary" data-confirm="Change this administrator’s role and permissions?">Save</button></form><?php endif; ?>
        </td>
        <td><div class="badge-stack"><span class="badge <?= $admin->disabled ? 'bad' : 'ok' ?>"><?= $admin->disabled ? 'Disabled' : 'Active' ?></span><span class="badge <?= $admin->mfaEnabled ? 'ok' : 'warn' ?>">MFA <?= $admin->mfaEnabled ? 'on' : 'off' ?></span></div></td>
        <td><span class="date-cell"><?= e($admin->lastLoginAt ?? 'Never') ?></span></td>
        <td><?php if ($admin->id !== $user->id && (!$admin->isOwner() || $user->isOwner())): ?><div class="account-actions">
          <form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $admin->id ?>/disable" class="inline-toggle-form" data-confirm="<?= $admin->disabled ? 'Enable this administrator account?' : 'Disable this administrator and revoke every active session?' ?>"><?= $csrf->field() ?><label class="toggle-control compact"><span class="sr-only"><?= $admin->disabled ? 'Enable' : 'Disable' ?> <?= e($admin->username) ?></span><input type="checkbox" <?= $admin->disabled ? '' : 'checked' ?> data-auto-submit><span class="toggle-track" aria-hidden="true"></span></label></form>
          <details class="row-editor"><summary class="btn small secondary">Reset password</summary><div class="row-editor-panel"><h3>Reset <?= e($admin->username) ?></h3><p class="muted">This immediately signs the administrator out everywhere.</p><form method="post" action="<?= e_attr($basePath) ?>/admin/users/<?= (int) $admin->id ?>/reset-password"><?= $csrf->field() ?><label for="reset-<?= (int) $admin->id ?>">New temporary password</label><input id="reset-<?= (int) $admin->id ?>" name="new_password" type="password" required minlength="12" autocomplete="new-password"><button class="btn danger" type="submit" data-confirm="Reset this password and revoke every existing session?">Reset and sign out</button></form></div></details>
        </div><?php else: ?><span class="muted">Protected account</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="field-note">Core prevents self-disable and protects the last enabled owner from disablement or demotion.</p>
</section>

<?php if ($canCreate): ?><section class="card account-create-card">
  <div class="card-heading"><div><p class="section-kicker">New access</p><h2>Add administrator</h2><p class="card-subtitle">Create an individual account with the least-privileged role that fits the person’s work.</p></div></div>
  <form method="post" action="<?= e_attr($basePath) ?>/admin/users/create" data-protect-unsaved><?= $csrf->field() ?>
    <div class="account-create-grid"><div><label for="new-admin-username">Username</label><input id="new-admin-username" name="username" required minlength="3" maxlength="60" autocomplete="off" value="<?= e_attr((string) ($createInput['username'] ?? '')) ?>"><small>Letters, numbers, dots, dashes, and underscores.</small></div><div><label for="new-admin-email">Email</label><input id="new-admin-email" name="email" type="email" required autocomplete="email" value="<?= e_attr((string) ($createInput['email'] ?? '')) ?>"></div><div><label for="new-admin-role">Role</label><select id="new-admin-role" name="role"><?php foreach ($createRoles as $role): ?><option value="<?= e_attr($role) ?>" <?= ($createInput['role'] ?? 'editor') === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option><?php endforeach; ?></select><small>Only owners can create another owner.</small></div><div><label for="new-admin-password">Temporary password</label><input id="new-admin-password" name="password" type="password" required minlength="12" autocomplete="new-password"><small>Use 12 or more characters and share it out-of-band.</small></div></div>
    <div class="action-bar"><span>The new administrator should change this password and enable MFA after signing in.</span><button class="btn" type="submit" data-busy-label="Creating administrator…">Create administrator</button></div>
  </form>
</section><?php endif; ?>
