<?php /** @var array $backups @var \Core\Security\Csrf $csrf @var string $basePath @var \Core\Auth\AdminUser $user */ ?>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
<span class="muted"><?= count($backups) ?> backup(s) in protected storage</span>
<form method="post" action="<?= e_attr($basePath) ?>/admin/backups/create">
<?= $csrf->field() ?><button class="btn">Create backup now</button>
</form>
</div>
<p class="muted">A backup contains a full database dump plus config and uploads,
integrity-hashed inside the archive. Restores verify checksums and driver
before applying — restores are owner-only and audited.</p>
<?php if ($backups === []): ?>
<p class="muted">No backups yet.</p>
<?php else: ?>
<table>
<tr><th>File</th><th>Size</th><th>Created</th><th></th></tr>
<?php foreach ($backups as $b): ?>
<tr>
<td><code><?= e($b['name']) ?></code></td>
<td><?= round($b['size'] / 1024, 1) ?> KB</td>
<td><?= e(date('Y-m-d H:i:s', $b['mtime'])) ?></td>
<td>
<?php if ($user->isOwner()): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/backups/restore" style="display:inline"
      onsubmit="return confirm('Restore <?= e_attr($b['name']) ?>? Current database and config/upload files will be replaced.')">
<?= $csrf->field() ?><input type="hidden" name="name" value="<?= e_attr($b['name']) ?>">
<button class="btn small secondary">Restore</button>
</form>
<?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/backups/delete" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="name" value="<?= e_attr($b['name']) ?>">
<button class="btn small danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
