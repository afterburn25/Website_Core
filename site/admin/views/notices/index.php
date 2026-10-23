<?php /** @var list<array> $notices */ ?>
<div class="card">
<?php if ($notices === []): ?><div class="empty-state"><strong>You’re all caught up.</strong><p>No active system notifications.</p></div><?php endif; ?>
<?php foreach ($notices as $notice): ?>
<article class="notice-row notice-<?= e_attr($notice['kind']) ?>">
<div><h2><?= e($notice['title']) ?></h2><?php if ($notice['message']): ?><p><?= e($notice['message']) ?></p><?php endif; ?><small><?= e($notice['created_at']) ?></small></div>
<div class="notice-actions"><?php if ($notice['action_url']): ?><a class="btn small" href="<?= e_attr($basePath . $notice['action_url']) ?>">Open</a><?php endif; ?><form method="post" action="<?= e_attr($basePath) ?>/admin/notices/<?= (int) $notice['id'] ?>/dismiss"><?= $csrf->field() ?><button class="btn secondary small" type="submit">Dismiss</button></form></div>
</article>
<?php endforeach; ?>
</div>
