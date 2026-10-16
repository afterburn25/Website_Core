<?php /** @var array $types @var string $basePath */ ?>
<div class="card">
<h2 style="margin-top:0">Package content types</h2>
<?php if ($types === []): ?>
<p class="muted">No enabled package registers a content type. Packages declare
types via <code>ctx-&gt;contentType()</code>; each gets a generated list and
edit form here.</p>
<?php else: ?>
<table>
<tr><th>Type</th><th>Package</th><th>Fields</th><th>Items</th><th></th></tr>
<?php foreach ($types as $key => $def): ?>
<tr>
<td><?= e($def['label']) ?> <code><?= e($def['type']) ?></code></td>
<td><code><?= e($def['package']) ?></code></td>
<td class="muted"><?= e(implode(', ', array_column($def['fields'], 'name'))) ?></td>
<td><?= count($def['items']) ?></td>
<td>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/content/list?pkg=<?= rawurlencode($def['package']) ?>&amp;type=<?= rawurlencode($def['type']) ?>">Manage</a>
<a class="btn small" href="<?= e_attr($basePath) ?>/admin/content/edit?pkg=<?= rawurlencode($def['package']) ?>&amp;type=<?= rawurlencode($def['type']) ?>">New</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
