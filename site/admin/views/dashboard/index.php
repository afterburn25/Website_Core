<?php /** @var int $pageCount @var int $published @var array $jobs @var array $packages @var array $integrity @var array $notices @var array $moduleWidgets @var ?array $analytics @var string $php @var string $dbVersion */ ?>
<div class="metric-grid">
<a class="metric metric-link" href="<?= e_attr($basePath) ?>/admin/pages"><span>Published pages</span><strong><?= $published ?></strong><small><?= $pageCount ?> total · Manage content →</small></a>
<a class="metric metric-link" href="<?= e_attr($basePath) ?>/admin/modules"><span>Active packages</span><strong><?= count(array_filter($packages, fn ($p) => $p['state'] === 'enabled')) ?></strong><small><?= count($packages) ?> discovered · Manage packages →</small></a>
<a class="metric metric-link" href="<?= e_attr($basePath) ?>/admin/operations"><span>Pending work</span><strong><?= (int) $jobs['pending'] + (int) $jobs['uncertain'] ?></strong><small><?= (int) $jobs['failed'] ?> failed · Review operations →</small></a>
<a class="metric metric-link" href="<?= e_attr($basePath) ?>/admin/health"><span>Integrity</span><strong class="<?= $integrity['error'] ? 'text-bad' : 'text-ok' ?>"><?= $integrity['error'] ? $integrity['error'] . ' errors' : 'Ready' ?></strong><small><?= (int) $integrity['warn'] ?> warnings · Open health report →</small></a>
</div>
<?php if ($analytics !== null): ?><section class="dashboard-analytics"><div><p class="section-kicker">Last 7 days</p><h2>Audience snapshot</h2><p class="muted">First-party insight from successful public page visits.</p></div><div class="dashboard-analytics-metrics"><span><strong><?= (int)$analytics['visits'] ?></strong> visitors</span><span><strong><?= (int)$analytics['pageviews'] ?></strong> pageviews</span><span><strong><?= e(number_format((float)$analytics['bounce_rate'],1)) ?>%</strong> bounce</span><span class="live"><strong><?= (int)$analytics['live'] ?></strong> live now</span></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/analytics">Open analytics</a></section><?php endif; ?>
<?php if ($moduleWidgets !== []): ?>
<div class="metric-grid module-metrics"><?php foreach ($moduleWidgets as $widget): ?>
<div class="metric"><span><?= e($widget['label']) ?></span><strong><?= e((string) ($widget['value'] ?? '—')) ?></strong><small><?= e((string) ($widget['detail'] ?? $widget['package'])) ?></small><?php if (!empty($widget['url'])): ?><a class="metric-action" href="<?= e_attr($basePath . $widget['url']) ?>">Open workspace →</a><?php endif; ?></div>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="dashboard-grid">
<section class="card">
<div class="card-heading"><div><p class="section-kicker">System snapshot</p><h2>Environment</h2></div><a href="<?= e_attr($basePath) ?>/admin/system/diagnostics">Download diagnostics</a></div>
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
</section>
<section class="card">
<p class="section-kicker">Common tasks</p>
<h2>Quick actions</h2>
<div class="quick-action-grid">
<a href="<?= e_attr($basePath) ?>/admin/pages/edit"><strong>Create a page</strong><span>Start and publish website content</span></a>
<a href="<?= e_attr($basePath) ?>/admin/media"><strong>Manage media</strong><span>Upload and organize images and files</span></a>
<a href="<?= e_attr($basePath) ?>/admin/navigation"><strong>Edit navigation</strong><span>Reorder links and build submenus</span></a>
<a href="<?= e_attr($basePath) ?>/admin/settings"><strong>Site settings</strong><span>Identity, contact information, and mail</span></a>
</div>
</section>
</div>
<?php if ($notices !== []): ?>
<div class="card"><div class="card-heading"><h2>Notifications</h2><a href="<?= e_attr($basePath) ?>/admin/notices">View all</a></div>
<?php foreach ($notices as $notice): ?><p><span class="badge <?= $notice['kind'] === 'error' ? 'bad' : ($notice['kind'] === 'warning' ? 'warn' : '') ?>"><?= e($notice['kind']) ?></span> <strong><?= e($notice['title']) ?></strong> — <?= e($notice['message']) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card getting-started"><div><p class="section-kicker">Core workflow</p><h2>Build from the universal foundation</h2><p class="muted">Manage shared pages, media, navigation, design, security, and operations in Core. Industry-specific workflows remain inside the installed industry module.</p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/modules">Review installed modules</a></div>
