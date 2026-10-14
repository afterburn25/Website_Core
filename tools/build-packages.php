<?php
declare(strict_types=1);

/**
 * Build the release artifacts from the working tree:
 *
 *   Website-Core-VERSION-FULL-PROJECT.zip    source + docs + tests + sdk +
 *                                            examples + CI (no .git, vendor,
 *                                            secrets, or runtime debris)
 *   Website-Core-VERSION-COMPLETE-UPLOAD.zip deployable site/ contents +
 *                                            runtime vendor deps — extract
 *                                            straight into the web dir, no
 *                                            enclosing wrapper
 *   Website-Core-VERSION-UPDATE.zip          same deployable set; updating a
 *                                            site = extract over it (config,
 *                                            storage, upload, installed
 *                                            modules/themes are not members)
 *   Website-Core-Package-NAME-VERSION.zip    one per reference example
 *   SHA256SUMS.txt                           checksum manifest for all zips
 *
 * Usage: php tools/build-packages.php [outdir]
 * Run from a clean commit for release builds.
 */

$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/dist';
@mkdir($out, 0777, true);

$version = '0.0.0-dev';
if (is_file($root . '/site/includes/bootstrap.php')) {
    $b = file_get_contents($root . '/site/includes/bootstrap.php');
    if (preg_match("/define\\('WC_VERSION', '([^']+)'\\)/", $b, $m)) {
        $version = $m[1];
    }
}
$ver = preg_replace('/[^a-zA-Z0-9.\-]+/', '-', $version);
echo "version={$ver}\n";

$sums = [];

/**
 * Zip a set of "source => zip-path" files.
 * $map callback: fn(relativeSourcePath): ?string target name or null to skip.
 */
function buildZip(string $zipPath, string $base, callable $map, array $extra = [], bool $append = false): int
{
    $zip = new ZipArchive();
    $flags = $append ? ZipArchive::CREATE : (ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zip->open($zipPath, $flags) !== true) {
        fwrite(STDERR, "cannot create {$zipPath}\n");
        exit(1);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    $count = 0;
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
        $target = $map($rel);
        if ($target === null) {
            continue;
        }
        $zip->addFile($f->getPathname(), $target);
        $count++;
    }
    foreach ($extra as $target => $sourcePath) {
        $zip->addFile($sourcePath, $target);
        $count++;
    }
    $zip->close();
    return $count;
}

$skipRuntime = fn (string $rel): bool =>
    str_starts_with($rel, 'config/config.php')
    || str_starts_with($rel, 'config/installed.lock')
    || str_starts_with($rel, 'storage/')      // runtime data; keep only guards
    || str_starts_with($rel, 'upload/') && !preg_match('#^upload/\.(htaccess|gitkeep|html)#', $rel)
    || str_contains($rel, '/.git/')
    || str_ends_with($rel, '.ser');           // purifier cache

// --- production vendor: clean `composer install --no-dev` in a temp dir --
$tmpVendor = sys_get_temp_dir() . '/wc-build-' . getmypid();
@mkdir($tmpVendor, 0777, true);
copy($root . '/composer.json', $tmpVendor . '/composer.json');
copy($root . '/composer.lock', $tmpVendor . '/composer.lock');
$code = 1;
$cout = [];
$candidates = ['composer', 'composer.bat', 'composer.phar', getenv('HOME') . '/bin/composer'];
foreach ($candidates as $composer) {
    // Composer is distributed as a PHP phar; invoking it through PHP_BINARY
    // works whether the entry point is a shim, bat wrapper, or bare phar.
    $cmd = sprintf(
        '"%s" "%s" install --no-dev --no-scripts --no-plugins --prefer-dist --no-interaction --working-dir=%s 2>&1',
        PHP_BINARY,
        $composer,
        escapeshellarg($tmpVendor)
    );
    exec($cmd, $cout, $code);
    if ($code === 0) {
        break;
    }
}
if ($code !== 0 || !is_dir($tmpVendor . '/vendor')) {
    fwrite(STDERR, "composer install --no-dev failed:\n" . implode("\n", $cout) . "\n");
    exit(1);
}
echo "prod vendor: " . count($cout) . " lines from composer\n";

// --- COMPLETE-UPLOAD: site/ at archive root + runtime vendor -------------
$upload = "{$out}/Website-Core-{$ver}-COMPLETE-UPLOAD.zip";
$n = buildZip($upload, $root . '/site', function (string $rel) use ($skipRuntime) {
    if ($skipRuntime($rel)) {
        return null;
    }
    return $rel;
});
$n += buildZip($upload, $tmpVendor . '/vendor', fn (string $rel) => 'vendor/' . $rel, [], true);
echo "COMPLETE-UPLOAD {$n} members\n";
$sums[] = $upload;

// --- UPDATE: identical deployable payload (update path = overwrite) ------
$update = "{$out}/Website-Core-{$ver}-UPDATE.zip";
copy($upload, $update);
$sums[] = $update;

// --- FULL-PROJECT: repo minus .git/vendor/dist/runtime -------------------
$full = "{$out}/Website-Core-{$ver}-FULL-PROJECT.zip";
$n = buildZip($full, $root, function (string $rel) {
    if (str_starts_with($rel, '.git/')
        || str_starts_with($rel, 'vendor/')
        || str_starts_with($rel, 'dist/')
        || str_starts_with($rel, 'site/config/config.php')
        || str_starts_with($rel, 'site/config/installed.lock')
        || str_starts_with($rel, 'site/storage/') && !str_ends_with($rel, '.gitkeep') && !str_ends_with($rel, '.htaccess')
        || str_starts_with($rel, 'site/upload/') && !preg_match('#^site/upload/\.(htaccess|gitkeep|html)#', $rel)
    ) {
        return null;
    }
    return 'Website-Core/' . $rel;
});
echo "FULL-PROJECT {$n} members\n";
$sums[] = $full;

// --- Reference packages ---------------------------------------------------
foreach (glob($root . '/examples/*', GLOB_ONLYDIR) as $dir) {
    $name = basename($dir);
    $pkgManifest = is_file($dir . '/addon.json') ? $dir . '/addon.json'
        : (is_file($dir . '/theme.json') ? $dir . '/theme.json' : null);
    $pv = '0.0.0';
    $inner = $name;
    if ($pkgManifest) {
        $m = json_decode((string) file_get_contents($pkgManifest), true);
        $pv = $m['version'] ?? $pv;
        $inner = explode('/', (string) ($m['id'] ?? "x/{$name}"))[1] ?? $name;
    }
    $zipPath = "{$out}/Website-Core-Package-{$inner}-{$pv}.zip";
    $n = buildZip($zipPath, $dir, fn (string $rel) => $inner . '/' . $rel);
    echo "PACKAGE {$inner} {$n} members\n";
    $sums[] = $zipPath;
}

// --- Checksums ------------------------------------------------------------
$lines = '';
foreach ($sums as $p) {
    $lines .= hash_file('sha256', $p) . '  ' . basename($p) . "\n";
}
file_put_contents($out . '/SHA256SUMS.txt', $lines);
echo "wrote " . count($sums) . " artifacts + SHA256SUMS.txt to {$out}\n";
