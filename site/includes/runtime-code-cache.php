<?php
declare(strict_types=1);

/**
 * Refresh PHP's opcode cache once per deployed Core version.
 *
 * Shared hosts sometimes disable timestamp validation. A hosting-panel
 * extraction can then update bootstrap.php while workers continue executing
 * an older cached Application/controller graph. Bootstrap calls this before
 * the autoloader loads Core classes, so every worker sees the current files.
 */
function wc_refresh_runtime_code_cache(string $root, string $configDir, string $version): void
{
    $marker = rtrim($configDir, '/\\') . '/.runtime-code-version';
    $recorded = @file_get_contents($marker);
    if (is_string($recorded) && trim($recorded) === $version) {
        return;
    }

    try {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('opcache_invalidate')) {
            foreach ([$root . '/core', $root . '/admin'] as $directory) {
                if (!is_dir($directory)) {
                    continue;
                }
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($files as $file) {
                    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                        @opcache_invalidate($file->getPathname(), true);
                    }
                }
            }
            foreach ([$root . '/index.php', $root . '/cron.php'] as $entry) {
                if (is_file($entry)) {
                    @opcache_invalidate($entry, true);
                }
            }
        }
    } catch (Throwable $e) {
        // Cache refresh is best effort. Leaving the marker unwritten retries
        // on the next request rather than blocking the website.
        error_log('[website-core] runtime code-cache refresh: ' . $e->getMessage());
        return;
    }

    if (!is_dir($configDir) && !@mkdir($configDir, 0750, true) && !is_dir($configDir)) {
        return;
    }
    @file_put_contents($marker, $version . PHP_EOL, LOCK_EX);
}
