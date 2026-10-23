<?php
declare(strict_types=1);

// Test bootstrap: loads the site autoloader + helpers without requiring an
// installed config. Native (MySQL) tests skip unless WC_TEST_MYSQL_DSN is set.

define('WC_ROOT', dirname(__DIR__) . '/site');
define('WC_CONFIG', WC_ROOT . '/config');
define('WC_CORE', WC_ROOT . '/core');
define('WC_THEMES', WC_ROOT . '/themes');
define('WC_MODULES', WC_ROOT . '/modules');
define('WC_INSTALLED_LOCK', WC_CONFIG . '/installed.lock');
define('WC_VERSION', '1.4.0');
define('WC_API_VERSION', 1);
define('WC_SCHEMA_VERSION', '2026_10_23_0015');

require WC_ROOT . '/includes/autoload.php';
require WC_ROOT . '/includes/helpers.php';

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

/**
 * Temp dirs that must outlive a single test. Package provider classes are
 * loaded once per PHP process; whichever site's copy is loaded first keeps
 * resolving its own views dir in later tests, so per-test cleanup would
 * delete files a previously-loaded class still references. Shutdown cleanup
 * matches the real lifetime. Installs are byte-identical, so which physical
 * copy a shared class binds to is immaterial — data services stay isolated.
 */
final class WcProcessTmpDirs
{
    /** @var list<string> */
    private static array $dirs = [];
    private static bool $hooked = false;

    public static function make(string $prefix): string
    {
        if (!self::$hooked) {
            register_shutdown_function([self::class, 'clean']);
            self::$hooked = true;
        }
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
        self::$dirs[] = $dir;
        return $dir;
    }

    public static function clean(): void
    {
        foreach (self::$dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($dir);
        }
        self::$dirs = [];
    }
}
