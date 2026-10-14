<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Health + audit views: real versions, queue depth/age, backup state,
 * failed packages, storage — no invented metrics.
 */
final class SystemController extends Controller
{
    public function health(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.view')) {
            return $this->forbidden();
        }
        $db = $this->db();
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');

        $jobs = $this->c->get(\Core\Jobs\JobQueue::class)->counts();
        $oldestPending = $db->fetchColumn(
            'SELECT MIN(run_after) FROM `' . $db->table('jobs') . "` WHERE status IN ('pending','uncertain')"
        );
        $backups = glob($storage . '/backups/backup-*.zip') ?: [];
        $latestBackup = $backups === [] ? null : max(array_map('filemtime', $backups));
        $failedPackages = $db->fetchAll(
            'SELECT package_id, state FROM `' . $db->table('packages') . "` WHERE state IN ('failed','upgrading')"
        );
        $driver = $db->driver();
        $dbVersion = $driver === 'sqlite'
            ? 'SQLite ' . (string) $db->pdo()->query('SELECT sqlite_version()')->fetchColumn()
            : (string) $db->pdo()->query('SELECT VERSION()')->fetchColumn();

        return $this->page($r, 'system/health', [
            'checks' => [
                'Core version'     => defined('WC_VERSION') ? WC_VERSION : '?',
                'PHP'              => PHP_VERSION,
                'Database'         => $driver . ' — ' . $dbVersion,
                'Pending jobs'     => (string) ($jobs['pending'] + $jobs['uncertain']),
                'Oldest pending job' => $oldestPending ? date('Y-m-d H:i:s', (int) $oldestPending) : '—',
                'Failed jobs'      => (string) $jobs['failed'],
                'Latest backup'    => $latestBackup ? date('Y-m-d H:i:s', (int) $latestBackup) : 'none yet',
                'Failed/quarantined packages' => $failedPackages === [] ? 'none'
                    : implode(', ', array_map(fn ($p) => $p['package_id'] . ' (' . $p['state'] . ')', $failedPackages)),
                'Storage writable' => is_writable($storage) ? 'yes' : 'NO',
                'Disk free'        => function_exists('disk_free_space')
                    ? round(disk_free_space(WC_ROOT) / 1048576) . ' MB' : 'n/a',
            ],
        ], 'System health');
    }

    public function audit(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'audit.view')) {
            return $this->forbidden();
        }
        $rows = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('audit_log') . '` ORDER BY id DESC LIMIT 100'
        );
        return $this->page($r, 'system/audit', ['rows' => $rows], 'Audit log');
    }
}
