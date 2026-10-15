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
 *        php tools/build-packages.php bundle <industry> <license_key> [outdir]
 *                                    Create a bundled package (core + industry module
 *                                    with license_key embedded in addon.json)
 * Run from a clean commit for release builds.
 */

$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/dist';

if ($argv[1] === 'bundle') {
    // php tools/build-packages.php bundle <industry> <license_key> [outdir]
    $industry = $argv[2] ?? null;
    $licenseKey = $argv[3] ?? null;
    $out = $argv[4] ?? $root . '/dist';
    @mkdir($out, 0777, true);
    if ($industry === null || $licenseKey === null) {
        fwrite(STDERR, "Usage: php tools/build-packages.php bundle <industry> <license_key> [outdir]\n");
        exit(1);
    }
    $version = '0.0.0-dev';
    if (is_file($root . '/site/includes/bootstrap.php')) {
        $b = file_get_contents($root . '/site/includes/bootstrap.php');
        if (preg_match("/define\\('WC_VERSION', '([^']+)'\\)/", $b, $m)) {
            $version = $m[1];
        }
    }
    $ver = preg_replace('/[^a-zA-Z0-9.\-]+/', '-', $version);
    echo "Building bundle for {$industry} with license_key={$licenseKey}\n";
    echo "version={$ver}\n";
    // Find the industry module in examples/
    $examples = glob($root . '/examples/industry-*', GLOB_ONLYDIR);
    $moduleDir = null;
    foreach ($examples as $dir) {
        if (basename($dir) === 'industry-' . $industry) {
            $moduleDir = $dir;
            break;
        }
    }
    if ($moduleDir === null) {
        fwrite(STDERR, "Industry module '{$industry}' not found in examples/\n");
        exit(1);
    }
    $moduleName = basename($moduleDir);
    // Build the bundle ZIP
    $bundleZip = "{$out}/Website-Core-{$industry}-{$ver}-BUNDLE.zip";
    $zip = new ZipArchive();
    if ($zip->open($bundleZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "cannot create {$bundleZip}\n");
        exit(1);
    }
    // Add core files
    $coreBase = $root . '/site';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($coreBase, FilesystemIterator::SKIP_DOTS)
    );
    $count = 0;
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($coreBase) + 1));
        // Skip secrets and runtime artifacts
        if (str_starts_with($rel, 'config/config.php')
            || str_starts_with($rel, 'config/installed.lock')
            || str_starts_with($rel, 'config/setup.token')
            || str_starts_with($rel, 'storage/')
            || str_starts_with($rel, 'upload/')
            || str_contains($rel, '.key')
            || str_contains($rel, '.pem')
        ) {
            continue;
        }
        $zip->addFile($path, 'site/' . $rel);
        $count++;
    }
    // Add module files with embedded license_key
    $moduleBase = $moduleDir;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleBase, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($moduleBase) + 1));
        // Embed license_key in addon.json
        if ($rel === 'addon.json') {
            $content = file_get_contents($path);
            $manifest = json_decode($content, true);
            $manifest['license_key'] = $licenseKey;
            $zip->addFromString('site/modules/industries/' . $moduleName . '/addon.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $zip->addFile($path, 'site/modules/industries/' . $moduleName . '/' . $rel);
        }
        $count++;
    }
    $zip->close();
    echo "Bundle created: {$bundleZip} ({$count} files)\n";
    exit(0);
}

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
$args = 'install --no-dev --no-scripts --no-plugins --prefer-dist --no-interaction --working-dir='
    . escapeshellarg($tmpVendor) . ' 2>&1';
// Composer may be a real executable on PATH, a bat wrapper, or a bare PHP
// phar (the ~/bin shim on Windows). Try the executable form first, then
// drive the same candidates through PHP_BINARY for phar entry points.
$cmds = [
    'composer ' . $args,
    'composer.bat ' . $args,
    '"' . PHP_BINARY . '" composer ' . $args,
    '"' . PHP_BINARY . '" ' . escapeshellarg((getenv('HOME') ?: '') . '/bin/composer') . ' ' . $args,
];
foreach ($cmds as $cmd) {
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

// --- UPDATE: deployable payload + update.json manifest -------------------
$update = "{$out}/Website-Core-{$ver}-UPDATE.zip";
copy($upload, $update);
// Manifest: sha256 of every member, so CoreUpdater can integrity-check the
// payload; optionally Ed25519-signed when WC_UPDATE_SIGN_KEY is set.
$uz = new ZipArchive();
$uz->open($update);
$files = [];
for ($i = 0; $i < $uz->numFiles; $i++) {
    $n = $uz->getNameIndex($i);
    if (!str_ends_with($n, '/')) {
        $files[$n] = hash('sha256', (string) $uz->getFromIndex($i));
    }
}
ksort($files);
$manifest = [
    'product'  => 'website-core',
    'version'  => $ver,
    'files'    => $files,
    // Rollback/replay posture honored by CoreUpdater::inspect: stale
    // metadata and archives declaring a floor newer than the running core
    // are refused.
    'expires'  => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400),
    'min_core' => $ver,
];
$signKey = getenv('WC_UPDATE_SIGN_KEY');
if ($signKey && extension_loaded('sodium')) {
    $secret = base64_decode($signKey, true);
    if ($secret !== false && strlen($secret) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        $payload = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $manifest['signature'] = base64_encode(sodium_crypto_sign_detached($payload, $secret));
        echo "update manifest signed\n";
    } else {
        fwrite(STDERR, "WC_UPDATE_SIGN_KEY set but invalid — shipping unsigned update\n");
    }
}
$uz->addFromString('update.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$uz->close();
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

// --- SBOM (runtime dependency set, CycloneDX) --------------------------------
passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/tools/sbom.php')
    . ' ' . escapeshellarg($out . '/SBOM.json'), $sbomRc);
if ($sbomRc !== 0) {
    fwrite(STDERR, "SBOM generation failed\n");
    exit(1);
}
$sums[] = $out . '/SBOM.json';

// --- Checksums ------------------------------------------------------------
$lines = '';
foreach ($sums as $p) {
    $lines .= hash_file('sha256', $p) . '  ' . basename($p) . "\n";
}
file_put_contents($out . '/SHA256SUMS.txt', $lines);
echo "wrote " . count($sums) . " artifacts + SHA256SUMS.txt to {$out}\n";
