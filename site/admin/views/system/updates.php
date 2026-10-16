<?php /** @var string $current @var array|null $staged @var array $journal @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Core updates</h2>
<p>Installed version: <strong><?= e($current) ?></strong></p>
<p class="muted">Upload a <code>Website-Core-*-UPDATE.zip</code> built by
<code>tools/build-packages.php</code>. Applying replaces core files only —
your config, storage, uploads, and installed modules/themes are never
touched. Every replaced file is backed up and pending migrations run
automatically.</p>

<?php if ($staged === null): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/updates/stage" enctype="multipart/form-data">
<?= $csrf->field() ?>
<p><input type="file" name="update" accept=".zip" required>
<button type="submit">Stage update</button></p>
</form>
<?php else: ?>
<table>
<tr><td>Staged version</td><td><strong><?= e($staged['version']) ?></strong></td></tr>
<tr><td>Members</td><td><?= e((string) $staged['members']) ?></td></tr>
<tr><td>Signed</td><td><?= $staged['signed'] ? 'yes — verified' : 'no' ?></td></tr>
</table>
<form method="post" action="<?= e_attr($basePath) ?>/admin/updates/apply">
<?= $csrf->field() ?>
<p><button type="submit" class="danger"
    onclick="return confirm('Apply this update? Replaced files are backed up first.')">
    Apply update now</button></p>
</form>
<?php endif; ?>
</div>

<?php if ($journal !== []): ?>
<div class="card">
<h2 style="margin-top:0">Update journal</h2>
<pre style="white-space:pre-wrap"><?= e(implode("\n", $journal)) ?></pre>
</div>
<?php endif; ?>
