<?php
declare(strict_types=1);

/**
 * Thin installer entry. Successful setup removes this file and install/.
 * If host permissions prevent cleanup, the installed site stays locked until
 * both artifacts are removed manually. See docs/INSTALLATION.md.
 */
$app = require __DIR__ . '/includes/bootstrap.php';

$installer = new \Install\Installer($app);
$installer->run();
