<?php
declare(strict_types=1);

// Syntax-lint every PHP file in the repo (php -l equivalent, parallel-safe).
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$fail = 0;
$count = 0;
foreach ($it as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $count++;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $fail++;
        echo implode("\n", $out), "\n";
    }
    $out = [];
}
echo "Linted {$count} files, {$fail} failure(s)\n";
exit($fail === 0 ? 0 : 1);
