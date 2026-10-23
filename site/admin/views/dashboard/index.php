<?php /** @var int $pageCount @var int $published @var array $jobs @var array $packages @var array $integrity @var array $notices @var array $moduleWidgets @var string $php @var string $dbVersion */ ?>
<div class="metric-grid">
<div class="metric"><span>Published pages</span><strong><?= $published ?></strong><small><?= $pageCount ?> total</small></div>
<div class="metric"><span>Active packages</span><strong><?= count(array_filter($packages, fn ($p) => $p['state'] === 'enabled')) ?></strong><small><?= count($packages) ?> discovered</small></div>
<div class="metric"><span>Pending work</span><strong><?= (int) $jobs['pending'] + (int) $jobs['uncertain'] ?></strong><small><?= (int) $jobs['failed'] ?> failed</small></div>
<div class="metric"><span>Integrity</span><strong class="<?= $integrity['error'] ? 'text-bad' : 'text-ok' ?>"><?= $integrity['error'] ? $integrity['error'] . ' errors' : 'Ready' ?></strong><small><?= (int) $integrity['warn'] ?> warnings</small></div>
</div>
<?php if ($moduleWidgets !== []): ?>
<div class="metric-grid module-metrics"><?php foreach ($moduleWidgets as $widget): ?>
<div class="metric"><span><?= e($widget['label']) ?></span><strong><?= e((string) ($widget['value'] ?? '—')) ?></strong><small><?= e((string) ($widget['detail'] ?? $widget['package'])) ?></small><?php if (!empty($widget['url'])): ?><a href="<?= e_attr($basePath . $widget['url']) ?>">Open</a><?php endif; ?></div>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="card">
<h2 style="margin-top:0">Overview</h2>
<table>
<tr><th>PHP</th><td><?= e($php) ?></td></tr>
<tr><th>Database</th><td><?= e((string) $dbVersion) ?></td></tr>
<tr><th>Pages</th><td><?= (int) $pageCount ?></td></tr>
<tr><th>Jobs</th><td>
<?php foreach ($jobs as $k => $v): ?>
<span class="badge <?= $k === 'failed' || $k === 'uncertain' ? 'bad' : '' ?>"><?= e($k) ?>: <?= (int) $v ?></span>
<?php endforeach; ?>
</td></tr>
<tr><th>Packages</th><td><?= count($packages) ?> discovered</td></tr>
</table>
</div>
<?php if ($notices !== []): ?>
<div class="card"><div class="card-heading"><h2>Notifications</h2><a href="<?= e_attr($basePath) ?>/admin/notices">View all</a></div>
<?php foreach ($notices as $notice): ?><p><span class="badge <?= $notice['kind'] === 'error' ? 'bad' : ($notice['kind'] === 'warning' ? 'warn' : '') ?>"><?= e($notice['kind']) ?></span> <strong><?= e($notice['title']) ?></strong> — <?= e($notice['message']) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card">
<h2 style="margin-top:0">Getting started</h2>
<p class="muted">Core-only installation: manage pages under Content, configure site identity
under Settings, and install industry/add-on packages under Modules.</p>
</div>
