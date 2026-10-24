<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Fail-safe health workspace.
 *
 * A diagnostic screen must remain available when one diagnostic source is
 * broken. Every collector is therefore isolated and the page renders the
 * remaining evidence plus an actionable warning instead of returning 500.
 */
final class HealthController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.view')) {
            return $this->forbidden();
        }

        $warnings = [];
        $collect = static function (string $label, callable $callback, mixed $fallback) use (&$warnings): mixed {
            try {
                return $callback();
            } catch (\Throwable $e) {
                error_log('[website-core] health collector ' . $label . ': ' . $e->getMessage());
                $warnings[] = $label . ' could not be checked.';
                return $fallback;
            }
        };

        $db = $this->db();
        $storage = (string) $this->c->get(\Core\Config::class)
            ->get('paths.storage', WC_ROOT . '/storage');
        $jobs = $collect('Background jobs', fn (): array => $this->c
            ->get(\Core\Jobs\JobQueue::class)->counts(), [
                'pending' => 0, 'uncertain' => 0, 'failed' => 0,
            ]);
        $oldestPending = $collect('Oldest pending job', fn () => $db->fetchColumn(
            'SELECT MIN(run_after) FROM `' . $db->table('jobs')
                . "` WHERE status IN ('pending','uncertain')"
        ), null);
        $latestBackup = $collect('Backups', static function () use ($storage): ?int {
            $times = [];
            foreach (glob($storage . '/backups/backup-*.zip') ?: [] as $file) {
                $time = @filemtime($file);
                if (is_int($time)) {
                    $times[] = $time;
                }
            }
            return $times === [] ? null : max($times);
        }, null);
        $failedPackages = $collect('Package state', fn (): array => $db->fetchAll(
            'SELECT package_id, state FROM `' . $db->table('packages')
                . "` WHERE state IN ('failed','upgrading')"
        ), []);
        $driver = $collect('Database driver', fn (): string => $db->driver(), 'unknown');
        $dbVersion = $collect('Database version', function () use ($db, $driver): string {
            if ($driver === 'sqlite') {
                return 'SQLite ' . (string) $db->pdo()->query('SELECT sqlite_version()')->fetchColumn();
            }
            return (string) $db->pdo()->query('SELECT VERSION()')->fetchColumn();
        }, 'unavailable');
        $mailRows = $collect('Mail queue', fn (): array => $db->fetchAll(
            'SELECT `to`, subject, status, attempts, last_error, created_at, sent_at'
                . ' FROM `' . $db->table('system_messages') . '` ORDER BY id DESC LIMIT 20'
        ), []);
        $integrity = $collect('Installation integrity', fn (): array => $this->c
            ->get(\Core\System\Integrity::class)->report(), []);
        $moduleHealth = $collect('Package health', fn (): array => $this->c
            ->get(\Core\Modules\ModuleRegistry::class)->healthChecks(), []);
        $diskFree = $collect('Disk space', static function (): string {
            if (!function_exists('disk_free_space')) {
                return 'n/a';
            }
            $bytes = @disk_free_space(WC_ROOT);
            return is_float($bytes) || is_int($bytes)
                ? number_format($bytes / 1048576, 0) . ' MB'
                : 'unavailable';
        }, 'unavailable');

        return $this->page($r, 'system/health', [
            'warnings' => array_values(array_unique($warnings)),
            'mailRows' => is_array($mailRows) ? $mailRows : [],
            'integrity' => is_array($integrity) ? $integrity : [],
            'moduleHealth' => is_array($moduleHealth) ? $moduleHealth : [],
            'checks' => [
                'Core version' => defined('WC_VERSION') ? WC_VERSION : '?',
                'PHP' => PHP_VERSION,
                'Database' => $driver . ' — ' . $dbVersion,
                'Pending jobs' => (string) ((int) ($jobs['pending'] ?? 0) + (int) ($jobs['uncertain'] ?? 0)),
                'Oldest pending job' => $oldestPending ? date('Y-m-d H:i:s', (int) $oldestPending) : '—',
                'Failed jobs' => (string) ((int) ($jobs['failed'] ?? 0)),
                'Latest backup' => $latestBackup ? date('Y-m-d H:i:s', (int) $latestBackup) : 'none yet',
                'Failed/quarantined packages' => $failedPackages === [] ? 'none'
                    : implode(', ', array_map(static fn (array $p): string =>
                        (string) ($p['package_id'] ?? '?') . ' (' . (string) ($p['state'] ?? '?') . ')', $failedPackages)),
                'Storage writable' => is_dir($storage) && is_writable($storage) ? 'yes' : 'NO',
                'Disk free' => $diskFree,
            ],
        ], 'System health');
    }
}
