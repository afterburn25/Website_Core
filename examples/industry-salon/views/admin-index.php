<?php /** @var array $services @var array $staff @var \Core\Security\Csrf $csrf */ ?>
<div class="card">
<h2 style="margin-top:0">Services</h2>
<table>
<tr><th>Name</th><th>Duration</th><th>Price</th><th>Visible</th><th></th></tr>
<?php foreach ($services as $s): ?>
<tr>
<td><?= e($s['name']) ?></td>
<td><?= (int) $s['duration_minutes'] ?> min</td>
<td>$<?= e(number_format(((int) $s['price_cents']) / 100, 2)) ?></td>
<td><?= $s['visible'] ? 'yes' : 'no' ?></td>
<td><form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete_service">
<input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
<button class="btn small danger">Remove</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="add_service">
<input name="name" placeholder="Service name" required>
<input name="duration" type="number" value="30" style="width:5rem"> min
<input name="price" placeholder="0.00" style="width:6rem">
<label><input type="checkbox" name="visible" value="1" checked> visible</label>
<button class="btn small">Add service</button>
</form>
</div>
<div class="card">
<h2 style="margin-top:0">Staff</h2>
<table>
<tr><th>Name</th><th>Title</th><th></th></tr>
<?php foreach ($staff as $p): ?>
<tr>
<td><?= e($p['name']) ?></td><td><?= e($p['title'] ?? '') ?></td>
<td><form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete_staff">
<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<button class="btn small danger">Remove</button></form></td>
</tr>
<?php endforeach; ?>
</table>
<form method="post" style="margin-top:.5rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="add_staff">
<input name="name" placeholder="Name" required>
<input name="title" placeholder="Title">
<button class="btn small">Add staff member</button>
</form>
</div>
