<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$outputRoot = $argv[1] ?? '';
if ($outputRoot === '') {
    fwrite(STDERR, "Usage: php tools/render-theme-previews.php OUTPUT_DIRECTORY\n");
    exit(2);
}

require dirname(__DIR__) . '/tests/bootstrap.php';

$fixture = cateringFixture();
$collection = $fixture['ctx']->service(Core\Appearance\ModuleThemeCollections::class);
$themes = $collection->collections()['cajunwebpro/catering']['variants'] ?? [];

if (!is_dir($outputRoot) && !mkdir($outputRoot, 0750, true) && !is_dir($outputRoot)) {
    throw new RuntimeException('Unable to create preview output directory.');
}

foreach ($themes as $themeId => $theme) {
    $paletteId = (string) array_key_first($theme['palettes'] ?? []);
    [$activated, $error] = $collection->activate('cajunwebpro/catering', (string) $themeId, $paletteId);
    if (!$activated || $error !== null) {
        throw new RuntimeException('Unable to activate ' . $themeId . ': ' . ($error ?? 'unknown error'));
    }

    // Save real public HTML for every principal route rather than only
    // Home. This proves that each theme REPLACES the original route hero.
    $pages = [
        'index' => ['home', '/'],
        'services' => ['services', '/services'],
        'menus' => ['menus', '/menus'],
        'packages' => ['packages', '/packages'],
        'about' => ['about', '/about'],
        'gallery' => ['gallery', '/gallery'],
        'faq' => ['faq', '/faq'],
        'contact' => ['contact', '/contact'],
    ];
    $directory = rtrim($outputRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $themeId;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create preview directory for ' . $themeId . '.');
    }

    foreach ($pages as $file => [$method, $route]) {
        $html = $fixture['controller']->{$method}(cateringRequest('GET', $route))->body();
        $html = preg_replace_callback(
            '/(href|src)="\/catering\/index\.php\?p=\/_a\/modules\/industry-catering\/([^"&]+)(?:&amp;v=[^"]+)?"/',
            static fn (array $match): string => $match[1] . '="../' . $match[2] . '"',
            $html,
        ) ?? $html;
        // Strip the module route prefix only: the remaining path already
        // starts with assets/. Adding assets/ here would duplicate it.
        // CSS url() substitutions can resolve relative to the stylesheet
        // containing var(...), not the source <style> declaration. Use an
        // absolute preview asset path for both quote styles to avoid
        // /assets/assets/ double-prefixes and missing Grand Wedding photos.
        $html = str_replace(
            [
                'url("/catering/index.php?p=/_a/modules/industry-catering/',
                "url('/catering/index.php?p=/_a/modules/industry-catering/",
                'url("../assets/',
                "url('../assets/",
            ],
            [
                'url("/',
                "url('/",
                'url("/assets/',
                "url('/assets/",
            ],
            $html,
        );
        // Normalize every static preview asset reference, not only CSS
        // url() fragments. A relative URL stored in a custom property can
        // be resolved from the *using* stylesheet, giving /assets/assets/.
        $html = str_replace('../assets/', '/assets/', $html);
        if (str_contains($html, '/assets/assets/')) {
            $at = strpos($html, '/assets/assets/');
            throw new RuntimeException('Duplicated preview asset URL ' . $themeId . '/' . $file . ': ' . substr($html, max(0, $at - 60), 150));
        }
        if (file_put_contents($directory . '/' . $file . '.html', $html) === false) {
            throw new RuntimeException('Unable to write preview for ' . $themeId . '/' . $file);
        }
    }
}

echo 'Rendered ' . count($themes) . " theme previews.\n";
