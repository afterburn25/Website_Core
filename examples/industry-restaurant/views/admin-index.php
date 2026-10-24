<?php /** @var array $cats @var array $items @var \Core\Security\Csrf $csrf */ ?>
<div class="card">
<h2 style="margin-top:0">Categories</h2>
<table>
<?php foreach ($cats as $c): ?>
<tr>
<td><?= e($c['name']) ?></td><td><?= (int) $c['sort_order'] ?></td>
<td><form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete_category">
<input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
<button class="btn small danger">Delete (+ items)</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="add_category">
<input name="name" placeholder="Category name" required>
<input name="sort_order" type="number" value="0" style="width:5rem">
<button class="btn small">Add category</button>
</form>
</div>
<div class="card">
<h2 style="margin-top:0">Items</h2>
<table>
<tr><th>Name</th><th>Category</th><th>Price</th><th>Visible</th><th></th></tr>
<?php foreach ($items as $i): ?>
<tr>
<td><?= e($i['name']) ?></td>
<td><?= (int) $i['category_id'] ?></td>
<td><?= e('$' . number_format(((int) $i['price_cents']) / 100, 2)) ?></td>
<td><?= $i['visible'] ? 'yes' : 'no' ?></td>
<td><form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete_item">
<input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
<button class="btn small danger">Remove</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="add_item">
<select name="category_id">
<?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
</select>
<input name="name" placeholder="Item name" required>
<input name="price" placeholder="0.00" style="width:6rem">
<input name="description" placeholder="Description">
<label><input type="checkbox" name="visible" value="1" checked> visible</label>
<button class="btn small">Add item</button>
</form>
</div>
