<?php /** @var array $items @var array $folders @var array $tags @var int $confirm
  * @var \Core\Security\Csrf $csrf @var string $basePath @var \Core\Media\MediaService $mediaService */ ?>
<div class="card">
<h2 style="margin-top:0">Upload</h2>
<form method="post" enctype="multipart/form-data">
<?= $csrf->field() ?><input type="hidden" name="action" value="upload">
<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0 1rem;max-width:44rem;align-items:end">
<div><label>File (jpg/png/gif/webp/pdf/txt, max 15 MB)</label><input type="file" name="file" required></div>
<div><label>Folder (optional)</label><input name="folder" list="media-folders"></div>
<div><label><input type="checkbox" name="private" value="1" style="width:auto"> Private</label></div>
</div>
<div style="margin-top:1rem"><button class="btn" type="submit">Upload</button></div>
</form>
<datalist id="media-folders"><?php foreach ($folders as $f): ?><option value="<?= e_attr($f) ?>"><?php endforeach; ?></datalist>
</div>

<div class="card">
<form method="get" action="<?= e_attr($basePath) ?>/admin/media" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
<div><label>Search media</label><input name="q" value="<?= e_attr((string) ($_GET['q'] ?? '')) ?>" placeholder="filename, alt text, caption"></div>
<div><label>Folder</label><select name="folder"><option value="">All folders</option><?php foreach ($folders as $f): ?><option <?= (($_GET['folder'] ?? '') === $f) ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select></div>
<div><label>Tag</label><select name="tag"><option value="">All tags</option><?php foreach ($tags as $tag): ?><option <?= (($_GET['tag'] ?? '') === $tag) ? 'selected' : '' ?>><?= e($tag) ?></option><?php endforeach; ?></select></div>
<button class="btn secondary">Filter</button><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/media">Clear</a>
</form>
</div>

<div class="card">
<form method="post" id="media-bulk">
<?= $csrf->field() ?><input type="hidden" name="action" value="bulk">
<div style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem">
<strong><?= count($items) ?> file(s)</strong>
<div><label>Move selected to</label><input name="folder" list="media-folders" placeholder="keep current when blank"></div>
<div><label>Add tags</label><input name="tags" placeholder="seasonal, homepage"></div>
<button class="btn secondary">Apply to selected</button>
</div>
</form>
<div style="overflow:auto"><table>
<tr><th><input type="checkbox" aria-label="Select all" data-select-all style="width:auto"></th><th>File</th><th>Type</th><th>Organization &amp; presentation</th><th>Usage</th><th></th></tr>
<?php foreach ($items as $m): $itemTags = json_decode((string) ($m['tags_json'] ?? '[]'), true) ?: []; $uses = $mediaService->usages((int) $m['id']); ?>
<tr>
<td><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" form="media-bulk" style="width:auto"></td>
<td>
<?php if (str_starts_with($m['mime'], 'image/')): ?><img src="<?= e_attr($basePath) ?>/media/<?= (int) $m['id'] ?>/crop/96x96/<?= e_attr($m['original_name']) ?>" alt="<?= e_attr(($m['alt_text'] ?? '') !== '' ? $m['alt_text'] : 'Thumbnail for ' . $m['original_name']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:6px;vertical-align:middle"><?php endif; ?>
<a href="<?= e_attr($basePath) ?>/media/<?= (int) $m['id'] ?>/<?= e_attr($m['original_name']) ?>"><?= e($m['original_name']) ?></a><?= $m['is_private'] ? ' <span class="badge warn">private</span>' : '' ?>
<br><span class="muted"><?= e($m['folder'] ?: '(root)') ?> · <?= e($m['created_at'] ?? '') ?></span>
</td>
<td><?= e($m['mime']) ?><?= $m['width'] ? '<br><span class="muted">' . (int) $m['width'] . '×' . (int) $m['height'] . '</span>' : '' ?><br><span class="muted"><?= number_format(((int) $m['size_bytes']) / 1024, 1) ?> KB</span></td>
<td>
<details><summary class="btn small secondary">Edit</summary><form method="post" style="min-width:22rem;padding:.5rem 0">
<?= $csrf->field() ?><input type="hidden" name="action" value="meta"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
<label>Alt text</label><input name="alt_text" value="<?= e_attr($m['alt_text'] ?? '') ?>"><label>Caption</label><input name="caption" value="<?= e_attr($m['caption'] ?? '') ?>">
<label>Folder</label><input name="folder" list="media-folders" value="<?= e_attr($m['folder'] ?? '') ?>"><label>Tags (comma-separated)</label><input name="tags" value="<?= e_attr(implode(', ', $itemTags)) ?>">
<?php if (str_starts_with($m['mime'], 'image/')): ?><div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem"><div><label>Focal point X %</label><input type="number" min="0" max="100" step=".1" name="focal_x" value="<?= e_attr((string) ($m['focal_x'] ?? 50)) ?>"></div><div><label>Focal point Y %</label><input type="number" min="0" max="100" step=".1" name="focal_y" value="<?= e_attr((string) ($m['focal_y'] ?? 50)) ?>"></div></div><?php endif; ?>
<button class="btn small" type="submit">Save metadata</button></form></details>
<?php foreach ($itemTags as $tag): ?><span class="badge"><?= e($tag) ?></span> <?php endforeach; ?>
</td>
<td><details><summary><?= count($uses) ?> reference(s)</summary><?php if (!$uses): ?><p class="muted">Not currently referenced.</p><?php else: ?><ul><?php foreach ($uses as $use): ?><li><?= e($use['context']) ?><?= $use['context_id'] ? ' #' . (int) $use['context_id'] : '' ?></li><?php endforeach; ?></ul><?php endif; ?></details></td>
<td><details><summary class="btn small secondary">Replace</summary><form method="post" enctype="multipart/form-data" style="min-width:18rem;padding:.5rem 0"><?= $csrf->field() ?><input type="hidden" name="action" value="replace"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><label>New file</label><input type="file" name="replacement" required><p class="muted">Keeps this media ID and all references.</p><button class="btn small">Replace file</button></form></details>
<form method="post" style="margin-top:.5rem"><?= $csrf->field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="confirm" value="<?= $confirm === (int) $m['id'] ? '1' : '0' ?>"><button class="btn small danger"><?= $confirm === (int) $m['id'] ? 'Confirm delete' : 'Delete' ?></button></form></td>
</tr><?php endforeach; ?></table></div>
</div>
<script>document.querySelector('[data-select-all]')?.addEventListener('change',e=>document.querySelectorAll('input[name="ids[]"]').forEach(x=>x.checked=e.target.checked));</script>
