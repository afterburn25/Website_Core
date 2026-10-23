<?php
declare(strict_types=1);

/**
 * Thin installer entry. Keep the installed-state guard outside install/ so a
 * partially removed installer still shows the complete branded recovery UI.
 */
require_once __DIR__ . '/includes/runtime-paths.php';
require_once __DIR__ . '/includes/installer-cleanup.php';

$configDirectory = wc_runtime_config_dir(__DIR__);
if (wc_completed_installation($configDirectory)) {
    $remaining = wc_remove_installer_artifacts(__DIR__);
    if ($remaining === []) {
        @unlink($configDirectory . '/cleanup-required.lock');
        $destination = (wc_installer_base_path() === '' ? '' : wc_installer_base_path()) . '/';
        header('Location: ' . $destination, true, 303);
        exit;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 60');
    echo wc_installer_cleanup_page(__DIR__, $remaining);
    exit;
}

$app = require __DIR__ . '/includes/bootstrap.php';

$installer = new \Install\Installer($app);
$installer->run();
