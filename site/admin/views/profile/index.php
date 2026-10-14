<?php /** @var array $sessions @var string|null $error @var string|null $mfaEnroll
  * @var string|null $otpauthUri @var array|null $recoveryCodes
  * @var \Core\Security\Csrf $csrf @var string $basePath @var \Core\Auth\AdminUser $user */ ?>

<?php if ($error): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
<h2 style="margin-top:0">Change password</h2>
<form method="post" action="<?= e_attr($basePath) ?>/admin/profile">
<?= $csrf->field() ?>
<input type="hidden" name="action" value="password">
<label>Current password</label>
<input type="password" name="current_password" autocomplete="current-password" required>
<label>New password (12+ characters)</label>
<input type="password" name="new_password" autocomplete="new-password" required>
<label>Confirm new password</label>
<input type="password" name="new_password2" autocomplete="new-password" required>
<div style="margin-top:1rem"><button class="btn" type="submit">Change password</button></div>
</form>
</div>

<div class="card">
<h2 style="margin-top:0">Two-factor authentication</h2>
<?php if ($user->mfaEnabled): ?>
<p><span class="badge ok">Enabled</span></p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/profile/mfa/disable">
<?= $csrf->field() ?>
<label>Confirm with your password to disable</label>
<input type="password" name="password" autocomplete="current-password" required>
<div style="margin-top:.75rem"><button class="btn danger" type="submit">Disable MFA</button></div>
</form>
<?php elseif ($mfaEnroll): ?>
<p>Add this secret to your authenticator app, then enter the 6-digit code to confirm.</p>
<p><code style="font-size:1.05rem"><?= e($mfaEnroll) ?></code></p>
<p class="muted"><?= e($otpauthUri) ?></p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/profile/mfa/enable">
<?= $csrf->field() ?>
<input type="hidden" name="step" value="confirm">
<label>6-digit code</label>
<input name="code" inputmode="numeric" autocomplete="one-time-code" required>
<div style="margin-top:.75rem"><button class="btn" type="submit">Confirm and enable</button></div>
</form>
<?php else: ?>
<p><span class="badge">Not enabled</span></p>
<p class="muted">Protect your account with a TOTP authenticator app.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/profile/mfa/enable">
<?= $csrf->field() ?>
<input type="hidden" name="step" value="start">
<button class="btn" type="submit">Enable MFA</button>
</form>
<?php endif; ?>
</div>

<?php if (is_array($recoveryCodes)): ?>
<div class="card">
<h2 style="margin-top:0">Recovery codes — shown once</h2>
<p class="muted">Each code works once. Store them somewhere safe.</p>
<p style="columns:2;font-family:monospace">
<?php foreach ($recoveryCodes as $code): ?><?= e($code) ?><br><?php endforeach; ?>
</p>
</div>
<?php unset($_SESSION['_wc_show_recovery']); endif; ?>

<div class="card">
<h2 style="margin-top:0">Active sessions</h2>
<table>
<tr><th>IP</th><th>User agent</th><th>Started</th><th>Last seen</th></tr>
<?php foreach ($sessions as $s): ?>
<tr>
<td><?= e($s['ip'] ?? '') ?></td>
<td><?= e(substr((string) $s['user_agent'], 0, 60)) ?></td>
<td><?= e($s['created_at'] ?? '') ?></td>
<td><?= e($s['last_seen'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
