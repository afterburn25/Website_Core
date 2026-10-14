<?php /** @var array $page @var bool $isHome */ ?>
<article class="page">
<h1><?= e($page['title']) ?></h1>
<div class="page-body">
<?php
// Page bodies are admin-authored HTML. The rich-text sanitization policy
// lands with the M2 editor; for now content is emitted by trusted admins.
echo $page['body'] ?? '';
?>
</div>
</article>
