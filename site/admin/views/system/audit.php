<?php /** @var array $rows */ ?>
<div class="card">
<h2 style="margin-top:0">Audit log</h2>
<?php if ($rows === []): ?>
<p class="muted">No audited events yet.</p>
<?php else: ?>
<table>
<tr><th>When</th><th>Event</th><th>Admin</th><th>Detail</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= e($row['created_at'] ?? '') ?></td>
<td><?= e($row['event'] ?? '') ?></td>
<td><?= e((string) ($row['admin_id'] ?? '')) ?></td>
<td class="muted"><?= e(substr((string) ($row['detail'] ?? ''), 0, 120)) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
