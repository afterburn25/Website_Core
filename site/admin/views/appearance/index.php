<?php /** @var array $themes @var string $active @var array $branding @var array $mediaItems @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Brand identity</h2>
<p class="muted">These core values are available to every compatible theme. Industry modules can use them without owning or duplicating brand settings.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/branding">
<?= $csrf->field() ?>
<?php foreach (['logo_media_id' => 'Site logo', 'favicon_media_id' => 'Favicon', 'social_media_id' => 'Default social image'] as $field => $label): ?>
<label><?= e($label) ?></label><select name="<?= e_attr($field) ?>"><option value="">— none —</option><?php foreach ($mediaItems as $media): ?><option value="<?= (int) $media['id'] ?>" <?= (int) $branding[$field] === (int) $media['id'] ? 'selected' : '' ?>>#<?= (int) $media['id'] ?> — <?= e($media['original_name']) ?></option><?php endforeach; ?></select>
<?php endforeach; ?>
<div class="inline-fields"><div><label>Primary color</label><input type="color" name="primary_color" value="<?= e_attr($branding['primary_color']) ?>"></div><div><label>Accent color</label><input type="color" name="accent_color" value="<?= e_attr($branding['accent_color']) ?>"></div><div><label>Corner radius (px)</label><input type="number" min="0" max="24" name="radius" value="<?= e_attr($branding['radius']) ?>"></div></div>
<div class="inline-fields"><div><label>Header layout</label><select name="header_layout"><?php foreach (['classic'=>'Classic','centered'=>'Centered','compact'=>'Compact'] as $value=>$label): ?><option value="<?= e_attr($value) ?>" <?= $branding['header_layout']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div><label>Footer layout</label><select name="footer_layout"><?php foreach (['centered'=>'Centered','split'=>'Split','minimal'=>'Minimal'] as $value=>$label): ?><option value="<?= e_attr($value) ?>" <?= $branding['footer_layout']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
<div style="margin-top:1rem"><button class="btn" type="submit">Save branding</button></div>
</form>
</div>
<div class="card">
<h2 style="margin-top:0">Themes</h2>
<p class="muted">Switching themes changes presentation only — pages, media,
menus and settings are never touched.</p>
<?php if ($themes === []): ?>
<p class="muted">No themes discovered.</p>
<?php else: ?>
<table>
<tr><th>Theme</th><th>Version</th><th>Publisher</th><th></th></tr>
<?php foreach ($themes as $id => $info): $m = $info['manifest']; $slug = basename((string) ($info['dir'] ?? '')); ?>
<tr>
<td><strong><?= e($m['name'] ?? $id) ?></strong><br><span class="muted"><?= e($id) ?></span></td>
<td><?= e($m['version'] ?? '?') ?></td>
<td><?= e(explode('/', $id)[0]) ?></td>
<td>
<?php if ($slug === $active): ?>
<span class="badge ok">active</span>
<?php else: ?>
<form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/activate" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="slug" value="<?= e_attr($slug) ?>">
<button class="btn small">Activate</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
