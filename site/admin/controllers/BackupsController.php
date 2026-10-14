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
            $this->db(), $storage . '/backups', $storage . '/staging', WC_ROOT
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
        if (!$err) {
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
        [$ok, $err] = $this->svc()->restore($path);
        $this->c->get(\Core\Audit\AuditLog::class)->record('backup.restore', $u->id, ['file' => $name, 'ok' => $ok]);
        if ($ok) {
            $this->c->get(\Core\Events\Dispatcher::class)->dispatch(
                'backup.restored', ['file' => $name, 'by' => $u->id]);
        }
        $this->flash($ok ? 'ok' : 'error', $ok ? 'Backup restored.' : (string) $err);
        return $this->redirect($r, '/admin/backups');
    }

    public function delete(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'backups.manage')) {
            return $this->forbidden();
        }
        $this->svc()->delete($r->string('name'));
        $this->flash('ok', 'Backup deleted.');
        return $this->redirect($r, '/admin/backups');
    }
}
