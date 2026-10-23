<?php /** @var string $content @var string $siteName @var string $tagline @var string $basePath @var array $pages @var array|null $page @var string|null $brandLogoUrl @var string|null $faviconUrl @var string|null $socialImageUrl @var array $designTokens */ ?>
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
<?php elseif (!empty($socialImageUrl)): ?>
<meta property="og:title" content="<?= e_attr($page['meta_title'] ?? $page['title'] ?? $siteName) ?>">
<meta property="og:image" content="<?= e_attr($socialImageUrl) ?>">
<?php endif; ?>
<?php if (!empty($faviconUrl)): ?><link rel="icon" href="<?= e_attr($faviconUrl) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($basePath . '/index.php?p=/_a/themes/base/assets/css/style.css&v=' . rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development')) ?>">
<style>:root{--color-accent:<?= e_attr($designTokens['primary']) ?>;--color-highlight:<?= e_attr($designTokens['accent']) ?>;--radius:<?= (int) $designTokens['radius'] ?>px}</style>
</head>
<body>
<header class="site-header">
<a class="site-brand" href="<?= e_attr($basePath) ?>/"><?php if (!empty($brandLogoUrl)): ?><img src="<?= e_attr($brandLogoUrl) ?>" alt="<?= e_attr($siteName) ?>"><?php else: ?><?= e($siteName) ?><?php endif; ?></a>
<?php if ($tagline !== ''): ?><span class="site-tagline"><?= e($tagline) ?></span><?php endif; ?>
<nav class="site-nav" aria-label="Primary">
<?php
$renderMenu = function (array $items, bool $child = false) use (&$renderMenu, $basePath): void {
    echo '<ul class="' . ($child ? 'nav-children' : 'nav-root') . '">';
    foreach ($items as $nav) {
        $url = (string) ($nav['url'] ?? '/' . ($nav['slug'] ?? ''));
        $href = str_starts_with($url, '/') ? $basePath . $url : $url;
        $label = (string) ($nav['label'] ?? $nav['title'] ?? '');
        echo '<li class="nav-item"><a href="' . e_url($href) . '">' . e($label) . '</a>';
        if (!empty($nav['children'])) $renderMenu($nav['children'], true);
        echo '</li>';
    }
    echo '</ul>';
};
$renderMenu($pages ?? []);
?>
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
