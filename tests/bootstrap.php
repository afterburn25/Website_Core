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
define('WC_VERSION', '0.1.0-dev');
define('WC_API_VERSION', 1);

require WC_ROOT . '/includes/autoload.php';
require WC_ROOT . '/includes/helpers.php';

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
}
