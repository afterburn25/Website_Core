<?php
/** @var array|null $page @var string|null $error @var array $revisions @var array $blockDefinitions
  * @var array $seoAudit @var bool $canPublish @var array $parentPages @var array $ogImages
  * @var \Core\Security\Csrf $csrf @var string $basePath */
$editing = !empty($page['id']);
$status = (string) ($page['status'] ?? 'draft');
$reviewStatus = (string) ($page['review_status'] ?? 'draft');
$assetVersion = rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development');
?>
<div class="editor-toolbar">
<div><a class="back-link" href="<?= e_attr($basePath) ?>/admin/pages">← All pages</a><p class="muted"><?= $editing ? 'Last updated ' . e($page['updated_at'] ?? '—') : 'Create a draft, then preview and publish it.' ?></p></div>
<div class="button-row">
<?php if ($editing && $status === 'published'): ?>
<a class="btn secondary" href="<?= e_attr($basePath) ?>/<?= e_attr($page['slug']) ?>" target="_blank" rel="noopener">View live ↗</a>
<?php endif; ?>
<?php if ($editing): ?>
<button class="btn secondary" type="submit" form="page-preview-form">Preview draft ↗</button>
<?php endif; ?>
<button class="btn" type="submit" form="page-editor-form">Save draft</button>
</div>
</div>

<?php if ($error): ?><div class="flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="page-editor-form" class="editor-layout" data-dirty-check>
<?= $csrf->field() ?>
<input type="hidden" name="lock_version" value="<?= (int) ($page['lock_version'] ?? 1) ?>">
<main class="editor-main">
<section class="card editor-card" aria-labelledby="page-content-heading">
<div class="card-heading"><div><h2 id="page-content-heading">Page content</h2><p class="muted">Required fields are marked with an asterisk.</p></div><span class="badge <?= $status === 'published' ? 'ok' : 'warn' ?>"><?= e(ucfirst($status)) ?></span></div>
<label for="page-title">Title <span aria-hidden="true">*</span></label>
<input id="page-title" name="title" value="<?= e_attr($page['title'] ?? '') ?>" maxlength="190" autocomplete="off" required>
<div class="field-help"><span>The visitor-facing page name.</span><span data-count-for="page-title"></span></div>

<div class="grid2">
<div><label for="page-slug">URL slug</label><div class="input-prefix"><span>/</span><input id="page-slug" name="slug" value="<?= e_attr($page['slug'] ?? '') ?>" maxlength="190" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" aria-describedby="slug-help"></div><p class="field-help" id="slug-help">Generated from the title until you edit it.</p></div>
<div><label for="parent-id">Parent page</label><select id="parent-id" name="parent_id"><option value="">No parent</option><?php foreach ($parentPages as $parent): ?><option value="<?= (int) $parent['id'] ?>"<?= (int) ($page['parent_id'] ?? 0) === (int) $parent['id'] ? ' selected' : '' ?>><?= e($parent['title']) ?> · /<?= e($parent['slug']) ?></option><?php endforeach; ?></select><p class="field-help">Organizes page relationships; menus are managed separately.</p></div>
</div>

<label for="page-body">Body</label>
<textarea id="page-body" name="body" rows="12" aria-describedby="body-help"><?= e($page['body'] ?? '') ?></textarea>
<p class="field-help" id="body-help">Safe rich HTML is allowed. Scripts and unsafe embeds are removed when saved. Blocks below take precedence when present.</p>
</section>

<section class="builder-shell" aria-labelledby="builder-title">
<div class="card-heading"><div><h2 id="builder-title">Visual page composer</h2><p class="muted">Build the page from reusable Core and module blocks. Drag or use the move buttons to reorder.</p></div><div class="button-row"><label class="sr-only" for="block-type">Block type</label><select id="block-type"><?php foreach ($blockDefinitions as $type => $definition): ?><option value="<?= e_attr($type) ?>"><?= e($definition['label']) ?><?= ($definition['package'] ?? 'core') !== 'core' ? ' · ' . e($definition['package']) : '' ?></option><?php endforeach; ?></select><button class="btn secondary" type="button" id="add-block">Add block</button></div></div>
<input type="hidden" name="blocks_json" id="blocks-json" value="<?= e_attr((string) ($page['blocks_json'] ?? '[]')) ?>">
<div id="blocks-list" class="blocks-list"></div>
<div id="blocks-empty" class="empty-state"><strong>No visual blocks.</strong><p>The body field above will be displayed until a block is added.</p></div>
<p class="sr-only" id="blocks-status" aria-live="polite"></p>
</section>

<section class="card" aria-labelledby="seo-heading">
<div class="card-heading"><div><h2 id="seo-heading">Search and sharing</h2><p class="muted">Control how this page appears in search results and social previews.</p></div></div>
<div class="grid2"><div><label for="meta-title">Meta title</label><input id="meta-title" name="meta_title" value="<?= e_attr($page['meta_title'] ?? '') ?>" maxlength="190"><div class="field-help"><span>Defaults to the page title.</span><span data-count-for="meta-title"></span></div></div><div><label for="og-media">Social image</label><select id="og-media" name="og_media_id"><option value="">No image selected</option><?php foreach ($ogImages as $image): ?><option value="<?= (int) $image['id'] ?>"<?= (int) ($page['og_media_id'] ?? 0) === (int) $image['id'] ? ' selected' : '' ?>><?= e($image['original_name']) ?></option><?php endforeach; ?></select><p class="field-help">Only public images can be selected.</p></div></div>
<label for="meta-description">Meta description</label><textarea id="meta-description" name="meta_description" rows="3" maxlength="300"><?= e($page['meta_description'] ?? '') ?></textarea><div class="field-help"><span>A concise summary for search results.</span><span data-count-for="meta-description"></span></div>

<div class="search-preview" aria-label="Search result preview"><span class="search-preview-label">Preview</span><div id="seo-preview-title"><?= e(($page['meta_title'] ?? '') !== '' ? $page['meta_title'] : ($page['title'] ?? 'Page title')) ?></div><div class="search-preview-url">/<?= e($page['slug'] ?? 'page-url') ?></div><p id="seo-preview-description"><?= e(($page['meta_description'] ?? '') !== '' ? $page['meta_description'] : 'Add a meta description to control this preview.') ?></p></div>

<div class="grid2"><div><label for="canonical">Canonical URL</label><input id="canonical" name="canonical" type="url" value="<?= e_attr($page['canonical'] ?? '') ?>" maxlength="500" placeholder="https://example.com/preferred-page"><p class="field-help">Optional complete URL for duplicate-content cases.</p></div><div><label for="schema-json">Schema.org JSON</label><textarea id="schema-json" name="schema_json" rows="7" spellcheck="false" placeholder='{"@type":"Service","name":"Catering"}'><?= e($page['schema_json'] ?? '') ?></textarea><p class="field-help"><code>@context</code> is supplied automatically.</p></div></div>
<label class="check-row"><input type="checkbox" name="noindex" value="1" <?= !empty($page['noindex']) ? 'checked' : '' ?>><span><strong>Hide from search engines</strong><small>Adds noindex/nofollow and excludes the page from the sitemap.</small></span></label>
<?php if ($seoAudit): ?><div class="content-checks"><h3>Content checks</h3><?php foreach ($seoAudit as $issue): ?><p><span class="badge <?= $issue['level'] === 'error' ? 'bad' : 'warn' ?>"><?= e(ucfirst($issue['level'])) ?></span><span><strong><?= e($issue['title']) ?></strong><small><?= e($issue['help']) ?></small></span></p><?php endforeach; ?></div><?php endif; ?>
</section>
</main>

<aside class="editor-sidebar" aria-label="Page settings">
<section class="card"><h2>Save and status</h2><dl class="status-list"><div><dt>Publishing</dt><dd><span class="badge <?= $status === 'published' ? 'ok' : 'warn' ?>"><?= e(ucfirst($status)) ?></span></dd></div><div><dt>Review</dt><dd><span class="badge <?= $reviewStatus === 'approved' ? 'ok' : ($reviewStatus === 'pending' ? 'warn' : '') ?>"><?= e(ucfirst($reviewStatus)) ?></span></dd></div><?php if ($editing): ?><div><dt>Revision</dt><dd>#<?= (int) ($page['lock_version'] ?? 1) ?></dd></div><?php endif; ?></dl><p class="muted">Saving creates a draft and clears approval and scheduling so changes cannot go live accidentally.</p><button class="btn full" type="submit">Save draft</button></section>
<?php if ($editing): ?><section class="card"><h2>Quick links</h2><div class="stack-actions"><a href="<?= e_attr($basePath) ?>/admin/navigation">Add to navigation</a><a href="<?= e_attr($basePath) ?>/admin/media">Open Media library</a><a href="<?= e_attr($basePath) ?>/admin/seo">Open SEO audit</a></div></section><?php endif; ?>
</aside>
</form>

<?php if ($editing): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/preview" id="page-preview-form" target="_blank"><?= $csrf->field() ?></form>

<div class="editor-lower-grid">
<section class="card">
<div class="card-heading"><div><h2>Editorial workflow</h2><p class="muted">Publishing actions are recorded in the audit log.</p></div></div>
<?php if (!empty($page['review_note'])): ?><div class="review-note"><strong>Reviewer note</strong><p><?= nl2br(e($page['review_note'])) ?></p></div><?php endif; ?>
<?php if ($canPublish): ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/publish" data-confirm="<?= $status === 'published' ? 'Unpublish this page now?' : 'Approve and publish this draft now?' ?>"><?= $csrf->field() ?><input type="hidden" name="do" value="<?= $status === 'published' ? 'unpublish' : 'publish' ?>"><button class="btn"><?= $status === 'published' ? 'Unpublish page' : 'Approve and publish' ?></button></form>
<hr><h3>Publishing schedule</h3><p class="muted">Times use the configured site/server timezone. Clear both fields to remove the schedule.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/schedule"><div class="grid2"><?= $csrf->field() ?><label>Publish at<input type="datetime-local" name="publish_at" value="<?= e_attr(!empty($page['publish_at']) ? date('Y-m-d\TH:i', strtotime($page['publish_at'])) : '') ?>"></label><label>Unpublish at<input type="datetime-local" name="unpublish_at" value="<?= e_attr(!empty($page['unpublish_at']) ? date('Y-m-d\TH:i', strtotime($page['unpublish_at'])) : '') ?>"></label></div><button class="btn secondary">Save schedule</button></form>
<?php else: ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/review"><?= $csrf->field() ?><label for="review-note">Note for reviewer <span class="muted">optional</span></label><textarea id="review-note" name="review_note" rows="4" maxlength="1000"><?= e($page['review_note'] ?? '') ?></textarea><button class="btn">Submit for review</button></form><?php endif; ?>
</section>

<section class="card">
<div class="card-heading"><div><h2>Revision history</h2><p class="muted">The 20 most recent saved versions are available for comparison and recovery.</p></div></div>
<?php if ($revisions === []): ?><div class="empty-state"><strong>No earlier revisions.</strong><p>A snapshot is created before the next successful save.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Saved</th><th>By</th><th>Title</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody><?php foreach ($revisions as $index => $rev): ?><tr><td><?= e($rev['created_at']) ?></td><td><?= e($rev['saved_by_name'] ?: 'Administrator #' . (int) $rev['saved_by']) ?></td><td><?= e($rev['title']) ?></td><td><div class="row-actions"><?php if (isset($revisions[$index + 1])): ?><a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/compare/<?= (int) $revisions[$index + 1]['id'] ?>/<?= (int) $rev['id'] ?>">Compare</a><?php endif; ?><form method="post" action="<?= e_attr($basePath) ?>/admin/pages/<?= (int) $page['id'] ?>/restore/<?= (int) $rev['id'] ?>" data-confirm="Restore this revision as the new draft? The current page will be preserved as another revision."><?= $csrf->field() ?><button class="btn small">Restore</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
</div>
<?php endif; ?>

<script type="application/json" id="block-definitions"><?= json_encode(array_map(static fn (array $definition): array => ['label' => $definition['label'], 'fields' => $definition['fields'], 'package' => $definition['package'] ?? 'core'], $blockDefinitions), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e_attr($basePath) ?>/_a/core/js/page-editor.js&amp;v=<?= e_attr($assetVersion) ?>" defer></script>
