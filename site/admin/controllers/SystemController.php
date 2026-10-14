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

    /** Core self-update: stage an UPDATE.zip, review, apply, migrate. */
    public function updates(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }
        $staged = $this->stagedPath();
        $info = null;
        if (is_file($staged)) {
            [$info, $err] = $this->updater()->inspect($staged);
            if ($err !== null) {
                @unlink($staged);
            }
        }
        return $this->page($r, 'system/updates', [
            'current' => defined('WC_VERSION') ? WC_VERSION : '?',
            'staged'  => $info,
            'journal' => $this->updater()->journalTail(),
        ], 'Core updates');
    }

    public function stageUpdate(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }
        $file = $r->files['update'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'No update archive uploaded.');
            return Response::redirect($r->basePath . '/admin/updates');
        }
        $staged = $this->stagedPath();
        @mkdir(dirname($staged), 0777, true);
        if (!move_uploaded_file((string) $file['tmp_name'], $staged)
            && !rename((string) $file['tmp_name'], $staged)) {
            $this->flash('error', 'Could not stage the upload.');
            return Response::redirect($r->basePath . '/admin/updates');
        }
        [, $err] = $this->updater()->inspect($staged);
        if ($err !== null) {
            @unlink($staged);
            $this->flash('error', $err);
        } else {
            $this->flash('ok', 'Update staged — review and apply.');
        }
        return Response::redirect($r->basePath . '/admin/updates');
    }

    public function applyUpdate(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.update')) {
            return $this->forbidden();
        }
        $staged = $this->stagedPath();
        if (!is_file($staged)) {
            $this->flash('error', 'No staged update — upload first.');
            return Response::redirect($r->basePath . '/admin/updates');
        }
        [$ok, $err, $backup] = $this->updater()->apply($staged);
        if (!$ok) {
            $this->flash('error', 'Update failed: ' . $err
                . ($backup ? ' Replaced files were backed up to ' . basename($backup) : ''));
            return Response::redirect($r->basePath . '/admin/updates');
        }
        @unlink($staged);
        // New files are live — run pending core migrations against this DB.
        (new \Core\Database\Migrator(
            $this->db(), WC_ROOT . '/database/migrations'
        ))->migrate();
        $this->c->get(\Core\Audit\AuditLog::class)->record('core.update', $u->id, [
            'backup' => $backup ? basename($backup) : null,
        ]);
        $this->flash('ok', 'Update applied; migrations run; replaced files backed up.');
        return Response::redirect($r->basePath . '/admin/updates');
    }

    private function updater(): \Core\Update\CoreUpdater
    {
        $config = $this->c->get(\Core\Config::class);
        return new \Core\Update\CoreUpdater(
            WC_ROOT,
            (string) $config->get('paths.storage', WC_ROOT . '/storage'),
            new \Core\Modules\ArchiveInspector(),
            new \Core\Update\SignatureVerifier(
                (array) $config->get('trust.public_keys', []),
                (bool) $config->get('trust.allow_unsigned', true)
            ),
        );
    }

    private function stagedPath(): string
    {
        return (string) $this->c->get(\Core\Config::class)
            ->get('paths.storage', WC_ROOT . '/storage') . '/staged-update.zip';
    }
}
