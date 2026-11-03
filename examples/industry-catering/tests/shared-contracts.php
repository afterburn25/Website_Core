<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$cateringRoot = dirname(__DIR__);
$landscapingRoot = getenv('CATERING_LANDSCAPING_ROOT');
if (!$landscapingRoot || !is_file($landscapingRoot . '/addon.json')) {
    throw new RuntimeException('Set CATERING_LANDSCAPING_ROOT to the pinned Landscaping module checkout.');
}
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$catering = json_decode((string) file_get_contents($cateringRoot . '/addon.json'), true, 512, JSON_THROW_ON_ERROR);
$landscaping = json_decode((string) file_get_contents($landscapingRoot . '/addon.json'), true, 512, JSON_THROW_ON_ERROR);
$check($catering['id'] === 'cajunwebpro/catering' && $landscaping['id'] === 'cajunwebpro/landscaping', 'Industry packages retain distinct IDs');
$check($catering['type'] === 'industry' && $landscaping['type'] === 'industry', 'Both packages use the industry package contract');
$check((int) $catering['requires']['api'] === 1 && (int) $landscaping['requires']['api'] === 1, 'Both packages target Module API 1');
$check($catering['provider'] !== $landscaping['provider'] && array_key_first($catering['psr4']) !== array_key_first($landscaping['psr4']),
    'Providers and autoload namespaces remain isolated');

$requiredCalls = ['navigationMenu(', 'brandAssets(', 'adminRoute(', 'permission(', 'healthCheck('];
foreach ([$cateringRoot, $landscapingRoot] as $root) {
    $provider = (string) file_get_contents($root . '/src/Provider.php');
    foreach ($requiredCalls as $call) $check(str_contains($provider, '->' . $call), basename($root) . ' declares ' . $call);
}

$readPhp = static function (string $root): string {
    $source = '';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile() && $file->getExtension() === 'php') $source .= (string) file_get_contents($file->getPathname());
    return $source;
};
$cateringSource = $readPhp($cateringRoot . '/migrations');
$landscapingSource = $readPhp($landscapingRoot . '/migrations');
$check(!str_contains($cateringSource, 'landscaping_'), 'Catering migrations never own Landscaping tables');
$check(!str_contains($landscapingSource, 'catering_'), 'Landscaping migrations never own Catering tables');
$check(!str_contains($readPhp($cateringRoot . '/src'), 'CajunWebPro\\Landscaping'), 'Catering source has no Landscaping dependency');
$check(!str_contains($readPhp($landscapingRoot . '/src'), 'CajunWebPro\\Catering'), 'Landscaping source has no Catering dependency');
echo "Cross-module shared contracts: {$checks} checks passed.\n";
