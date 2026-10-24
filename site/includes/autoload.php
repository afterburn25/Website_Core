<?php
declare(strict_types=1);

/**
 * PSR-4 autoloader for core, admin, and registered module namespaces.
 * Module namespaces are registered at boot by the ModuleRegistry.
 */
final class CoreAutoload
{
    /** @var array<string, string> prefix => base directory */
    private static array $prefixes = [];

    public static function register(string $prefix, string $baseDir): void
    {
        self::$prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
                $path = $dir . $relative . '.php';
                if (!is_file($path)) {
                    // Layout uses lowercase dirs (admin/controllers); retry
                    // with a lowercased first segment for case-sensitive FS.
                    $segments = explode(DIRECTORY_SEPARATOR, $relative);
                    $segments[0] = strtolower($segments[0]);
                    $path = $dir . implode(DIRECTORY_SEPARATOR, $segments) . '.php';
                }
                if (is_file($path)) {
                    if (function_exists('wc_runtime_code_mirror')
                        && defined('WC_CONFIG') && defined('WC_VERSION')) {
                        $path = wc_runtime_code_mirror($path, WC_CONFIG, WC_VERSION);
                    }
                    require $path;
                }
                return;
            }
        }
    }
}

CoreAutoload::register('Core', __DIR__ . '/../core');
CoreAutoload::register('Admin', __DIR__ . '/../admin');

spl_autoload_register([CoreAutoload::class, 'load']);
