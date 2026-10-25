<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php sdk/package.php <package-dir> <output.zip>\n");
    exit(2);
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The zip extension is required.\n");
    exit(2);
}
$source = realpath($argv[1]);
$output = $argv[2];
if ($source === false || (!is_file($source . '/addon.json') && !is_file($source . '/theme.json'))) {
    fwrite(STDERR, "Package directory or manifest not found.\n");
    exit(2);
}
if (strtolower(pathinfo($output, PATHINFO_EXTENSION)) !== 'zip') {
    fwrite(STDERR, "Output must use a .zip extension.\n");
    exit(2);
}
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create output archive.\n");
    exit(1);
}
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
    if (strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'zip') {
        $zip->close(); @unlink($output);
        fwrite(STDERR, "Nested ZIP files are forbidden: {$relative}\n");
        exit(1);
    }
    $zip->addFile($file->getPathname(), $relative);
}
$zip->close();
fwrite(STDOUT, "Built directly installable package: {$output}\n");
