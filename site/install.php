<?php
declare(strict_types=1);

/**
 * Public setup entry. Successful setup physically removes this file and the
 * companion install/ folder. A completed installation can never run setup
 * again: it only retries exact cleanup and then returns to the application.
 */
$cleanupGate = require __DIR__ . '/includes/install-cleanup-gate.php';
if ($cleanupGate(__DIR__)) {
    exit;
}

$app = require __DIR__ . '/includes/bootstrap.php';
if ($app->installed()) {
    header('Location: ./', true, 302);
    exit;
}

(new \Core\Install\Installer($app))->run();
