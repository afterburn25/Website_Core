<?php /** @var array $packages @var array $journal @var \Core\Security\Csrf $csrf @var string $basePath
  * @var bool $canInstall @var \Core\Auth\AdminUser $user */ ?>

<section class="status-strip"><div class="status-chip ok"><strong><?= count($packages) ?></strong><span>Discovered packages</span></div><div class="status-chip <?= !empty($recovery)?'warn':'ok' ?>"><strong><?= !empty($recovery)?'On':'Off' ?></strong><span>Recovery mode</span></div><a class="status-chip" href="<?= e_attr($basePath) ?>/admin/marketplace"><strong>Browse</strong><span>Marketplace</span></a></section>
<?php if (!empty($recovery)): ?>
<div class="card danger-zone">
<strong>Recovery mode is ON.</strong> No package code is running. Turn it off
once the failing package is fixed or removed.
<form method="post" action="<?= e_attr($basePath) ?>/admin/modules/recovery" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="mode" value="off">
<button class="btn">Turn recovery mode off</button>
</form>
</div>
<?php endif; ?>

<?php if (!empty($canInstall)): ?>
<div class="card">
<div class="card-heading"><div><h2>Install package</h2><p class="card-subtitle">Upload an industry module, add-on, or theme package for inspection.</p></div></div>
<form method="post" action="<?= e_attr($basePath) ?>/admin/modules/upload" enctype="multipart/form-data" data-busy-label="Inspecting package…">
<?= $csrf->field() ?>
<div class="drop-zone" data-drop-zone><strong>Choose or drop a package ZIP</strong><p class="field-note">The archive is staged and reviewed before any package code runs.</p><input type="file" name="package" accept=".zip,application/zip" required></div>
<div class="action-bar"><span>ZIP paths, symlinks, collisions, compatibility, and size are validated.</span><button class="btn" type="submit">Upload &amp; stage</button></div>
<p class="muted">ZIP is inspected (paths, symlinks, collisions, size) and staged before any
code runs. Reviewed packages move to their destination only on Install.</p>
</form>
</div>

<?php if (empty($recovery)): ?>
<div class="card">
<h2 style="margin-top:0">Recovery mode</h2>
<p class="muted">If a package breaks the site, recovery mode boots trusted core only —
no package code runs — so you can log in and remove it. You can also create
<code>storage/recovery.flag</code> by hand on the host.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/modules/recovery">
<?= $csrf->field() ?><input type="hidden" name="mode" value="on">
<button class="btn secondary">Enable recovery mode</button>
</form>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
<?php if ($packages === []): ?>
<div class="empty-state"><strong>No optional packages discovered</strong><span>Upload an industry module, add-on, or theme ZIP above.</span></div>
<?php else: ?>
<table>
<tr><th>Package</th><th>Type</th><th>Version</th><th>State</th><th>Issues</th><th>Actions</th></tr>
<?php foreach ($packages as $id => $info):
    $m = $info['manifest'];
    $protected = \Core\Modules\ModuleRegistry::isProtectedPackage((string) $id);
    $state = $protected ? 'built-in' : $info['state'];
?>
<tr>
<td><strong><?= e($id) ?></strong><br><span class="muted"><?= e($protected ? 'Website Core base theme' : ($m['name'] ?? '')) ?></span></td>
<td><?= e($protected ? 'core' : ($m['type'] ?? '?')) ?></td>
<td><?= e($protected && defined('WC_VERSION') ? WC_VERSION : ($m['version'] ?? '?')) ?></td>
<td><span class="badge <?= in_array($state, ['enabled', 'built-in'], true) ? 'ok' : ($state === 'failed' ? 'bad' : 'warn') ?>"><?= e($state) ?></span></td>
<td class="err">
<?php if (!empty($info['filesystem_update'])): ?>
<strong>Files changed outside Module Manager.</strong><br>
<span class="muted">Recorded <?= e($info['database_version']) ?>; files report <?= e($m['version'] ?? '?') ?>. Finalize to run migrations and synchronize the record.</span>
<?php else: ?>
<?= e(implode('; ', $info['errors'])) ?>
<?php endif; ?>
</td>
<td>
<?php
$buttons = $protected ? [] : match ($state) {
    'staged'    => [['install', !empty($info['upgrade']) ? 'Upgrade' : 'Install']],
    'installed' => [['enable', 'Enable'], ['uninstall', 'Uninstall']],
    'enabled'   => [['disable', 'Disable']],
    'disabled'  => [['enable', 'Enable'], ['uninstall', 'Uninstall']],
    'failed'    => [['uninstall', 'Remove files']],
    'removed'   => [['purge', 'Purge data']],
    default     => [],
};
if (!empty($info['filesystem_update'])) {
    array_unshift($buttons, ['finalize', 'Finalize update']);
}
?>
<?php if ($protected): ?>
<span class="muted">Required system component</span>
<?php elseif ($state === 'removed'): ?>
<form method="post" action="<?= e_attr($basePath . '/admin/modules/' . $id . '/purge') ?>">
<?= $csrf->field() ?>
<input name="confirm" placeholder="type <?= e_attr($id) ?> to purge" style="max-width:12rem" required>
<button class="btn small danger">Purge data</button>
</form>
<?php else: ?>
<?php foreach ($buttons as [$op, $label]): ?>
<form method="post" action="<?= e_attr($basePath . '/admin/modules/' . $id . '/' . $op) ?>" style="display:inline">
<?= $csrf->field() ?>
<button class="btn small <?= in_array($op, ['uninstall', 'purge'], true) ? 'danger' : '' ?>" <?= in_array($op,['disable','uninstall'],true)?'data-confirm="'.e_attr($op==='disable'?'Disable this package? Its public and admin features will stop loading.':'Uninstall this package? Its data will be preserved until explicitly purged.').'"':'' ?>><?= e($label) ?></button>
</form>
<?php endforeach; ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<?php if ($journal !== []): ?>
<div class="card">
<h2 style="margin-top:0">Recent operations</h2>
<table>
<tr><th>When</th><th>Package</th><th>Operation</th><th>Result</th><th>Detail</th></tr>
<?php foreach ($journal as $j): ?>
<tr>
<td><?= e($j['created_at']) ?></td><td><?= e($j['package_id']) ?></td>
<td><?= e($j['operation']) ?></td>
<td><span class="badge <?= $j['result'] === 'ok' ? 'ok' : 'bad' ?>"><?= e($j['result']) ?></span></td>
<td class="muted"><?= e(substr((string) $j['detail'], 0, 80)) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
