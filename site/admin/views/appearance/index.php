<?php /** @var array $themes @var string $active @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Themes</h2>
<p class="muted">Switching themes changes presentation only — pages, media,
menus and settings are never touched.</p>
<?php if ($themes === []): ?>
<p class="muted">No themes discovered.</p>
<?php else: ?>
<table>
<tr><th>Theme</th><th>Version</th><th>Publisher</th><th></th></tr>
<?php foreach ($themes as $id => $info): $m = $info['manifest']; $slug = basename((string) ($info['dir'] ?? '')); ?>
<tr>
<td><strong><?= e($m['name'] ?? $id) ?></strong><br><span class="muted"><?= e($id) ?></span></td>
<td><?= e($m['version'] ?? '?') ?></td>
<td><?= e(explode('/', $id)[0]) ?></td>
<td>
<?php if ($slug === $active): ?>
<span class="badge ok">active</span>
<?php else: ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/activate" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="slug" value="<?= e_attr($slug) ?>">
<button class="btn small">Activate</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
