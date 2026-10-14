<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;
use Core\Modules\PackageManager;

final class ModulesController extends Controller
{
    private function manager(): PackageManager
    {
        return $this->c->get(PackageManager::class);
    }

    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'modules.view')) {
            return $this->forbidden();
        }
        $this->c->get(ModuleRegistry::class)->discover();
        $packages = $this->c->get(ModuleRegistry::class)->all();
        $journal = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('package_journal') . '` ORDER BY id DESC LIMIT 30'
        );
        return $this->page($r, 'modules/index', [
            'packages' => $packages,
            'journal'  => $journal,
        ], 'Modules');
    }

    public function upload(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'modules.install')) {
            return $this->forbidden();
        }
        $file = $r->files['package'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Choose a package ZIP to upload.');
            return $this->redirect($r, '/admin/modules');
        }
        [$staged, $err] = $this->manager()->stage((string) $file['tmp_name']);
        $this->flash($err ? 'error' : 'ok', $err ?? 'Package staged — review and install.');
        return $this->redirect($r, '/admin/modules');
    }

    public function action(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'modules.install')) {
            return $this->forbidden();
        }
        $id = (string) ($r->attributes['id'] ?? '');
        $op = (string) ($r->attributes['op'] ?? '');
        $m = $this->manager();

        [$ok, $err] = match ($op) {
            'install'   => $m->install($id),
            'enable'    => $m->enable($id),
            'disable'   => $m->disable($id),
            'uninstall' => $m->uninstall($id),
            'purge'     => $r->string('confirm') === $id
                ? $m->purgeData($id)
                : [false, 'Purge requires typing the package id to confirm.'],
            default     => [false, 'Unknown operation.'],
        };
        $this->flash($ok ? 'ok' : 'error', $ok ? ucfirst($op) . ' complete.' : (string) $err);
        return $this->redirect($r, '/admin/modules');
    }
}
