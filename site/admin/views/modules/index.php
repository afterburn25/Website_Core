<?php /** @var array $packages */ ?>
<div class="card">
<?php if ($packages === []): ?>
<p class="muted">No packages discovered. Upload industry modules or add-ons to
<code>modules/industries/</code> or <code>modules/addons/</code> — the Module Manager
upload/review flow arrives in milestone M3.</p>
<?php else: ?>
<table>
<tr><th>Package</th><th>Type</th><th>Version</th><th>State</th><th>Issues</th></tr>
<?php foreach ($packages as $id => $info): $m = $info['manifest']; ?>
<tr>
<td><strong><?= e($id) ?></strong><br><span class="muted"><?= e($m['name'] ?? '') ?></span></td>
<td><?= e($m['type'] ?? '?') ?></td>
<td><?= e($m['version'] ?? '?') ?></td>
<td><span class="badge <?= $info['state'] === 'enabled' ? 'ok' : ($info['state'] === 'failed' ? 'bad' : '') ?>"><?= e($info['state']) ?></span></td>
<td class="err"><?= e(implode('; ', $info['errors'])) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
