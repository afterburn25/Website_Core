<?php
$values = $old !== [] ? $old : ($editing ?? []);
$text = static fn (string $key, string $default = ''): string => is_scalar($values[$key] ?? $default) ? (string) ($values[$key] ?? $default) : $default;
?>
<?php if ($saved): ?><div class="flash-ok" role="status">Menu collection changes saved.</div><?php endif; ?>
<?php if ($error): ?><div class="flash-error" role="alert"><?= e($error) ?> Your entries are retained below.</div><?php endif; ?>

<div class="card"><div class="card-heading"><div><p class="section-kicker">Reusable menus</p><h2>Menu collections</h2><p class="muted">Build a reusable set of dishes, then link it to one or more packages. Package category allowances still control how many dishes a customer chooses.</p></div></div>
<div class="table-wrap"><table><thead><tr><th scope="col">Collection</th><th scope="col">Dishes</th><th scope="col">Availability</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($groups as $group): ?><tr>
<td><strong><?= e($group['name']) ?></strong><br><small><?= e($group['description']) ?></small></td>
<td><?= count($group['items']) ?></td>
<td><?= e($group['available_from'] ?: 'Any time') ?> – <?= e($group['available_until'] ?: 'No end') ?></td>
<td><?= $group['visible'] ? 'Visible' : 'Hidden' ?></td>
<td><a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/catering/menu-groups&amp;edit_group=<?= (int) $group['id'] ?>#collection-editor">Edit</a>
<form method="post" style="display:inline"><?= $csrf->field() ?><input type="hidden" name="action" value="toggle_group"><input type="hidden" name="id" value="<?= (int) $group['id'] ?>"><button class="btn small secondary"><?= $group['visible'] ? 'Hide' : 'Show' ?></button></form>
<details style="display:inline-block"><summary>Delete</summary><form method="post"><?= $csrf->field() ?><input type="hidden" name="action" value="delete_group"><input type="hidden" name="id" value="<?= (int) $group['id'] ?>"><p>Remove <?= e($group['name']) ?>? Linked packages will remain and become unrestricted by a collection.</p><label><input type="checkbox" name="confirm_delete" value="1" required style="width:auto"> Confirm removal</label><button class="btn small danger">Delete collection</button></form></details></td>
</tr><?php endforeach; ?>
<?php if ($groups === []): ?><tr><td colspan="5">No menu collections yet.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="card" id="collection-editor"><h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add menu collection' ?></h2><form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="<?= $editing ? 'update_group' : 'add_group' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
<label>Name<input name="name" required maxlength="190" value="<?= e_attr($text('name')) ?>"></label>
<label>Description<textarea name="description" rows="3" maxlength="3000"><?= e($text('description')) ?></textarea></label>
<div class="form-grid"><label>Available from <small>optional</small><input type="date" name="available_from" value="<?= e_attr($text('available_from')) ?>"></label><label>Available until <small>optional</small><input type="date" name="available_until" value="<?= e_attr($text('available_until')) ?>"></label><label>Sort order<input type="number" name="sort_order" value="<?= e_attr($text('sort_order', '50')) ?>"></label></div>
<fieldset><legend>Dishes in this collection</legend><p class="muted">Only currently available dishes appear to customers; historical inquiry snapshots retain earlier selections.</p>
<?php foreach ($items as $item): ?><label><input type="checkbox" style="width:auto" name="item_ids[]" value="<?= (int) $item['id'] ?>"<?= in_array((int) $item['id'], $memberIds, true) ? ' checked' : '' ?>> <?= e($item['name']) ?><?= !$item['visible'] ? ' (hidden)' : '' ?></label><?php endforeach; ?>
</fieldset>
<button class="btn" type="submit"><?= $editing ? 'Save collection' : 'Add collection' ?></button> <a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/catering/menu-groups">Clear editor</a>
</form></div>
