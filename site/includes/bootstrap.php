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
define('WC_CONFIG', WC_ROOT . '/config');
define('WC_CORE', WC_ROOT . '/core');
define('WC_THEMES', WC_ROOT . '/themes');
define('WC_MODULES', WC_ROOT . '/modules');
define('WC_INSTALLED_LOCK', WC_CONFIG . '/installed.lock');
define('WC_VERSION', '0.1.0-dev');
define('WC_API_VERSION', 1);

require __DIR__ . '/autoload.php';
require __DIR__ . '/helpers.php';

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
