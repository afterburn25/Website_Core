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
<?php if (!empty($page['noindex'])): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if (!empty($page['canonical'])): ?>
<link rel="canonical" href="<?= e_attr($page['canonical']) ?>">
<?php endif; ?>
<?php if (!empty($page['og_image_url'])): ?>
<meta property="og:title" content="<?= e_attr($page['meta_title'] ?? $page['title'] ?? '') ?>">
<meta property="og:image" content="<?= e_attr($page['og_image_url']) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($basePath . '/index.php?p=/_a/themes/base/assets/css/style.css&v=' . rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development')) ?>">
</head>
<body>
<header class="site-header">
<a class="site-brand" href="<?= e_attr($basePath) ?>/"><?= e($siteName) ?></a>
<?php if ($tagline !== ''): ?><span class="site-tagline"><?= e($tagline) ?></span><?php endif; ?>
<nav class="site-nav" aria-label="Primary">
<?php foreach ($pages ?? [] as $nav): ?>
<?php $href = ($nav['url'] ?? '') !== '' && $nav['url'][0] !== '/' ? $nav['url'] : ($basePath . ($nav['url'] ?? '/' . ($nav['slug'] ?? ''))); ?>
<span class="nav-item">
<a href="<?= e_url($href) ?>"><?= e($nav['title']) ?></a>
<?php if (!empty($nav['children'])): ?>
<span class="nav-children">
<?php foreach ($nav['children'] as $child): ?>
<a href="<?= e_url($child['url'][0] === '/' ? $basePath . $child['url'] : $child['url']) ?>"><?= e($child['label']) ?></a>
<?php endforeach; ?>
</span>
<?php endif; ?>
</span>
<?php endforeach; ?>
</nav>
</header>
<main>
<?= $content ?>
</main>
<footer class="site-footer">
<p><?= e($siteName) ?> — <?= e(__('site.powered_by')) ?></p>
</footer>
</body>
</html>
