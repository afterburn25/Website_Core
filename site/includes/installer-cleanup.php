<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime-paths.php';

/**
 * A completed install requires both the permanent marker and a structurally
 * valid generated config. Database tables alone never mark a site installed.
 */
function wc_completed_installation(string $configDirectory): bool
{
    $installedLock = $configDirectory . '/installed.lock';
    if (!is_file($installedLock)) {
        return false;
    }
    $marker = @file_get_contents($installedLock);
    if (!is_string($marker) || !str_contains($marker, 'install complete')) {
        return false;
    }

    $configFile = $configDirectory . '/config.php';
    if (!is_file($configFile) || is_link($configFile)) {
        return false;
    }

    try {
        $config = (static function (string $path): mixed {
            return @include $path;
        })($configFile);
    } catch (Throwable) {
        return false;
    }
    if (!is_array($config)) {
        return false;
    }

    $appUrl = $config['app']['url'] ?? null;
    $database = $config['database'] ?? null;
    $paths = $config['paths'] ?? null;
    $appKey = $config['security']['app_key'] ?? null;
    $driver = is_array($database) ? ($database['driver'] ?? null) : null;
    $databaseReady = $driver === 'sqlite'
        ? is_string($database['name'] ?? null) && ($database['name'] ?? '') !== ''
        : $driver === 'mysql'
            && is_string($database['host'] ?? null) && ($database['host'] ?? '') !== ''
            && is_string($database['name'] ?? null) && ($database['name'] ?? '') !== ''
            && is_string($database['user'] ?? null) && ($database['user'] ?? '') !== '';

    return is_string($appUrl)
        && filter_var($appUrl, FILTER_VALIDATE_URL) !== false
        && $databaseReady
        && preg_match('/^[a-zA-Z0-9_]{0,16}$/', (string) ($database['prefix'] ?? '')) === 1
        && is_array($paths)
        && is_string($paths['storage'] ?? null) && ($paths['storage'] ?? '') !== ''
        && is_string($paths['upload'] ?? null) && ($paths['upload'] ?? '') !== ''
        && is_string($appKey)
        && preg_match('/^[a-f0-9]{64}$/i', $appKey) === 1;
}

/** @return list<string> */
function wc_remaining_installer_artifacts(string $root): array
{
    clearstatcache();
    return array_values(array_filter([
        is_dir($root . '/install') ? 'install/' : null,
        is_file($root . '/install.php') ? 'install.php' : null,
    ]));
}

/**
 * Remove only the two shipped installer artifacts. Direct deletion is tried
 * first. On split-ownership hosts, the exact artifact is atomically retired
 * into the protected runtime outside the document root and erased there.
 *
 * @return list<string> Canonical public installer artifacts that still exist.
 */
function wc_remove_installer_artifacts(string $root): array
{
    $root = rtrim($root, DIRECTORY_SEPARATOR);
    $runtime = null;

    $entry = $root . '/install.php';
    if (is_file($entry) && !@unlink($entry)) {
        $runtime = wc_private_runtime_dir($root, true);
        wc_retire_installer_artifact($entry, $runtime, false);
    }

    $directory = $root . '/install';
    if (is_dir($directory) && !wc_remove_installer_tree($directory)) {
        $runtime ??= wc_private_runtime_dir($root, true);
        wc_retire_installer_artifact($directory, $runtime, true);
    }

    return wc_remaining_installer_artifacts($root);
}

function wc_retire_installer_artifact(string $path, ?string $runtime, bool $directory): bool
{
    if ($runtime === null || !is_dir($runtime)) {
        return false;
    }

    $retiredRoot = $runtime . DIRECTORY_SEPARATOR . 'retired-installers';
    if (!is_dir($retiredRoot) && !@mkdir($retiredRoot, 0700)) {
        return false;
    }
    @chmod($retiredRoot, 0700);

    try {
        $suffix = bin2hex(random_bytes(8));
    } catch (Throwable) {
        return false;
    }
    $name = $directory ? 'install-directory-' : 'install-entry-';
    $destination = $retiredRoot . DIRECTORY_SEPARATOR . $name . $suffix;
    if (!@rename($path, $destination)) {
        return false;
    }

    // The executable public path is already gone. Finish physical deletion
    // immediately where permissions allow, while never restoring it publicly.
    $removed = $directory
        ? wc_remove_installer_tree($destination)
        : @unlink($destination);
    if (!$removed && (is_dir($destination) || is_file($destination))) {
        error_log('[website-core install] retired installer artifact remains in protected runtime: ' . $destination);
    }
    return true;
}

/** Retry physical erasure of previously retired artifacts on later loads. */
function wc_sweep_retired_installer_artifacts(string $root): void
{
    $runtime = wc_private_runtime_dir($root, false);
    if ($runtime === null) {
        return;
    }
    $retiredRoot = $runtime . DIRECTORY_SEPARATOR . 'retired-installers';
    if (!is_dir($retiredRoot) || is_link($retiredRoot)) {
        return;
    }
    foreach (glob($retiredRoot . DIRECTORY_SEPARATOR . 'install-*') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match('/^install-(?:directory|entry)-[a-f0-9]{16}$/', $name)) {
            continue;
        }
        is_dir($path) && !is_link($path)
            ? wc_remove_installer_tree($path)
            : @unlink($path);
    }
    @rmdir($retiredRoot);
}

function wc_remove_installer_tree(string $directory): bool
{
    if (is_link($directory)) {
        return @unlink($directory);
    }
    if (!is_dir($directory)) {
        return true;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                @unlink($path);
            } elseif ($item->isDir()) {
                @rmdir($path);
            }
        }
    } catch (UnexpectedValueException) {
        return false;
    }
    @rmdir($directory);
    clearstatcache(true, $directory);
    return !is_dir($directory);
}

/** Base path for root and subfolder installations, including direct entry URLs. */
function wc_installer_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $path = parse_url($script, PHP_URL_PATH);
    $directory = str_replace('\\', '/', dirname(is_string($path) ? $path : '/index.php'));
    if (basename($directory) === 'admin') {
        $directory = dirname($directory);
    }
    $directory = rtrim($directory, '/');
    return $directory === '' || $directory === '.' ? '' : $directory;
}

/** @param list<string> $remaining */
function wc_installer_cleanup_page(string $root, array $remaining): string
{
    $escape = static fn (string $value): string => htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    $base = wc_installer_base_path();
    $siteUrl = ($base === '' ? '' : $base) . '/';
    $adminUrl = ($base === '' ? '' : $base) . '/admin';
    $recheckUrl = $siteUrl . '?installer_cleanup_recheck=1';
    $logoUrl = ($base === '' ? '' : $base) . '/assets/images/cajunwebpro-logo.png';
    $items = $remaining === [] ? 'installer files' : implode(' and ', array_map($escape, $remaining));

    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="dark"><title>Installer cleanup required</title>'
        . '<style>*{box-sizing:border-box}html{min-height:100%;background:#07111f}body{margin:0;min-height:100vh;display:grid;place-items:center;'
        . 'padding:24px;background:radial-gradient(circle at 18% 8%,#183653 0,transparent 34%),radial-gradient(circle at 90% 90%,#3a2a10 0,transparent 30%),#07111f;'
        . 'color:#eef5ff;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.box{width:min(760px,100%);padding:clamp(28px,6vw,52px);'
        . 'border:1px solid #725627;border-radius:28px;background:linear-gradient(145deg,#172638f5,#0a1422f7);box-shadow:0 32px 100px #000a;overflow:hidden}'
        . '.logo-link{display:block;width:fit-content;max-width:100%}.logo{display:block;width:min(390px,88%);height:auto;margin:0 0 30px;filter:drop-shadow(0 12px 24px #0008)}'
        . '.flag{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #805f26;border-radius:999px;background:#4b3513;color:#ffd98a;font-weight:850;font-size:.74rem;letter-spacing:.1em}'
        . '.flag:before{content:"";width:8px;height:8px;border-radius:50%;background:#f2ba52;box-shadow:0 0 0 5px #f2ba5224}h1{margin:18px 0 12px;font-family:Georgia,serif;font-size:clamp(2.15rem,6vw,3.8rem);line-height:1.02;letter-spacing:-.035em}'
        . 'p,li{color:#c7d3e2;line-height:1.72}ol{margin:24px 0;padding-left:1.3rem}li{padding:4px 0 4px 8px}code{overflow-wrap:anywhere;color:#ffe0a0;font-family:ui-monospace,SFMono-Regular,Consolas,monospace}strong{color:#fff}'
        . '.notice{margin:24px 0;padding:16px 18px;border-left:3px solid #d6a94f;border-radius:0 12px 12px 0;background:#d6a94f12}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}'
        . '.button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 18px;border:1px solid #47617d;border-radius:12px;color:#f4f8ff;text-decoration:none;font-weight:800;transition:transform .15s ease,background .15s ease}'
        . '.button:hover{transform:translateY(-1px);background:#1a2e45}.button:focus-visible{outline:3px solid #f1c76c;outline-offset:3px}.button.primary{border-color:#e1b657;background:#d6a94f;color:#07111f}.button.primary:hover{background:#e5bd67}'
        . '@media(max-width:560px){body{padding:12px}.box{border-radius:20px}.actions{display:grid}.button{width:100%}}@media(prefers-reduced-motion:reduce){.button{transition:none}}'
        . '</style></head><body><main class="box">'
        . '<a class="logo-link" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Visit CajunWebPro">'
        . '<img class="logo" src="' . $escape($logoUrl) . '" alt="CajunWebPro — Web Design &amp; Development"></a>'
        . '<span class="flag">SITE SAFELY LOCKED</span><h1>One cleanup step remains</h1>'
        . '<p>The website was installed, but the server could not remove <strong>' . $items . '</strong>. The public site and administration area remain locked for security.</p>'
        . '<ol><li>Open your hosting File Manager.</li><li>Go to <code>' . $escape($root) . '</code>.</li>'
        . '<li>Delete the <code>install</code> folder and the root <code>install.php</code> file.</li>'
        . '<li>Select <strong>Recheck cleanup</strong>. When both are gone, it opens the website automatically.</li></ol>'
        . '<p class="notice"><strong>Keep these files:</strong> do not delete <code>installed.lock</code> or <code>cleanup-required.lock</code>.</p>'
        . '<div class="actions"><a class="button primary" href="' . $escape($recheckUrl) . '">Recheck cleanup</a>'
        . '<a class="button" href="' . $escape($siteUrl) . '">Visit website</a>'
        . '<a class="button" href="' . $escape($adminUrl) . '">Open admin</a></div>'
        . '</main></body></html>';
}
