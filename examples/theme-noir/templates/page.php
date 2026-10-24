<?php /** @var array $page */ ?>
<article class="doc">
<header class="doc-head">
<span class="doc-rule"></span>
<h1><?= e($page['title']) ?></h1>
</header>
<div class="doc-body">
<?= $page['body'] ?? '' /* sanitized at save time by Core\Content\Sanitizer */ ?>
</div>
</article>
