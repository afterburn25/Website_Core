<?php
/** @var array $pages @var array $sections @var array $shadowed @var bool $trash @var bool $canPublish
  * @var array $filters @var array $pagination @var array $counts
  * @var \Core\Security\Csrf $csrf @var string $basePath @var string $siteBasePath */
$pageUrl = static function (array $query = []) use ($basePath): string {
    return $basePath . '/admin/pages' . ($query ? '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
};
$activeQuery = array_filter([
    'view' => $trash ? 'trash' : null,
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
    'status' => $filters['status'] !== '' ? $filters['status'] : null,
    'review' => $filters['review'] !== '' ? $filters['review'] : null,
], static fn (mixed $value): bool => $value !== null && $value !== '');
?>
<div class="content-summary" aria-label="Content summary">
<a class="summary-stat<?= !$trash && $filters['status'] === '' && $filters['review'] === '' ? ' is-active' : '' ?>" href="<?= e_attr($pageUrl()) ?>"><strong><?= $counts['active'] ?></strong><span>Active</span></a>
<a class="summary-stat<?= $filters['status'] === 'published' ? ' is-active' : '' ?>" href="<?= e_attr($pageUrl(['status' => 'published'])) ?>"><strong><?= $counts['published'] ?></strong><span>Published</span></a>
<a class="summary-stat<?= $filters['status'] === 'draft' ? ' is-active' : '' ?>" href="<?= e_attr($pageUrl(['status' => 'draft'])) ?>"><strong><?= $counts['draft'] ?></strong><span>Drafts</span></a>
<a class="summary-stat<?= $filters['review'] === 'pending' ? ' is-active' : '' ?>" href="<?= e_attr($pageUrl(['review' => 'pending'])) ?>"><strong><?= $counts['pending'] ?></strong><span>Review</span></a>
<a class="summary-stat<?= $trash ? ' is-active' : '' ?>" href="<?= e_attr($pageUrl(['view' => 'trash'])) ?>"><strong><?= $counts['trash'] ?></strong><span>Trash</span></a>
</div>

<div class="card">
<div class="card-heading">
<div><h2><?= $trash ? 'Deleted pages' : 'Pages' ?></h2><p class="muted"><?= $trash ? 'Restore a page or permanently remove it and its revision history.' : 'Create, review, schedule, and publish the pages visitors see.' ?></p></div>
<?php if (!$trash): ?><a class="btn" href="<?= e_attr($basePath) ?>/admin/pages/edit">New page</a><?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= e_attr($siteBasePath) ?>/index.php">
<input type="hidden" name="p" value="/admin/pages">
<?php if ($trash): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
<label><span>Search</span><input type="search" name="q" value="<?= e_attr($filters['q']) ?>" placeholder="Title or URL"></label>
<?php if (!$trash): ?>
<label><span>Publishing</span><select name="status"><option value="">Any status</option><option value="published"<?= $filters['status'] === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $filters['status'] === 'draft' ? ' selected' : '' ?>>Draft</option></select></label>
<label><span>Review</span><select name="review"><option value="">Any state</option><option value="draft"<?= $filters['review'] === 'draft' ? ' selected' : '' ?>>Draft</option><option value="pending"<?= $filters['review'] === 'pending' ? ' selected' : '' ?>>Pending</option><option value="approved"<?= $filters['review'] === 'approved' ? ' selected' : '' ?>>Approved</option></select></label>
<?php endif; ?>
<button class="btn secondary" type="submit">Apply filters</button>
<?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['review'] !== ''): ?><a class="btn secondary" href="<?= e_attr($pageUrl($trash ? ['view' => 'trash'] : [])) ?>">Clear</a><?php endif; ?>
</form>

<?php if ($pages === []): ?>
<div class="empty-state"><strong><?= $filters['q'] !== '' ? 'No pages match these filters.' : ($trash ? 'Trash is empty.' : 'No pages yet.') ?></strong><p><?= !$trash && $filters['q'] === '' ? 'Create the first page to begin building the site.' : 'Try changing the filters or return to active pages.' ?></p><?php if (!$trash && $filters['q'] === ''): ?><a class="btn" href="<?= e_attr($basePath) ?>/admin/pages/edit">Create a page</a><?php endif; ?></div>
<?php else: ?>
<div class="table-wrap"><table class="content-table">
<thead><tr><th>Page</th><th>Publishing</th><th>Review</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
<tbody>
<?php foreach ($pages as $p): ?>
<tr>
<td><strong><?= e($p['title']) ?></strong><span class="page-path">/<?= e($p['slug']) ?></span><?php if (!empty($shadowed[$p['slug']])): ?> <span class="badge bad" title="A reserved path or installed module route handles this URL.">unreachable</span><?php endif; ?></td>
<td><span class="badge <?= $p['status'] === 'published' ? 'ok' : 'warn' ?>"><?= e(ucfirst($p['status'])) ?></span><?php if (!empty($p['publish_at'])): ?><span class="table-note">Starts <?= e($p['publish_at']) ?></span><?php endif; ?><?php if (!empty($p['unpublish_at'])): ?><span class="table-note">Ends <?= e($p['unpublish_at']) ?></span><?php endif; ?></td>
<td><span class="badge <?= ($p['review_status'] ?? 'draft') === 'approved' ? 'ok' : (($p['review_status'] ?? 'draft') === 'pending' ? 'warn' : '') ?>"><?= e(ucfirst($p['review_status'] ?? 'draft')) ?></span></td>
<td><time datetime="<?= e_attr($p['updated_at'] ?? '') ?>"><?= e($p['updated_at'] ?? '—') ?></time></td>
<td><div class="row-actions">
<?php if ($trash): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/restore"><?= $csrf->field() ?><button class="btn small">Restore</button></form>
<?php if ($canPublish): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/purge" data-confirm="Permanently delete this page, its translations, preview links, menu links, and revision history? This cannot be undone."><?= $csrf->field() ?><button class="btn small danger">Delete permanently</button></form><?php endif; ?>
<?php else: ?>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $p['id'] ?>">Edit</a>
<?php if ($p['status'] === 'published'): ?><a class="btn small secondary" href="<?= e_attr($basePath) ?>/<?= e_attr($p['slug']) ?>" target="_blank" rel="noopener">View ↗</a><?php else: ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/preview" target="_blank"><?= $csrf->field() ?><button class="btn small secondary">Preview ↗</button></form><?php endif; ?>
<?php if ($canPublish): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/publish"><?= $csrf->field() ?><input type="hidden" name="do" value="<?= $p['status'] === 'published' ? 'unpublish' : 'publish' ?>"><button class="btn small secondary"><?= $p['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
<?php else: ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/review"><?= $csrf->field() ?><button class="btn small secondary" <?= ($p['review_status'] ?? '') === 'pending' ? 'disabled' : '' ?>><?= ($p['review_status'] ?? '') === 'pending' ? 'Review pending' : 'Submit for review' ?></button></form><?php endif; ?>
<details class="row-menu"><summary class="btn small secondary" aria-label="More actions for <?= e_attr($p['title']) ?>">More</summary><div><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/duplicate"><?= $csrf->field() ?><button>Duplicate as draft</button></form><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $p['id'] ?>/trash" data-confirm="Move this page to trash? It will immediately be unpublished."><?= $csrf->field() ?><button class="text-bad">Move to trash</button></form></div></details>
<?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>

<?php if ($pagination['pages'] > 1): ?><nav class="pagination" aria-label="Page list pagination"><span>Showing <?= count($pages) ?> of <?= $pagination['total'] ?></span><div><?php if ($pagination['page'] > 1): ?><a class="btn small secondary" href="<?= e_attr($pageUrl($activeQuery + ['page' => $pagination['page'] - 1])) ?>">Previous</a><?php endif; ?><span>Page <?= $pagination['page'] ?> of <?= $pagination['pages'] ?></span><?php if ($pagination['page'] < $pagination['pages']): ?><a class="btn small secondary" href="<?= e_attr($pageUrl($activeQuery + ['page' => $pagination['page'] + 1])) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
<?php endif; ?>
</div>

<?php if (!$trash): ?>
<div class="card">
<div class="card-heading"><div><h2>Reusable sections</h2><p class="muted">Sanitized HTML fragments themes can render with <code>section('key')</code>.</p></div><details><summary class="btn secondary">New section</summary><form method="post" class="popover-form"><?= $csrf->field() ?><input type="hidden" name="action" value="section_save"><label>Key <span class="muted">a-z, 0-9, underscore</span><input name="key" required pattern="[a-z0-9_]+"></label><label>Label<input name="label" required></label><label>Body<textarea name="body" rows="6"></textarea></label><button class="btn">Create section</button></form></details></div>
<?php if ($sections === []): ?><div class="empty-state"><strong>No reusable sections.</strong><p>Create one when a theme needs shared content outside a page.</p></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Key</th><th>Label</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($sections as $s): ?><tr><td><code><?= e($s['key']) ?></code></td><td><?= e($s['label']) ?></td><td><?= e($s['updated_at'] ?? '—') ?></td><td><div class="row-actions"><details><summary class="btn small secondary">Edit</summary><form method="post" class="popover-form"><?= $csrf->field() ?><input type="hidden" name="action" value="section_save"><input type="hidden" name="key" value="<?= e_attr($s['key']) ?>"><label>Label<input name="label" value="<?= e_attr($s['label']) ?>" required></label><label>Body<textarea name="body" rows="6"><?= e($s['body'] ?? '') ?></textarea></label><button class="btn small">Save</button></form></details><form method="post" data-confirm="Remove this reusable section? Themes using its key will receive no content."><?= $csrf->field() ?><input type="hidden" name="action" value="section_delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn small danger">Remove</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</div>
<div class="card"><div class="card-heading"><div><h2>Package content types</h2><p class="muted">Structured FAQs, testimonials, and other records declared by installed modules.</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/content">Manage content types</a></div></div>
<?php endif; ?>
