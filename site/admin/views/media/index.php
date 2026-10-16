<?php /** @var array $items @var array $folders @var int $confirm
  * @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Upload</h2>
<form method="post" enctype="multipart/form-data">
<?= $csrf->field() ?><input type="hidden" name="action" value="upload">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0 1rem;max-width:44rem;align-items:end">
<div><label>File (jpg/png/gif/webp/pdf/txt, max 15 MB)</label>
<input type="file" name="file" required></div>
<div><label>Folder (optional)</label><input name="folder"></div>
<div><label><input type="checkbox" name="private" value="1" style="width:auto"> Private</label></div>
</div>
<div style="margin-top:1rem"><button class="btn" type="submit">Upload</button></div>
</form>
</div>

<div class="card">
<div style="display:flex;gap:1rem;align-items:center;margin-bottom:.75rem">
<span class="muted"><?= count($items) ?> file(s)</span>
<?php foreach ($folders as $f): ?>
<a href="<?= e_attr($basePath) ?>/admin/media?folder=<?= e_attr($f) ?>"><?= e($f) ?></a>
<?php endforeach; ?>
<a href="<?= e_attr($basePath) ?>/admin/media">all</a>
</div>
<table>
<tr><th>File</th><th>Type</th><th>Size</th><th>Alt text / caption</th><th></th></tr>
<?php foreach ($items as $m): ?>
<tr>
<td>
<?php if (str_starts_with($m['mime'], 'image/')): ?>
<img src="<?= e_attr($basePath) ?>/media/<?= (int) $m['id'] ?>/<?= e_attr($m['original_name']) ?>"
     alt="Thumbnail for <?= e_attr($m['original_name']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:4px;vertical-align:middle">
<?php endif; ?>
<a href="<?= e_attr($basePath) ?>/media/<?= (int) $m['id'] ?>/<?= e_attr($m['original_name']) ?>"><?= e($m['original_name']) ?></a>
<?= $m['is_private'] ? ' <span class="badge warn">private</span>' : '' ?>
<br><span class="muted"><?= e($m['folder'] ?: '(root)') ?> · <?= e($m['created_at'] ?? '') ?></span>
</td>
<td><?= e($m['mime']) ?><?= $m['width'] ? '<br><span class="muted">' . (int) $m['width'] . '×' . (int) $m['height'] . '</span>' : '' ?></td>
<td><?= number_format(((int) $m['size_bytes']) / 1024, 1) ?> KB</td>
<td>
<form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="meta">
<input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<input name="alt_text" value="<?= e_attr($m['alt_text'] ?? '') ?>" placeholder="alt text" style="max-width:12rem;margin-bottom:.25rem">
<input name="caption" value="<?= e_attr($m['caption'] ?? '') ?>" placeholder="caption" style="max-width:12rem">
<button class="btn small" type="submit">Save</button>
</form>
</td>
<td>
<form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<input type="hidden" name="confirm" value="<?= $confirm === (int) $m['id'] ? '1' : '0' ?>">
<button class="btn small danger"><?= $confirm === (int) $m['id'] ? 'Confirm delete' : 'Delete' ?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
