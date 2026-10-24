<?php /** @var array $themes @var string $active @var array $branding @var array $mediaItems @var array $brandPreviews @var array $socialProfiles @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Brand identity</h2>
<p class="muted">Core owns these assets after installation and makes them available to the public site, industry modules, and administration area.</p>
<form method="post" action="<?= e_attr($basePath) ?>/admin/appearance/branding" enctype="multipart/form-data">
<?= $csrf->field() ?>
<div class="brand-asset-grid">
<?php foreach ([
    'logo' => ['field' => 'logo_media_id', 'upload' => 'logo_upload', 'label' => 'Site logo', 'help' => 'PNG or WebP with transparency works best.'],
    'favicon' => ['field' => 'favicon_media_id', 'upload' => 'favicon_upload', 'label' => 'Favicon', 'help' => 'Use a square PNG, WebP, GIF, or ICO image.'],
] as $role => $asset): ?>
<section class="brand-asset-card">
<div class="brand-preview <?= $role === 'favicon' ? 'is-favicon' : '' ?>">
<?php if (!empty($brandPreviews[$role])): ?><img src="<?= e_attr($brandPreviews[$role]) ?>" alt="Current <?= e_attr(strtolower($asset['label'])) ?>"><?php else: ?><span>No <?= e(strtolower($asset['label'])) ?> selected</span><?php endif; ?>
</div>
<h3><?= e($asset['label']) ?></h3><p class="muted"><?= e($asset['help']) ?></p>
<label for="<?= e_attr($asset['upload']) ?>">Upload a new <?= e(strtolower($asset['label'])) ?></label>
<input id="<?= e_attr($asset['upload']) ?>" type="file" name="<?= e_attr($asset['upload']) ?>" accept="image/png,image/jpeg,image/webp,image/gif,image/x-icon,image/vnd.microsoft.icon,.ico">
<label for="<?= e_attr($asset['field']) ?>">Or choose from Media</label>
<select id="<?= e_attr($asset['field']) ?>" name="<?= e_attr($asset['field']) ?>"><option value="">— none —</option><?php foreach ($mediaItems as $media): ?><option value="<?= (int) $media['id'] ?>" <?= (int) $branding[$asset['field']] === (int) $media['id'] ? 'selected' : '' ?>>#<?= (int) $media['id'] ?> — <?= e($media['original_name']) ?></option><?php endforeach; ?></select>
</section>
<?php endforeach; ?>
</div>
<label>Default social image</label><select name="social_media_id"><option value="">— none —</option><?php foreach ($mediaItems as $media): ?><option value="<?= (int) $media['id'] ?>" <?= (int) $branding['social_media_id'] === (int) $media['id'] ? 'selected' : '' ?>>#<?= (int) $media['id'] ?> — <?= e($media['original_name']) ?></option><?php endforeach; ?></select>
<div class="inline-fields"><div><label>Primary color</label><input type="color" name="primary_color" value="<?= e_attr($branding['primary_color']) ?>"></div><div><label>Accent color</label><input type="color" name="accent_color" value="<?= e_attr($branding['accent_color']) ?>"></div><div><label>Corner radius (px)</label><input type="number" min="0" max="24" name="radius" value="<?= e_attr($branding['radius']) ?>"></div></div>
<div class="inline-fields"><div><label>Header layout</label><select name="header_layout"><?php foreach (['classic'=>'Classic','centered'=>'Centered','compact'=>'Compact'] as $value=>$label): ?><option value="<?= e_attr($value) ?>" <?= $branding['header_layout']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div><label>Footer layout</label><select name="footer_layout"><?php foreach (['centered'=>'Centered','split'=>'Split','minimal'=>'Minimal'] as $value=>$label): ?><option value="<?= e_attr($value) ?>" <?= $branding['footer_layout']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div>
<label for="copyright">Copyright notice</label>
<input id="copyright" name="copyright" maxlength="240" value="<?= e_attr($branding['copyright']) ?>" placeholder="© {year} {site_name}. All rights reserved.">
<p class="muted">Plain text only. Use <code>{year}</code> for the current year and <code>{site_name}</code> for the website name. Leave blank for the automatic notice. The required Cajun Web Pro credit remains separate.</p>
<h3>Social links</h3>
<p class="muted">Enter complete HTTPS profile URLs and choose where each icon appears. A platform is omitted everywhere when its URL is blank.</p>
<div class="social-profile-grid"><?php foreach ($socialProfiles as $profile): ?><div class="social-profile-row"><div><label for="social_<?= e_attr($profile['platform']) ?>"><?= e($profile['label']) ?></label><input type="url" id="social_<?= e_attr($profile['platform']) ?>" name="social_<?= e_attr($profile['platform']) ?>" value="<?= e_attr($profile['url']) ?>" placeholder="https://"></div><div><label for="social_<?= e_attr($profile['platform']) ?>_placement">Display in</label><select id="social_<?= e_attr($profile['platform']) ?>_placement" name="social_<?= e_attr($profile['platform']) ?>_placement"><?php foreach (['header'=>'Header','footer'=>'Footer','both'=>'Header and footer'] as $value=>$label): ?><option value="<?= e_attr($value) ?>" <?= $profile['placement']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></div><?php endforeach; ?></div>
<style>.social-profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:.75rem 1rem}.social-profile-row{display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:.65rem;padding:.75rem;border:1px solid var(--border);border-radius:.6rem;background:#f8fafc}.social-profile-grid label{margin-top:0}@media(max-width:560px){.social-profile-row{grid-template-columns:1fr}}</style>
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
