<?php
declare(strict_types=1);

/**
 * A completed installation must remove its public installer entry and folder.
 * Every public/admin request repeats the exact cleanup, and the site remains
 * fail-closed with recovery instructions until both canonical paths are gone.
 *
 * @return callable(string): bool
 */
return static function (string $root): bool {
    require_once __DIR__ . '/installer-cleanup.php';
    $configDirectory = wc_runtime_config_dir($root);
    if (!wc_completed_installation($configDirectory)) {
        return false;
    }

    // A prior request may have retired a split-owned artifact outside the
    // web root but lacked permission to erase its contents at that moment.
    wc_sweep_retired_installer_artifacts($root);

    $cleanupLock = $configDirectory . '/cleanup-required.lock';
    $remaining = wc_remove_installer_artifacts($root);
    if ($remaining !== []) {
        @file_put_contents($cleanupLock, date('c') . " installer cleanup pending\n", LOCK_EX);
        http_response_code(503);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=utf-8');
        echo wc_installer_cleanup_page($root, $remaining);
        return true;
    }

    @unlink($cleanupLock);
    return false;
};
