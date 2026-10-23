<?php
declare(strict_types=1);

/**
 * Block every application entry point after installation until the installer
 * directory and root entry file are gone. Returns true when the caller must
 * stop execution. Kept outside the front controllers so those remain thin.
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

    $remaining = wc_remaining_installer_artifacts($root);
    $cleanupLock = $configDirectory . '/cleanup-required.lock';
    if ($remaining !== []) {
        // Every normal site load retries the exact allow-listed cleanup. This
        // also removes installer files accidentally re-uploaded months later.
        $remaining = wc_remove_installer_artifacts($root);
    }
    if ($remaining === []) {
        if (is_file($cleanupLock)) {
            @unlink($cleanupLock);
        }
        return false;
    }

    if (!is_file($cleanupLock)) {
        @file_put_contents($cleanupLock, date('c') . " installer artifacts remain\n", LOCK_EX);
    }
    http_response_code(503);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Site locked: remove install/ and install.php, then retry.\n");
        return true;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 60');
    echo wc_installer_cleanup_page($root, $remaining);
    return true;
};
