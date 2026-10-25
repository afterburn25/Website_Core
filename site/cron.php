<?php
declare(strict_types=1);

/**
 * Job runner for ordinary host cron ("php cron.php" or a scheduled fetch of
 * /cron.php?token=...). Drains a bounded batch of due jobs, then exits.
 * Browser triggering requires the cron token from Settings > System so
 * visitor traffic alone can never drive the queue.
 */
$cleanupGate = require __DIR__ . '/includes/install-cleanup-gate.php';
if ($cleanupGate(__DIR__)) {
    exit(1);
}

$app = require __DIR__ . '/includes/bootstrap.php';

if (!$app->installed()) {
    http_response_code(503);
    exit('Not installed');
}

$cli = PHP_SAPI === 'cli';
// Honor maintenance.flag like the request path — jobs and mail must not
// run against a mid-swap tree during an update/restore window.
$storage = (string) $app->container()->get(\Core\Config::class)
    ->get('paths.storage', WC_ROOT . '/storage');
if (is_file($storage . '/maintenance.flag')) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(503);
    }
    exit("maintenance\n");
}
// Non-HTTP entry points must boot explicitly — dispatch() does it for web
// requests, cron never dispatches. bootJobs() also boots enabled packages
// so their scheduledJob() handlers are registered before the runner claims.
$app->bootJobs();
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

// Optional shared-host backup schedule. Cron remains the only trigger; a
// front-end visit never performs heavy backup work.
$settings = $app->container()->get(\Core\Settings\SettingsRepository::class);
$autoDays = (int) $settings->get('backup.auto_days', 0);
if ($autoDays > 0) {
    $backups = glob($storage . '/backups/backup-*.zip') ?: [];
    $latest = $backups === [] ? 0 : max(array_map('filemtime', $backups));
    $db = $app->container()->get(\Core\Database\Connection::class);
    $pendingBackup = (int) $db->fetchColumn(
        'SELECT COUNT(*) FROM `' . $db->table('jobs') . "` WHERE type = 'core.backup' AND status IN ('pending','running','uncertain')"
    );
    if (!$pendingBackup && $latest < time() - ($autoDays * 86400)) {
        $app->container()->get(\Core\Jobs\JobQueue::class)->dispatch('core.backup', ['automatic' => true]);
    }
}

$runner = new \Core\Jobs\Runner($app->container());
$processed = $runner->run(10, 25);
// Drain the transactional queue every pass — no separate cron entry needed.
$mailed = $app->container()->get(\Core\Mail\SystemMail::class)->flush(10);
$analyticsPruned = 0;
try {
    $analyticsPruned = $app->container()->get(\Core\Analytics\AnalyticsService::class)->cleanup();
} catch (\Throwable $e) {
    error_log('[website-core] analytics retention: ' . $e->getMessage());
}
$settings->set('system.cron_last_run', date('Y-m-d H:i:s'));
if (!$cli) {
    header('Content-Type: text/plain');
}
echo "processed={$processed} mailed={$mailed} analytics_pruned={$analyticsPruned}\n";
