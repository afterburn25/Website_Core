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
        return $this->page($r, 'backups/index', [
            'backups' => $this->svc()->list(),
        ], 'Backups');
    }

    public function create(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'backups.manage')) {
            return $this->forbidden();
        }
        [$path, $err] = $this->svc()->create();
        if ($err === null) {
            $remoteError = (new \Core\Backup\RemoteBackup($this->c->get(\Core\Config::class)))->upload((string) $path);
            if ($remoteError !== null) $err = 'Local backup created, but remote replication failed: ' . $remoteError;
        }
        if (!$err) {
            $this->audit('backup.created', ['file' => basename((string) $path)]);
            $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                'backup.created', ['file' => basename((string) $path), 'by' => $u->id]);
        }
        $this->flash($err ? 'error' : 'ok', $err ?? 'Backup created: ' . basename((string) $path));
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
        if (!$alreadyMaintenance) file_put_contents($flag, date('c') . ' pre-restore');
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
        $this->svc()->delete($name);
        $this->audit('backup.deleted', ['file' => $name]);
        $this->flash('ok', 'Backup deleted.');
        return $this->redirect($r, '/admin/backups');
    }
}
