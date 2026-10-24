<?php
/** @var string $content @var string $siteName @var string $tagline @var string $basePath @var string $assetBase
 * @var string $currentPath @var string $phone @var string $contactEmail @var array $page @var array $navigation
 * @var array $footerNavigation @var string $copyrightNotice
 * @var string|null $brandLogoUrl @var string|null $faviconUrl */
$asset = $assetBase;
$assetVersion = rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development');
$renderNavigation = function (array $items, bool $child = false) use (&$renderNavigation, $basePath, $currentPath): void {
    echo '<ul class="' . ($child ? 'c-nav-children' : 'c-nav-root') . '">';
    foreach ($items as $item) {
        $url = (string) ($item['url'] ?? '/');
        $href = str_starts_with($url, '/') ? $basePath . $url : $url;
        $active = str_starts_with($url, '/') && ($currentPath === $url || ($url !== '/' && str_starts_with($currentPath, rtrim($url, '/') . '/')));
        $target = (string) ($item['target'] ?? 'self');
        $attrs = $target === 'tab' ? ' target="_blank" rel="noopener noreferrer"'
            : ($target === 'window' ? ' target="wc-nav-window" rel="noopener noreferrer" data-open-window' : '');
        echo '<li class="c-nav-item"><a href="' . e_url($href) . '"' . ($active ? ' aria-current="page"' : '') . $attrs . '>' . e((string) ($item['label'] ?? '')) . '</a>';
        if (!empty($item['children']) && is_array($item['children'])) {
            $renderNavigation($item['children'], true);
        }
        echo '</li>';
    }
    echo '</ul>';
};
$renderFooterNavigation = function (array $items, bool $child = false) use (&$renderFooterNavigation, $basePath): void {
    echo '<ul class="' . ($child ? 'c-footer-nav-children' : 'c-footer-nav') . '">';
    foreach ($items as $item) {
        $url = (string) ($item['url'] ?? '/');
        $href = str_starts_with($url, '/') ? $basePath . $url : $url;
        $target = (string) ($item['target'] ?? 'self');
        $attrs = $target === 'tab' ? ' target="_blank" rel="noopener noreferrer"'
            : ($target === 'window' ? ' target="wc-nav-window" rel="noopener noreferrer" data-open-window' : '');
        echo '<li><a href="' . e_url($href) . '"' . $attrs . '>' . e((string) ($item['label'] ?? '')) . '</a>';
        if (!empty($item['children']) && is_array($item['children'])) $renderFooterNavigation($item['children'], true);
        echo '</li>';
    }
    echo '</ul>';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(($page['title'] ?? '') !== 'Home' ? ($page['title'] ?? '') . ' — ' : '') ?><?= e($siteName) ?></title>
<meta name="description" content="<?= e_attr($page['meta_description'] ?? '') ?>">
<?php if (!empty($page['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<?php if (!empty($faviconUrl)): ?><link rel="icon" href="<?= e_attr($faviconUrl) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($asset . '/assets/css/catering.css&v=' . $assetVersion) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="c-site-header" data-site-header>
<a class="c-brand" href="<?= e_attr($basePath) ?>/" aria-label="<?= e_attr($siteName) ?> home">
<?php if (!empty($brandLogoUrl)): ?>
<img class="c-brand-logo" src="<?= e_attr($brandLogoUrl) ?>" alt="<?= e_attr($siteName) ?>">
<?php else: ?>
<span class="c-brand-name"><?= e($siteName) ?></span>
<span class="c-brand-rule"></span>
<span class="c-brand-kind">Catering</span>
<?php endif; ?>
</a>
<button class="c-nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle>
<span></span><span></span><span></span><span class="sr-only">Open navigation</span>
</button>
<nav id="primary-navigation" class="c-nav" aria-label="Primary" data-nav>
<?php $renderNavigation($navigation); ?>
</nav>
<a class="c-button c-button-gold c-header-cta" href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a>
</header>
<main id="main-content">
<?= $content ?>
</main>
<section class="c-final-cta" aria-labelledby="final-cta-title">
<div>
<p class="c-eyebrow c-eyebrow-light">Your gathering starts here</p>
<h2 id="final-cta-title">Let’s create something beautiful together.</h2>
<p>Share a few details and we’ll help shape the menu, service, and experience.</p>
<a class="c-button c-button-gold" href="<?= e_attr($basePath) ?>/request-a-quote">Request a Quote</a>
</div>
</section>
<footer class="c-footer">
<div class="c-footer-grid">
<div>
<a class="c-brand c-brand-footer" href="<?= e_attr($basePath) ?>/">
<?php if (!empty($brandLogoUrl)): ?><span class="c-footer-logo-frame"><img class="c-brand-logo" src="<?= e_attr($brandLogoUrl) ?>" alt="<?= e_attr($siteName) ?>"></span><?php else: ?>
<span class="c-brand-name"><?= e($siteName) ?></span><span class="c-brand-kind">Catering</span>
<?php endif; ?>
</a>
<p><?= e($tagline) ?> Thoughtful food and gracious hospitality for weddings, corporate events, and private celebrations.</p>
</div>
<div class="c-footer-links"><h2>Explore</h2><?php $renderFooterNavigation($footerNavigation ?? []); ?></div>
<div><h2>Get in touch</h2><a href="tel:<?= e_attr((string) preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a><a href="mailto:<?= e_attr($contactEmail) ?>"><?= e($contactEmail) ?></a><p>Consultations by appointment.</p></div>
</div>
<div class="c-footer-bottom"><span><?= e($copyrightNotice) ?></span><span>Demo content should be replaced before launch.</span></div>
</footer>
<script src="<?= e_attr($asset . '/assets/js/catering.js&v=' . $assetVersion) ?>" defer></script>
</body>
</html>
