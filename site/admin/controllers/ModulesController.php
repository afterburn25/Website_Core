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
        // Staged/removed/failed packages aren't on disk in a destination dir,
        // so the registry can't discover them — merge their DB records in.
        $databasePackages = [];
        foreach ($this->db()->fetchAll(
            'SELECT package_id, state, version, manifest FROM `' . $this->db()->table('packages') . '`'
        ) as $row) {
            $databasePackages[(string) $row['package_id']] = $row;
            $storedManifest = json_decode((string) $row['manifest'], true) ?: [];
            if (!isset($packages[$row['package_id']])) {
                $packages[$row['package_id']] = [
                    'manifest' => $storedManifest,
                    'dir'      => null,
                    'state'    => $row['state'],
                    'errors'   => [],
                ];
            } elseif ($row['state'] === 'staged') {
                // A staged update has a newer manifest in the DB while the
                // currently installed package still supplies the disk manifest.
                $packages[$row['package_id']]['manifest'] = $storedManifest;
                $packages[$row['package_id']]['state'] = 'staged';
            }
        }
        foreach ($packages as $packageId => &$package) {
            $package['upgrade'] = $package['state'] === 'staged' && is_string($package['dir']) && is_dir($package['dir']);
            $databaseManifest = json_decode(
                (string) ($databasePackages[$packageId]['manifest'] ?? ''),
                true
            ) ?: [];
            $diskVersion = (string) ($package['manifest']['version'] ?? '');
            $databaseVersion = (string) ($databaseManifest['version']
                ?? $databasePackages[$packageId]['version']
                ?? '');
            $package['filesystem_update'] = $package['state'] !== 'staged'
                && $databaseVersion !== ''
                && $diskVersion !== ''
                && $diskVersion !== $databaseVersion;
            $package['database_version'] = $databaseVersion;
        }
        unset($package);
        $journal = $this->db()->fetchAll(
            'SELECT * FROM `' . $this->db()->table('package_journal') . '` ORDER BY id DESC LIMIT 30'
        );
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');
        $stats = ['enabled' => 0, 'attention' => 0, 'staged' => 0];
        foreach ($packages as $package) {
            $state = (string) ($package['state'] ?? '');
            if ($state === 'enabled') $stats['enabled']++;
            if ($state === 'staged') $stats['staged']++;
            if (($package['errors'] ?? []) !== [] || !empty($package['filesystem_update']) || $state === 'failed') $stats['attention']++;
        }
        return $this->page($r, 'modules/index', [
            'packages'   => $packages,
            'journal'    => $journal,
            'canInstall' => $this->auth()->has($u, 'modules.install'),
            'recovery'   => is_file($storage . '/recovery.flag'),
            'stats'      => $stats,
        ], 'Modules');
    }

    /** Toggle recovery mode: boots trusted core with NO package code. */
    public function recovery(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'modules.install')) {
            return $this->forbidden();
        }
        $storage = (string) $this->c->get(\Core\Config::class)->get('paths.storage', WC_ROOT . '/storage');
        $flag = $storage . '/recovery.flag';
        if ($r->string('mode') === 'on') {
            if (!is_dir($storage) && !@mkdir($storage, 0750, true) && !is_dir($storage)) {
                $this->flash('error', 'Recovery mode could not be enabled because private storage is not writable.');
                return $this->redirect($r, '/admin/modules');
            }
            if (@file_put_contents($flag, date('c') . ' by ' . $u->id, LOCK_EX) === false) {
                $this->flash('error', 'Recovery mode flag could not be written. Check storage permissions.');
                return $this->redirect($r, '/admin/modules');
            }
            $this->flash('ok', 'Recovery mode ON — no optional package code will run until turned off.');
        } else {
            if (is_file($flag) && !@unlink($flag)) {
                $this->flash('error', 'Recovery mode flag could not be removed. Delete storage/recovery.flag in File Manager.');
                return $this->redirect($r, '/admin/modules');
            }
            $this->flash('ok', 'Recovery mode off — enabled packages boot normally.');
        }
        $this->c->get(\Core\Audit\AuditLog::class)
            ->record('modules.recovery', $u->id, ['mode' => $r->string('mode')]);
        return $this->redirect($r, '/admin/modules');
    }

    public function upload(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'modules.install')) {
            return $this->forbidden();
        }
        if (($err = $this->uploadError($r, 'package')) !== null) {
            $this->flash('error', $err);
            return $this->redirect($r, '/admin/modules');
        }
        $file = $r->files['package'];
        [$staged, $err] = $this->manager()->stage((string) $file['tmp_name']);
        $this->audit('modules.package_staged', ['package' => $staged, 'ok' => $err === null]);
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
            'finalize'  => $m->finalizeFilesystemUpdate($id),
            'disable'   => $m->disable($id),
            'uninstall' => $m->uninstall($id),
            'purge'     => $r->string('confirm') === $id
                ? $m->purgeData($id)
                : [false, 'Purge requires typing the package id to confirm.'],
            default     => [false, 'Unknown operation.'],
        };
        $this->audit('modules.' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($op)), ['package' => $id, 'ok' => $ok]);
        $this->flash($ok ? 'ok' : 'error', $ok ? ucfirst($op) . ' complete.' : (string) $err);
        return $this->redirect($r, '/admin/modules');
    }
}
