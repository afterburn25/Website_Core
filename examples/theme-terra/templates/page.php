<?php /** @var array $page */ ?>
<article class="entry">
<header class="entry-head"><h1><?= e($page['title']) ?></h1></header>
<div class="entry-body">
<?= $page['body'] ?? '' /* sanitized at save time by Core\Content\Sanitizer */ ?>
</div>
</article>
