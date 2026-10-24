<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Database\Connection;
use Core\Events\Dispatcher;
use Core\License\LicenseValidation;
use Core\Routing\Router;

/**
 * Package lifecycle manager: upload → stage → validate → install →
 * enable/disable → update → uninstall (data-preserving) → purge.
 *
 * - All operations are journaled (package_journal) and serialized by a
 *   lock file so overlapping operations cannot interleave.
 * - Package PHP is never loaded during stage/validate/install — providers
 *   only execute for enabled packages at boot.
 * - Uninstall preserves data by default; 'purge' is a separate explicit
 *   operation the UI requires double confirmation for.
 * - Filesystem and DB changes are NOT one transaction — the journal is the
 *   recovery record (see PACKAGE_SPEC).
 */
final class PackageManager
{
    public function __construct(
        private Connection $db,
        private ModuleRegistry $registry,
        private ArchiveInspector $inspector,
        private string $stagingDir,
        private string $industriesDir,
        private string $addonsDir,
        private string $themesDir,
        private string $lockFile,
        private ?\Core\Update\SignatureVerifier $verifier = null,
        private ?Dispatcher $events = null,
        private ?LicenseValidation $licenseValidator = null,
    ) {}

    /** Fire a lifecycle event for listeners registered via ctx->on(). */
    private function fire(string $event, array $payload = []): void
    {
        if ($this->events !== null) {
            foreach ($this->events->dispatch($event, $payload) as $err) {
                error_log('[website-core] event listener error ' . $err);
            }
        }
    }

    /** Check if the module's license matches the site's license. */
    private function checkLicense(string $packageId, array $manifest): array
    {
        // If no license in manifest, skip check (dev install)
        if (!isset($manifest['license_key'])) {
            return [true, null];
        }

        // If no license validator, skip check (dev install)
        if ($this->licenseValidator === null) {
            return [true, null];
        }

        // Get the site's stored license key
        $licenseRow = $this->db->fetch(
            'SELECT license_key FROM `' . $this->db->table('license') . '` LIMIT 1'
        );
        if (!$licenseRow) {
            return [false, 'No license found on this site.'];
        }

        // Compare keys
        if ($manifest['license_key'] !== $licenseRow['license_key']) {
            return [false, 'Module license does not match site license.'];
        }

        // Check if license is valid
        if (!$this->licenseValidator->isValid()) {
            return [false, 'Site license is not valid or has been deactivated.'];
        }

        return [true, null];
    }

    private function journal(string $id, string $op, string $result, string $detail = ''): void
    {
        $this->db->insert('package_journal', [
            'package_id' => $id, 'operation' => $op, 'result' => $result,
            'detail' => substr($detail, 0, 2000), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Serialize overlapping operations; returns false when already held. */
    private function lock(): bool
    {
        $fh = @fopen($this->lockFile, 'c+');
        if ($fh === false) {
            return false;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }
        // Lock survives for this object's lifetime via the handle.
        $this->lockHandle = $fh;
        return true;
    }

    private mixed $lockHandle = null;

    /** @return array{0:bool,1:string}|null */
    private function protectedOperation(string $packageId): ?array
    {
        return ModuleRegistry::isProtectedPackage($packageId)
            ? [false, 'core/base is a required Website Core component and cannot be installed, disabled, removed, or purged.']
            : null;
    }

    private function unlock(): void
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * Stage an uploaded ZIP: inspect → extract → manifest check.
     * @return array{0:?string,1:?string} [staged dir, error]
     */
    public function stage(string $zipPath): array
    {
        if (!$this->lock()) {
            return [null, 'Another package operation is in progress.'];
        }
        try {
            $errors = $this->inspector->inspect($zipPath);
            if ($errors !== []) {
                return [null, implode(' ', $errors)];
            }
            $dir = $this->inspector->extractTo($zipPath, $this->stagingDir);
            $manifestPath = $this->findManifest($dir);
            if ($manifestPath === null) {
                return [null, 'Manifest not found after extraction.'];
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                return [null, 'Manifest is not valid JSON.'];
            }
            $verrors = Manifest::validate($manifest);
            if ($verrors !== []) {
                return [null, implode(' ', $verrors)];
            }
            // Trust gate BEFORE anything executes or lands on disk:
            // signed packages must match a configured trusted key.
            if ($this->verifier !== null) {
                [$trusted, $terr] = $this->verifier->verify($manifest);
                if (!$trusted) {
                    $this->journal(
                        (string) ($manifest['id'] ?? 'unknown'),
                        'stage', 'rejected', (string) $terr
                    );
                    return [null, $terr];
                }
            }
            $id = (string) $manifest['id'];
            if (($blocked = $this->protectedOperation($id)) !== null) {
                $this->journal($id, 'stage', 'rejected', $blocked[1]);
                return [null, $blocked[1]];
            }
            $existing = $this->db->fetch(
                'SELECT state FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
                [$id]
            );
            $previousState = is_array($existing) ? (string) ($existing['state'] ?? '') : '';
            $this->recordPackage($manifest, 'staged');
            $this->journal($id, 'stage', 'ok', json_encode([
                'dir' => dirname($manifestPath),
                'previous_state' => $previousState,
            ], JSON_UNESCAPED_SLASHES));
            return [dirname($manifestPath), null];
        } finally {
            $this->unlock();
        }
    }

    private function findManifest(string $dir): ?string
    {
        foreach (['addon.json', 'theme.json'] as $name) {
            if (is_file($dir . '/' . $name)) {
                return $dir . '/' . $name;
            }
            foreach (glob($dir . '/*/' . $name) ?: [] as $found) {
                return $found;
            }
        }
        return null;
    }

    private function recordPackage(array $manifest, string $state): void
    {
        $table = $this->db->table('packages');
        $row = $this->db->fetch('SELECT package_id FROM `' . $table . '` WHERE package_id = ?', [$manifest['id']]);
        if ($row) {
            $this->db->update('packages', [
                'state' => $state, 'version' => (string) ($manifest['version'] ?? ''),
                'manifest' => json_encode($manifest), 'updated_at' => date('Y-m-d H:i:s'),
            ], 'package_id = ?', [$manifest['id']]);
        } else {
            $this->db->insert('packages', [
                'package_id' => $manifest['id'], 'state' => $state,
                'version' => (string) ($manifest['version'] ?? ''),
                'manifest' => json_encode($manifest), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function destDir(array $manifest): string
    {
        // Bundled installations may use a descriptive directory such as
        // industry-catering while Module Manager normally uses the package
        // id slug (catering). Always replace the already-discovered active
        // directory so an upgrade cannot create a duplicate package id.
        $this->registry->discover();
        $existing = $this->registry->all()[(string) $manifest['id']]['dir'] ?? null;
        if (is_string($existing) && is_dir($existing)) {
            return $existing;
        }
        $base = match ($manifest['type']) {
            'industry' => $this->industriesDir,
            'theme'    => $this->themesDir,
            default    => $this->addonsDir,
        };
        return $base . '/' . explode('/', (string) $manifest['id'])[1];
    }

    /**
     * Install a staged package: integrity check (when declared), atomic-ish
     * move into its destination. Existing install → 'upgrading' path.
     * @return array{0:bool,1:?string}
     */
    public function install(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        if (!$this->lock()) {
            return [false, 'Another package operation is in progress.'];
        }
        try {
            $row = $this->db->fetch(
                'SELECT * FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
                [$packageId]
            );
            if (!$row || $row['state'] !== 'staged') {
                return [false, "Package {$packageId} is not staged."];
            }
            $manifest = json_decode((string) $row['manifest'], true);
            $journal = $this->db->fetch(
                'SELECT detail FROM `' . $this->db->table('package_journal')
                . "` WHERE package_id = ? AND operation = 'stage' ORDER BY id DESC",
                [$packageId]
            );
            $stageDetail = (string) ($journal['detail'] ?? '');
            $stageMeta = json_decode($stageDetail, true);
            $stagedDir = is_array($stageMeta) ? ($stageMeta['dir'] ?? null) : $stageDetail;
            $previousState = is_array($stageMeta) ? (string) ($stageMeta['previous_state'] ?? '') : '';
            if (!is_string($stagedDir) || !is_dir($stagedDir)) {
                return [false, 'Staged files not found — re-upload the package.'];
            }

            // Integrity verification when the manifest declares hashes.
            $integrityErrors = $this->verifyIntegrity($stagedDir, $manifest);
            if ($integrityErrors !== []) {
                $this->journal($packageId, 'install', 'failed', implode(' ', $integrityErrors));
                $this->recordPackage($manifest, 'failed');
                return [false, implode(' ', $integrityErrors)];
            }

            // License verification
            [$licenseOk, $licenseError] = $this->checkLicense($packageId, $manifest);
            if (!$licenseOk) {
                $this->journal($packageId, 'install', 'failed', $licenseError ?? 'License check failed');
                $this->recordPackage($manifest, 'failed');
                return [false, $licenseError ?? 'License check failed'];
            }

            $dest = $this->destDir($manifest);
            $upgrade = is_dir($dest);
            $backup = null;
            $previousManifest = null;
            $restoredState = in_array($previousState, ['enabled', 'disabled', 'installed'], true)
                ? $previousState
                : 'installed';

            if ($upgrade) {
                $previousManifestPath = is_file($dest . '/addon.json')
                    ? $dest . '/addon.json'
                    : (is_file($dest . '/theme.json') ? $dest . '/theme.json' : null);
                if ($previousManifestPath !== null) {
                    $decoded = json_decode((string) file_get_contents($previousManifestPath), true);
                    $previousManifest = is_array($decoded) ? $decoded : null;
                }
            }
            $this->recordPackage($manifest, $upgrade ? 'upgrading' : 'installed');
            $this->journal($packageId, $upgrade ? 'upgrade.start' : 'install', 'ok', $dest);

            // Replace destination atomically where rename allows.
            if ($upgrade) {
                $backup = $dest . '.old-' . bin2hex(random_bytes(4));
                if (!@rename($dest, $backup)) {
                    if (is_array($previousManifest)) {
                        $this->recordPackage($previousManifest, $restoredState);
                    }
                    $this->journal($packageId, 'upgrade', 'failed', 'could not move existing dir');
                    return [false, 'Could not replace existing installation — check permissions.'];
                }
                if (!@rename($stagedDir, $dest)) {
                    $restored = @rename($backup, $dest);
                    if ($restored && is_array($previousManifest)) {
                        $this->recordPackage($previousManifest, $restoredState);
                    }
                    $this->journal(
                        $packageId,
                        'rollback',
                        $restored ? 'ok' : 'failed',
                        $restored
                            ? 'restored previous package files after replacement failure'
                            : 'could not restore previous package files after replacement failure'
                    );
                    $this->journal($packageId, 'upgrade', 'failed', 'could not move staged dir');
                    return [false, 'Could not install staged files — check permissions.'];
                }
            } else {
                if (!is_dir(dirname($dest))) {
                    mkdir(dirname($dest), 0750, true);
                }
                if (!@rename($stagedDir, $dest)) {
                    // Fallback: copy tree (cross-device or locked dirs).
                    if (!$this->copyTree($stagedDir, $dest)) {
                        $this->journal($packageId, 'install', 'failed', 'move/copy failed');
                        return [false, 'Could not move staged files into place.'];
                    }
                    $this->removeTree($stagedDir);
                }
            }

            // Package migrations run against its installed files; they own
            // only module-prefixed tables (enforced by convention + purge).
            $migDir = trim((string) ($manifest['migrations'] ?? ''), '/');
            if ($migDir !== '' && is_dir($dest . '/' . $migDir)) {
                try {
                    $applied = (new \Core\Database\Migrator($this->db, $dest . '/' . $migDir))->migrate();
                    if ($applied !== []) {
                        $this->journal($packageId, 'migrate', 'ok', implode(', ', $applied));
                    }
                } catch (\Throwable $e) {
                    $this->journal($packageId, 'migrate', 'failed', $e->getMessage());
                    if ($upgrade && is_string($backup) && is_dir($backup)) {
                        $this->removeTree($dest);
                        if (@rename($backup, $dest)) {
                            if (is_array($previousManifest)) {
                                $this->recordPackage($previousManifest, $restoredState);
                            }
                            $this->journal(
                                $packageId,
                                'rollback',
                                'ok',
                                'restored previous package files after migration failure'
                            );
                            return [
                                false,
                                'Package migration failed: ' . $e->getMessage()
                                . ' Previous package files were restored.',
                            ];
                        }
                        $this->journal(
                            $packageId,
                            'rollback',
                            'failed',
                            'could not restore previous package files after migration failure'
                        );
                    }
                    $this->recordPackage($manifest, 'failed');
                    return [false, 'Package migration failed: ' . $e->getMessage()];
                }
            }

            if ($upgrade && is_string($backup) && is_dir($backup)) {
                $this->removeTree($backup);
            }

            $finalState = $upgrade && in_array($previousState, ['enabled', 'disabled'], true)
                ? $previousState
                : 'installed';
            $this->recordPackage($manifest, $finalState);
            $this->journal($packageId, $upgrade ? 'upgrade' : 'install', 'ok', $dest);
            $this->fire($upgrade ? 'package.upgraded' : 'package.installed',
                ['package' => $packageId, 'version' => (string) ($manifest['version'] ?? ''), 'dir' => $dest]);
            return [true, null];
        } finally {
            $this->unlock();
        }
    }

    /**
     * Finalize a package whose files were replaced directly through a host
     * file manager. The disk manifest is validated, module migrations run,
     * and the database version/manifest is synchronized without changing
     * the package's enabled/disabled state.
     */
    public function finalizeFilesystemUpdate(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        if (!$this->lock()) {
            return [false, 'Another package operation is in progress.'];
        }
        try {
            $this->registry->discover();
            $info = $this->registry->all()[$packageId] ?? null;
            if ($info === null || !is_string($info['dir'] ?? null) || !is_dir($info['dir'])) {
                return [false, 'Package files were not found in the active module directory.'];
            }
            if (($info['errors'] ?? []) !== []) {
                $detail = implode(' ', $info['errors']);
                $this->journal($packageId, 'filesystem-update', 'failed', $detail);
                return [false, $detail];
            }
            $manifest = $info['manifest'];
            $row = $this->db->fetch(
                'SELECT state, version FROM `' . $this->db->table('packages') . '` WHERE package_id = ?',
                [$packageId]
            );
            if (!is_array($row)) {
                return [false, 'Package is not registered. Install it through Module Manager first.'];
            }
            $state = (string) ($row['state'] ?? 'installed');
            if (!in_array($state, ['enabled', 'disabled', 'installed'], true)) {
                return [false, 'Package state must be enabled, disabled, or installed before finalizing files.'];
            }

            [$licenseOk, $licenseError] = $this->checkLicense($packageId, $manifest);
            if (!$licenseOk) {
                $this->journal($packageId, 'filesystem-update', 'failed', $licenseError ?? 'License check failed');
                return [false, $licenseError ?? 'License check failed'];
            }

            $migDir = trim((string) ($manifest['migrations'] ?? ''), '/');
            if ($migDir !== '' && is_dir($info['dir'] . '/' . $migDir)) {
                try {
                    $applied = (new \Core\Database\Migrator(
                        $this->db,
                        $info['dir'] . '/' . $migDir
                    ))->migrate();
                    if ($applied !== []) {
                        $this->journal($packageId, 'migrate', 'ok', implode(', ', $applied));
                    }
                } catch (\Throwable $e) {
                    $this->journal($packageId, 'filesystem-update', 'failed', $e->getMessage());
                    return [false, 'Package migration failed: ' . $e->getMessage()];
                }
            }

            $oldVersion = (string) ($row['version'] ?? 'unknown');
            $this->recordPackage($manifest, $state);
            $newVersion = (string) ($manifest['version'] ?? 'unknown');
            $this->journal(
                $packageId,
                'filesystem-update',
                'ok',
                $oldVersion . ' -> ' . $newVersion . '; state preserved as ' . $state
            );
            $this->fire('package.upgraded', [
                'package' => $packageId,
                'version' => $newVersion,
                'dir' => $info['dir'],
                'source' => 'filesystem',
            ]);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            return [true, null];
        } finally {
            $this->unlock();
        }
    }

    /** Enable: dependency order + compatibility gate; provider boots next request. */
    public function enable(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        $this->registry->discover();
        $info = $this->registry->all()[$packageId] ?? null;
        if ($info === null) {
            return [false, 'Package not found on disk.'];
        }
        if ($info['errors'] !== []) {
            $this->journal($packageId, 'enable', 'failed', implode(' ', $info['errors']));
            return [false, implode(' ', $info['errors'])];
        }

        // License check
        [$licenseOk, $licenseError] = $this->checkLicense($packageId, $info['manifest']);
        if (!$licenseOk) {
            $this->journal($packageId, 'enable', 'failed', $licenseError ?? 'License check failed');
            return [false, $licenseError ?? 'License check failed'];
        }

        // Enable dependencies first (deterministic).
        foreach (array_keys($info['manifest']['dependencies'] ?? []) as $dep) {
            if ($this->registry->state($dep) !== 'enabled') {
                $depInfo = $this->registry->all()[$dep] ?? null;
                if ($depInfo === null) {
                    return [false, "Missing dependency {$dep}."];
                }
                [$ok, $err] = $this->enable($dep);
                if (!$ok) {
                    return [false, "Dependency {$dep} failed to enable: {$err}"];
                }
            }
        }
        $this->registry->setState($packageId, 'enabled');
        $this->journal($packageId, 'enable', 'ok');
        $this->fire('package.enabled', ['package' => $packageId]);
        return [true, null];
    }

    /** Disable: blocked when enabled dependents exist. */
    public function disable(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        $this->registry->discover();
        foreach ($this->registry->all() as $id => $info) {
            if ($this->registry->state($id) === 'enabled'
                && isset($info['manifest']['dependencies'][$packageId])) {
                return [false, "Cannot disable — {$id} depends on it."];
            }
        }

        // No license check here — disabling only reduces a module's surface;
        // a deactivated site must be able to disable its licensed modules
        // during recovery. install/enable remain license-gated.

        $this->registry->setState($packageId, 'disabled');
        $this->journal($packageId, 'disable', 'ok');
        $this->fire('package.disabled', ['package' => $packageId]);
        return [true, null];
    }

    /**
     * Uninstall: removes package FILES only, preserves data (module tables,
     * uploads, settings). Purge is a separate explicit operation.
     */
    public function uninstall(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        if (!$this->lock()) {
            return [false, 'Another package operation is in progress.'];
        }
        try {
            $this->registry->discover();
            foreach ($this->registry->all() as $id => $info) {
                if ($this->registry->state($id) === 'enabled'
                    && isset($info['manifest']['dependencies'][$packageId])) {
                    return [false, "Cannot uninstall — {$id} depends on it."];
                }
            }
            $info = $this->registry->all()[$packageId] ?? null;
            if ($info !== null) {
                $dest = $info['dir'];
                $this->removeTree($dest);
            }
            $this->registry->setState($packageId, 'removed');
            $this->journal($packageId, 'uninstall', 'ok', 'data preserved');
            $this->fire('package.uninstalled', ['package' => $packageId, 'data' => 'preserved']);
            return [true, null];
        } finally {
            $this->unlock();
        }
    }

    /** Irreversible data purge — only for 'removed' packages, journaled loudly. */
    public function purgeData(string $packageId): array
    {
        if (($blocked = $this->protectedOperation($packageId)) !== null) {
            return $blocked;
        }
        if ($this->registry->state($packageId) !== 'removed') {
            return [false, 'Purge requires the package to be uninstalled first.'];
        }
        $prefix = explode('/', $packageId)[1] . '_';
        foreach ($this->db->fetchAll(
            $this->db->driver() === 'sqlite'
                ? "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE ?"
                : 'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?',
            [$this->db->prefix() . $prefix . '%']
        ) as $row) {
            $this->db->pdo()->exec('DROP TABLE IF EXISTS `' . $row['name'] . '`');
            $this->journal($packageId, 'purge', 'ok', 'dropped ' . $row['name']);
        }
        // Package rows in shared stores the table-prefix convention can't see.
        foreach (['content_items'] as $shared) {
            if ($this->db->tableExists($shared)) {
                $n = $this->db->run(
                    'DELETE FROM `' . $this->db->table($shared) . '` WHERE package = ?',
                    [$packageId]
                )->rowCount();
                if ($n > 0) {
                    $this->journal($packageId, 'purge', 'ok', "deleted {$n} {$shared} row(s)");
                }
            }
        }
        $this->journal($packageId, 'purge', 'ok', 'data purge complete');
        $this->fire('package.purged', ['package' => $packageId]);
        return [true, null];
    }

    /** @return list<string> */
    private function verifyIntegrity(string $dir, array $manifest): array
    {
        $errors = [];
        $files = $manifest['integrity']['files'] ?? [];
        foreach ($files as $rel => $hash) {
            $path = $dir . '/' . $rel;
            if (!is_file($path)) {
                $errors[] = "integrity: missing {$rel}";
                continue;
            }
            if (!hash_equals((string) $hash, hash_file('sha256', $path))) {
                $errors[] = "integrity: hash mismatch {$rel}";
            }
        }
        return $errors;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private function copyTree(string $src, string $dest): bool
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            $target = $dest . '/' . substr($f->getPathname(), strlen($src) + 1);
            if ($f->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0750, true)) {
                    return false;
                }
            } elseif (!@copy($f->getPathname(), $target)) {
                return false;
            }
        }
        return true;
    }
}
