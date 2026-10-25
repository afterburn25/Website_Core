<?php
declare(strict_types=1);

/**
 * Shared bootstrap. Defines path constants, registers the autoloader, sets
 * error handling, and loads configuration when the site is installed.
 *
 * Returns the \Core\Application instance (uninstalled-safe: config may be
 * absent while the installer runs).
 */

define('WC_ROOT', dirname(__DIR__));                       // deployable site dir
require_once __DIR__ . '/runtime-paths.php';
require_once __DIR__ . '/installer-cleanup.php';
define('WC_CONFIG', wc_runtime_config_dir(WC_ROOT));
define('WC_CORE', WC_ROOT . '/core');
define('WC_THEMES', WC_ROOT . '/themes');
define('WC_MODULES', WC_ROOT . '/modules');
define('WC_INSTALLED_LOCK', WC_CONFIG . '/installed.lock');
define('WC_VERSION', '2.0.16');
define('WC_API_VERSION', 1);
// Latest core migration bundled with this code. Installed sites compare this
// marker with the migration journal during boot so a manual shared-hosting
// file update cannot leave newer PHP running against an older schema.
define('WC_SCHEMA_VERSION', '2026_10_25_0024');

require_once __DIR__ . '/server-error-pages.php';
wc_prepare_server_error_page(WC_ROOT);

require_once __DIR__ . '/runtime-code-cache.php';
wc_refresh_runtime_code_cache(WC_ROOT, WC_CONFIG, WC_VERSION);

require_once __DIR__ . '/runtime-code-loader.php';
require wc_runtime_code_mirror(__DIR__ . '/autoload.php', WC_CONFIG, WC_VERSION);
require wc_runtime_code_mirror(__DIR__ . '/helpers.php', WC_CONFIG, WC_VERSION);

// Composer dependencies — bundled at site/vendor in deployment packages,
// repo-root vendor during development. Both locations stay non-public.
foreach ([WC_ROOT . '/vendor/autoload.php', dirname(WC_ROOT) . '/vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

use Core\Application;
use Core\Config;

// --- Error handling -------------------------------------------------------
// Display is always off here; the ErrorHandler decides final output based on
// config. Log via PHP's mechanism so shared hosts capture it.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$config = new Config(WC_CONFIG . '/config.php');

if ($config->loaded()) {
    date_default_timezone_set($config->get('app.timezone', 'UTC'));
    if (($config->get('app.env') === 'development') || $config->get('app.debug')) {
        ini_set('display_errors', '1');
    }
}

$app = new Application($config);

return $app;
