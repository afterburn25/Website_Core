<?php /** @var array $page @var array $left @var array $right @var string $basePath */ ?>
<div class="card">
<div class="card-heading"><div><h2 style="margin:0">Revision comparison</h2><p class="muted"><?= e($page['title']) ?></p></div><a class="btn secondary" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $page['id'] ?>">Back to editor</a></div>
</div>
<div class="grid2">
<?php foreach ([['Older', $left], ['Newer', $right]] as [$label, $revision]): ?>
<article class="card"><h2 style="margin-top:0"><?= e($label) ?></h2><p class="muted"><?= e($revision['created_at']) ?> · saved by #<?= (int) $revision['saved_by'] ?></p><h3><?= e($revision['title']) ?></h3><h4>Body</h4><pre style="white-space:pre-wrap;overflow:auto"><?= e($revision['body'] ?? '') ?></pre><h4>Blocks</h4><pre style="white-space:pre-wrap;overflow:auto"><?= e(json_encode(json_decode((string) ($revision['blocks_json'] ?? '[]'), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article>
<?php endforeach; ?>
</div>
