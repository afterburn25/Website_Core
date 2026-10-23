<?php /** @var array $admins @var bool $required @var array $coverage @var bool $canManage */ ?>
<div class="metric-grid">
<div class="metric"><span>MFA coverage</span><strong><?= (int) $coverage['protected'] ?>/<?= (int) $coverage['enabled'] ?></strong><small>enabled administrators protected</small></div>
<div class="metric"><span>Policy</span><strong class="<?= $required ? 'text-ok' : '' ?>"><?= $required ? 'Required' : 'Optional' ?></strong><small>for all administrator roles</small></div>
</div>
<div class="card">
<div class="card-heading"><div><h2>Administrator security</h2><p class="muted">Session counts update as administrators sign in, revoke devices, or reset passwords.</p></div>
<?php if ($canManage): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/security"><?= $csrf->field() ?><input type="hidden" name="require_mfa" value="<?= $required ? '0' : '1' ?>"><button class="btn <?= $required ? 'secondary' : '' ?>" type="submit"><?= $required ? 'Make MFA optional' : 'Require MFA' ?></button></form><?php endif; ?></div>
<table><tr><th>Administrator</th><th>Role</th><th>MFA</th><th>Active sessions</th><th>Last login</th></tr>
<?php foreach ($admins as $admin): ?><tr><td><?= e($admin['username']) ?> <?= $admin['disabled'] ? '<span class="badge bad">disabled</span>' : '' ?></td><td><?= e($admin['role']) ?></td><td><span class="badge <?= $admin['mfa_enabled'] ? 'ok' : 'warn' ?>"><?= $admin['mfa_enabled'] ? 'enabled' : 'not enabled' ?></span></td><td><?= (int) $admin['active_sessions'] ?></td><td><?= e($admin['last_login_at'] ?? '—') ?></td></tr><?php endforeach; ?>
</table>
</div>
