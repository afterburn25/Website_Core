<?php /** @var array $checks @var array $mailRows @var array $integrity @var array $moduleHealth @var array $warnings */ $warnings=$warnings??[]; $integrityProblems=count(array_filter($integrity,static fn($c)=>($c['status']??'error')!=='ok')); $moduleProblems=count(array_filter($moduleHealth,static fn($c)=>($c['status']??'error')!=='ok')); $mailProblems=count(array_filter($mailRows,static fn($m)=>($m['status']??'')==='failed')); ?>
<?php if ($warnings !== []): ?><div class="flash-error" role="alert"><strong>Some health checks could not run.</strong><p>The rest of the report is still available. Download diagnostics or review the protected application log for details.</p><ul><?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="status-strip"><div class="status-chip <?= $integrityProblems?'bad':'ok' ?>"><strong><?= $integrityProblems ?></strong><span>Integrity issues</span></div><div class="status-chip <?= $moduleProblems?'warn':'ok' ?>"><strong><?= $moduleProblems ?></strong><span>Package warnings</span></div><div class="status-chip <?= $mailProblems?'warn':'ok' ?>"><strong><?= $mailProblems ?></strong><span>Mail failures</span></div></section>
<div class="card">
<div class="card-heading"><div><h2>System health</h2><p class="card-subtitle">Runtime facts and recovery signals from this installation.</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/system/diagnostics" download>Download diagnostics</a></div>
<p class="muted">The bundle contains versions, package states, and journal tails; configuration values are never included.</p>
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
