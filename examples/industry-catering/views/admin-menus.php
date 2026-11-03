<?php
use CajunWebPro\Catering\MenuPlanner;
$categoryNames = array_column($categories, 'name', 'id');
$values = $old !== [] ? $old : ($editing ?? []);
$text = static fn (string $key, string $default = ''): string => is_scalar($values[$key] ?? $default) ? (string) ($values[$key] ?? $default) : $default;
$chosenDietary = MenuPlanner::tags($values['dietary_tags'] ?? [], $dietaryLabels);
$chosenAllergens = MenuPlanner::tags($values['allergens'] ?? [], $allergenLabels);
?>
<?php if ($saved): ?><div class="flash-ok" role="status">Menu changes saved.</div><?php endif; ?>
<?php if ($error): ?><div class="flash-error" role="alert"><?= e($error) ?> Your entries are retained below.</div><?php endif; ?>
<?php if ($quickManagerUrl !== ''): ?><div class="card"><h2>Quick catalog management</h2><p class="muted">Use Core’s shared workspace for searching, accessible drag-free ordering, and bulk show, hide, feature, or unfeature actions. Return here for pricing, dietary, allergen, image, and discount-tier details.</p><a class="btn secondary" href="<?= e_attr($basePath . $quickManagerUrl) ?>">Open menu quick manager</a></div><?php endif; ?>
<div class="card">
<h2>Menu categories</h2>
<div style="overflow:auto"><table><thead><tr><th>Name</th><th>Visible</th><th>Order</th></tr></thead><tbody>
<?php foreach ($categories as $category): ?><tr><td><?= e($category['name']) ?></td><td><?= $category['visible'] ? 'Yes' : 'No' ?></td><td><?= (int) $category['sort_order'] ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<details><summary>Add a category</summary><form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="add_category"><label>Category name<input name="name" required maxlength="190" value="<?= e_attr(($old['action'] ?? '') === 'add_category' ? $text('name') : '') ?>"></label><label>Description<textarea name="description" rows="3" maxlength="2000"><?= e(($old['action'] ?? '') === 'add_category' ? $text('description') : '') ?></textarea></label><label>Sort order<input type="number" name="sort_order" value="50"></label><button class="btn" type="submit">Add category</button></form></details>
</div>
<div class="card"><h2>Menu items</h2><p class="muted">Edit a dish to configure its pricing, serving size, dietary labels and known allergens. Only verified structured labels appear in the planner’s dietary filter.</p>
<div style="overflow:auto"><table><thead><tr><th>Dish</th><th>Category</th><th>Unit price / method</th><th>Availability</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?><tr><td><strong><?= e($item['name']) ?></strong></td><td><?= e($categoryNames[$item['category_id']] ?? 'Unknown') ?></td><td>$<?= e(number_format(((int) $item['price_cents']) / 100, 2)) ?> · <?= e($pricingLabels[$item['pricing_basis']] ?? 'Team confirmation required') ?></td><td><?= e($item['available_from'] ?: 'Any time') ?> – <?= e($item['available_until'] ?: 'No end') ?></td><td><?= $item['visible'] ? 'Visible' : 'Hidden' ?></td><td>
<a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/catering/menus&amp;edit_item=<?= (int) $item['id'] ?>#dish-editor">Edit</a>
<form method="post" style="display:inline"><?= $csrf->field() ?><input type="hidden" name="action" value="toggle_item"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="btn small secondary"><?= $item['visible'] ? 'Hide' : 'Show' ?></button></form>
<details style="display:inline-block"><summary>Delete</summary><form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><p>Remove <?= e($item['name']) ?>? Existing inquiry snapshots are preserved.</p><label><input type="checkbox" name="confirm_delete" value="1" required style="width:auto"> Confirm removal</label><button class="btn small danger">Delete dish</button></form></details>
</td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="card" id="dish-editor"><h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add menu item' ?></h2>
<form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="<?= $editing ? 'update_item' : 'add_item' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
<label>Category<select name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"<?= $text('category_id') === (string) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
<label>Name<input name="name" required maxlength="190" value="<?= e_attr($text('name')) ?>"></label>
<label>Description<textarea name="description" rows="3" maxlength="3000"><?= e($text('description')) ?></textarea></label>
<label>Dish image<select name="image"><option value="">No image</option><?php foreach ($dishImages as $filename => $label): ?><option value="<?= e_attr($filename) ?>"<?= $text('image') === $filename ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<fieldset><legend>Food estimate pricing</legend>
<label>Pricing method<select name="pricing_basis"><?php foreach ($pricingLabels as $key => $label): ?><option value="<?= e_attr($key) ?>"<?= $text('pricing_basis', 'manual') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<label>Unit price ($)<input type="number" name="price" min="0" max="1000000" step="0.01" required value="<?= e_attr($text('price', isset($values['price_cents']) ? number_format((int) $values['price_cents'] / 100, 2, '.', '') : '0.00')) ?>"></label>
<label>Display price note<input name="price_unit" maxlength="80" value="<?= e_attr($text('price_unit', 'per guest')) ?>"></label>
<label>Servings per tray <small>used for per-tray pricing</small><input type="number" name="servings_per_tray" min="1" max="10000" value="<?= e_attr($text('servings_per_tray', '1')) ?>"></label>
<label>Minimum billed guests or trays <small>fixed event prices always bill once</small><input type="number" name="minimum_quantity" min="1" max="10000" value="<?= e_attr($text('minimum_quantity', '1')) ?>"></label>
</fieldset>
<fieldset><legend>Seasonal availability <small>optional</small></legend><div class="form-grid"><label>Available from<input type="date" name="available_from" value="<?= e_attr($text('available_from')) ?>"></label><label>Available until<input type="date" name="available_until" value="<?= e_attr($text('available_until')) ?>"></label></div><p class="muted">Outside this inclusive window, the dish is omitted from public menus and quote planning without deleting it.</p></fieldset>
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
<fieldset><legend>Dietary information</legend><p class="muted">Use structured labels only after checking the recipe and preparation process. Existing display text is preserved separately.</p>
<?php foreach ($dietaryLabels as $key => $label): ?><label><input type="checkbox" style="width:auto" name="dietary_tags[]" value="<?= e_attr($key) ?>"<?= in_array($key, $chosenDietary, true) ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
<label>Additional dietary display text<input name="dietary" maxlength="255" value="<?= e_attr($text('dietary')) ?>"></label></fieldset>
<fieldset><legend>Known allergens</legend><p class="muted">An empty list means information has not been supplied; it does not mean allergen-free.</p>
<?php foreach ($allergenLabels as $key => $label): ?><label><input type="checkbox" style="width:auto" name="allergens[]" value="<?= e_attr($key) ?>"<?= in_array($key, $chosenAllergens, true) ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
<label>Ingredients, other allergens or cross-contact notes<textarea name="allergen_notes" maxlength="1000" rows="3"><?= e($text('allergen_notes')) ?></textarea></label></fieldset>
<label><input type="checkbox" name="featured" value="1" style="width:auto"<?= $text('featured') === '1' ? ' checked' : '' ?>> Feature on homepage</label>
<label>Sort order<input type="number" name="sort_order" value="<?= e_attr($text('sort_order', '50')) ?>"></label>
<button class="btn" type="submit"><?= $editing ? 'Save dish' : 'Add menu item' ?></button> <a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/catering/menus">Clear editor</a>
</form></div>
