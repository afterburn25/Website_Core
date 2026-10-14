<?php /** @var array $pages @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
<span class="muted"><?= count($pages) ?> page(s)</span>
<a class="btn" href="<?= e_attr($basePath) ?>/admin/pages/edit">New page</a>
</div>
<?php if ($pages === []): ?>
<p class="muted">No pages yet — create your first one.</p>
<?php else: ?>
<table>
<tr><th>Slug</th><th>Title</th><th>Status</th><th>Updated</th><th></th></tr>
<?php foreach ($pages as $p): ?>
<tr>
<td><code>/<?= e($p['slug']) ?></code></td>
<td><?= e($p['title']) ?></td>
<td><span class="badge <?= $p['status'] === 'published' ? 'ok' : 'warn' ?>"><?= e($p['status']) ?></span></td>
<td><?= e($p['updated_at'] ?? '') ?></td>
<td>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $p['id'] ?>">Edit</a>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/publish" style="display:inline">
<?= $csrf->field() ?>
<?php if ($p['status'] === 'published'): ?>
<input type="hidden" name="do" value="unpublish"><button class="btn small secondary">Unpublish</button>
<?php else: ?>
<input type="hidden" name="do" value="publish"><button class="btn small">Publish</button>
<?php endif; ?>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
