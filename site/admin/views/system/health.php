<?php /** @var array $checks */ ?>
<div class="card">
<h2 style="margin-top:0">System health</h2>
<table>
<?php foreach ($checks as $label => $value): ?>
<tr><td><?= e($label) ?></td><td><strong><?= e($value) ?></strong></td></tr>
<?php endforeach; ?>
</table>
</div>
