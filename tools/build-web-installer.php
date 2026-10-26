<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php tools/build-web-installer.php <TEST-BUNDLE.zip> <output.php>\n");
    exit(1);
}

$root = dirname(__DIR__);
$payloadPath = (string) $argv[1];
$outputPath = (string) $argv[2];
$stubPath = __DIR__ . '/web-installer.php.stub';
$logoPath = $root . '/site/assets/images/cajunwebpro-logo.png';

foreach ([$payloadPath, $stubPath, $logoPath] as $required) {
    if (!is_file($required)) {
        fwrite(STDERR, "missing build input: {$required}\n");
        exit(1);
    }
}

$payload = file_get_contents($payloadPath);
$stub = file_get_contents($stubPath);
$logo = file_get_contents($logoPath);
if ($payload === false || $stub === false || $logo === false) {
    fwrite(STDERR, "could not read web-installer build inputs\n");
    exit(1);
}

$version = '0.0.0-dev';
if (preg_match('/Website-Core-catering-([a-zA-Z0-9.\-]+)-TEST-BUNDLE\.zip$/', basename($payloadPath), $match)) {
    $version = $match[1];
}
$stub = str_replace(
    ['__WC_VERSION__', '__WC_PAYLOAD_BYTES__', '__WC_PAYLOAD_SHA256__', '__WC_LOGO_DATA_URI__'],
    [
        var_export($version, true),
        (string) strlen($payload),
        var_export(hash('sha256', $payload), true),
        var_export('data:image/png;base64,' . base64_encode($logo), true),
    ],
    $stub
);
if (str_contains($stub, '__WC_')) {
    fwrite(STDERR, "unresolved web-installer template placeholder\n");
    exit(1);
}
if (file_put_contents($outputPath, rtrim($stub) . $payload, LOCK_EX) === false) {
    fwrite(STDERR, "could not write {$outputPath}\n");
    exit(1);
}
echo "created {$outputPath} (" . filesize($outputPath) . " bytes)\n";
