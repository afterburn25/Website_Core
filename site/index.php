<?php
declare(strict_types=1);

/**
 * Thin front controller. All application logic lives in core/.
 * Supports rewriting (.htaccess) and the ?p=/path no-rewrite fallback.
 */
$cleanupGate = require __DIR__ . '/includes/install-cleanup-gate.php';
if ($cleanupGate(__DIR__)) {
    exit;
}

$app = require __DIR__ . '/includes/bootstrap.php';
$app->run();
