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

    $html = $fixture['controller']->home(cateringRequest('GET', '/'))->body();
    $html = preg_replace_callback(
        '/(href|src)="\/catering\/index\.php\?p=\/_a\/modules\/industry-catering\/([^"&]+)(?:&amp;v=[^"]+)?"/',
        static fn (array $match): string => $match[1] . '="../' . $match[2] . '"',
        $html,
    ) ?? $html;
    $html = str_replace(
        'url("/catering/index.php?p=/_a/modules/industry-catering/',
        'url("../',
        $html,
    );

    $directory = rtrim($outputRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $themeId;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create preview directory for ' . $themeId . '.');
    }
    if (file_put_contents($directory . '/index.html', $html) === false) {
        throw new RuntimeException('Unable to write preview for ' . $themeId . '.');
    }
}

echo 'Rendered ' . count($themes) . " theme previews.\n";
