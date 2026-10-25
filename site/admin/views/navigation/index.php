<?php
/**
 * @var array $items @var array $tree @var array $pages @var array $redirects
 * @var array $depths @var int $menuId @var string $location @var array $locationMeta
 * @var \Core\Security\Csrf $csrf @var string $basePath @var string $siteBasePath
 */
$targetLabels = ['self' => 'Same tab', 'tab' => 'New tab', 'window' => 'New window'];
$targetLabel = static fn (string $target): string => $targetLabels[$target] ?? $targetLabels['self'];
$linkType = static function (array $item): string {
    if (!empty($item['page_id'])) return 'Website page';
    $url = (string) ($item['url'] ?? '');
    if (str_starts_with($url, 'mailto:')) return 'Email link';
    if (str_starts_with($url, 'tel:')) return 'Telephone link';
    if (str_starts_with($url, '#')) return 'Page anchor';
    return preg_match('#^https?://#i', $url) ? 'External website' : 'Internal path';
};
$pageOptions = function (?int $selected = null) use ($pages): void {
    foreach ($pages as $page): ?>
<option value="<?= (int) $page['id'] ?>" data-page-title="<?= e_attr($page['title']) ?>" <?= $selected === (int) $page['id'] ? 'selected' : '' ?>><?= e($page['title']) ?> — /<?= e($page['slug']) ?><?= $page['status'] !== 'published' ? ' (' . e($page['status']) . ')' : '' ?></option>
<?php endforeach;
};
$parentOptions = function () use ($items, $depths): void {
    foreach ($items as $candidate):
        $depth = min(5, (int) ($depths[(int) $candidate['id']] ?? 0)); ?>
<option value="<?= (int) $candidate['id'] ?>" <?= $depth >= 5 ? 'disabled' : '' ?>><?= e(str_repeat('— ', $depth) . $candidate['label'] . ($depth >= 5 ? ' — maximum depth' : '')) ?></option>
<?php endforeach;
};
$renderTree = function (array $nodes) use (&$renderTree, $csrf, $pageOptions, $targetLabels, $targetLabel, $linkType, $location): void {
    foreach ($nodes as $item):
        $id = (int) $item['id'];
        $enabled = (int) ($item['is_enabled'] ?? 1) === 1;
        $prefix = 'nav-item-' . $id;
?>
<li class="menu-node<?= $enabled ? '' : ' is-disabled' ?>" data-menu-node data-id="<?= $id ?>" draggable="true">
<div class="menu-card">
<button type="button" class="drag-grip" aria-label="Drag <?= e_attr($item['label']) ?> to reorder or nest" title="Drag to reorder or nest">⋮⋮</button>
<div class="menu-summary"><div><strong><?= e($item['label']) ?></strong><?php if (!$enabled): ?><span class="badge warn">Hidden</span><?php endif; ?></div><small><?= e($linkType($item)) ?> · <?= e($targetLabel((string) ($item['target'] ?? 'self'))) ?></small><code><?= e(!empty($item['page_id']) ? '/' . ($item['page_slug'] ?? '') : $item['url']) ?></code></div>
<div class="menu-actions"><button type="button" class="icon-btn move-up" aria-label="Move <?= e_attr($item['label']) ?> up" title="Move up">↑ <span class="move-label">Up</span></button><button type="button" class="icon-btn move-down" aria-label="Move <?= e_attr($item['label']) ?> down" title="Move down">↓ <span class="move-label">Down</span></button><button type="button" class="icon-btn indent" aria-label="Make <?= e_attr($item['label']) ?> a submenu" title="Make submenu">→</button><button type="button" class="icon-btn outdent" aria-label="Move <?= e_attr($item['label']) ?> up one level" title="Move up one level">←</button></div>
</div>
<details class="editor-drawer"><summary>Edit link</summary><div class="editor-drawer-body">
<form method="post" class="menu-edit-form" data-menu-detail-form><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="parent_id" value="<?= e_attr((string) ($item['parent_id'] ?? '')) ?>"><input type="hidden" name="sort_order" value="<?= (int) $item['sort_order'] ?>">
<label for="<?= $prefix ?>-label">Navigation label</label><input id="<?= $prefix ?>-label" name="label" value="<?= e_attr($item['label']) ?>" maxlength="190" required>
<div class="link-source"><div class="source-panel"><label for="<?= $prefix ?>-page">Website page</label><select id="<?= $prefix ?>-page" name="page_id"><option value="">— use a custom link —</option><?php $pageOptions(!empty($item['page_id']) ? (int) $item['page_id'] : null); ?></select><p class="field-note">The link follows that page if its URL changes.</p></div><div class="source-panel"><label for="<?= $prefix ?>-url">Custom URL</label><input id="<?= $prefix ?>-url" name="url" value="<?= e_attr($item['url']) ?>" maxlength="500" placeholder="/contact, #section, tel: or https://"><p class="field-note">Internal path, anchor, web, email, or telephone link.</p></div></div>
<fieldset class="target-fieldset"><legend>Open link</legend><div class="target-options"><?php foreach ($targetLabels as $value => $label): ?><label><input type="radio" name="target" value="<?= e_attr($value) ?>" <?= ($item['target'] ?? 'self') === $value ? 'checked' : '' ?>> <span><?= e($label) ?></span></label><?php endforeach; ?></div></fieldset>
<label class="toggle-control"><span><strong>Visible on website</strong><small>Turn this off to stage or temporarily hide this link and its submenu.</small></span><input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>><span class="toggle-track" aria-hidden="true"></span></label>
<button class="btn small" data-busy-label="Saving…">Save link details</button>
</form>
<form method="post" class="menu-remove-form" data-menu-detail-form data-confirm="Remove this menu item? Its child links will move up one level and remain available."><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn small danger">Remove link</button></form>
</div></details>
<ul class="menu-children"><?php $renderTree($item['children'] ?? []); ?></ul>
</li>
<?php endforeach;
};
?>

<div class="card-heading navigation-heading"><div><h2>Website navigation</h2><p class="card-subtitle">Build the header and footer menus visitors use throughout the site.</p></div><a class="btn secondary" href="<?= e_attr(rtrim($siteBasePath, '/') . '/') ?>" target="_blank" rel="noopener">Preview website ↗</a></div>

<nav class="menu-location-tabs" aria-label="Menu location">
<?php foreach ($locationMeta as $key => $meta): $menuCount = (int) ($meta['count'] ?? 0); ?><a class="menu-location-tab <?= $location === $key ? 'is-active' : '' ?>" href="<?= e_attr($basePath) ?>/admin/navigation?location=<?= e_attr($key) ?>" <?= $location === $key ? 'aria-current="page"' : '' ?>><span><?= e($meta['label']) ?></span><small><?= $menuCount ?> link<?= $menuCount === 1 ? '' : 's' ?><?= !empty($meta['hidden']) ? ' · ' . (int) $meta['hidden'] . ' hidden' : '' ?></small></a><?php endforeach; ?>
</nav>

<div class="navigation-status"><span><strong>Drag precisely:</strong> top edge places before, center creates a submenu, bottom edge places after.</span><span><strong>Keyboard:</strong> arrow buttons provide the same ordering and nesting controls.</span></div>

<div class="nav-workspace" data-menu-editor data-max-menu-depth="5">
<section class="card"><div class="card-heading"><div><h2><?= e($locationMeta[$location]['label']) ?></h2><p class="card-subtitle"><?= $location === 'primary' ? 'Shown in the website header.' : 'Shown in the website footer.' ?> Hidden parents also hide their submenu on the public site.</p></div><span class="badge"><?= count($items) ?> links</span></div>
<div class="menu-canvas"><div class="tree-drop-root" data-root-drop>Drop here to move a link to the top level</div><ul class="menu-tree" data-menu-tree><?php $renderTree($tree); ?></ul></div>
<form method="post" data-tree-form><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="reorder"><input type="hidden" name="tree_json" data-tree-json><div class="menu-savebar"><span class="muted" data-tree-status aria-live="polite">Menu order is up to date.</span><div class="button-row"><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/navigation?location=<?= e_attr($location) ?>" data-reset-tree hidden>Discard changes</a><button class="btn" data-save-tree disabled data-busy-label="Saving…">Save menu structure</button></div></div></form>
</section>

<aside class="card add-menu-card"><div class="card-heading"><div><h2>Add a <?= $location === 'primary' ? 'header' : 'footer' ?> link</h2><p class="card-subtitle">Link a managed page or enter a custom destination.</p></div></div>
<form method="post" data-menu-detail-form><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="sort_order" value="<?= count($items) ?>">
<label for="new-label">Navigation label</label><input id="new-label" name="label" maxlength="190" required placeholder="About us">
<div class="source-panel"><label for="new-page">Website page</label><select name="page_id" id="new-page"><option value="">— use a custom link —</option><?php $pageOptions(); ?></select><label for="new-url">Custom URL</label><input name="url" id="new-url" maxlength="500" placeholder="/contact, #section, tel: or https://"></div>
<label for="new-parent">Place under</label><select id="new-parent" name="parent_id"><option value="">Top level</option><?php $parentOptions(); ?></select><p class="field-note">Choose a parent now or position the link visually after adding it.</p>
<fieldset class="target-fieldset"><legend>Open link</legend><div class="target-options"><?php foreach ($targetLabels as $value => $label): ?><label><input type="radio" name="target" value="<?= e_attr($value) ?>" <?= $value === 'self' ? 'checked' : '' ?>> <span><?= e($label) ?></span></label><?php endforeach; ?></div></fieldset>
<label class="toggle-control"><span><strong>Visible immediately</strong><small>Turn this off when preparing a link before launch.</small></span><input type="checkbox" name="is_enabled" value="1" checked><span class="toggle-track" aria-hidden="true"></span></label>
<button class="btn" data-busy-label="Adding…">Add to <?= $location === 'primary' ? 'header' : 'footer' ?> menu</button>
</form></aside>
</div>

<section class="card redirect-workspace"><div class="card-heading"><div><h2>URL redirects</h2><p class="card-subtitle">Send retired or changed addresses to a live destination without losing visitors.</p></div><span class="badge"><?= count($redirects) ?> redirects</span></div>
<?php if ($redirects === []): ?><div class="empty-state"><strong>No redirects configured</strong><p>Add one when a page moves or an old campaign URL should lead somewhere new.</p></div><?php else: ?>
<table><thead><tr><th>Source</th><th>Target</th><th>Code</th><th>Hits</th><th>Actions</th></tr></thead><tbody><?php foreach ($redirects as $rd): ?><tr><td><code><?= e($rd['source']) ?></code></td><td><code><?= e($rd['target']) ?></code></td><td><span class="badge"><?= (int) $rd['status_code'] ?></span></td><td><?= number_format((int) $rd['hits']) ?></td><td><div class="table-actions"><details class="row-editor"><summary class="btn small secondary">Edit</summary><form method="post" class="row-editor-panel"><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="redirect_save"><input type="hidden" name="id" value="<?= (int) $rd['id'] ?>"><label for="redirect-<?= (int) $rd['id'] ?>-source">Source path</label><input id="redirect-<?= (int) $rd['id'] ?>-source" name="source" value="<?= e_attr($rd['source']) ?>" maxlength="500" required><label for="redirect-<?= (int) $rd['id'] ?>-target">Target</label><input id="redirect-<?= (int) $rd['id'] ?>-target" name="target" value="<?= e_attr($rd['target']) ?>" maxlength="500" required><label for="redirect-<?= (int) $rd['id'] ?>-code">Status code</label><select id="redirect-<?= (int) $rd['id'] ?>-code" name="status_code"><?php foreach ([301,302,307,308] as $code): ?><option <?= (int) $rd['status_code'] === $code ? 'selected' : '' ?>><?= $code ?></option><?php endforeach; ?></select><button class="btn small" data-busy-label="Saving…">Save redirect</button></form></details><form method="post" data-confirm="Remove this redirect? Requests to its old address will no longer be forwarded."><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="redirect_delete"><input type="hidden" name="id" value="<?= (int) $rd['id'] ?>"><button class="btn small danger">Remove</button></form></div></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<details class="disclosure"><summary class="btn secondary">Add redirect</summary><div class="disclosure-body"><form method="post"><?= $csrf->field() ?><input type="hidden" name="location" value="<?= e_attr($location) ?>"><input type="hidden" name="action" value="redirect_save"><div class="redirect-form"><div><label for="redirect-source">Source path</label><input id="redirect-source" name="source" maxlength="500" placeholder="/old-page" required></div><div><label for="redirect-target">Target</label><input id="redirect-target" name="target" maxlength="500" placeholder="/new-page" required></div><div><label for="redirect-code">Code</label><select id="redirect-code" name="status_code"><option>301</option><option>302</option><option>307</option><option>308</option></select></div><button class="btn" data-busy-label="Adding…">Add redirect</button></div></form></div></details>
</section>
