<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Backup\BackupService;
use Core\Http\Request;
use Core\Http\Response;

final class BackupsController extends Controller
{
    private function svc(): BackupService
    {
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');
        return new BackupService(
            $this->db(), $storage . '/backups', $storage . '/staging', WC_ROOT, $storage
        );
    }

    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'backups.manage')) {
            return $this->forbidden();
        }
        $service = $this->svc();
        $backups = $service->list();
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');
        foreach ($backups as &$backup) {
            [$meta, $error] = $service->inspect($storage . '/backups/' . $backup['name']);
            $backup['verified'] = $meta !== null;
            $backup['error'] = $error;
            $backup['core'] = $meta['core'] ?? null;
            $backup['tables'] = count((array) ($meta['tables'] ?? []));
            $backup['files'] = count((array) ($meta['files'] ?? []));
        }
        unset($backup);
        return $this->page($r, 'backups/index', [
            'backups' => $backups,
            'remoteConfigured' => (new \Core\Backup\RemoteBackup($this->c->get(\Core\Config::class)))->configured(),
        ], 'Backups');
    }

    public function create(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'backups.manage')) {
            return $this->forbidden();
        }
        [$path, $err] = $this->svc()->create();
        $remoteError = null;
        if ($err === null) {
            $remoteError = (new \Core\Backup\RemoteBackup($this->c->get(\Core\Config::class)))->upload((string) $path);
            $this->audit('backup.created', ['file' => basename((string) $path)]);
            $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                'backup.created', ['file' => basename((string) $path), 'by' => $u->id]);
            if ($remoteError !== null) $this->audit('backup.remote_failed', ['file' => basename((string) $path), 'error' => $remoteError]);
        }
        $message = $err ?? ($remoteError ? 'Local backup created, but remote replication failed: ' . $remoteError : 'Backup created and verified: ' . basename((string) $path));
        $this->flash($err || $remoteError ? 'error' : 'ok', $message);
        return $this->redirect($r, '/admin/backups');
    }

    public function restore(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$u->isOwner()) {
            // Full restores rewrite data + config — owner-only.
            return $this->forbidden();
        }
        $name = basename($r->string('name'));
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');
        $path = $storage . '/backups/' . $name;
        if (!is_file($path)) {
            $this->flash('error', 'Backup not found.');
            return $this->redirect($r, '/admin/backups');
        }
        // Always capture the current state first and pause cron/public traffic
        // during restore. A failed restore must still leave a rollback point.
        [$safety, $safetyError] = $this->svc()->create();
        if ($safetyError !== null) {
            $this->flash('error', 'Restore stopped because the pre-restore safety backup failed: ' . $safetyError);
            return $this->redirect($r, '/admin/backups');
        }
        $flag = $storage . '/maintenance.flag';
        $alreadyMaintenance = is_file($flag);
        if (!$alreadyMaintenance && @file_put_contents($flag, date('c') . ' pre-restore', LOCK_EX) === false) {
            $this->flash('error', 'Restore stopped because maintenance mode could not be enabled. Safety snapshot: ' . basename((string) $safety));
            return $this->redirect($r, '/admin/backups');
        }
        $ok = false;
        $err = 'Restore did not complete.';
        try {
            [$ok, $err] = $this->svc()->restore($path);
            if ($ok && function_exists('opcache_reset')) @opcache_reset();
        } finally {
            if (!$alreadyMaintenance) @unlink($flag);
        }
        $this->c->get(\Core\Audit\AuditLog::class)->record('backup.restore', $u->id, ['file' => $name, 'ok' => $ok]);
        if ($ok) {
            $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                'backup.restored', ['file' => $name, 'by' => $u->id]);
        }
        $this->flash($ok ? 'ok' : 'error', $ok
            ? 'Backup restored. Pre-restore safety snapshot: ' . basename((string) $safety)
            : (string) $err . ' Safety snapshot: ' . basename((string) $safety));
        return $this->redirect($r, '/admin/backups');
    }

    public function delete(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'backups.manage')) {
            return $this->forbidden();
        }
        $name = basename($r->string('name'));
        if (!$this->svc()->delete($name)) {
            $this->flash('error', 'Backup could not be deleted. It may already be missing or protected by hosting ownership.');
            return $this->redirect($r, '/admin/backups');
        }
        $this->audit('backup.deleted', ['file' => $name]);
        $this->flash('ok', 'Backup deleted.');
        return $this->redirect($r, '/admin/backups');
    }
}
