<?php
/** @var string $content @var string $siteName @var string $tagline @var string $basePath @var string $assetBase
 * @var array $navigation @var array $footerNavigation @var string|null $brandLogoUrl @var string $activeTheme
 * @var string $copyrightNotice @var array $headerSocialLinks @var array $footerSocialLinks */
$renderNav = function (array $items, bool $child = false) use (&$renderNav, $basePath, $currentPath): void {
    echo '<ul class="' . ($child ? 'l-subnav' : 'l-nav-list') . '">';
    foreach ($items as $item) {
        $url = (string) ($item['url'] ?? '/');
        $href = str_starts_with($url, '/') ? $basePath . $url : $url;
        $target=(string)($item['target']??'self'); $attrs=$target==='tab'?' target="_blank" rel="noopener noreferrer"':($target==='window'?' target="wc-nav-window" rel="noopener noreferrer" data-open-window':'');
        $active=str_starts_with($url,'/') && ($currentPath===$url || ($url!=='/' && str_starts_with($currentPath,rtrim($url,'/').'/')));
        echo '<li><a href="' . e_url($href) . '"'.($active?' aria-current="page"':'').$attrs.'>' . e((string) ($item['label'] ?? '')) . '</a>';
        if (!empty($item['children'])) $renderNav((array) $item['children'], true);
        echo '</li>';
    }
    echo '</ul>';
};
$themeCss = $basePath . '/_a/themes/' . rawurlencode($activeTheme) . '/assets/css/landscaping-theme.css';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(($page['title'] ?? '') !== 'Home' ? ($page['title'] ?? '') . ' — ' : '') ?><?= e($siteName) ?></title><meta name="description" content="<?= e_attr($page['meta_description'] ?? '') ?>">
<?php if (!empty($page['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?><?php if (!empty($faviconUrl)): ?><link rel="icon" href="<?= e_attr($faviconUrl) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e_attr($assetBase . '/assets/css/landscaping.css') ?>">
<?php if ($activeTheme !== 'landscaping-bold-outdoors'): ?><link rel="stylesheet" href="<?= e_attr($themeCss) ?>"><?php endif; ?></head>
<body class="l-theme <?= e_attr($activeTheme) ?>"><a class="l-skip" href="#main">Skip to content</a>
<header class="l-header"><a class="l-brand" href="<?= e_attr($basePath) ?>/"><?php if ($brandLogoUrl): ?><img src="<?= e_attr($brandLogoUrl) ?>" alt=""><?php endif; ?><strong><?= e($siteName) ?></strong></a><nav class="l-nav" aria-label="Primary"><?php $renderNav($navigation); ?></nav><a class="l-btn l-btn-primary l-header-cta" href="<?= e_attr($basePath) ?>/request-estimate">Free Estimate →</a><?php if ($headerSocialLinks): ?><nav class="l-social" aria-label="Social media"><?php foreach ($headerSocialLinks as $social): ?><a href="<?= e_attr($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e_attr($social['label']) ?>"><?= \Core\Branding\SocialIcon::svg((string) $social['platform']) ?></a><?php endforeach; ?></nav><?php endif; ?></header>
<main id="main"><?= $content ?></main>
<footer class="l-footer"><div><a class="l-brand" href="<?= e_attr($basePath) ?>/"><?php if ($brandLogoUrl): ?><img src="<?= e_attr($brandLogoUrl) ?>" alt=""><?php endif; ?><strong><?= e($siteName) ?></strong></a><p><?= e($tagline) ?></p><?php if ($footerSocialLinks): ?><nav class="l-social" aria-label="Social media"><?php foreach ($footerSocialLinks as $social): ?><a href="<?= e_attr($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e_attr($social['label']) ?>"><?= \Core\Branding\SocialIcon::svg((string) $social['platform']) ?></a><?php endforeach; ?></nav><?php endif; ?></div><nav aria-label="Footer"><?php $renderNav($footerNavigation); ?></nav><p><?= e($copyrightNotice) ?></p></footer>
</body></html>
