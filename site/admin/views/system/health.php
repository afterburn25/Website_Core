<?php /** @var array $checks @var array $mailRows */ ?>
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
