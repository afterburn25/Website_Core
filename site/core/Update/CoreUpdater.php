<?php
declare(strict_types=1);

namespace Core\Update;

use Core\Modules\ArchiveInspector;
use ZipArchive;

/**
 * Owner-controlled core self-update.
 *
 * Flow: inspect() a Website-Core-*-UPDATE.zip — archive safety via the same
 * ArchiveInspector packages use, a version read from the bundled bootstrap,
 * and an optional update.json manifest. The manifest carries a sha256 map
 * of every member plus an optional Ed25519 signature over itself; when a
 * SignatureVerifier is injected it decides acceptance exactly as it does
 * for packages. apply() re-verifies immediately before writing (the file
 * could have been swapped since review), validates each member's hash
 * against the manifest when one is present, backs up every file it will
 * replace into storage/update-backups/, then writes — the caller runs
 * pending migrations and audits the result.
 *
 * Site-owned paths (config.php, installed.lock, storage/, upload/, and
 * installed modules/themes) are never touched by an update.
 */
final class CoreUpdater
{
    /** Paths an update archive may never write (relative to root). */
    private const SKIP_PREFIXES = [
        'config/config.php',
        'config/installed.lock',
        'storage/',
        'upload/',
        'modules/industries/',
        'modules/addons/',
    ];

    public function __construct(
        private string $rootDir,
        private string $storageDir,
        private ArchiveInspector $inspector,
        private ?SignatureVerifier $verifier = null,
    ) {
        $this->rootDir = rtrim($this->rootDir, '/\\');
        $this->storageDir = rtrim($this->storageDir, '/\\');
    }

    /**
     * Inspect an uploaded update archive.
     * @return array{0:?array{version:string,members:int,signed:bool,manifest:array},1:?string}
     */
    public function inspect(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            return [null, 'The staged update file does not exist.'];
        }
        if (!is_readable($zipPath)) {
            return [null, 'The staged update file is not readable by PHP.'];
        }
        $errors = $this->inspector->inspectSafety($zipPath);
        if ($errors !== []) {
            return [null, implode(' ', $errors)];
        }
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::RDONLY);
        $bootstrap = $zip->getFromName('includes/bootstrap.php');
        if ($bootstrap === false) {
            $zip->close();
            return [null, 'Not a Website Core update archive (missing includes/bootstrap.php).'];
        }
        if (!preg_match("/define\\('WC_VERSION', '([^']+)'\\)/", $bootstrap, $m)) {
            $zip->close();
            return [null, 'Update archive does not declare a version.'];
        }
        $version = $m[1];
        // Rollback floor: an archive older than the running core must never
        // silently downgrade the site — intentional recovery goes through
        // backup restore, not an update artifact.
        $running = defined('WC_VERSION') ? WC_VERSION : null;
        if ($running !== null && !self::versionGe($version, $running)) {
            $zip->close();
            return [null, "Refusing downgrade: archive is {$version}, running {$running}."];
        }
        $manifestJson = $zip->getFromName('update.json');
        $members = $zip->numFiles;
        $zip->close();

        $manifest = [];
        if ($manifestJson !== false) {
            $manifest = json_decode((string) $manifestJson, true);
            if (!is_array($manifest)) {
                return [null, 'update.json is malformed.'];
            }
            if (($manifest['product'] ?? '') !== 'website-core') {
                return [null, 'update.json is not a Website Core update manifest.'];
            }
            // The manifest's declared version must match the code it ships —
            // metadata is not allowed to describe a different release.
            $declared = $manifest['version'] ?? null;
            if (is_string($declared) && $declared !== '' && $declared !== $version) {
                return [null, "update.json version {$declared} does not match bundled bootstrap {$version}."];
            }
            // Rollback/replay posture (both fields optional): expires
            // rejects stale metadata; min_core rejects archives whose
            // declared floor is newer than the running core.
            $expires = $manifest['expires'] ?? null;
            if (is_string($expires) && $expires !== '') {
                $expTs = strtotime($expires);
                if ($expTs === false) {
                    return [null, "update.json has an unparseable expires: {$expires}"];
                }
                if ($expTs < time()) {
                    return [null, "Update metadata expired at {$expires} — a replayed or stale archive is refused."];
                }
            }
            $minCore = $manifest['min_core'] ?? null;
            $running = defined('WC_VERSION') ? WC_VERSION : null;
            if (is_string($minCore) && $minCore !== '' && $running !== null
                && !self::versionGe($running, $minCore)) {
                return [null, "Update requires core >= {$minCore}; running {$running}."];
            }
        }

        if ($this->verifier !== null) {
            [$ok, $err] = $this->verifier->verify($manifest);
            if (!$ok) {
                return [null, 'Signature check failed: ' . $err];
            }
        }
        return [[
            'version' => $version,
            'members' => $members,
            'signed' => is_string($manifest['signature'] ?? null),
            'manifest' => $manifest,
        ], null];
    }

    /**
     * Apply an inspected update archive over the deployment root.
     * Creates storage/update-backups/update-TS.zip containing every file
     * being replaced, writes the new members, appends a journal line.
     * @return array{0:bool,1:?string,2:?string} ok, error, backup path
     */
    public function apply(string $zipPath): array
    {
        // One apply at a time — concurrent applies would interleave
        // member writes and leave a mixed file tree.
        @mkdir($this->storageDir, 0777, true);
        $lockFh = @fopen($this->storageDir . '/update.lock', 'c');
        if ($lockFh === false) {
            return [false, 'Cannot open the update lock file in storage — check permissions.', null];
        }
        if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
            fclose($lockFh);
            return [false, 'Another update is already in progress.', null];
        }
        try {
            return $this->applyLocked($zipPath);
        } finally {
            flock($lockFh, LOCK_UN);
            fclose($lockFh);
        }
    }

    private function applyLocked(string $zipPath): array
    {
        // Re-inspect immediately before writing — the upload could have
        // been swapped between the review and confirm steps.
        [$info, $err] = $this->inspect($zipPath);
        if ($err !== null || $info === null) {
            return [false, $err ?? 'inspect failed', null];
        }
        $hashes = $info['manifest']['files'] ?? null;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return [false, 'Cannot open update archive.', null];
        }

        // Validation pass BEFORE any write: every payload member must match
        // its manifest hash — a corrupt or tampered archive aborts with zero
        // files touched rather than a half-applied update.
        $payload = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if (str_ends_with($name, '/') || $this->isSkipped($name) || $name === 'update.json') {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $zip->close();
                return [false, "Unreadable member {$name}", null];
            }
            if (is_array($hashes) && $hashes !== []) {
                $expected = $hashes[$name] ?? null;
                if (!is_string($expected) || !hash_equals($expected, hash('sha256', $contents))) {
                    $zip->close();
                    return [false, "Integrity check failed for {$name}", null];
                }
            }
            // Refuse existing symlinks at ANY component, including the
            // destination itself, before touching the deployment tree. A
            // realpath() string-prefix test is insufficient: sibling roots
            // can share prefixes, and a missing descendant may hide a link.
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe update destination: {$name}", null];
            }
            $payload[$name] = $contents;
        }

        // A rollback must exist and pass a read-back checksum verification
        // BEFORE the first deployment file is replaced. Previously a failed
        // ZIP open/add/close was ignored while site files were overwritten.
        $backupDir = $this->storageDir . '/update-backups';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
            $zip->close();
            return [false, 'Cannot create the rollback backup directory; no update files changed.', null];
        }
        $backupPath = $backupDir . '/update-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.zip';
        $backup = new ZipArchive();
        if ($backup->open($backupPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            $zip->close();
            return [false, 'Cannot create the rollback archive; no update files changed.', null];
        }
        $originalHashes = [];
        foreach ($payload as $name => $_contents) {
            if (!$this->safeDestination($name)) {
                $zip->close();
                $backup->close();
                @unlink($backupPath);
                return [false, "Refusing unsafe update destination: {$name}", null];
            }
            $dest = $this->rootDir . '/' . $name;
            if (!is_file($dest)) {
                continue;
            }
            $original = @file_get_contents($dest);
            if (!is_string($original) || !$backup->addFromString($name, $original)) {
                $zip->close();
                $backup->close();
                @unlink($backupPath);
                return [false, "Cannot capture rollback file: {$name}", null];
            }
            $originalHashes[$name] = hash('sha256', $original);
        }
        if (!$backup->close()) {
            $zip->close();
            @unlink($backupPath);
            return [false, 'Rollback archive could not be finalized; no update files changed.', null];
        }
        $checkBackup = new ZipArchive();
        if ($checkBackup->open($backupPath, ZipArchive::RDONLY) !== true) {
            $zip->close();
            return [false, 'Rollback archive could not be reopened for verification; no update files changed.', null];
        }
        foreach ($originalHashes as $name => $digest) {
            $saved = $checkBackup->getFromName($name);
            if (!is_string($saved) || !hash_equals($digest, hash('sha256', $saved))) {
                $checkBackup->close();
                $zip->close();
                return [false, "Rollback archive verification failed for {$name}; no update files changed.", null];
            }
        }
        $checkBackup->close();

        $written = 0;
        foreach ($payload as $name => $contents) {
            // Recheck immediately before each write in case a shared-host
            // process changed the filesystem after the validation pass.
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe update destination: {$name}", $backupPath];
            }
            $dest = $this->rootDir . '/' . $name;
            @mkdir(dirname($dest), 0777, true);
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe update destination: {$name}", $backupPath];
            }
            // Temp-write + rename keeps each member atomic — a crash
            // mid-write never leaves a truncated file being served.
            // A unique, exclusively created temp file cannot follow a
            // pre-planted predictable .tmp-PID symlink into another path.
            $tmp = @tempnam(dirname($dest), '.wc-update-');
            if (!is_string($tmp) || dirname($tmp) !== dirname($dest)) {
                if (is_string($tmp)) @unlink($tmp);
                $zip->close();
                return [false, "Cannot prepare safe temporary update file for {$name}", $backupPath];
            }
            if (file_put_contents($tmp, $contents) === false
                || (!@rename($tmp, $dest)
                    // Windows rename() cannot overwrite an existing file.
                    && (!@unlink($dest) || !@rename($tmp, $dest)))) {
                @unlink($tmp);
                $zip->close();
                return [false, "Failed writing {$name}", $backupPath];
            }
            $written++;
        }
        $zip->close();

        $this->journal(sprintf(
            'apply version=%s members=%d backup=%s',
            $info['version'],
            $written,
            basename($backupPath)
        ));
        return [true, null, $backupPath];
    }

    /** Loose semver compare: true when $have satisfies >= $need (dev suffixes treated as lowest). */
    private static function versionGe(string $have, string $need): bool
    {
        $norm = fn (string $v) => preg_replace('/^v/', '', $v);
        return version_compare($norm($have), $norm($need), '>=');
    }

    /**
     * Verify every component below the canonical deployment root, not
     * merely the resolved destination's string prefix. An existing link in
     * an ancestor OR the final member is never an acceptable update target.
     * The root itself may be a legitimate hosting-panel symlink.
     */
    private function safeDestination(string $name): bool
    {
        $root = realpath($this->rootDir);
        if ($root === false || !is_dir($root) || !ArchiveInspector::memberNameOk($name)) {
            return false;
        }
        $parts = explode('/', str_replace('\\', '/', $name));
        $path = $root;
        $last = count($parts) - 1;
        foreach ($parts as $index => $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                return false;
            }
            $path .= '/' . $component;
            if (is_link($path)) {
                return false;
            }
            if ($index < $last && file_exists($path) && !is_dir($path)) {
                return false;
            }
        }
        return !is_dir($path);
    }

    private function isSkipped(string $name): bool
    {
        $name = ltrim($name, '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }
        // Installed themes are site-owned; only the shipped base theme and
        // the dir's own .htaccess are core-writable.
        if (str_starts_with($name, 'themes/')
            && $name !== 'themes/.htaccess'
            && !str_starts_with($name, 'themes/base/')) {
            return true;
        }
        return false;
    }

    /** @return list<string> */
    public function journalTail(int $lines = 25): array
    {
        $file = $this->storageDir . '/core-update-journal.log';
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $contents = @file_get_contents($file);
        if (!is_string($contents)) {
            return [];
        }
        return array_slice(array_filter(explode("\n", trim($contents))), -$lines);
    }

    /** Restore files captured immediately before an update write. */
    public function restoreFiles(string $backupPath): array
    {
        if (!is_file($backupPath)) return [false, 'Rollback archive not found.'];
        $errors = $this->inspector->inspectSafety($backupPath);
        if ($errors !== []) return [false, implode(' ', $errors)];
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::RDONLY) !== true) return [false, 'Rollback archive is unreadable.'];
        $payload = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (str_ends_with($name, '/') || $this->isSkipped($name)) continue;
            $contents = $zip->getFromIndex($i);
            if ($contents === false) { $zip->close(); return [false, "Rollback member is unreadable: {$name}"]; }
            // Preflight ALL destinations before restoring the first file.
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe rollback destination: {$name}"];
            }
            $payload[$name] = $contents;
        }
        foreach ($payload as $name => $contents) {
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe rollback destination: {$name}"];
            }
            $dest = $this->rootDir . '/' . $name;
            @mkdir(dirname($dest), 0750, true);
            if (!$this->safeDestination($name)) {
                $zip->close();
                return [false, "Refusing unsafe rollback destination: {$name}"];
            }
            $tmp = @tempnam(dirname($dest), '.wc-rollback-');
            if (!is_string($tmp) || dirname($tmp) !== dirname($dest)) {
                if (is_string($tmp)) @unlink($tmp);
                $zip->close();
                return [false, "Cannot prepare safe temporary rollback file for {$name}"];
            }
            if (@file_put_contents($tmp, $contents, LOCK_EX) === false || !@rename($tmp, $dest)) {
                @unlink($tmp); $zip->close(); return [false, "Rollback could not restore {$name}"];
            }
        }
        $zip->close();
        $this->journal('rollback archive=' . basename($backupPath));
        return [true, null];
    }

    private function journal(string $entry): void
    {
        @mkdir($this->storageDir, 0777, true);
        file_put_contents(
            $this->storageDir . '/core-update-journal.log',
            '[' . date('c') . '] ' . $entry . "\n",
            FILE_APPEND
        );
    }
}
