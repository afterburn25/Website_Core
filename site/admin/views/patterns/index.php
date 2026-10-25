<?php
/** @var array $patterns @var array $pages @var \Core\Security\Csrf $csrf @var string $basePath */
$blockLabels = [
    'hero' => 'Hero', 'rich_text' => 'Rich text', 'image_text' => 'Image + text', 'gallery' => 'Gallery',
    'cta' => 'Call to action', 'testimonials' => 'Testimonials', 'faq' => 'FAQ', 'stats' => 'Statistics',
    'logo_strip' => 'Logo strip', 'contact' => 'Contact details', 'form' => 'Form', 'spacer' => 'Spacer',
];
$formatDate = static function (mixed $value): string {
    $time = strtotime((string) $value);
    return $time ? date('M j, Y · g:i a', $time) : (string) $value;
};
?>
<section class="status-strip" aria-label="Layout pattern overview">
    <div class="status-chip <?= $patterns ? 'ok' : '' ?>"><strong><?= count($patterns) ?></strong><span>Saved patterns</span></div>
    <div class="status-chip"><strong><?= count($pages) ?></strong><span>Available pages</span></div>
    <a class="status-chip" href="<?= e_attr($basePath) ?>/admin/pages"><strong>Pages</strong><span>Open content library</span></a>
</section>

<section class="card pattern-capture-card">
    <div class="card-heading"><div><p class="section-kicker">Capture</p><h2>Save a reusable layout</h2><p class="card-subtitle">Copy the visual-block structure and content from an existing page into a reusable starting point.</p></div></div>
    <form method="post" class="pattern-capture-form">
        <?= $csrf->field() ?><input type="hidden" name="action" value="create">
        <div><label for="pattern-name">Pattern name</label><input id="pattern-name" name="name" maxlength="190" required placeholder="Service landing page"></div>
        <div><label for="pattern-source">Copy blocks from</label><select id="pattern-source" name="page_id" required><option value="">Choose a page with visual blocks…</option><?php foreach ($pages as $page): ?><option value="<?= (int) $page['id'] ?>"><?= e($page['title']) ?> · /<?= e($page['slug']) ?></option><?php endforeach; ?></select></div>
        <button class="btn" data-busy-label="Capturing…">Create pattern</button>
    </form>
    <p class="field-note">Applying a pattern never publishes automatically. Core saves the current page as a revision, applies the pattern, and returns the page to draft.</p>
</section>

<section class="pattern-library-heading">
    <div><p class="section-kicker">Library</p><h2>Reusable layouts</h2><p class="muted">Preview each structure, create a variant, or apply it safely to a page.</p></div>
    <?php if ($patterns): ?><label class="pattern-search"><span>Find a pattern</span><input type="search" placeholder="Search names or block types" data-pattern-filter></label><?php endif; ?>
</section>

<?php if (!$patterns): ?>
    <div class="empty-state pattern-empty"><strong>No saved layouts yet</strong><span>Choose a page above to turn its visual blocks into the first reusable pattern.</span></div>
<?php else: ?>
<div class="pattern-grid" data-pattern-library>
<?php foreach ($patterns as $pattern):
    $blocks = json_decode((string) $pattern['blocks_json'], true);
    $blocks = is_array($blocks) ? array_values($blocks) : [];
    $types = [];
    foreach ($blocks as $block) {
        $type = (string) ($block['type'] ?? 'unknown');
        $types[] = $blockLabels[$type] ?? ucwords(str_replace(['_', '-'], ' ', $type));
    }
?>
    <article class="card pattern-card" data-pattern-card data-pattern-keywords="<?= e_attr(strtolower($pattern['name'] . ' ' . implode(' ', $types))) ?>">
        <div class="pattern-card-heading"><div><span class="pattern-icon" aria-hidden="true">▦</span><div><h3><?= e($pattern['name']) ?></h3><p>Updated <?= e($formatDate($pattern['updated_at'])) ?></p></div></div><span class="badge"><?= count($blocks) ?> block<?= count($blocks) === 1 ? '' : 's' ?></span></div>
        <div class="pattern-preview" aria-label="Block order preview">
            <?php if ($blocks === []): ?><span class="pattern-block is-empty">No visual blocks</span><?php else: foreach ($blocks as $index => $block): $type = (string) ($block['type'] ?? 'unknown'); ?>
                <span class="pattern-block <?= !empty($block['hidden']) ? 'is-hidden' : '' ?>"><b><?= $index + 1 ?></b><span><?= e($blockLabels[$type] ?? ucwords(str_replace(['_', '-'], ' ', $type))) ?></span><?= !empty($block['hidden']) ? '<small>Hidden</small>' : '' ?></span>
            <?php endforeach; endif; ?>
        </div>
        <form method="post" class="pattern-apply-form" data-confirm="Apply this layout? The target page will become a draft and its current layout will be saved in revisions.">
            <?= $csrf->field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="pattern_id" value="<?= (int) $pattern['id'] ?>">
            <label for="pattern-<?= (int) $pattern['id'] ?>-page">Apply to page</label><div><select id="pattern-<?= (int) $pattern['id'] ?>-page" name="page_id" required><option value="">Choose destination…</option><?php foreach ($pages as $page): ?><option value="<?= (int) $page['id'] ?>"><?= e($page['title']) ?></option><?php endforeach; ?></select><button class="btn small" data-busy-label="Applying…">Apply</button></div>
        </form>
        <div class="pattern-actions">
            <details class="row-editor"><summary class="btn small secondary">Rename</summary><form method="post" class="row-editor-panel"><?= $csrf->field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int) $pattern['id'] ?>"><label for="pattern-<?= (int) $pattern['id'] ?>-name">Pattern name</label><input id="pattern-<?= (int) $pattern['id'] ?>-name" name="name" maxlength="190" value="<?= e_attr($pattern['name']) ?>" required><button class="btn small">Save name</button></form></details>
            <form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int) $pattern['id'] ?>"><button class="btn small secondary">Duplicate</button></form>
            <form method="post" data-confirm="Delete this reusable pattern? Pages that already use it will not change."><?= $csrf->field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $pattern['id'] ?>"><button class="btn small danger">Delete</button></form>
        </div>
    </article>
<?php endforeach; ?>
</div>
<p class="empty-state compact pattern-no-results" data-pattern-empty hidden><strong>No matching patterns</strong><span>Try a different name or block type.</span></p>
<?php endif; ?>
