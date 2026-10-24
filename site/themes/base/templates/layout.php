<?php
/** @var string $content @var string $siteName @var string $tagline @var string $basePath @var array $pages @var array|null $page @var array $footerNavigation @var string $copyrightNotice */
$brandLogoUrl = $brandLogoUrl ?? null;
$faviconUrl = $faviconUrl ?? null;
$socialImageUrl = $socialImageUrl ?? null;
$currentUrl = $currentUrl ?? '';
$organization = is_array($organization ?? null) ? $organization : [];
$designTokens = array_replace(
    ['primary' => '#0369a1', 'accent' => '#d97706', 'radius' => 8],
    is_array($designTokens ?? null) ? $designTokens : []
);
?>
<!doctype html>
<html lang="<?= e_attr(\Core\I18n\Translator::instance()->langTag()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($page['meta_title'] ?? $page['title'] ?? '') !== '' ? ($page['meta_title'] ?? $page['title']) . ' — ' : '') ?><?= e($siteName) ?></title>
<?php if (!empty($page['meta_description'])): ?>
<meta name="description" content="<?= e_attr($page['meta_description']) ?>">
<?php elseif ($tagline !== ''): ?>
<meta name="description" content="<?= e_attr($tagline) ?>">
<?php endif; ?>
<meta property="og:type" content="website"><meta property="og:url" content="<?= e_attr($currentUrl) ?>">
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
<?php foreach (($languageLinks ?? []) as $language): ?><link rel="alternate" hreflang="<?= e_attr($language['locale']) ?>" href="<?= e_attr($language['url']) ?>"><?php endforeach; ?>
<link rel="stylesheet" href="<?= e_attr($basePath . '/index.php?p=/_a/themes/base/assets/css/style.css&v=' . rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development')) ?>">
<style>:root{--color-accent:<?= e_attr($designTokens['primary']) ?>;--color-highlight:<?= e_attr($designTokens['accent']) ?>;--radius:<?= (int) $designTokens['radius'] ?>px}</style>
<?php
$schema = ['@context' => 'https://schema.org', '@type' => 'Organization'] + array_filter($organization);
if (!empty($brandLogoUrl)) $schema['logo'] = $brandLogoUrl;
?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php if (!empty($page['schema_json']) && is_array($pageSchema = json_decode((string) $page['schema_json'], true))): ?><script type="application/ld+json"><?= json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script><?php endif; ?>
</head>
<body class="header-<?= e_attr($designTokens['header_layout'] ?? 'classic') ?> footer-<?= e_attr($designTokens['footer_layout'] ?? 'centered') ?>">
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
        $target = (string) ($nav['target'] ?? 'self');
        $attrs = $target === 'tab' ? ' target="_blank" rel="noopener noreferrer"'
            : ($target === 'window' ? ' target="wc-nav-window" rel="noopener noreferrer" data-open-window' : '');
        echo '<li class="nav-item"><a href="' . e_url($href) . '"' . $attrs . '>' . e($label) . '</a>';
        if (!empty($nav['children'])) $renderMenu($nav['children'], true);
        echo '</li>';
    }
    echo '</ul>';
};
$renderMenu($pages ?? []);
?>
</nav>
<?php if (!empty($languageLinks)): ?><nav class="language-switcher" aria-label="Languages"><?php foreach($languageLinks as $language): ?><a href="<?= e_attr($language['url']) ?>" lang="<?= e_attr($language['locale']) ?>" <?= ($currentLocale??null)===$language['locale']?'aria-current="page"':'' ?>><?= e($language['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
</header>
<main<?= !empty($page['rendered_blocks']) ? ' class="builder-main"' : '' ?>>
<?= $content ?>
</main>
<footer class="site-footer">
<?php if (!empty($footerNavigation)): ?><nav class="footer-nav" aria-label="Footer"><?php $renderMenu($footerNavigation); ?></nav><?php endif; ?>
<p><?= e($copyrightNotice ?? ('© ' . date('Y') . ' ' . $siteName . '. All rights reserved.')) ?></p>
</footer>
<script>document.querySelectorAll('[data-open-window]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();window.open(link.href,'wc-nav-window','noopener,noreferrer,width=1200,height=800,resizable=yes,scrollbars=yes');}));</script>
</body>
</html>
