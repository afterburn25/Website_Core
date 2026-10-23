<?php /** @var array $checks @var array $mailRows @var array $integrity @var array $moduleHealth */ ?>
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

<?php if ($moduleHealth !== []): ?>
<div class="card"><h2 style="margin-top:0">Package health</h2><table><tr><th>Package</th><th>Check</th><th>Status</th><th>Detail</th></tr>
<?php foreach ($moduleHealth as $check): ?><tr><td><?= e($check['package']) ?></td><td><?= e($check['label']) ?></td><td><span class="badge <?= $check['status'] === 'ok' ? 'ok' : ($check['status'] === 'error' ? 'bad' : 'warn') ?>"><?= e($check['status']) ?></span></td><td><?= e($check['detail']) ?></td></tr><?php endforeach; ?>
</table></div>
<?php endif; ?>

<div class="card">
<h2 style="margin-top:0">Installation integrity</h2>
<table>
<tr><th>Check</th><th>Status</th><th>Detail</th></tr>
<?php foreach ($integrity as $check): ?>
<tr>
<td><?= e($check['name']) ?></td>
<td><span class="badge <?= $check['status'] === 'ok' ? 'ok' : 'bad' ?>"><?= e($check['status']) ?></span></td>
<td class="muted"><?= e($check['detail']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2 style="margin-top:0">System mail queue (last 20)</h2>
<?php if ($mailRows === []): ?>
<p class="muted">No queued or sent messages yet.</p>
<?php else: ?>
<table>
<tr><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Queued</th><th>Sent</th><th>Error</th></tr>
<?php foreach ($mailRows as $m): ?>
<tr>
<td><?= e($m['to']) ?></td>
<td><?= e($m['subject']) ?></td>
<td><strong><?= e($m['status']) ?></strong></td>
<td><?= e((string) $m['attempts']) ?></td>
<td><?= e($m['created_at']) ?></td>
<td><?= e($m['sent_at'] ?? '—') ?></td>
<td><?= e($m['last_error'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
