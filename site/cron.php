<?php
declare(strict_types=1);

/**
 * Job runner for ordinary host cron ("php cron.php" or a scheduled fetch of
 * /cron.php?token=...). Drains a bounded batch of due jobs, then exits.
 * Browser triggering requires the cron token from Settings > System so
 * visitor traffic alone can never drive the queue.
 */
$app = require __DIR__ . '/includes/bootstrap.php';

if (!$app->installed()) {
    http_response_code(503);
    exit('Not installed');
}

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $token = (string) ($_GET['token'] ?? '');
    $db = $app->container()->get(\Core\Database\Connection::class);
    $settings = new \Core\Settings\SettingsRepository($db);
    $expected = (string) $settings->get('system.cron_token', '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$runner = new \Core\Jobs\Runner($app->container());
$processed = $runner->run(10, 25);
if (!$cli) {
    header('Content-Type: text/plain');
}
echo "processed={$processed}\n";
