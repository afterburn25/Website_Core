<?php
declare(strict_types=1);

/**
 * Return a version-specific copy of a PHP source file.
 *
 * Some shared hosts run OPcache with timestamp validation disabled and also
 * disable opcache_reset()/opcache_invalidate(). Requiring the same pathname
 * after a hosting-panel upload can therefore execute old bytecode forever.
 * A new Core version gets a new pathname, so PHP must compile the deployed
 * source even on those hosts.
 */
function wc_runtime_code_mirror(string $source, string $configDir, string $version): string
{
    if (!is_file($source) || !is_readable($source)) {
        return $source;
    }

    $safeVersion = preg_replace('/[^A-Za-z0-9._-]/', '-', $version) ?: 'unknown';
    $cacheDir = rtrim($configDir, '/\\') . '/.runtime-code/' . $safeVersion;
    $target = $cacheDir . '/' . sha1(str_replace('\\', '/', $source)) . '-' . basename($source);

    if (is_file($target) && filesize($target) === filesize($source)) {
        return $target;
    }

    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
        return $source;
    }

    // Defense in depth when a host places the private config directory under
    // the document root despite the installer's preferred external runtime.
    $deny = $cacheDir . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\nDeny from all\n", LOCK_EX);
    }

    $contents = @file_get_contents($source);
    if (!is_string($contents)) {
        return $source;
    }
    $temporary = $target . '.tmp-' . getmypid();
    if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
        @unlink($temporary);
        return $source;
    }
    @chmod($temporary, 0640);
    if (!@rename($temporary, $target)) {
        @unlink($temporary);
        return is_file($target) ? $target : $source;
    }
    return $target;
}
