<?php
/**
 * @var array $languages @var array $progress @var array $pages
 * @var int $selectedPage @var string $selectedLocale @var array|null $translation
 * @var string $baseLocale @var \Core\Security\Csrf $csrf @var string $basePath
 */
$source = array_values(array_filter($pages, static fn (array $page): bool => (int) $page['id'] === $selectedPage))[0] ?? null;
$activeCount = count(array_filter($languages, static fn (array $language): bool => (int) $language['active'] === 1));
$translatedCount = array_sum(array_map(static fn (array $language): int => (int) ($language['translation_count'] ?? 0), $languages));
$value = static fn (string $field, string $fallback = ''): string => (string) ($translation[$field] ?? $fallback);
?>
<section class="status-strip" aria-label="Translation overview">
    <div class="status-chip <?= $activeCount > 0 ? 'ok' : 'warn' ?>"><strong><?= $activeCount ?></strong><span>Live languages</span></div>
    <div class="status-chip"><strong><?= count($pages) ?></strong><span>Source pages</span></div>
    <div class="status-chip <?= $translatedCount > 0 ? 'active' : '' ?>"><strong><?= $translatedCount ?></strong><span>Translations</span></div>
    <div class="status-chip"><strong><?= e(strtoupper($baseLocale)) ?></strong><span>Source locale</span></div>
</section>

<div class="translation-workspace">
    <section class="card translation-picker">
        <div class="card-heading"><div><p class="section-kicker">Editor</p><h2>Choose content</h2><p class="card-subtitle">Select a source page and language to create or continue its localized version.</p></div></div>
        <form method="get" class="translation-picker-form" action="<?= e_attr($basePath) ?>/admin/translations">
            <div><label for="translation-page">Source page</label><select id="translation-page" name="page_id" required><option value="">Choose page…</option><?php foreach ($pages as $page): ?><option value="<?= (int) $page['id'] ?>" <?= $selectedPage === (int) $page['id'] ? 'selected' : '' ?>><?= e($page['title']) ?> · <?= e(ucfirst($page['status'])) ?> · <?= (int) $page['translation_count'] ?> translated</option><?php endforeach; ?></select></div>
            <div><label for="translation-locale">Language</label><select id="translation-locale" name="locale" required><option value="">Choose language…</option><?php foreach ($languages as $language): ?><option value="<?= e_attr($language['locale']) ?>" <?= $selectedLocale === $language['locale'] ? 'selected' : '' ?>><?= e($language['name']) ?> (<?= e($language['locale']) ?>)<?= (int) $language['active'] === 1 ? '' : ' — hidden' ?></option><?php endforeach; ?></select></div>
            <button class="btn secondary">Load editor</button>
        </form>
        <?php if ($languages === []): ?><p class="field-note">Add a language before opening the translation editor.</p><?php endif; ?>
    </section>

    <aside class="card language-manager">
        <div class="card-heading"><div><p class="section-kicker">Languages</p><h2>Public availability</h2><p class="card-subtitle">Hidden languages keep their work but disappear from public URLs and language links.</p></div></div>
        <?php if ($progress === []): ?>
            <div class="empty-state compact"><strong>No translated languages</strong><span>Add the first language below.</span></div>
        <?php else: ?>
            <div class="language-progress-list">
            <?php foreach ($progress as $language): ?>
                <div class="language-progress-row">
                    <div class="language-progress-heading"><span><strong><?= e($language['name']) ?></strong><small><?= e($language['locale']) ?> · <?= (int) $language['translated'] ?>/<?= (int) $language['total'] ?> pages</small></span><b><?= (int) $language['percent'] ?>%</b></div>
                    <progress class="progress-track" aria-label="<?= e_attr($language['name']) ?> translation progress" value="<?= (int) $language['percent'] ?>" max="100"><?= (int) $language['percent'] ?>%</progress>
                    <form method="post" class="inline-toggle-form" data-confirm="<?= (int) $language['active'] === 1 ? 'Hide this language from the public site? Existing translations will be kept.' : '' ?>">
                        <?= $csrf->field() ?><input type="hidden" name="action" value="language_toggle"><input type="hidden" name="locale" value="<?= e_attr($language['locale']) ?>"><input type="hidden" name="active" value="<?= (int) $language['active'] === 1 ? '0' : '1' ?>">
                        <label class="toggle-control compact"><span><strong><?= (int) $language['active'] === 1 ? 'Public' : 'Hidden' ?></strong><small><?= (int) $language['active'] === 1 ? 'Visitors can open translated pages.' : 'Editors can continue working privately.' ?></small></span><input type="checkbox" <?= (int) $language['active'] === 1 ? 'checked' : '' ?> data-auto-submit><span class="toggle-track" aria-hidden="true"></span></label>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <details class="disclosure"><summary class="btn secondary">Add or restore language</summary><div class="disclosure-body">
            <form method="post" class="stack-form" action="<?= e_attr($basePath) ?>/admin/translations">
                <?= $csrf->field() ?><input type="hidden" name="action" value="language_add">
                <div class="grid2"><div><label for="new-locale">Locale</label><input id="new-locale" name="locale" pattern="[A-Za-z]{2}(-[A-Za-z]{2})?" maxlength="5" placeholder="fr or es-mx" required></div><div><label for="new-language-name">Language name</label><input id="new-language-name" name="name" maxlength="80" placeholder="French" required></div></div>
                <p class="field-note">Adding an existing locale updates its name and makes it public again.</p><button class="btn">Save language</button>
            </form>
        </div></details>
    </aside>
</div>

<?php if ($source && $selectedLocale): ?>
<section class="card translation-editor">
    <div class="card-heading">
        <div><p class="section-kicker"><?= $translation && isset($translation['id']) ? 'Existing translation' : 'New translation' ?></p><h2><?= e($source['title']) ?> <span aria-hidden="true">→</span> <?= e(strtoupper($selectedLocale)) ?></h2><p class="card-subtitle">Source material stays visible beside the localized fields so changes are easier to review.</p></div>
        <a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $selectedPage ?>">Open source page</a>
    </div>
    <form method="post" data-busy-label="Saving translation…" data-translation-editor>
        <?= $csrf->field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="page_id" value="<?= (int) $selectedPage ?>"><input type="hidden" name="locale" value="<?= e_attr($selectedLocale) ?>">
        <div class="translation-pair-grid">
            <div class="source-reference"><span class="translation-column-label">Source · <?= e(strtoupper($baseLocale)) ?></span><strong><?= e($source['title']) ?></strong><code>/<?= e($source['slug']) ?></code></div>
            <div><span class="translation-column-label">Translation · <?= e(strtoupper($selectedLocale)) ?></span><label for="translated-title">Translated title</label><input id="translated-title" name="title" maxlength="190" value="<?= e_attr($value('title')) ?>" placeholder="<?= e_attr($source['title']) ?>" required><label for="translated-slug">Translated URL slug</label><div class="input-prefix"><span>/l/<?= e($selectedLocale) ?>/</span><input id="translated-slug" name="slug" maxlength="190" value="<?= e_attr($value('slug')) ?>" placeholder="translated-page" required></div></div>
        </div>
        <div class="translation-pair-grid content-pair">
            <div class="source-reference"><span class="translation-column-label">Source content</span><div class="source-content-preview"><?php $sourceText = trim(strip_tags((string) ($source['body'] ?? ''))); ?><?php if ($sourceText !== ''): ?><p><?= nl2br(e($sourceText)) ?></p><?php else: ?><p class="muted">This page uses visual blocks or has no classic content.</p><?php endif; ?></div></div>
            <div><span class="translation-column-label">Translated content</span><label class="sr-only" for="translated-body">Translated page content</label><textarea id="translated-body" name="body" rows="14"><?= e($value('body', (string) ($source['body'] ?? ''))) ?></textarea></div>
        </div>
        <div class="translation-seo-panel">
            <div><p class="section-kicker">Search preview</p><label for="translated-meta-title">Search title</label><input id="translated-meta-title" name="meta_title" maxlength="60" value="<?= e_attr($value('meta_title')) ?>" placeholder="<?= e_attr($source['meta_title'] ?: $source['title']) ?>"><label for="translated-meta-description">Search description</label><textarea id="translated-meta-description" name="meta_description" maxlength="160" rows="4"><?= e($value('meta_description')) ?></textarea></div>
            <div class="search-preview"><span class="search-preview-label">Localized result preview</span><div data-translation-preview-title><?= e($value('meta_title', $value('title', (string) $source['title']))) ?></div><div class="search-preview-url" data-translation-preview-url>/l/<?= e($selectedLocale) ?>/<?= e($value('slug', 'translated-page')) ?></div><p data-translation-preview-description><?= e($value('meta_description', 'Add a concise description for this language.')) ?></p></div>
        </div>
        <details class="disclosure"><summary class="btn secondary">Advanced visual-block and structured data</summary><div class="disclosure-body">
            <p class="field-note">Both fields are validated before saving. Visual blocks must be a JSON list; structured data must be a JSON object or list.</p>
            <label for="translated-blocks">Visual-block JSON</label><textarea id="translated-blocks" name="blocks_json" rows="10" spellcheck="false"><?= e($value('blocks_json', (string) ($source['blocks_json'] ?? '[]'))) ?></textarea>
            <label for="translated-schema">Structured-data JSON</label><textarea id="translated-schema" name="schema_json" rows="7" spellcheck="false"><?= e($value('schema_json', (string) ($source['schema_json'] ?? ''))) ?></textarea>
        </div></details>
        <div class="action-bar"><span><?php if ($source['status'] === 'published'): ?>The source page is published. The translation is public only while its language is enabled.<?php else: ?>The source is <?= e($source['status']) ?>, so this translation is not public yet.<?php endif; ?></span><button class="btn">Save translation</button></div>
    </form>
    <?php if ($translation && isset($translation['id'])): ?><form method="post" class="translation-delete" data-confirm="Remove this translation? The source page and other languages will remain unchanged."><?= $csrf->field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="page_id" value="<?= (int) $selectedPage ?>"><input type="hidden" name="locale" value="<?= e_attr($selectedLocale) ?>"><button class="btn small danger">Remove <?= e(strtoupper($selectedLocale)) ?> translation</button></form><?php endif; ?>
</section>
<?php else: ?>
<div class="empty-state translation-empty"><strong>Choose a page and language</strong><span>The side-by-side translation editor will open here without changing the source page.</span></div>
<?php endif; ?>
