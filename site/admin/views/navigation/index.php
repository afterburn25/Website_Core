<?php /** @var array $items @var array $redirects @var int $menuId @var \Core\Security\Csrf $csrf @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Primary navigation</h2>
<table>
<tr><th>Label</th><th>URL</th><th>Parent</th><th>Order</th><th></th></tr>
<?php foreach ($items as $item): ?>
<tr>
<td><?= e($item['label']) ?></td>
<td><code><?= e($item['url']) ?></code></td>
<td><?= e((string) ($item['parent_id'] ?? '')) ?></td>
<td><?= (int) $item['sort_order'] ?></td>
<td>
<form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn small danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>
<div class="card">
<h2 style="margin-top:0">Add item</h2>
<form method="post">
<?= $csrf->field() ?><input type="hidden" name="action" value="add">
<label>Label</label><input name="label" required>
<label>URL <span class="muted">(internal path like /about or https:// link)</span></label>
<input name="url" required>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;max-width:34rem">
<div>
<label>Parent item</label>
<select name="parent_id">
<option value="">— top level —</option>
<?php foreach ($items as $item): ?>
<option value="<?= (int) $item['id'] ?>"><?= e($item['label']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div><label>Sort order</label><input name="sort_order" type="number" value="0"></div>
</div>
<div style="margin-top:1rem"><button class="btn" type="submit">Add item</button></div>
</form>
</div>

<div class="card">
<h2 style="margin-top:0">Redirects</h2>
<?php if ($redirects === []): ?>
<p class="muted">No redirects. Add one when a page moves so old links keep working.</p>
<?php else: ?>
<table>
<tr><th>Source</th><th>Target</th><th>Code</th><th>Hits</th><th></th></tr>
<?php foreach ($redirects as $rd): ?>
<tr>
<td><code><?= e($rd['source']) ?></code></td>
<td><code><?= e($rd['target']) ?></code></td>
<td><?= (int) $rd['status_code'] ?></td>
<td><?= (int) $rd['hits'] ?></td>
<td>
<form method="post" style="display:inline">
<?= $csrf->field() ?><input type="hidden" name="action" value="redirect_delete">
<input type="hidden" name="id" value="<?= (int) $rd['id'] ?>">
<button class="btn small danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<form method="post" style="margin-top:1rem">
<?= $csrf->field() ?><input type="hidden" name="action" value="redirect_add">
<div style="display:grid;grid-template-columns:1fr 1fr auto;gap:0 1rem;max-width:44rem;align-items:end">
<div><label>Source path</label><input name="source" placeholder="/old-page" required></div>
<div><label>Target <span class="muted">(path or https:// URL)</span></label><input name="target" placeholder="/new-page" required></div>
<div><label>Code</label><select name="status_code"><option>301</option><option>302</option><option>307</option><option>308</option></select></div>
</div>
<div style="margin-top:1rem"><button class="btn" type="submit">Add redirect</button></div>
</form>
</div>
