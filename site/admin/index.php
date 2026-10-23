<?php
declare(strict_types=1);

/**
 * Friendly admin-directory entry for servers that resolve the physical
 * admin/ directory before the application's front controller.
 */
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
$basePath = rtrim(dirname(dirname($script)), '/');
$target = ($basePath === '.' ? '' : $basePath) . '/index.php?p=/admin';
header('Location: ' . $target, true, 302);
exit;
