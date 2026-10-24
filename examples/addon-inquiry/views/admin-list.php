<?php /** @var array $rows */ ?>
<div class="card">
<h2 style="margin-top:0">Inquiry submissions</h2>
<?php if ($rows === []): ?>
<p class="muted">No inquiries yet.</p>
<?php else: ?>
<table>
<tr><th>When</th><th>Name</th><th>Email</th><th>Message</th><th>Status</th></tr>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= e($row['created_at']) ?></td>
<td><?= e($row['name']) ?></td>
<td><?= e($row['email']) ?></td>
<td><?= e(substr((string) $row['message'], 0, 200)) ?></td>
<td><span class="badge"><?= e($row['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
</div>
