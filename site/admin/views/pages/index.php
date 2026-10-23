<?php /** @var array $pages @var array $sections @var array $shadowed @var bool $trash @var bool $canPublish @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
<span><a class="btn small <?= !$trash ? '' : 'secondary' ?>" href="<?= e_attr($basePath) ?>/admin/pages">Active pages</a> <a class="btn small <?= $trash ? '' : 'secondary' ?>" href="<?= e_attr($basePath) ?>/admin/pages?view=trash">Trash</a> <span class="muted"><?= count($pages) ?> page(s)</span></span>
<?php if (!$trash): ?>
<a class="btn" href="<?= e_attr($basePath) ?>/admin/pages/edit">New page</a>
<?php endif; ?>
</div>
<?php if ($pages === []): ?>
<p class="muted">No pages yet — create your first one.</p>
<?php else: ?>
<table>
<tr><th>Slug</th><th>Title</th><th>Publishing</th><th>Review</th><th>Updated</th><th></th></tr>
<?php foreach ($pages as $p): ?>
<tr>
<td><code>/<?= e($p['slug']) ?></code><?php if (!empty($shadowed[$p['slug']])): ?> <span class="badge warn" title="A reserved path or installed module route already handles this URL — visitors can never reach this page.">unreachable</span><?php endif; ?></td>
<td><?= e($p['title']) ?></td>
<td><span class="badge <?= $p['status'] === 'published' ? 'ok' : 'warn' ?>"><?= e($p['status']) ?></span></td>
<td><span class="badge <?= ($p['review_status'] ?? 'draft') === 'approved' ? 'ok' : (($p['review_status'] ?? 'draft') === 'pending' ? 'warn' : '') ?>"><?= e($p['review_status'] ?? 'draft') ?></span></td>
<td><?= e($p['updated_at'] ?? '') ?></td>
<td>
<?php if ($trash): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/restore" style="display:inline"><?= $csrf->field() ?><button class="btn small">Restore</button></form>
<?php else: ?>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $p['id'] ?>">Edit</a>
<?php if ($canPublish): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/publish" style="display:inline">
<?= $csrf->field() ?>
<?php if ($p['status'] === 'published'): ?>
<input type="hidden" name="do" value="unpublish"><button class="btn small secondary">Unpublish</button>
<?php else: ?>
<input type="hidden" name="do" value="publish"><button class="btn small">Publish</button>
<?php endif; ?>
</form>
<?php else: ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/review" style="display:inline"><?= $csrf->field() ?><button class="btn small secondary" <?= ($p['review_status'] ?? '') === 'pending' ? 'disabled' : '' ?>><?= ($p['review_status'] ?? '') === 'pending' ? 'Review pending' : 'Submit for review' ?></button></form><?php endif; ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/duplicate" style="display:inline"><?= $csrf->field() ?><button class="btn small secondary">Duplicate</button></form>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/trash" style="display:inline"><?= $csrf->field() ?><button class="btn small danger">Trash</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>

<?php if (!$trash): ?><div class="card">
<h2 style="margin-top:0">Reusable sections</h2>
<p class="muted">Keyed HTML blocks rendered by themes via <code>section('key')</code>. Bodies are sanitized on save.</p>
<?php if ($sections !== []): ?>
<table>
<tr><th>Key</th><th>Label</th><th>Updated</th><th></th></tr>
<?php foreach ($sections as $s): ?>
<tr>
<td><code><?= e($s['key']) ?></code></td><td><?= e($s['label']) ?></td>
<td><?= e($s['updated_at'] ?? '') ?></td>
<td>
<details style="display:inline"><summary class="btn small secondary">Edit</summary>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="section_save">
<input type="hidden" name="key" value="<?= e_attr($s['key']) ?>">
<label>Label</label><input name="label" value="<?= e_attr($s['label']) ?>" required>
<label>Body</label><textarea name="body" rows="6"><?= e($s['body'] ?? '') ?></textarea>
<button class="btn small">Save</button>
</form>
</details>
<form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="section_delete">
<input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
<button class="btn small danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<details style="margin-top:.75rem"><summary class="btn secondary">New section</summary>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="section_save">
<label>Key <span class="muted">(e.g. contact_strip — a-z 0-9 _)</span></label>
<input name="key" required>
<label>Label</label><input name="label" required>
<label>Body</label><textarea name="body" rows="6"></textarea>
<button class="btn">Create section</button>
</form>
</details>
</div>
<div class="card">
<h2 style="margin-top:0">Package content types</h2>
<p class="muted">Structured lists declared by installed packages (FAQs,
testimonials, …) get a generated admin UI.</p>
<p><a class="btn small" href="<?= e_attr($basePath) ?>/admin/content">Manage content types</a></p>
</div>
<?php endif; ?>
