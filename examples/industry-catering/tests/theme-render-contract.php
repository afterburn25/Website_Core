<?php
declare(strict_types=1);

// Exercise the real packaged Catering controllers and all 10 Core theme
// variants: a theme must REPLACE the old page hero, not add another H1.
require __DIR__ . '/bootstrap.php';

$fixture = cateringFixture();
$collection = $fixture['ctx']->service(Core\Appearance\ModuleThemeCollections::class);
$themes = $collection->collections()['cajunwebpro/catering']['variants'] ?? [];
if (count($themes) !== 10) {
    throw new RuntimeException('Expected exactly ten Catering themes.');
}

$routes = [
    'home'     => ['/', 'home', 'c-hero-home'],
    'services' => ['/services', 'services', 'c-page-hero-services'],
    'menus'    => ['/menus', 'menus', 'c-page-hero-menu'],
    'packages' => ['/packages', 'packages', 'c-page-hero-packages'],
    'about'    => ['/about', 'about', 'c-page-hero-about'],
    'gallery'  => ['/gallery', 'gallery', 'c-page-title-centered'],
    'faq'      => ['/faq', 'faq', 'c-page-title-centered'],
    'contact'  => ['/contact', 'contact', null],
];
$checks = 0;
foreach ($themes as $themeId => $theme) {
    $paletteId = (string) array_key_first($theme['palettes'] ?? []);
    [$ok, $error] = $collection->activate('cajunwebpro/catering', (string) $themeId, $paletteId);
    if (!$ok || $error !== null) {
        throw new RuntimeException('Cannot activate theme '.$themeId.': '.($error ?? 'unknown'));
    }

    $images = [];
    foreach ($routes as $template => [$path, $method, $legacyClass]) {
        $html = $fixture['controller']->{$method}(cateringRequest('GET', $path))->body();
        $count = preg_match_all('/<h1\b/i', $html);
        if ($count !== 1) {
            throw new RuntimeException($themeId.' / '.$template.': expected exactly one H1, got '.$count);
        }
        if ($legacyClass !== null && str_contains($html, $legacyClass)) {
            throw new RuntimeException($themeId.' / '.$template.': theme stacked the original page hero');
        }
        if (!str_contains($html, 'data-site-header') || !str_contains($html, 'data-nav')) {
            throw new RuntimeException($themeId.' / '.$template.': theme header/nav missing');
        }
        $hero = CajunWebPro\Catering\ThemeSupport::heroImage([
            'pageTemplate' => $template,
            'assetBase' => '/catering/asset',
            'themeMedia' => [
                'hero' => '/media/1/home.webp',
                'feature' => '/media/2/services.webp',
                'accent' => '/media/3/menus.webp',
            ],
        ]);
        if (in_array($hero, $images, true)) {
            throw new RuntimeException($themeId.' / '.$template.': duplicated route hero');
        }
        $images[] = $hero;
        $checks++;
    }
}
echo "Catering themed render contract: {$checks} routes have one H1, no stacked legacy hero, unique route imagery.\n";
