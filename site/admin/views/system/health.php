<?php /** @var array $checks */ ?>
<div class="card">
<h2 style="margin-top:0">System health</h2>
<p><a href="<?= e_attr($basePath) ?>/admin/system/diagnostics" download>Download diagnostics bundle</a>
— versions, package states, journals; config values are never included.</p>
<table>
<?php foreach ($checks as $label => $value): ?>
<tr><td><?= e($label) ?></td><td><strong><?= e($value) ?></strong></td></tr>
<?php endforeach; ?>
</table>
</div>
