<?php /** @var string $content @var string $siteName @var string $tagline @var string $basePath @var array $pages @var array|null $page */ ?>
<!doctype html>
<html lang="<?= e_attr(\Core\I18n\Translator::instance()->langTag()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($page['meta_title'] ?? $page['title'] ?? '') !== '' ? ($page['meta_title'] ?? $page['title']) . ' — ' : '') ?><?= e($siteName) ?></title>
<?php if (!empty($page['meta_description'])): ?>
<meta name="description" content="<?= e_attr($page['meta_description']) ?>">
<?php endif; ?>
<?php if (!empty($page['noindex'])): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($page['canonical'])): ?><link rel="canonical" href="<?= e_attr($page['canonical']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($basePath) ?>/_a/themes/terra/assets/css/style.css">
</head>
<body>
<header class="masthead">
<div class="masthead-inner">
<a class="brand" href="<?= e_attr($basePath) ?>/"><?= e($siteName) ?></a>
<?php if ($tagline !== ''): ?><p class="tagline"><?= e($tagline) ?></p><?php endif; ?>
</div>
<nav class="nav" aria-label="Primary">
<?php foreach ($pages ?? [] as $nav): ?>
<?php $href = ($nav['url'] ?? '') !== '' && $nav['url'][0] !== '/' ? $nav['url'] : ($basePath . ($nav['url'] ?? '/' . ($nav['slug'] ?? ''))); ?>
<?php $target = $nav['target'] ?? 'self'; ?>
<a href="<?= e_url($href) ?>"<?= $target === 'tab' ? ' target="_blank" rel="noopener noreferrer"' : ($target === 'window' ? ' target="wc-nav-window" rel="noopener noreferrer" data-open-window' : '') ?>><?= e($nav['label'] ?? $nav['title'] ?? '') ?></a>
<?php endforeach; ?>
</nav>
</header>
<main class="column">
<?= $content ?>
</main>
<footer class="colophon">
<p><?= e($siteName) ?></p>
</footer>
<script>document.querySelectorAll('[data-open-window]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();window.open(link.href,'wc-nav-window','noopener,noreferrer,width=1200,height=800,resizable=yes,scrollbars=yes');}));</script>
</body>
</html>
