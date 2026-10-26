<?php
/**
 * @var array $themes @var string $active @var array $branding @var array $mediaItems
 * @var array $brandPreviews @var array $socialProfiles @var string $socialPlacement
 * @var array $moduleThemeCollections @var array $moduleThemeCurrent @var string $themeQuery
 * @var \Core\Security\Csrf $csrf @var string $basePath @var string $siteBasePath
 */
$activeTheme = null;
foreach ($themes as $theme) if (basename((string) ($theme['dir'] ?? '')) === $active) $activeTheme = $theme;
$populatedSocial = count(array_filter($socialProfiles, static fn (array $profile): bool => $profile['url'] !== ''));
?>
<section class="status-strip" aria-label="Appearance overview">
    <div class="status-chip <?= $activeTheme ? 'ok' : 'warn' ?>"><strong><?= e($activeTheme['manifest']['name'] ?? ucfirst($active)) ?></strong><span>Active theme</span></div>
    <div class="status-chip <?= $brandPreviews['logo'] ? 'ok' : 'warn' ?>"><strong><?= $brandPreviews['logo'] ? 'Ready' : 'Missing' ?></strong><span>Site logo</span></div>
    <div class="status-chip <?= $populatedSocial ? 'active' : '' ?>"><strong><?= $populatedSocial ?></strong><span>Social profiles</span></div>
    <a class="status-chip" href="<?= e_attr(rtrim($siteBasePath, '/') . '/') ?>" target="_blank" rel="noopener"><strong>Open ↗</strong><span>Public website</span></a>
</section>

<div class="appearance-workspace">
<section class="card appearance-settings">
    <div class="card-heading"><div><p class="section-kicker">Brand system</p><h2>Website appearance</h2><p class="card-subtitle">Manage the shared identity that Core, the active theme, administration, and compatible modules use.</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/media">Open Media</a></div>
    <form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/branding" enctype="multipart/form-data" data-protect-unsaved data-appearance-form>
        <?= $csrf->field() ?>
        <div class="brand-asset-grid">
        <?php foreach ([
            'logo' => ['field' => 'logo_media_id', 'upload' => 'logo_upload', 'label' => 'Site logo', 'help' => 'Transparent PNG or WebP works best.', 'accept' => 'image/png,image/jpeg,image/webp,image/gif'],
            'favicon' => ['field' => 'favicon_media_id', 'upload' => 'favicon_upload', 'label' => 'Favicon', 'help' => 'Use a square PNG, WebP, GIF, or ICO.', 'accept' => 'image/png,image/jpeg,image/webp,image/gif,image/x-icon,image/vnd.microsoft.icon,.ico'],
            'social' => ['field' => 'social_media_id', 'upload' => 'social_upload', 'label' => 'Social sharing image', 'help' => 'Use a wide image around 1200 × 630.', 'accept' => 'image/png,image/jpeg,image/webp'],
        ] as $role => $asset): ?>
            <article class="brand-asset-card">
                <div class="brand-preview <?= $role === 'favicon' ? 'is-favicon' : '' ?>" data-brand-role="<?= e_attr($role) ?>"><?php if (!empty($brandPreviews[$role])): ?><img src="<?= e_attr($brandPreviews[$role]) ?>" alt="Current <?= e_attr(strtolower($asset['label'])) ?>"><?php else: ?><span>No <?= e(strtolower($asset['label'])) ?> selected</span><?php endif; ?></div>
                <h3><?= e($asset['label']) ?></h3><p class="muted"><?= e($asset['help']) ?></p>
                <label for="<?= e_attr($asset['upload']) ?>">Upload replacement</label><input id="<?= e_attr($asset['upload']) ?>" type="file" name="<?= e_attr($asset['upload']) ?>" accept="<?= e_attr($asset['accept']) ?>" data-image-preview=".brand-preview">
                <label for="<?= e_attr($asset['field']) ?>">Or choose from Media</label><select id="<?= e_attr($asset['field']) ?>" name="<?= e_attr($asset['field']) ?>"><option value="">— none —</option><?php foreach ($mediaItems as $media): ?><option value="<?= (int) $media['id'] ?>" <?= (int) $branding[$asset['field']] === (int) $media['id'] ? 'selected' : '' ?>>#<?= (int) $media['id'] ?> — <?= e($media['original_name']) ?></option><?php endforeach; ?></select>
            </article>
        <?php endforeach; ?>
        </div>

        <section class="appearance-panel"><div class="card-heading"><div><h3>Colors and shape</h3><p class="field-note">These safe design tokens follow the active theme without replacing its layout.</p></div></div><div class="appearance-token-grid">
            <div><label for="primary-color">Primary color</label><input id="primary-color" type="color" name="primary_color" value="<?= e_attr($branding['primary_color']) ?>" data-appearance-primary></div>
            <div><label for="accent-color">Accent color</label><input id="accent-color" type="color" name="accent_color" value="<?= e_attr($branding['accent_color']) ?>" data-appearance-accent></div>
            <div><label for="corner-radius">Corner radius</label><div class="range-field"><input id="corner-radius" type="range" min="0" max="24" name="radius" value="<?= e_attr($branding['radius']) ?>" data-appearance-radius><output for="corner-radius"><?= (int) $branding['radius'] ?>px</output></div></div>
        </div></section>

        <section class="appearance-panel"><div class="card-heading"><div><h3>Header and footer</h3><p class="field-note">Choose presentation only. Navigation content remains in Website → Navigation Menu.</p></div></div><div class="grid2">
            <div><label for="header-layout">Header layout</label><select id="header-layout" name="header_layout" data-appearance-header><?php foreach (['classic' => 'Classic', 'centered' => 'Centered', 'compact' => 'Compact'] as $value => $label): ?><option value="<?= e_attr($value) ?>" <?= $branding['header_layout'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label for="footer-layout">Footer layout</label><select id="footer-layout" name="footer_layout" data-appearance-footer><?php foreach (['centered' => 'Centered', 'split' => 'Split', 'minimal' => 'Minimal'] as $value => $label): ?><option value="<?= e_attr($value) ?>" <?= $branding['footer_layout'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        </div><label for="copyright">Copyright notice</label><input id="copyright" name="copyright" maxlength="240" value="<?= e_attr($branding['copyright']) ?>" placeholder="© {year} {site_name}. All rights reserved."><p class="field-note">Plain text only. Use <code>{year}</code> and <code>{site_name}</code>. The required Cajun Web Pro credit remains separately protected by Core.</p></section>

        <section class="appearance-panel"><div class="card-heading"><div><h3>Social links</h3><p class="field-note">Blank profiles stay hidden. One placement choice controls every populated icon.</p></div><div><label for="social-placement">Display icons</label><select id="social-placement" name="social_placement"><?php foreach (['header' => 'Header', 'footer' => 'Footer', 'both' => 'Header and footer'] as $value => $label): ?><option value="<?= e_attr($value) ?>" <?= $socialPlacement === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
        <div class="social-profile-grid"><?php foreach ($socialProfiles as $profile): ?><div class="social-profile-row"><span class="social-profile-icon" aria-hidden="true"><?= \Core\Branding\SocialIcon::svg((string) $profile['platform']) ?></span><div><label for="social_<?= e_attr($profile['platform']) ?>"><?= e($profile['label']) ?></label><input type="url" id="social_<?= e_attr($profile['platform']) ?>" name="social_<?= e_attr($profile['platform']) ?>" maxlength="500" value="<?= e_attr($profile['url']) ?>" placeholder="https://"></div></div><?php endforeach; ?></div></section>
        <div class="action-bar"><span>Changes are validated and shared across the website.</span><button class="btn" type="submit" data-busy-label="Saving appearance…">Save appearance</button></div>
    </form>
</section>

<aside class="card appearance-live-card" data-appearance-preview style="--preview-primary:<?= e_attr($branding['primary_color']) ?>;--preview-accent:<?= e_attr($branding['accent_color']) ?>;--preview-radius:<?= (int) $branding['radius'] ?>px">
    <div class="card-heading"><div><p class="section-kicker">Live preview</p><h2>Brand direction</h2><p class="card-subtitle">A representative preview of shared Core elements. Save, then open the public site for the complete active theme.</p></div></div>
    <div class="appearance-preview-frame header-<?= e_attr($branding['header_layout']) ?> footer-<?= e_attr($branding['footer_layout']) ?>" data-appearance-frame>
        <header><div class="preview-brand"><?php if ($brandPreviews['logo']): ?><img src="<?= e_attr($brandPreviews['logo']) ?>" alt=""><?php else: ?><strong>Website Logo</strong><?php endif; ?></div><nav aria-label="Preview navigation"><span>Home</span><span>Services</span><span>Contact</span></nav></header>
        <main><span class="preview-eyebrow">Built for your business</span><h3>Your website, clearly presented.</h3><p>Colors, spacing, buttons, and corners update as you make choices.</p><button type="button">Primary action</button><a href="#">Secondary link</a></main>
        <footer><span>Footer navigation</span><span>© <?= date('Y') ?> Website</span></footer>
    </div>
</aside>
</div>

<section class="theme-library-heading"><div><p class="section-kicker">Theme library</p><h2>Presentation packages</h2><p class="muted">Themes change presentation only. Pages, media, menus, forms, and module records are never touched.</p></div><span class="badge"><?= count($themes) ?> installed</span></section>
<?php if ($themes === []): ?><div class="empty-state"><strong>No themes discovered</strong><span>Install a compatible theme package through Modules.</span></div><?php else: ?>
<div class="theme-card-grid">
<?php foreach ($themes as $id => $info):
    $manifest = $info['manifest']; $slug = basename((string) ($info['dir'] ?? '')); $tokens = is_array($manifest['tokens'] ?? null) ? $manifest['tokens'] : [];
    $isActive = $slug === $active; $errors = $info['errors'] ?? [];
?>
    <article class="card theme-card <?= $isActive ? 'is-active' : '' ?>">
        <div class="theme-swatch" style="--theme-bg:<?= e_attr((string) ($tokens['color-bg'] ?? '#f8fafc')) ?>;--theme-text:<?= e_attr((string) ($tokens['color-text'] ?? '#1e293b')) ?>;--theme-accent:<?= e_attr((string) ($tokens['color-accent'] ?? '#0369a1')) ?>"><span></span><strong>Aa</strong><i></i></div>
        <div class="theme-card-body"><div class="theme-card-title"><div><h3><?= e($manifest['name'] ?? $id) ?></h3><p><?= e($id) ?></p></div><?php if ($isActive): ?><span class="badge ok">Active</span><?php elseif ($errors): ?><span class="badge bad">Unavailable</span><?php endif; ?></div>
        <dl class="theme-meta"><div><dt>Version</dt><dd><?= e($manifest['version'] ?? '?') ?></dd></div><div><dt>Publisher</dt><dd><?= e(explode('/', $id)[0]) ?></dd></div></dl>
        <?php if ($errors): ?><p class="theme-error"><?= e((string) $errors[0]) ?></p><?php elseif (!$isActive): ?><form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/activate" data-confirm="Activate this theme? Website content and settings will stay unchanged."><?= $csrf->field() ?><input type="hidden" name="slug" value="<?= e_attr($slug) ?>"><button class="btn small">Activate theme</button></form><?php else: ?><a class="btn small secondary" href="<?= e_attr(rtrim($siteBasePath, '/') . '/') ?>" target="_blank" rel="noopener">View live theme ↗</a><?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (($moduleThemeCollections ?? []) !== [] || ($themeQuery ?? '') !== ''): ?>
<section class="theme-library-heading"><div><p class="section-kicker">Industry layouts</p><h2>Module theme collections</h2><p class="muted">Draft, preview, publish, or roll back package layouts without replacing pages, navigation, records, or administrator-selected Media.</p></div></section>
<form method="get" action="<?= e_attr($basePath) ?>/admin/appearance" class="toolbar-search">
    <label for="module-theme-search">Search layouts</label>
    <input id="module-theme-search" type="search" name="theme_q" value="<?= e_attr($themeQuery ?? '') ?>" maxlength="120" placeholder="Search layout name or description">
    <button class="btn secondary" type="submit">Search</button>
</form>
<?php if (($moduleThemeCollections ?? []) === []): ?><div class="empty-state"><strong>No matching module layouts</strong><span>Clear the search or enable a module that registers a theme collection.</span></div><?php endif; ?>
<?php foreach (($moduleThemeCollections ?? []) as $package => $collection): $current = $moduleThemeCurrent[$package] ?? null; ?>
<section class="card" aria-labelledby="collection-<?= e_attr(substr(hash('sha256', (string) $package), 0, 10)) ?>">
    <div class="card-heading"><div><h3 id="collection-<?= e_attr(substr(hash('sha256', (string) $package), 0, 10)) ?>"><?= e($collection['label']) ?></h3><p class="muted"><?= e($package) ?> · <?= count($collection['variants']) ?> available layouts</p></div><?php if ($current): ?><span class="badge ok"><?= e($current['variant']['name']) ?> / <?= e($current['palette']['name']) ?></span><?php endif; ?></div>
    <div class="theme-card-grid">
    <?php foreach ($collection['variants'] as $variant): ?>
        <article class="theme-card">
            <div class="theme-card-body"><h4><?= e($variant['name']) ?></h4><p><?= e($variant['description']) ?></p>
            <?php foreach ($variant['palettes'] as $paletteId => $palette): $tokens = $palette['tokens']; ?>
            <form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/module-theme" class="appearance-panel">
                <?= $csrf->field() ?><input type="hidden" name="operation" value="draft"><input type="hidden" name="package" value="<?= e_attr($package) ?>"><input type="hidden" name="variant" value="<?= e_attr($variant['id']) ?>"><input type="hidden" name="palette" value="<?= e_attr($paletteId) ?>">
                <div class="theme-swatch" style="--theme-bg:<?= e_attr($tokens['background']) ?>;--theme-text:<?= e_attr($tokens['text']) ?>;--theme-accent:<?= e_attr($tokens['accent'] ?? $tokens['primary'] ?? $tokens['text']) ?>"><span></span><strong>Aa</strong><i></i></div>
                <strong><?= e($palette['name']) ?></strong>
                <?php foreach ($variant['media_slots'] as $slot => $slotDef): ?><label><?= e($slotDef['label']) ?><select name="media[<?= e_attr($slot) ?>]"><option value="">Bundled default</option><?php foreach ($mediaItems as $media): ?><option value="<?= (int) $media['id'] ?>"><?= e($media['original_name']) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
                <button class="btn small secondary" type="submit">Save draft</button>
            </form>
            <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <div class="action-bar">
        <form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/module-theme"><?= $csrf->field() ?><input type="hidden" name="operation" value="rollback"><input type="hidden" name="package" value="<?= e_attr($package) ?>"><button class="btn secondary" type="submit">Roll back</button></form>
        <form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/module-theme" data-confirm="Publish this module layout and palette?"><?= $csrf->field() ?><input type="hidden" name="operation" value="publish"><input type="hidden" name="package" value="<?= e_attr($package) ?>"><button class="btn" type="submit">Publish draft</button></form>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>
