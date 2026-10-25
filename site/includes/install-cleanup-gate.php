<?php
declare(strict_types=1);

/**
 * A completed installation retries removal of its public installer entry and
 * folder. The validated configuration and permanent installation marker keep
 * setup disabled even when shared-host ownership prevents physical deletion,
 * so cleanup residue must never make the installed website unavailable.
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
        error_log('[website-core install] manual installer cleanup recommended: ' . implode(', ', $remaining));
        return false;
    }

    @unlink($cleanupLock);
    return false;
};
