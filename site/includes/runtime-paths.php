<?php
declare(strict_types=1);

/**
 * Locate a durable private runtime directory for split-ownership hosts.
 * Temporary directories and anything beneath the public document root are
 * deliberately rejected.
 */
function wc_private_runtime_dir(string $siteRoot, bool $create = false): ?string
{
    $siteRoot = realpath($siteRoot) ?: rtrim($siteRoot, DIRECTORY_SEPARATOR);
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath((string) $_SERVER['DOCUMENT_ROOT'])
        : false;
    if ($documentRoot === false) {
        $documentRoot = $siteRoot;
    }

    $candidates = [];
    $configured = trim((string) (getenv('WC_PRIVATE_RUNTIME') ?: ''));
    if ($configured !== '') {
        $candidates[] = $configured;
    }
    if ($documentRoot !== DIRECTORY_SEPARATOR) {
        $candidates[] = dirname($documentRoot);
    }
    $home = trim((string) (getenv('HOME') ?: ''));
    if ($home !== '') {
        $candidates[] = $home;
    }
    $candidates[] = dirname($siteRoot);
    $suffix = '.website-core-' . substr(hash('sha256', $siteRoot), 0, 20);

    foreach (array_unique($candidates) as $base) {
        $base = realpath($base);
        if ($base === false || !is_dir($base) || is_link($base)) {
            continue;
        }
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        if ($base === '' || $base === DIRECTORY_SEPARATOR || wc_path_inside($base, sys_get_temp_dir())) {
            continue;
        }
        if (wc_path_inside($base, $documentRoot) || wc_path_inside($base, $siteRoot)) {
            continue;
        }

        $runtime = $base . DIRECTORY_SEPARATOR . $suffix;
        if (!is_dir($runtime)) {
            if (!$create || !@mkdir($runtime, 0700)) {
                continue;
            }
        }
        if (is_link($runtime) || !is_dir($runtime)) {
            continue;
        }
        @chmod($runtime, 0700);
        $permissions = @fileperms($runtime);
        if ($permissions === false || ($permissions & 0077) !== 0 || !wc_directory_probe($runtime)) {
            continue;
        }
        return $runtime;
    }
    return null;
}

function wc_runtime_config_dir(string $siteRoot): string
{
    $private = wc_private_runtime_dir($siteRoot, false);
    if ($private !== null) {
        $candidate = $private . DIRECTORY_SEPARATOR . 'config';
        if (is_file($candidate . DIRECTORY_SEPARATOR . 'config.php')
            || is_file($candidate . DIRECTORY_SEPARATOR . 'installed.lock')
        ) {
            return $candidate;
        }
    }
    return rtrim($siteRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
}

function wc_directory_probe(string $directory): bool
{
    if (!is_dir($directory)) {
        return false;
    }
    try {
        $token = bin2hex(random_bytes(5));
    } catch (Throwable) {
        return false;
    }
    $first = $directory . DIRECTORY_SEPARATOR . '.wc-probe-' . $token;
    $second = $first . '-renamed';
    $handle = @fopen($first, 'x');
    if ($handle === false) {
        return false;
    }
    $written = fwrite($handle, 'ok') === 2;
    fclose($handle);
    $read = $written && @file_get_contents($first) === 'ok';
    $renamed = $read && @rename($first, $second);
    $removed = $renamed && @unlink($second);
    @unlink($first);
    @unlink($second);
    return $removed;
}

function wc_path_inside(string $path, string $parent): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $parent = rtrim(str_replace('\\', '/', $parent), '/');
    return $path === $parent || str_starts_with($path, $parent . '/');
}
