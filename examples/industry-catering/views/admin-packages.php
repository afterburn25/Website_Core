<?php
$values = $old !== [] ? $old : ($editing ?? []);
$text = static fn (string $key, string $default = ''): string => is_scalar($values[$key] ?? $default) ? (string) ($values[$key] ?? $default) : $default;
$rules = json_decode($text('choice_rules'), true) ?: [];
$choices = is_array($old['choices'] ?? null) ? $old['choices'] : [];
?>
<?php if ($saved): ?><div class="flash-ok" role="status">Package changes saved.</div><?php endif; ?>
<?php if ($error): ?><div class="flash-error" role="alert"><?= e($error) ?> Your entries are retained below.</div><?php endif; ?>
<?php if ($quickManagerUrl !== ''): ?><div class="card"><h2>Quick catalog management</h2><p class="muted">Use Core’s shared workspace for searching, accessible drag-free ordering, and bulk show, hide, feature, or unfeature actions. Return here for pricing, guest ranges, discount tiers, and included menu choices.</p><a class="btn secondary" href="<?= e_attr($basePath . $quickManagerUrl) ?>">Open package quick manager</a></div><?php endif; ?>
<div class="card"><h2>Packages</h2><div style="overflow:auto"><table><thead><tr><th>Name</th><th>Unit price / method</th><th>Guests</th><th>Availability</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($packages as $package): ?><tr><td><?= e($package['name']) ?></td><td>$<?= e(number_format(((int) $package['price_cents']) / 100, 2)) ?> · <?= e($pricingLabels[$package['pricing_basis']] ?? 'Team confirmation required') ?></td><td><?= (int) $package['guest_min'] ?><?= $package['guest_max'] ? '–' . (int) $package['guest_max'] : '+' ?></td><td><?= e($package['available_from'] ?: 'Any time') ?> – <?= e($package['available_until'] ?: 'No end') ?></td><td><?= $package['visible'] ? 'Visible' : 'Hidden' ?></td><td>
<a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/catering/packages&amp;edit_package=<?= (int) $package['id'] ?>#package-editor">Edit</a>
<form method="post" style="display:inline"><?= $csrf->field() ?><input type="hidden" name="action" value="toggle_package"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>"><button class="btn small secondary"><?= $package['visible'] ? 'Hide' : 'Show' ?></button></form>
<details style="display:inline-block"><summary>Delete</summary><form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="delete_package"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>"><p>Remove <?= e($package['name']) ?>? Existing inquiry snapshots are preserved.</p><label><input type="checkbox" name="confirm_delete" value="1" required style="width:auto"> Confirm removal</label><button class="btn small danger">Delete package</button></form></details>
</td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card" id="package-editor"><h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add package' ?></h2><form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="<?= $editing ? 'update_package' : 'add_package' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
<label>Name<input name="name" required maxlength="190" value="<?= e_attr($text('name')) ?>"></label>
<label>Short label<input name="eyebrow" maxlength="120" value="<?= e_attr($text('eyebrow')) ?>"></label>
<label>Description<textarea name="description" rows="4" maxlength="3000"><?= e($text('description')) ?></textarea></label>
<fieldset><legend>Food estimate pricing</legend>
<label>Pricing method<select name="pricing_basis"><?php foreach ($pricingLabels as $key => $label): ?><option value="<?= e_attr($key) ?>"<?= $text('pricing_basis', 'manual') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label>Unit price ($)<input type="number" name="price" min="0" max="1000000" step="0.01" required value="<?= e_attr($text('price', isset($values['price_cents']) ? number_format((int) $values['price_cents'] / 100, 2, '.', '') : '0.00')) ?>"></label>
<label>Display price note<input name="price_note" maxlength="120" value="<?= e_attr($text('price_note', 'starting per guest')) ?>"></label>
<label>Servings per tray <small>used for per-tray pricing</small><input type="number" name="servings_per_tray" min="1" max="10000" value="<?= e_attr($text('servings_per_tray', '1')) ?>"></label>
<label>Minimum billed guests or trays<input type="number" name="minimum_quantity" min="1" max="10000" value="<?= e_attr($text('minimum_quantity', '1')) ?>"></label>
<label>Minimum guests<input type="number" name="guest_min" min="1" max="10000" required value="<?= e_attr($text('guest_min', '20')) ?>"></label>
<label>Maximum guests <small>leave blank for no maximum</small><input type="number" name="guest_max" min="1" max="10000" value="<?= e_attr($text('guest_max')) ?>"></label>
</fieldset>
<fieldset><legend>Catalog relationship and availability</legend><label>Reusable menu collection<select name="menu_group_id"><option value="">All available dishes</option><?php foreach ($menuGroups as $group): ?><option value="<?= (int) $group['id'] ?>"<?= $text('menu_group_id') === (string) $group['id'] ? ' selected' : '' ?>><?= e($group['name']) ?><?= !$group['visible'] ? ' (hidden)' : '' ?></option><?php endforeach; ?></select></label><p class="muted">When selected, only dishes in this collection are valid for the package. Category choice limits still apply.</p><div class="form-grid"><label>Available from <small>optional</small><input type="date" name="available_from" value="<?= e_attr($text('available_from')) ?>"></label><label>Available until <small>optional</small><input type="date" name="available_until" value="<?= e_attr($text('available_until')) ?>"></label></div><p class="muted">Outside this inclusive window, the package is omitted from public pages and quote planning without deleting it.</p></fieldset>
<fieldset><legend>Guest-count discounts <small>optional</small></legend><p class="muted">The highest reached guest threshold sets the discount for the entire food unit price. Prices round to cents per billed guest, tray or event. Leave both fields blank to omit a tier.</p>
<?php
$tiers = is_array($old['guest_tiers'] ?? null) ? $old['guest_tiers'] : (json_decode($text('guest_tiers'), true) ?: []);
for ($tierIndex = 0; $tierIndex < 5; $tierIndex++):
$tier = is_array($tiers[$tierIndex] ?? null) ? $tiers[$tierIndex] : [];
$tierGuests = is_scalar($tier['minimum_guests'] ?? null) ? (string) $tier['minimum_guests'] : '';
$tierDiscount = isset($tier['discount_bps']) ? number_format((int) $tier['discount_bps'] / 100, 2, '.', '') : (is_scalar($tier['discount_percent'] ?? null) ? (string) $tier['discount_percent'] : '');
?>
<div style="display:flex;gap:1rem;flex-wrap:wrap"><label>Tier <?= $tierIndex + 1 ?> — at least guests<input type="number" name="guest_tiers[<?= $tierIndex ?>][minimum_guests]" min="1" max="10000" value="<?= e_attr($tierGuests) ?>"></label><label>Discount (%)<input type="number" name="guest_tiers[<?= $tierIndex ?>][discount_percent]" min="0" max="100" step="0.01" value="<?= e_attr($tierDiscount) ?>"></label></div>
<?php endfor; ?></fieldset>
<fieldset><legend>Included dish choices</legend><p class="muted">Enable each category included in the package and set the number of distinct dishes customers may choose. Selected dishes in these categories are included in the package price; they are not charged again. Without configured allowances, menu selections require a team-confirmed price.</p>
<?php foreach ($categories as $category):
$id = (int) $category['id'];
$rule = $choices !== [] ? ($choices[$id] ?? []) : ($rules[$id] ?? []);
$rule = is_array($rule) ? $rule : [];
$enabled = $choices !== [] ? (($rule['enabled'] ?? '') === '1') : isset($rules[$id]);
$min = is_scalar($rule['min'] ?? null) ? (string) $rule['min'] : '0';
$max = is_scalar($rule['max'] ?? null) ? (string) $rule['max'] : '1';
?>
<div style="padding:.75rem;border-bottom:1px solid #ddd"><label><input type="checkbox" style="width:auto" name="choices[<?= $id ?>][enabled]" value="1"<?= $enabled ? ' checked' : '' ?>> Include <?= e($category['name']) ?><?= !$category['visible'] ? ' (currently hidden)' : '' ?></label>
<label>Minimum dishes<input type="number" name="choices[<?= $id ?>][min]" min="0" max="100" value="<?= e_attr($min) ?>"></label><label>Maximum dishes<input type="number" name="choices[<?= $id ?>][max]" min="1" max="100" value="<?= e_attr($max) ?>"></label></div>
<?php endforeach; ?></fieldset>
<label>Other inclusions <small>one per line; display only, not an extra charge</small><textarea name="inclusions" rows="6" maxlength="5000"><?= e($text('inclusions')) ?></textarea></label>
<label><input type="checkbox" name="featured" value="1" style="width:auto"<?= $text('featured') === '1' ? ' checked' : '' ?>> Mark as most popular</label>
<label>Sort order<input type="number" name="sort_order" value="<?= e_attr($text('sort_order', '50')) ?>"></label>
<button class="btn" type="submit"><?= $editing ? 'Save package' : 'Add package' ?></button> <a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/catering/packages">Clear editor</a>
</form></div>
