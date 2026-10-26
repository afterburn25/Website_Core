<?php /** @var string $query @var list<array{type:string,title:string,detail:string,url:string}> $results */ ?>
<div class="card">
<form method="get" action="<?= e_attr($basePath) ?>/admin/search" role="search">
<label for="admin-search">Search pages, media, packages, and active module records</label>
<div class="inline-fields"><input id="admin-search" type="search" name="q" value="<?= e_attr($query) ?>" autofocus><button class="btn" type="submit">Search</button></div>
</form>
</div>
<?php if ($query !== ''): ?>
<div class="card">
<h2 style="margin-top:0">Results for “<?= e($query) ?>”</h2>
<?php if ($results === []): ?><p class="muted">No matching items.</p><?php else: ?>
<div class="result-list">
<?php foreach ($results as $result): ?>
<a class="result-row" href="<?= e_url($basePath . $result['url']) ?>"><span class="badge"><?= e($result['type']) ?></span><span><strong><?= e($result['title']) ?></strong><small><?= e($result['detail']) ?></small></span></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php endif; ?>
