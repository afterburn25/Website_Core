<?php /** @var array|null $page @var string|null $error @var array $revisions
  * @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<?php if ($error): ?><div class="flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<?= $csrf->field() ?>
<label>Title</label>
<input name="title" value="<?= e_attr($page['title'] ?? '') ?>" required>
<label>Slug <span class="muted">(auto from title if blank)</span></label>
<input name="slug" value="<?= e_attr($page['slug'] ?? '') ?>">
<label>Body (HTML allowed; sanitization policy arrives with the editor milestone)</label>
<textarea name="body"><?= e($page['body'] ?? '') ?></textarea>
<label>Meta title</label>
<input name="meta_title" value="<?= e_attr($page['meta_title'] ?? '') ?>">
<label>Meta description</label>
<input name="meta_description" value="<?= e_attr($page['meta_description'] ?? '') ?>">
<div style="margin-top:1rem"><button class="btn" type="submit">Save draft</button></div>
</form>
</div>

<?php if ($revisions !== []): ?>
<div class="card">
<h2 style="margin-top:0">Recent revisions</h2>
<table>
<tr><th>Saved</th><th>By</th><th>Title</th></tr>
<?php foreach ($revisions as $rev): ?>
<tr><td><?= e($rev['created_at']) ?></td><td><?= e((string) $rev['saved_by']) ?></td><td><?= e($rev['title']) ?></td></tr>
<?php endforeach; ?>
</table>
<p class="muted">Restore-as-draft and diffs arrive in milestone M2.</p>
</div>
<?php endif; ?>
