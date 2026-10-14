<?php /** @var int $pageCount @var array $jobs @var array $packages @var string $php @var string $dbVersion */ ?>
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
<div class="card">
<h2 style="margin-top:0">Getting started</h2>
<p class="muted">Core-only installation: manage pages under Content, configure site identity
under Settings, and install industry/add-on packages under Modules.</p>
</div>
