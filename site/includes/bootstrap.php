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
define('WC_VERSION', '0.1.14-dev');
define('WC_API_VERSION', 1);

require __DIR__ . '/autoload.php';
require __DIR__ . '/helpers.php';

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
