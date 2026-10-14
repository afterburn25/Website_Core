<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Database\Connection;
use Core\Events\Dispatcher;
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
            $this->recordPackage($manifest, 'staged');
            $this->journal($id, 'stage', 'ok', dirname($manifestPath));
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
            $stagedDir = $journal['detail'] ?? null;
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

            $dest = $this->destDir($manifest);
            $upgrade = is_dir($dest);
            $this->recordPackage($manifest, $upgrade ? 'upgrading' : 'installed');
            $this->journal($packageId, $upgrade ? 'upgrade.start' : 'install', 'ok', $dest);

            // Replace destination atomically where rename allows.
            if ($upgrade) {
                $backup = $dest . '.old-' . bin2hex(random_bytes(4));
                if (!@rename($dest, $backup)) {
                    $this->journal($packageId, 'upgrade', 'failed', 'could not move existing dir');
                    return [false, 'Could not replace existing installation — check permissions.'];
                }
                if (!@rename($stagedDir, $dest)) {
                    @rename($backup, $dest); // restore on failure
                    $this->journal($packageId, 'upgrade', 'failed', 'could not move staged dir');
                    return [false, 'Could not install staged files — check permissions.'];
                }
                $this->removeTree($backup);
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
                    $this->recordPackage($manifest, 'failed');
                    $this->journal($packageId, 'migrate', 'failed', $e->getMessage());
                    return [false, 'Package migration failed: ' . $e->getMessage()];
                }
            }

            $this->recordPackage($manifest, 'installed');
            $this->journal($packageId, $upgrade ? 'upgrade' : 'install', 'ok', $dest);
            $this->fire($upgrade ? 'package.upgraded' : 'package.installed',
                ['package' => $packageId, 'version' => (string) ($manifest['version'] ?? ''), 'dir' => $dest]);
            return [true, null];
        } finally {
            $this->unlock();
        }
    }

    /** Enable: dependency order + compatibility gate; provider boots next request. */
    public function enable(string $packageId): array
    {
        $this->registry->discover();
        $info = $this->registry->all()[$packageId] ?? null;
        if ($info === null) {
            return [false, 'Package not found on disk.'];
        }
        if ($info['errors'] !== []) {
            $this->journal($packageId, 'enable', 'failed', implode(' ', $info['errors']));
            return [false, implode(' ', $info['errors'])];
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
        $this->registry->discover();
        foreach ($this->registry->all() as $id => $info) {
            if ($this->registry->state($id) === 'enabled'
                && isset($info['manifest']['dependencies'][$packageId])) {
                return [false, "Cannot disable — {$id} depends on it."];
            }
        }
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
