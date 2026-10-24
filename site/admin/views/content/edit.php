<?php
/** @var array $def @var ?array $item @var ?array $input @var \Core\Security\Csrf $csrf @var string $basePath */
$val = function (string $name) use ($item, $input): string {
    if (isset($input[$name])) {
        return (string) $input[$name];
    }
    return (string) ($item['fields'][$name] ?? '');
};
$qs = 'pkg=' . rawurlencode($def['package']) . '&amp;type=' . rawurlencode($def['type']);
?>
<div class="card">
<h2 style="margin-top:0"><?= e(($item ? 'Edit' : 'New') . ' ' . $def['label']) ?></h2>
<form method="post" action="<?= e_attr($basePath) ?>/admin/content/save">
<?= $csrf->field() ?>
<input type="hidden" name="pkg" value="<?= e_attr($def['package']) ?>">
<input type="hidden" name="type" value="<?= e_attr($def['type']) ?>">
<?php if ($item): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>
<?php foreach ($def['fields'] as $f): $req = $f['required'] ? 'required' : ''; ?>
<label for="f_<?= e_attr($f['name']) ?>"><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></label>
<?php switch ($f['type']):
    case 'textarea': ?>
<textarea id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" rows="4" <?= $req ?>><?= e($val($f['name'])) ?></textarea>
<?php break;
    case 'html': ?>
<textarea id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" rows="8" <?= $req ?>><?= e($val($f['name'])) ?></textarea>
<p class="muted">HTML allowed — sanitized on save (same rules as page bodies).</p>
<?php break;
    case 'number': ?>
<input id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" type="number" step="any" value="<?= e_attr($val($f['name'])) ?>" <?= $req ?>>
<?php break;
    case 'url': ?>
<input id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" type="url" value="<?= e_attr($val($f['name'])) ?>" <?= $req ?>>
<?php break;
    case 'select': ?>
<select id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" <?= $req ?>>
<option value="">—</option>
<?php foreach ($f['options'] as $opt => $label): ?>
<option value="<?= e_attr((string) $opt) ?>" <?= $val($f['name']) === (string) $opt ? 'selected' : '' ?>><?= e((string) $label) ?></option>
<?php endforeach; ?>
</select>
<?php break;
    case 'checkbox': ?>
<label class="inline"><input id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" type="checkbox" value="1" <?= $val($f['name']) === '1' ? 'checked' : '' ?>> yes</label>
<?php break;
    default: ?>
<input id="f_<?= e_attr($f['name']) ?>" name="<?= e_attr($f['name']) ?>" value="<?= e_attr($val($f['name'])) ?>" <?= $req ?>>
<?php endswitch; ?>
<?php endforeach; ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 1rem;max-width:34rem">
<div>
<label>Status</label>
<select name="status">
<?php $st = $input['status'] ?? ($item['status'] ?? 'published'); ?>
<option value="published" <?= $st === 'published' ? 'selected' : '' ?>>published</option>
<option value="draft" <?= $st === 'draft' ? 'selected' : '' ?>>draft</option>
</select>
</div>
<div><label>Sort order</label><input name="sort" type="number" value="<?= (int) ($input['sort'] ?? ($item['sort'] ?? 0)) ?>"></div>
</div>
<p>
<button class="btn">Save</button>
<a href="<?= e_attr($basePath) ?>/admin/content/list&amp;<?= $qs ?>">Cancel</a>
</p>
</form>
</div>
