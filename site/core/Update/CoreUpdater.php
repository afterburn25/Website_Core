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
            if (file_put_contents($dest, $contents) === false) {
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

    private function isSkipped(string $name): bool
    {
        $name = ltrim($name, '/');
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    public function journalTail(int $lines = 25): array
    {
        $file = $this->storageDir . '/core-update-journal.log';
        if (!is_file($file)) {
            return [];
        }
        return array_slice(array_filter(explode("\n", trim((string) file_get_contents($file)))), -$lines);
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
