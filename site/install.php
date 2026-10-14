<?php
declare(strict_types=1);

/**
 * Thin installer entry. The installer self-disables after success and is
 * permanently blocked by config/installed.lock even if this file cannot be
 * removed by the host. See docs/INSTALLATION.md for the secure-claim model.
 */
$app = require __DIR__ . '/includes/bootstrap.php';

$installer = new \Install\Installer($app);
$installer->run();
