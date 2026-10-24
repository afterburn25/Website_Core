<?php
declare(strict_types=1);

/**
 * Thin front controller. All application logic lives in core/.
 * Supports rewriting (.htaccess) and the ?p=/path no-rewrite fallback.
 */
$fatalRoot = __DIR__;
register_shutdown_function(static function () use ($fatalRoot): void {
    $error = error_get_last();
    if (!is_array($error) || !in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $file = $fatalRoot . '/errors/500.html';
    $html = is_file($file) ? @file_get_contents($file) : false;
    if (!is_string($html) || $html === '') {
        echo '<!doctype html><meta charset="utf-8"><title>Server error</title><h1>Something needs attention.</h1><p>Please contact the site administrator.</p>';
        return;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }
    echo str_replace(
        ['src="../assets/', 'href="../index.php', 'href="../"'],
        ['src="' . $base . '/assets/', 'href="' . $base . '/index.php', 'href="' . ($base === '' ? '/' : $base . '/') . '"'],
        $html
    );
});
ini_set('display_errors', '0');

$cleanupGate = require __DIR__ . '/includes/install-cleanup-gate.php';
if ($cleanupGate(__DIR__)) {
    exit;
}

$app = require __DIR__ . '/includes/bootstrap.php';
$app->run();
