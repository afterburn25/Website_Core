<?php
declare(strict_types=1);

// Dependency-free module contract test: the selected theme owns one hero,
// distinct from the legacy page hero and with a different photograph per
// principal destination. No customer data, database, or web server required.
require_once dirname(__DIR__) . '/src/ThemeSupport.php';

use CajunWebPro\Catering\ThemeSupport;

$pages = [
    'home', 'services', 'menus', 'about', 'packages',
    'gallery', 'contact', 'quote', 'faq', 'venues',
    'event-stories', 'service-area', 'service-detail',
    'menu-detail', 'package-detail',
];
$seen = [];
foreach ($pages as $template) {
    $v = [
        'pageTemplate' => $template,
        'assetBase' => '/catering/module-assets?module=cajunwebpro/catering',
        'themeMedia' => [
            'hero' => '/catering/media/1/w/1800/hero.webp',
            'feature' => '/catering/media/2/w/1800/services.webp',
            'accent' => '/catering/media/3/w/1800/menus.webp',
        ],
        'page' => ['title' => 'Illustrative ' . $template],
        'pageHeroSource' => $template === 'service-detail' ? 'hero.webp' : '',
    ];
    $image = html_entity_decode(ThemeSupport::heroImage($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = ThemeSupport::heroTitle($v);
    $eyebrow = ThemeSupport::heroEyebrow($v);
    if ($image === '' || $title === '' || $eyebrow === '') {
        throw new RuntimeException('Missing route hero data: ' . $template);
    }
    if (isset($seen[$image])) {
        throw new RuntimeException('Different pages must not reuse their default hero: ' . $seen[$image] . ' and ' . $template);
    }
    $seen[$image] = $template;
}
$service = [
    'pageTemplate' => 'service-detail',
    'assetBase' => '/catering/module-assets?module=cajunwebpro/catering',
    'page' => ['title' => '<Private&Event>'],
    'pageHeroSource' => 'wedding-table.webp',
];
if (!str_contains(ThemeSupport::heroTitle($service), '&lt;Private&amp;Event&gt;')) {
    throw new RuntimeException('Page hero title must escape dynamic subject text.');
}
if (!str_contains(ThemeSupport::heroImage($service), 'wedding-table.webp')) {
    throw new RuntimeException('A service detail must receive its own bundled service photograph.');
}
echo 'Catering route hero contract passed: ' . count($pages) . " page templates, unique default photos, escaped titles.\n";
