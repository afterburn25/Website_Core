<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

/** Shared safe primitives; each theme template still owns a distinct document composition. */
final class ThemeSupport
{
    public static function head(array $v): string
    {
        $page = is_array($v['page'] ?? null) ? $v['page'] : [];
        $siteName = (string) ($v['siteName'] ?? 'Catering Co.');
        $title = ($page['title'] ?? '') !== 'Home' ? (string) ($page['title'] ?? '') . ' — ' . $siteName : $siteName;
        $asset = (string) ($v['assetBase'] ?? '');
        $version = rawurlencode((defined('WC_VERSION') ? WC_VERSION : 'development') . '-' . (string) ($v['moduleVersion'] ?? '2.0.0'));
        $style = '';
        foreach ((array) ($v['themeTokens'] ?? []) as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_-]*$/', (string) $key) && preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) {
                $style .= '--theme-' . $key . ':' . strtolower((string) $value) . ';';
            }
        }
        foreach (['hero', 'feature', 'accent'] as $slot) {
            $url = (string) (($v['themeMedia'] ?? [])[$slot] ?? '');
            if ($url !== '') {
                $encoded = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                if (is_string($encoded)) {
                    $style .= '--theme-image-' . $slot . ':url(' . $encoded . ');';
                }
            }
        }
        return '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . self::h($title) . '</title><meta name="description" content="' . self::a((string) ($page['meta_description'] ?? '')) . '">'
            . (!empty($page['noindex']) ? '<meta name="robots" content="noindex,nofollow">' : '')
            . (!empty($v['faviconUrl']) ? '<link rel="icon" href="' . self::a((string) $v['faviconUrl']) . '">' : '')
            . '<link rel="stylesheet" href="' . self::a($asset . '/assets/css/catering.css&v=' . $version) . '">'
            . '<link rel="stylesheet" href="' . self::a($asset . '/assets/css/themes.css&v=' . $version) . '">'
            . ($style !== '' ? '<style>:root{' . $style . '}</style>' : '');
    }

    public static function brand(array $v, string $class = 'c-brand'): string
    {
        $home = (string) ($v['basePath'] ?? '') . '/';
        $name = (string) ($v['siteName'] ?? 'Catering Co.');
        $inside = !empty($v['brandLogoUrl'])
            ? '<img class="c-brand-logo" src="' . self::a((string) $v['brandLogoUrl']) . '" alt="' . self::a($name) . '">'
            : '<span class="c-brand-name">' . self::h($name) . '</span><span class="c-brand-kind">Catering</span>';
        return '<a class="' . self::a($class) . '" href="' . self::a($home) . '" aria-label="' . self::a($name) . ' home">' . $inside . '</a>';
    }

    public static function navigation(array $items, array $v, string $class = 'c-nav-root'): string
    {
        $html = '<ul class="' . self::a($class) . '">';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $url = (string) ($item['url'] ?? '/');
            $href = str_starts_with($url, '/') ? (string) ($v['basePath'] ?? '') . $url : $url;
            $current = (string) ($v['currentPath'] ?? '/');
            $active = str_starts_with($url, '/') && ($current === $url || ($url !== '/' && str_starts_with($current, rtrim($url, '/') . '/')));
            $target = (string) ($item['target'] ?? 'self');
            $attrs = $target === 'tab' ? ' target="_blank" rel="noopener noreferrer"'
                : ($target === 'window' ? ' target="wc-nav-window" rel="noopener noreferrer" data-open-window' : '');
            $html .= '<li class="c-nav-item"><a href="' . self::a($href) . '"' . ($active ? ' aria-current="page"' : '') . $attrs . '>'
                . self::h((string) ($item['label'] ?? '')) . '</a>';
            if (!empty($item['children']) && is_array($item['children'])) {
                $html .= self::navigation($item['children'], $v, 'c-nav-children');
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }

    public static function social(array $items, string $class, string $label): string
    {
        if ($items === []) return '';
        $html = '<nav class="' . self::a($class) . '" aria-label="' . self::a($label) . '">';
        foreach ($items as $social) {
            if (!is_array($social)) continue;
            $html .= '<a href="' . self::a((string) ($social['url'] ?? '')) . '" target="_blank" rel="noopener noreferrer" aria-label="'
                . self::a((string) ($social['label'] ?? 'Social profile')) . '">'
                . \Core\Branding\SocialIcon::svg((string) ($social['platform'] ?? '')) . '</a>';
        }
        return $html . '</nav>';
    }

    public static function cta(array $v, string $heading = 'Let’s create something beautiful together.'): string
    {
        return '<section class="c-final-cta" aria-labelledby="theme-final-cta"><div><p class="c-eyebrow c-eyebrow-light">Your gathering starts here</p><h2 id="theme-final-cta">'
            . self::h($heading) . '</h2><p>Share a few details and we’ll help shape the menu, service, and experience.</p>'
            . self::action($v, 'Request a Quote', 'c-button c-button-gold') . '</div></section>';
    }

    /** Render the mode-aware public action without making the module own checkout. */
    public static function action(array $v, string $quoteLabel, string $class = ''): string
    {
        $mode = (string) ($v['orderingMode'] ?? 'quote_only');
        $quote = (string) ($v['basePath'] ?? '') . '/request-a-quote';
        $store = (string) ($v['storeUrl'] ?? '');
        $attribute = $class !== '' ? ' class="' . self::a($class) . '"' : '';
        if ($mode === 'direct_order' && $store !== '') {
            return '<a' . $attribute . ' href="' . self::a($store) . '">Order online</a>';
        }
        $html = '<a' . $attribute . ' href="' . self::a($quote) . '">' . self::h($quoteLabel) . '</a>';
        if ($mode === 'mixed' && $store !== '') {
            $html .= '<a' . $attribute . ' href="' . self::a($store) . '">Order online</a>';
        }
        return $html;
    }

    public static function scripts(array $v): string
    {
        $version = rawurlencode((defined('WC_VERSION') ? WC_VERSION : 'development') . '-' . (string) ($v['moduleVersion'] ?? '2.0.0'));
        return '<script src="' . self::a((string) ($v['assetBase'] ?? '') . '/assets/js/catering.js&v=' . $version) . '" defer></script>';
    }

    public static function media(array $v, string $slot): string
    {
        return self::a((string) (($v['themeMedia'] ?? [])[$slot] ?? ''));
    }

    public static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function a(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
