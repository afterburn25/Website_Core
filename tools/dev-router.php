<?php
declare(strict_types=1);

/**
 * Dev router for `php -S host:port tools/dev-router.php` run from site/.
 * Mimics .htaccess: existing public files pass through, everything else
 * goes to index.php. DEVELOPMENT ONLY — production uses Apache/LiteSpeed.
 */
$root = $_SERVER['DOCUMENT_ROOT'] ?: getcwd();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = $root . '/' . ltrim($path, '/');

if (preg_match('#^/(config|core|database|includes|install|modules|themes|upload|storage|admin/controllers|admin/views|vendor)(/|$)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if ($path !== '/' && is_file($file)) {
    return false; // let the server serve the real file
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
return true;
