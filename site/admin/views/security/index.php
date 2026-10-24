<?php /** @var array $admins @var bool $required @var array $coverage @var bool $canManage */ ?>
<div class="metric-grid">
<div class="metric"><span>MFA coverage</span><strong><?= (int) $coverage['protected'] ?>/<?= (int) $coverage['enabled'] ?></strong><small>enabled administrators protected</small></div>
<div class="metric"><span>Policy</span><strong class="<?= $required ? 'text-ok' : '' ?>"><?= $required ? 'Required' : 'Optional' ?></strong><small>for all administrator roles</small></div>
</div>
<div class="card">
<div class="card-heading"><div><h2>Administrator security</h2><p class="muted">Session counts update as administrators sign in, revoke devices, or reset passwords.</p></div></div>
<?php if ($canManage): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/security" data-confirm="<?= $required ? 'Make multi-factor authentication optional for administrators?' : 'Require multi-factor authentication for every administrator?' ?>"><?= $csrf->field() ?><input type="hidden" name="require_mfa" value="0"><label class="toggle-control"><span><strong>Require multi-factor authentication</strong><small>Applies to every enabled administrator. Enable MFA on your own profile first.</small></span><input type="checkbox" name="require_mfa" value="1" <?= $required ? 'checked' : '' ?> data-auto-submit><span class="toggle-track" aria-hidden="true"></span></label></form><?php endif; ?>
<table><tr><th>Administrator</th><th>Role</th><th>MFA</th><th>Active sessions</th><th>Last login</th></tr>
<?php foreach ($admins as $admin): ?><tr><td><?= e($admin['username']) ?> <?= $admin['disabled'] ? '<span class="badge bad">disabled</span>' : '' ?></td><td><?= e($admin['role']) ?></td><td><span class="badge <?= $admin['mfa_enabled'] ? 'ok' : 'warn' ?>"><?= $admin['mfa_enabled'] ? 'enabled' : 'not enabled' ?></span></td><td><?= (int) $admin['active_sessions'] ?></td><td><?= e($admin['last_login_at'] ?? '—') ?></td></tr><?php endforeach; ?>
</table>
</div>
