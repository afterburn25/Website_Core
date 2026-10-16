<?php /** @var array $def @var array $items @var \Core\Security\Csrf $csrf @var string $basePath */
$qs = 'pkg=' . rawurlencode($def['package']) . '&amp;type=' . rawurlencode($def['type']);
?>
<div class="card">
<h2 style="margin-top:0"><?= e($def['label']) ?> <span class="muted">— <?= e($def['package']) ?>/<?= e($def['type']) ?></span></h2>
<p><a class="btn" href="<?= e_attr($basePath) ?>/admin/content/edit?<?= $qs ?>">New item</a>
<a href="<?= e_attr($basePath) ?>/admin/content">All content types</a></p>
<?php if ($items === []): ?>
<p class="muted">No items yet.</p>
<?php else: ?>
<table>
<tr><th>Title</th><th>Status</th><th>Sort</th><th>Updated</th><th></th></tr>
<?php foreach ($items as $item): ?>
<tr>
<td><?= e($item['title'] !== '' ? $item['title'] : '#' . $item['id']) ?></td>
<td><?= e($item['status']) ?></td>
<td><?= (int) $item['sort'] ?></td>
<td class="muted"><?= e($item['updated_at']) ?></td>
<td>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/content/edit?<?= $qs ?>&amp;id=<?= (int) $item['id'] ?>">Edit</a>
<form method="post" action="<?= e_attr($basePath) ?>/admin/content/delete" style="display:inline"
 onsubmit="return confirm('Delete this item?')">
<?= $csrf->field() ?>
<input type="hidden" name="pkg" value="<?= e_attr($def['package']) ?>">
<input type="hidden" name="type" value="<?= e_attr($def['type']) ?>">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn small danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
