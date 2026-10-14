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

        $mailRows = $db->fetchAll(
            'SELECT `to`, subject, status, attempts, last_error, created_at, sent_at'
            . ' FROM `' . $db->table('system_messages') . '` ORDER BY id DESC LIMIT 20'
        );

        return $this->page($r, 'system/health', [
            'mailRows' => $mailRows,
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

    /**
     * Support diagnostics: plain-text bundle of versions, package states,
     * queue counts, and journal tails. Config KEYS are listed, values
     * never — secrets stay on the host.
     */
    public function diagnostics(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'system.view')) {
            return $this->forbidden();
        }
        $db = $this->db();
        $config = $this->c->get(\Core\Config::class);
        $storage = (string) $config->get('paths.storage', WC_ROOT . '/storage');

        $out = [];
        $out[] = 'Website Core diagnostics — ' . date('c');
        $out[] = 'core=' . (defined('WC_VERSION') ? WC_VERSION : '?')
            . ' php=' . PHP_VERSION
            . ' db=' . $db->driver()
            . ' sapi=' . PHP_SAPI;
        $out[] = '';

        $out[] = '== packages ==';
        foreach ($db->fetchAll('SELECT package_id, state, updated_at FROM `' . $db->table('packages') . '` ORDER BY package_id') as $p) {
            $out[] = $p['package_id'] . ' ' . $p['state'] . ' (' . $p['updated_at'] . ')';
        }

        $out[] = '';
        $out[] = '== jobs ==';
        $jobs = $this->c->get(\Core\Jobs\JobQueue::class)->counts();
        foreach ($jobs as $k => $v) {
            $out[] = $k . '=' . $v;
        }

        $out[] = '';
        $out[] = '== package journal (tail) ==';
        foreach ($db->fetchAll('SELECT * FROM `' . $db->table('package_journal') . '` ORDER BY id DESC LIMIT 30') as $j) {
            $out[] = ($j['created_at'] ?? '') . ' ' . ($j['package_id'] ?? '')
                . ' ' . ($j['operation'] ?? '') . ' ' . ($j['result'] ?? '')
                . ' ' . ($j['detail'] ?? '');
        }

        $out[] = '';
        $out[] = '== core update journal ==';
        foreach ($this->updater()->journalTail(15) as $line) {
            $out[] = $line;
        }

        $out[] = '';
        $out[] = '== config keys (values withheld) ==';
        $flat = [];
        foreach ((array) $config->all() as $section => $values) {
            if (is_array($values)) {
                foreach ($values as $k => $v) {
                    $flat[] = $section . '.' . $k;
                }
            } else {
                $flat[] = (string) $section;
            }
        }
        sort($flat);
        foreach ($flat as $k) {
            $out[] = $k;
        }

        $out[] = '';
        $out[] = '== storage ==';
        $out[] = 'writable=' . (is_writable($storage) ? 'yes' : 'NO');
        $out[] = 'backups=' . count(glob($storage . '/backups/*.zip') ?: []);

        return Response::text(implode("\n", $out))
            ->header('Content-Disposition', 'attachment; filename="website-core-diagnostics-' . date('Ymd-His') . '.txt"');
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
        // Optional maintenance window: when updates.require_window is set,
        // apply refuses unless the owner created storage/maintenance.flag.
        $config = $this->c->get(\Core\Config::class);
        $storage = (string) $config->get('paths.storage', WC_ROOT . '/storage');
        if ((bool) $config->get('updates.require_window', false)
            && !is_file($storage . '/maintenance.flag')) {
            $this->flash('error',
                'Maintenance window required — create ' . $storage . '/maintenance.flag first.');
            return Response::redirect($r->basePath . '/admin/updates');
        }
        [$ok, $err, $backup] = $this->updater()->apply($staged);
        if (!$ok) {
            $this->flash('error', 'Update failed: ' . $err
                . ($backup ? ' Replaced files were backed up to ' . basename($backup) : ''));
            return Response::redirect($r->basePath . '/admin/updates');
        }
        @unlink($staged);
        // New files are live — flush opcache so the next request can't run
        // stale bytecode, then run pending core migrations against this DB.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        (new \Core\Database\Migrator(
            $this->db(), WC_ROOT . '/database/migrations'
        ))->migrate();
        $this->c->get(\Core\Audit\AuditLog::class)->record('core.update', $u->id, [
            'backup' => $backup ? basename($backup) : null,
        ]);
        $this->c->get(\Core\Events\Dispatcher::class)->dispatch('core.update', [
            'by' => $u->id, 'backup' => $backup ? basename($backup) : null,
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
