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
            $dest = $this->rootDir . '/' . $name;
            $resolvedDir = realpath(dirname($dest)) ?: dirname($dest);
            if (!str_starts_with(str_replace('\\', '/', $resolvedDir), str_replace('\\', '/', $this->rootDir))) {
                $zip->close();
                return [false, "Refusing to write outside the deployment root: {$name}", null];
            }
            $payload[$name] = $contents;
        }

        $backupPath = $this->storageDir . '/update-backups/update-' . date('Ymd-His') . '.zip';
        @mkdir(dirname($backupPath), 0777, true);
        $backup = new ZipArchive();
        $backup->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $written = 0;
        foreach ($payload as $name => $contents) {
            $dest = $this->rootDir . '/' . $name;
            if (is_file($dest)) {
                $backup->addFromString($name, (string) file_get_contents($dest));
            }
            @mkdir(dirname($dest), 0777, true);
            // Temp-write + rename keeps each member atomic — a crash
            // mid-write never leaves a truncated file being served.
            $tmp = $dest . '.tmp-' . getmypid();
            if (file_put_contents($tmp, $contents) === false
                || (!@rename($tmp, $dest)
                    // Windows rename() cannot overwrite an existing file.
                    && (!@unlink($dest) || !@rename($tmp, $dest)))) {
                @unlink($tmp);
                $zip->close();
                $backup->close();
                return [false, "Failed writing {$name}", $backupPath];
            }
            $written++;
        }
        $zip->close();
        $backup->close();

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
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (str_ends_with($name, '/') || $this->isSkipped($name)) continue;
            $contents = $zip->getFromIndex($i);
            if ($contents === false) { $zip->close(); return [false, "Rollback member is unreadable: {$name}"]; }
            $dest = $this->rootDir . '/' . $name;
            @mkdir(dirname($dest), 0750, true);
            $tmp = $dest . '.rollback-' . getmypid();
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
