<?php
/** @var array $items @var array $folders @var array $tags @var int $confirm
 * @var array $filters @var array $summary @var int $total @var int $page @var int $pages
 * @var \Core\Security\Csrf $csrf @var string $basePath @var \Core\Media\MediaService $mediaService */
$pageUrl = static function (int $target) use ($basePath, $filters): string {
    $query = array_filter([
        'q' => $filters['search'], 'folder' => $filters['folder'], 'tag' => $filters['tag'],
        'page' => $target > 1 ? $target : null,
    ], static fn ($value): bool => $value !== null && $value !== '');
    return $basePath . '/admin/media' . ($query ? '?' . http_build_query($query) : '');
};
?>
<div class="metric-grid media-summary">
<div class="metric"><span>All media</span><strong><?= (int) $summary['total'] ?></strong><small>stored files</small></div>
<div class="metric"><span>Images</span><strong><?= (int) $summary['images'] ?></strong><small>with responsive variants</small></div>
<div class="metric"><span>Documents</span><strong><?= max(0, (int) $summary['total'] - (int) $summary['images']) ?></strong><small>PDF and text files</small></div>
<div class="metric"><span>Protected</span><strong><?= (int) $summary['private'] ?></strong><small>administrator-only files</small></div>
</div>

<div class="section-grid media-tools">
<section class="card">
<div class="card-heading"><div><h2>Upload media</h2><p class="card-subtitle">Add an image or document, then reuse it anywhere without uploading another copy.</p></div><span class="badge">15 MB maximum</span></div>
<form method="post" enctype="multipart/form-data">
<?= $csrf->field() ?><input type="hidden" name="action" value="upload">
<div class="drop-zone" data-drop-zone>
<strong>Choose a file or drop it here</strong>
<p class="field-note">JPG, PNG, GIF, WebP, ICO, PDF or plain text. Executable content is rejected.</p>
<input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.ico,.pdf,.txt" required>
</div>
<div class="inline-fields"><div><label for="upload-folder">Folder</label><input id="upload-folder" name="folder" list="media-folders" placeholder="For example: Homepage"></div><label class="choice-row"><input type="checkbox" name="private" value="1"><span><strong>Private file</strong><br><small>Only signed-in administrators can open it.</small></span></label></div>
<div class="button-row" style="margin-top:1rem"><button class="btn" type="submit" data-busy-label="Uploading…">Upload file</button></div>
</form>
</section>

<section class="card">
<div class="card-heading"><div><h2>Find media</h2><p class="card-subtitle">Narrow the library by filename, descriptive text, folder, or tag.</p></div><?php if ($filters['search'] || $filters['folder'] || $filters['tag']): ?><span class="badge warn">Filtered</span><?php endif; ?></div>
<form method="get" action="<?= e_attr($basePath) ?>/admin/media">
<label for="media-search">Search</label><input id="media-search" type="search" name="q" value="<?= e_attr($filters['search']) ?>" placeholder="Filename, alt text, or caption">
<div class="grid2"><div><label for="media-folder">Folder</label><select id="media-folder" name="folder"><option value="">All folders</option><?php foreach ($folders as $folder): ?><option value="<?= e_attr($folder) ?>" <?= $filters['folder'] === $folder ? 'selected' : '' ?>><?= e($folder) ?></option><?php endforeach; ?></select></div><div><label for="media-tag">Tag</label><select id="media-tag" name="tag"><option value="">All tags</option><?php foreach ($tags as $mediaTag): ?><option value="<?= e_attr($mediaTag) ?>" <?= $filters['tag'] === $mediaTag ? 'selected' : '' ?>><?= e($mediaTag) ?></option><?php endforeach; ?></select></div></div>
<div class="button-row" style="margin-top:1rem"><button class="btn secondary">Apply filters</button><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/media">Clear</a></div>
</form>
</section>
</div>

<datalist id="media-folders"><?php foreach ($folders as $folder): ?><option value="<?= e_attr($folder) ?>"><?php endforeach; ?></datalist>

<section class="card" data-selection-scope>
<div class="card-heading"><div><h2>Media library</h2><p class="card-subtitle">Showing <?= count($items) ?> of <?= $total ?> matching files.</p></div><span class="selection-summary" data-selection-summary>None selected</span></div>
<?php if ($items === []): ?>
<div class="empty-state"><strong>No media matches this view</strong><p>Clear the filters or upload the first file for this folder.</p></div>
<?php else: ?>
<form method="post" id="media-bulk" class="action-bar">
<?= $csrf->field() ?><input type="hidden" name="action" value="bulk">
<label class="choice-row"><input type="checkbox" aria-label="Select every visible media item" data-select-all><span>Select page</span></label>
<div><label for="bulk-folder">Move selected to</label><input id="bulk-folder" name="folder" list="media-folders" placeholder="Keep existing folders"></div>
<div><label for="bulk-tags">Add tags</label><input id="bulk-tags" name="tags" placeholder="featured, homepage"></div>
<button class="btn secondary" data-busy-label="Updating…">Update selected</button>
</form>

<div class="media-card-grid">
<?php foreach ($items as $media):
    $itemTags = json_decode((string) ($media['tags_json'] ?? '[]'), true) ?: [];
    $uses = $mediaService->usages((int) $media['id']);
    $isImage = str_starts_with((string) $media['mime'], 'image/');
?>
<article class="media-card">
<label class="media-select"><input type="checkbox" name="ids[]" value="<?= (int) $media['id'] ?>" form="media-bulk" data-select-item><span class="sr-only">Select <?= e($media['original_name']) ?></span></label>
<a class="media-preview" href="<?= e_attr($basePath) ?>/media/<?= (int) $media['id'] ?>/<?= e_attr($media['original_name']) ?>" target="_blank" rel="noopener">
<?php if ($isImage): ?><img src="<?= e_attr($basePath) ?>/media/<?= (int) $media['id'] ?>/crop/420x280/<?= e_attr($media['original_name']) ?>" alt="<?= e_attr(($media['alt_text'] ?? '') !== '' ? $media['alt_text'] : 'Preview of ' . $media['original_name']) ?>"><?php else: ?><span class="media-file-kind"><?= e(strtoupper(pathinfo((string) $media['original_name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span><?php endif; ?>
</a>
<div class="media-card-body">
<div class="media-card-title"><strong title="<?= e_attr($media['original_name']) ?>"><?= e($media['original_name']) ?></strong><?php if ((int) $media['is_private'] === 1): ?><span class="badge warn">Private</span><?php endif; ?></div>
<p class="media-meta"><?= e($media['folder'] ?: 'Unfiled') ?> · <?= number_format(((int) $media['size_bytes']) / 1024, 1) ?> KB<?php if ($media['width']): ?> · <?= (int) $media['width'] ?>×<?= (int) $media['height'] ?><?php endif; ?></p>
<div class="media-tags"><?php foreach ($itemTags as $itemTag): ?><span class="badge"><?= e($itemTag) ?></span><?php endforeach; ?><?php if ($itemTags === []): ?><span class="muted">No tags</span><?php endif; ?></div>
<div class="media-card-actions">
<details><summary class="btn small secondary">Details</summary><div class="media-popover">
<form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="meta"><input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
<label>Alt text</label><input name="alt_text" value="<?= e_attr($media['alt_text'] ?? '') ?>" placeholder="Describe the image for visitors who cannot see it"><label>Caption</label><input name="caption" value="<?= e_attr($media['caption'] ?? '') ?>"><label>Folder</label><input name="folder" list="media-folders" value="<?= e_attr($media['folder'] ?? '') ?>"><label>Tags</label><input name="tags" value="<?= e_attr(implode(', ', $itemTags)) ?>">
<?php if ($isImage): ?><div class="grid2"><div><label>Focal X %</label><input type="number" min="0" max="100" step=".1" name="focal_x" value="<?= e_attr((string) ($media['focal_x'] ?? 50)) ?>"></div><div><label>Focal Y %</label><input type="number" min="0" max="100" step=".1" name="focal_y" value="<?= e_attr((string) ($media['focal_y'] ?? 50)) ?>"></div></div><?php endif; ?>
<p class="field-note"><?= count($uses) ?> website reference<?= count($uses) === 1 ? '' : 's' ?>.</p><button class="btn small" data-busy-label="Saving…">Save details</button>
</form>
<form method="post" enctype="multipart/form-data" class="media-replace"><?= $csrf->field() ?><input type="hidden" name="action" value="replace"><input type="hidden" name="id" value="<?= (int) $media['id'] ?>"><label>Replace file</label><input type="file" name="replacement" required><p class="field-note">The media ID and existing website references stay intact.</p><button class="btn small secondary" data-busy-label="Replacing…">Replace</button></form>
</div></details>
<form method="post" data-confirm="<?= $confirm === (int) $media['id'] ? 'Delete this referenced file and remove its usage records?' : 'Delete this file?' ?>"><?= $csrf->field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $media['id'] ?>"><input type="hidden" name="confirm" value="<?= $confirm === (int) $media['id'] ? '1' : '0' ?>"><button class="btn small danger"><?= $confirm === (int) $media['id'] ? 'Confirm delete' : 'Delete' ?></button></form>
</div>
</div>
</article>
<?php endforeach; ?>
</div>

<?php if ($pages > 1): ?><nav class="pagination" aria-label="Media pages"><span>Page <?= $page ?> of <?= $pages ?></span><div><?php if ($page > 1): ?><a class="btn small secondary" href="<?= e_attr($pageUrl($page - 1)) ?>">Previous</a><?php endif; ?><?php if ($page < $pages): ?><a class="btn small secondary" href="<?= e_attr($pageUrl($page + 1)) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
<?php endif; ?>
</section>
<script>document.querySelector('[data-select-all]')?.addEventListener('change',event=>document.querySelectorAll('[data-select-item]').forEach(box=>{box.checked=event.target.checked;box.dispatchEvent(new Event('change',{bubbles:true}))}));</script>
