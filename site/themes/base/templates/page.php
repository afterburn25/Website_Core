<?php /** @var array $page @var bool $isHome */ ?>
<article class="page<?= !empty($page['rendered_blocks']) ? ' page-builder' : '' ?>">
<?php if (empty($page['rendered_blocks'])): ?>
<h1><?= e($page['title']) ?></h1>
<div class="page-body">
<?php
// Page bodies are admin-authored HTML. The rich-text sanitization policy
// Body is sanitized at the input boundary by Core\Content\Sanitizer, so
// emitting it here is safe — it contains only the allowlisted element set.
echo $page['body'] ?? '';
?>
</div>
<?php else: ?>
<?= $page['rendered_blocks'] ?>
<?php endif; ?>
</article>
