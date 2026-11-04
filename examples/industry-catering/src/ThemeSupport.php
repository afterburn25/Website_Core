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

    /**
     * Exactly one hero belongs to the selected theme on every page.
     * Existing page templates suppress their old top-level hero; the theme
     * supplies the subject, unique photograph, and primary H1 instead.
     * Hero subjects follow the current route, not the theme's homepage text.
     *
     * @return array{eyebrow:string,title:string,description:string}
     */
    private static function pageHeroText(array $v): array
    {
        $page = (string) ($v['pageTemplate'] ?? 'home');
        $title = trim((string) (($v['page'] ?? [])['title'] ?? ''));
        $defaults = [
            'home' => ['Catering for every occasion', 'Gather beautifully.', 'Thoughtful menus and gracious service for your most meaningful gatherings.'],
            'services' => ['Made for every gathering', 'Catering experiences, beautifully considered.', 'Explore full-service events, intimate celebrations, and effortless delivery.'],
            'menus' => ['Seasonal, personal, memorable', 'Menus that bring people together.', 'Make the menu yours with signature dishes and thoughtful selections.'],
            'packages' => ['Plan with confidence', 'Catering packages made for your occasion.', 'Start with a curated package and tailor every detail.'],
            'about' => ['Our story', 'Hospitality lives in the details.', 'The people, ingredients, and care behind every gathering.'],
            'gallery' => ['Moments around the table', 'Gatherings worth remembering.', 'See what considered hospitality looks like.'],
            'contact' => ['Start a conversation', 'Tell us what you are planning.', 'We would love to hear about your event.'],
            'quote' => ['Plan your gathering', 'A menu made for your occasion.', 'Choose your dishes and share the details of your celebration.'],
            'faq' => ['Good to know', 'Frequently asked questions.', 'Helpful answers for a more effortless event.'],
            'venues' => ['Preferred places', 'Venue partners.', 'Find a place that fits the moment.'],
            'event-stories' => ['Real gatherings', 'Event stories.', 'Thoughtful menus and memorable outcomes.'],
            'service-area' => ['Where we serve', 'Catering that comes to you.', 'Delivery, pickup, and full-service events in your area.'],
            'service-detail' => ['Made for your moment', $title !== '' ? $title : 'Personalized catering.', 'Beautifully prepared food and attentive service for your event.'],
            'menu-detail' => ['Explore the menu', $title !== '' ? $title : 'Our menu.', 'Discover dishes that fit your occasion.'],
            'package-detail' => ['Personalize your event', $title !== '' ? $title : 'Your catering package.', 'A starting point that you can make your own.'],
        ];
        $copy = $defaults[$page] ?? ['Catering, considered', $title !== '' ? $title : 'Gather beautifully.', 'Let us help plan your next event.'];
        return ['eyebrow' => $copy[0], 'title' => $copy[1], 'description' => $copy[2]];
    }

    public static function heroEyebrow(array $v): string
    {
        return self::h(self::pageHeroText($v)['eyebrow']);
    }

    public static function heroTitle(array $v): string
    {
        return self::h(self::pageHeroText($v)['title']);
    }

    public static function heroDescription(array $v): string
    {
        return self::h(self::pageHeroText($v)['description']);
    }

    /**
     * Give every public route a distinct default hero photograph. Homepage,
     * Services and Menus respect the existing Core theme Media selections;
     * other routes use unique bundled photographs instead of repeating
     * the theme's home hero on every page. No extra image is rendered.
     */
    public static function heroImage(array $v): string
    {
        $page = (string) ($v['pageTemplate'] ?? 'home');
        $slots = ['home' => 'hero', 'services' => 'feature', 'menus' => 'accent'];
        if (isset($slots[$page])) {
            $url = (string) (($v['themeMedia'] ?? [])[$slots[$page]] ?? '');
            if ($url !== '') return self::a($url);
        }

        $photos = [
            'about' => 'assets/images/corporate-service.webp',
            'packages' => 'assets/images/wedding-table.webp',
            'gallery' => 'assets/images/plated-dinner.webp',
            'contact' => 'assets/images/menu/crawfish-bread.webp',
            'quote' => 'assets/images/menu/chicken-andouille-gumbo.webp',
            'faq' => 'assets/images/menu/boudin-balls.webp',
            'venues' => 'assets/images/menu/crawfish-bisque.webp',
            'event-stories' => 'assets/images/menu/shrimp-fettuccine.webp',
            'service-area' => 'assets/images/menu/cajun-jambalaya.webp',
            'menu-detail' => 'assets/images/menu/blackened-redfish.webp',
            'package-detail' => 'assets/images/menu/cajun-shrimp-grits-bites.webp',
            'service-detail' => 'assets/images/hero.webp',
        ];
        $file = $photos[$page] ?? 'assets/images/hero.webp';
        if ($page === 'service-detail') {
            $image = (string) ($v['pageHeroSource'] ?? '');
            if (preg_match('/^[a-z0-9-]+\\.webp$/', $image) === 1) {
                $file = 'assets/images/' . $image;
            }
        }
        return self::a(rtrim((string) ($v['assetBase'] ?? ''), '/') . '/' . $file);
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
