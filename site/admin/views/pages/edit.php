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
<label>Body <span class="muted">(rich HTML; scripts/embeds are stripped on save)</span></label>
<textarea name="body"><?= e($page['body'] ?? '') ?></textarea>
<label>Meta title</label>
<input name="meta_title" value="<?= e_attr($page['meta_title'] ?? '') ?>">
<label>Meta description</label>
<input name="meta_description" value="<?= e_attr($page['meta_description'] ?? '') ?>">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;max-width:40rem">
<div>
<label>Canonical URL <span class="muted">(optional absolute URL)</span></label>
<input name="canonical" value="<?= e_attr($page['canonical'] ?? '') ?>">
</div>
<div>
<label>OG image media id <span class="muted">(from Media library)</span></label>
<input name="og_media_id" type="number" min="0" value="<?= e_attr((string) ($page['og_media_id'] ?? '')) ?>">
</div>
</div>
<label style="display:block;margin-top:.5rem">
<input type="checkbox" name="noindex" value="1" <?= !empty($page['noindex']) ? 'checked' : '' ?>>
Exclude this page from indexing (noindex, nofollow)
</label>
<div style="margin-top:1rem;display:flex;gap:.5rem">
<button class="btn" type="submit">Save draft</button>
</form>
<?php if ($page): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/preview">
<?= $csrf->field() ?><button class="btn secondary" type="submit">Preview link</button>
</form>
<?php endif; ?>
</div>
</div>

<?php if ($revisions !== []): ?>
<div class="card">
<h2 style="margin-top:0">Recent revisions</h2>
<table>
<tr><th>Saved</th><th>By</th><th>Title</th><th></th></tr>
<?php foreach ($revisions as $rev): ?>
<tr>
<td><?= e($rev['created_at']) ?></td><td><?= e((string) $rev['saved_by']) ?></td><td><?= e($rev['title']) ?></td>
<td>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $rev['id'] ?>">
<?= $csrf->field() ?><button class="btn small secondary">Restore as draft</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
