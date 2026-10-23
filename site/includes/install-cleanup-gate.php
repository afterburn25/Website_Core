<?php
declare(strict_types=1);

/**
 * Retire public installer artifacts left by pre-0.1.6 packages. Modern
 * packages run setup through the protected core and ship no public installer
 * entry. Legacy files are denied by access rules and their completed-install
 * guard, so panel-owned residue must never keep the installed site offline.
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
    wc_remove_installer_artifacts($root);
    if (is_file($cleanupLock)) {
        @unlink($cleanupLock);
    }
    return false;
};
