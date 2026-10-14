<?php /** @var string $content @var string $siteName @var string $tagline @var string $basePath @var array $pages @var array|null $page */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($page['meta_title'] ?? $page['title'] ?? '') !== '' ? ($page['meta_title'] ?? $page['title']) . ' — ' : '') ?><?= e($siteName) ?></title>
<?php if (!empty($page['meta_description'])): ?>
<meta name="description" content="<?= e_attr($page['meta_description']) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($basePath) ?>/_a/themes/base/assets/css/style.css">
</head>
<body>
<header class="site-header">
<a class="site-brand" href="<?= e_attr($basePath) ?>/"><?= e($siteName) ?></a>
<?php if ($tagline !== ''): ?><span class="site-tagline"><?= e($tagline) ?></span><?php endif; ?>
<nav class="site-nav" aria-label="Primary">
<?php foreach ($pages ?? [] as $nav): ?>
<a href="<?= e_attr($basePath . '/' . $nav['slug']) ?>"><?= e($nav['title']) ?></a>
<?php endforeach; ?>
</nav>
</header>
<main>
<?= $content ?>
</main>
<footer class="site-footer">
<p><?= e($siteName) ?> — powered by Website Core</p>
</footer>
</body>
</html>
