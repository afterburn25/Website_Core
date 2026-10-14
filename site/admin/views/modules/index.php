<?php /** @var array $packages @var array $journal @var \Core\Security\Csrf $csrf @var string $basePath
  * @var \Core\Auth\AdminUser $user */ ?>

<?php if ($user->isOwner() || true): ?>
<div class="card">
<h2 style="margin-top:0">Install package</h2>
<form method="post" action="<?= e_attr($basePath) ?>/admin/modules/upload" enctype="multipart/form-data">
<?= $csrf->field() ?>
<input type="file" name="package" accept=".zip" required>
<button class="btn" type="submit">Upload &amp; stage</button>
<p class="muted">ZIP is inspected (paths, symlinks, collisions, size) and staged before any
code runs. Reviewed packages move to their destination only on Install.</p>
</form>
</div>
<?php endif; ?>

<div class="card">
<?php if ($packages === []): ?>
<p class="muted">No packages discovered. Upload an industry module or add-on ZIP above.</p>
<?php else: ?>
<table>
<tr><th>Package</th><th>Type</th><th>Version</th><th>State</th><th>Issues</th><th>Actions</th></tr>
<?php foreach ($packages as $id => $info): $m = $info['manifest']; $state = $info['state']; ?>
<tr>
<td><strong><?= e($id) ?></strong><br><span class="muted"><?= e($m['name'] ?? '') ?></span></td>
<td><?= e($m['type'] ?? '?') ?></td>
<td><?= e($m['version'] ?? '?') ?></td>
<td><span class="badge <?= $state === 'enabled' ? 'ok' : ($state === 'failed' ? 'bad' : 'warn') ?>"><?= e($state) ?></span></td>
<td class="err"><?= e(implode('; ', $info['errors'])) ?></td>
<td>
<?php
$buttons = match ($state) {
    'staged'    => [['install', 'Install']],
    'installed' => [['enable', 'Enable'], ['uninstall', 'Uninstall']],
    'enabled'   => [['disable', 'Disable']],
    'disabled'  => [['enable', 'Enable'], ['uninstall', 'Uninstall']],
    'failed'    => [['uninstall', 'Remove files']],
    'removed'   => [['purge', 'Purge data']],
    default     => [],
};
?>
<?php if ($state === 'removed'): ?>
<form method="post" action="<?= e_attr($basePath . '/admin/modules/' . $id . '/purge') ?>">
<?= $csrf->field() ?>
<input name="confirm" placeholder="type <?= e_attr($id) ?> to purge" style="max-width:12rem" required>
<button class="btn small danger">Purge data</button>
</form>
<?php else: ?>
<?php foreach ($buttons as [$op, $label]): ?>
<form method="post" action="<?= e_attr($basePath . '/admin/modules/' . $id . '/' . $op) ?>" style="display:inline">
<?= $csrf->field() ?>
<button class="btn small <?= in_array($op, ['uninstall', 'purge'], true) ? 'danger' : '' ?>"><?= e($label) ?></button>
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
