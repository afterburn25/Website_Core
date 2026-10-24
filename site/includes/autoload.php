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
                        && defined('WC_CONFIG') && defined('WC_VERSION') && defined('WC_ROOT')) {
                        $normalized = str_replace('\\', '/', $path);
                        $root = rtrim(str_replace('\\', '/', WC_ROOT), '/');
                        // Module classes must retain their original pathname:
                        // trusted providers commonly use __DIR__ to locate
                        // their views/assets. Only Core-owned code is mirrored.
                        if (str_starts_with($normalized, $root . '/core/')
                            || str_starts_with($normalized, $root . '/admin/')) {
                            $path = wc_runtime_code_mirror($path, WC_CONFIG, WC_VERSION);
                        }
                    }
                    require $path;
                }
                return;
            }
        }
    }
}

$applicationRoot = defined('WC_ROOT') ? WC_ROOT : dirname(__DIR__);
CoreAutoload::register('Core', $applicationRoot . '/core');
CoreAutoload::register('Admin', $applicationRoot . '/admin');

spl_autoload_register([CoreAutoload::class, 'load']);
