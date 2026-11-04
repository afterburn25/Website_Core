<?php
declare(strict_types=1);

namespace Core\Backup;

use Core\Database\Connection;

/**
 * Full local backup/restore. Produces a ZIP in protected storage containing:
 *   meta.json   — backup format version, core version, driver, created_at,
 *                 table list, checksums
 *   db.sql      — portable schema+data dump (no mysqldump/shell dependency)
 *   files/      — configuration + selected protected dirs (uploads live in
 *                 the same archive under files/)
 *
 * Restore extracts to private staging, validates meta + checksums, imports
 * SQL, and only then reports success — an incomplete snapshot is rejected
 * rather than half-applied. Backups are runtime artifacts: they are never
 * committed or shipped in release packages.
 */
final class BackupService
{
    public const FORMAT = 1;
    private const MAX_ROWS_PER_SELECT = 500;

    public function __construct(
        private Connection $db,
        private string $backupDir,
        private string $stagingDir,
        private string $siteRoot,
        private ?string $storageRoot = null,
        private ?string $configRoot = null,
        private ?string $uploadRoot = null,
    ) {
        $this->storageRoot ??= $this->siteRoot . '/storage';
        $this->configRoot ??= $this->siteRoot . '/config';
        $this->uploadRoot ??= $this->siteRoot . '/upload';
    }

    /**
     * File roots a backup may embed and a restore may write back. Kept as
     * one constant so the two directions can never drift; storage/ is
     * deliberately absent (backups must never contain backups).
     */
    private const RESTORABLE_ROOTS = ['config', 'upload', 'modules', 'themes', 'storage/media'];

    /**
     * Create a backup. $includeDirs are site-root-relative paths whose trees
     * are embedded under files/<relpath>/; anything outside RESTORABLE_ROOTS
     * is silently dropped.
     * @return array{0:?string,1:?string} [backup path, error]
     */
    public function create(array $includeDirs = self::RESTORABLE_ROOTS): array
    {
        $includeDirs = array_values(array_intersect($includeDirs, self::RESTORABLE_ROOTS));
        if (!is_dir($this->backupDir) && !@mkdir($this->backupDir, 0750, true)) {
            return [null, 'Backup directory is not writable.'];
        }
        $name = 'backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        $path = $this->backupDir . '/' . $name;
        $tmp = $this->stagingDir . '/bk-' . bin2hex(random_bytes(4));
        if (!is_dir($tmp) && !@mkdir($tmp, 0750, true)) return [null, 'Backup staging directory is not writable.'];

        try {
            // 1. Database dump — consistent-enough snapshot: schema first,
            //    then bounded SELECTs per table.
            $sql = $this->dumpDatabase();
            if (@file_put_contents($tmp . '/db.sql', $sql, LOCK_EX) === false) return [null, 'Database snapshot could not be written to staging.'];

            // 2. Files.
            $manifest = ['files' => [], 'tables' => $this->tables()];
            $filesBase = $tmp . '/files';
            @mkdir($filesBase, 0750, true);
            foreach ($includeDirs as $rel) {
                $src = match ($rel) {
                    'config' => rtrim((string) $this->configRoot, '/\\'),
                    'upload' => rtrim((string) $this->uploadRoot, '/\\'),
                    'storage/media' => rtrim((string) $this->storageRoot, '/\\') . '/media',
                    default => $this->siteRoot . '/' . trim($rel, '/'),
                };
                // A configured root may be deliberately outside the webroot,
                // but it must be a real directory, not a link to an unrelated
                // tree. This avoids archiving secrets by following symlinks.
                if (is_link($src)) {
                    return [null, 'Backup stopped: a source root is a symbolic link.'];
                }
                if (!is_dir($src)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $f) {
                    // isFile() follows links to files; silently skipping links
                    // to directories would also create incomplete backups.
                    if ($f->isLink()) {
                        return [null, 'Backup stopped: a symbolic link was found in a restorable root.'];
                    }
                    if (!$f->isFile()) {
                        continue;
                    }
                    // Normalize to forward slashes — Windows iterators yield
                    // backslashes, and the manifest must be portable.
                    $sub = str_replace('\\', '/', trim($rel, '/') . '/' . substr($f->getPathname(), strlen($src) + 1));
                    $dest = $filesBase . '/' . $sub;
                    @mkdir(dirname($dest), 0750, true);
                    if (!@copy($f->getPathname(), $dest)) return [null, 'Backup stopped because a required file could not be read: ' . $sub];
                    // Hash the actual snapshot bytes, not the potentially
                    // changing original after copy (TOCTOU consistency).
                    $digest = hash_file('sha256', $dest);
                    if ($digest === false) {
                        return [null, 'Backup stopped: a copied file could not be verified.'];
                    }
                    $manifest['files'][$sub] = $digest;
                }
            }

            $meta = [
                'format'   => self::FORMAT,
                'core'     => defined('WC_VERSION') ? WC_VERSION : '0.0.0',
                'driver'   => $this->db->driver(),
                'prefix'   => $this->db->prefix(),
                'created'  => date('c'),
                'tables'   => $manifest['tables'],
                'files'    => $manifest['files'],
                'db_sha256' => hash_file('sha256', $tmp . '/db.sql'),
            ];
            if (@file_put_contents($tmp . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT), LOCK_EX) === false) return [null, 'Backup manifest could not be written.'];

            // 3. Zip it.
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return [null, 'Could not create backup archive.'];
            }
            if (!$zip->addFile($tmp . '/meta.json', 'meta.json')
                || !$zip->addFile($tmp . '/db.sql', 'db.sql')) {
                $zip->close();
                @unlink($path);
                return [null, 'Could not add mandatory backup members.'];
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($filesBase, \FilesystemIterator::SKIP_DOTS)
            );
            $allAdded = true;
            foreach ($it as $f) {
                if ($f->isFile()
                    && !$zip->addFile($f->getPathname(), 'files/' . substr($f->getPathname(), strlen($filesBase) + 1))) {
                    $allAdded = false;
                    break;
                }
            }
            $closed = $zip->close();
            if (!$allAdded || !$closed) {
                @unlink($path);
                return [null, 'Backup ZIP could not be completed with all required files.'];
            }
            // Do not show "Backup created and verified" until each stored
            // protected file has been re-read and checksum-verified from the
            // finalized ZIP. Remove incomplete or corrupted snapshots.
            [$verified, $verificationError] = $this->inspect($path);
            if ($verified === null) {
                @unlink($path);
                return [null, 'Backup archive failed final verification: ' . $verificationError];
            }
            return [$path, null];
        } finally {
            $this->removeTree($tmp);
        }
    }

    /** @return list<array{name:string,size:int,mtime:int}> */
    public function list(): array
    {
        $out = [];
        foreach (glob($this->backupDir . '/backup-*.zip') ?: [] as $p) {
            $out[] = ['name' => basename($p), 'size' => (int) filesize($p), 'mtime' => (int) filemtime($p)];
        }
        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $out;
    }

    /**
     * Validate a backup archive without applying it.
     * @return array{0:?array,1:?string} [meta, error]
     */
    public function inspect(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return [null, 'Not a readable archive.'];
        }
        // Member names are written verbatim by extractTo — reject any that
        // could traverse out of the staging dir before it ever runs.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (!\Core\Modules\ArchiveInspector::memberNameOk((string) $zip->getNameIndex($i))) {
                $name = (string) $zip->getNameIndex($i);
                $zip->close();
                return [null, "Illegal member path in backup: {$name}"];
            }
        }
        $metaRaw = $zip->getFromName('meta.json');
        $sqlRaw = $zip->getFromName('db.sql');
        $zip->close();
        // extractTo() restores unix file types: a symlink/hardlink member
        // with a legal name would be recreated in staging, and a later
        // member beneath it would write through the link and escape the
        // staging dir entirely. Names are checked above; types here.
        $nonRegular = (new \Core\Modules\ArchiveInspector())->nonRegularMembers($path);
        if ($nonRegular !== []) {
            return [null, "Illegal member type in backup: {$nonRegular[0]}"];
        }
        if (!is_string($metaRaw) || !is_string($sqlRaw)) {
            return [null, 'Incomplete backup: meta.json and db.sql are both required.'];
        }
        $meta = json_decode($metaRaw, true);
        if (!is_array($meta) || ($meta['format'] ?? 0) !== self::FORMAT) {
            return [null, 'Unsupported backup format.'];
        }
        if (!hash_equals((string) ($meta['db_sha256'] ?? ''), hash('sha256', $sqlRaw))) {
            return [null, 'Backup integrity check failed (db.sql checksum).'];
        }
        if (($meta['prefix'] ?? '') !== $this->db->prefix()) {
            return [null, 'Backup was made with a different table prefix.'];
        }

        // "Verified" in Admin > Backups and the owner-only download route
        // must mean that ALL protected file bytes are present and match the
        // manifest, not only that db.sql passed its SHA-256 check. Reading a
        // streaming ZIP member avoids loading uploads into PHP memory.
        $files = $meta['files'] ?? null;
        if (!is_array($files)) {
            return [null, 'Incomplete backup: protected-file manifest is missing.'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return [null, 'Not a readable archive.'];
        }
        try {
            $members = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $member = (string) $zip->getNameIndex($i);
                if (isset($members[$member])) {
                    return [null, 'Backup contains a duplicate archive member.'];
                }
                $members[$member] = true;
                if ($member === 'meta.json' || $member === 'db.sql' || str_ends_with($member, '/')) {
                    continue;
                }
                if (!str_starts_with($member, 'files/')
                    || !array_key_exists(substr($member, 6), $files)) {
                    return [null, 'Backup contains a protected file missing from its manifest.'];
                }
            }

            foreach ($files as $rel => $expected) {
                if (!is_string($rel) || $rel === ''
                    || !\Core\Modules\ArchiveInspector::memberNameOk($rel)
                    || str_contains($rel, '\\') || str_contains($rel, '//')
                    || str_starts_with($rel, '/') || str_ends_with($rel, '/')
                    || preg_match('#(^|/)\\.(/|$)#', $rel)) {
                    return [null, 'Backup manifest contains an unsafe protected-file path.'];
                }
                if (!is_string($expected) || preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1) {
                    return [null, 'Backup manifest contains an invalid protected-file checksum.'];
                }
                $member = 'files/' . $rel;
                if (!isset($members[$member])) {
                    return [null, 'Backup protected file is missing: ' . $rel];
                }
                $stream = $zip->getStream($member);
                if (!is_resource($stream)) {
                    return [null, 'Backup protected file cannot be read: ' . $rel];
                }
                try {
                    $hash = hash_init('sha256');
                    $bytes = hash_update_stream($hash, $stream);
                    if ($bytes === false || !hash_equals($expected, hash_final($hash))) {
                        return [null, 'Backup protected file integrity check failed: ' . $rel];
                    }
                } finally {
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
        return [$meta, null];
    }

    /**
     * Restore database + embedded files. Driver must match (a MySQL dump
     * cannot be replayed on SQLite and vice versa).
     * @return array{0:bool,1:?string}
     */
    public function restore(string $path, bool $restoreFiles = true): array
    {
        // One restore at a time — concurrent restores interleave file
        // copies and SQL imports into a mixed tree/database.
        @mkdir($this->stagingDir, 0750, true);
        $lockFh = @fopen($this->stagingDir . '/restore.lock', 'c');
        if ($lockFh === false) {
            return [false, 'Cannot open the restore lock file — check storage permissions.'];
        }
        if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
            fclose($lockFh);
            return [false, 'Another restore is already in progress.'];
        }
        try {
            return $this->restoreLocked($path, $restoreFiles);
        } finally {
            flock($lockFh, LOCK_UN);
            fclose($lockFh);
        }
    }

    private function restoreLocked(string $path, bool $restoreFiles): array
    {
        [$meta, $err] = $this->inspect($path);
        if ($meta === null) {
            return [false, $err];
        }
        if ($meta['driver'] !== $this->db->driver()) {
            return [false, 'Database driver mismatch — this backup cannot be restored here.'];
        }

        $tmp = $this->stagingDir . '/rs-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0750, true);
        try {
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::RDONLY);
            $zip->extractTo($tmp);
            $zip->close();

            // Verify every embedded file against its manifest hash before
            // writing anything live.
            foreach (($meta['files'] ?? []) as $rel => $hash) {
                $src = $tmp . '/files/' . $rel;
                if (!is_file($src) || !hash_equals((string) $hash, hash_file('sha256', $src))) {
                    return [false, "Backup file integrity failed: {$rel}"];
                }
            }

            // Preflight every destination before changing the database. On
            // split-ownership hosts deployment files may be readable but not
            // writable by PHP. Identical files need no replacement; a changed
            // protected file stops the restore before any SQL is imported.
            if ($restoreFiles) {
                foreach (($meta['files'] ?? []) as $rel => $hash) {
                    $dest = $this->restoreDestination((string) $rel);
                    if ($dest === null) {
                        continue;
                    }
                    if ($this->restoreDestinationHasSymlink((string) $rel, $dest)) {
                        return [false, "Restore refused a symbolic-link destination: {$rel}"];
                    }
                    if (is_file($dest) && hash_equals((string) $hash, hash_file('sha256', $dest))) {
                        continue;
                    }
                    if (is_link($dest) || (file_exists($dest) && !is_file($dest))) {
                        return [false, "Restore destination is not a regular file: {$rel}"];
                    }
                    if (is_file($dest) && !is_writable($dest)) {
                        return [false, "Restore cannot replace protected file: {$rel}"];
                    }
                    $parent = dirname($dest);
                    while (!file_exists($parent) && dirname($parent) !== $parent) {
                        $parent = dirname($parent);
                    }
                    if (!is_dir($parent) || !is_writable($parent)) {
                        return [false, "Restore cannot create protected file: {$rel}"];
                    }
                }
            }

            // Import statement-by-statement. The dump format separates
            // statements with an explicit delimiter line, and every string
            // value is hex-encoded — user data can never forge a delimiter.
            $sql = (string) file_get_contents($tmp . '/db.sql');
            foreach (explode("\n-- WC-STMT-END\n", $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                $this->db->pdo()->exec($stmt);
            }

            if ($restoreFiles) {
                foreach (($meta['files'] ?? []) as $rel => $hash) {
                    $dest = $this->restoreDestination((string) $rel);
                    if ($dest === null) {
                        continue;
                    }
                    // Recheck immediately before copying too: the directory
                    // tree may have changed since the preflight pass.
                    if ($this->restoreDestinationHasSymlink((string) $rel, $dest)) {
                        return [false, "Restore refused a symbolic-link destination: {$rel}"];
                    }
                    if (is_file($dest) && hash_equals((string) $hash, hash_file('sha256', $dest))) {
                        continue;
                    }
                    @mkdir(dirname($dest), 0750, true);
                    if (!@copy($tmp . '/files/' . $rel, $dest)) return [false, "Restore could not write required file: {$rel}"];
                }
            }
            return [true, null];
        } catch (\Throwable $e) {
            return [false, 'Restore failed: ' . $e->getMessage()];
        } finally {
            $this->removeTree($tmp);
        }
    }

    public function delete(string $name): bool
    {
        $path = $this->backupDir . '/' . basename($name);
        return is_file($path) && @unlink($path);
    }

    // --------------------------------------------------------------------

    /** @return list<string> */
    private function tables(): array
    {
        $prefix = $this->db->prefix();
        if ($this->db->driver() === 'sqlite') {
            return array_column(
                $this->db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE ?", [$prefix . '%']),
                'name'
            );
        }
        // MySQL and MariaDB do not accept native prepared placeholders in
        // SHOW TABLES LIKE. Query information_schema instead so the prefix
        // remains bound data rather than interpolated SQL. LEFT also treats
        // underscores and percent signs in an allowed prefix literally.
        return array_column(
            $this->db->fetchAll(
                'SELECT table_name AS name FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND LEFT(table_name, ?) = ? '
                . 'ORDER BY table_name',
                [strlen($prefix), $prefix]
            ),
            'name'
        );
    }

    private const STMT_END = "\n-- WC-STMT-END\n";

    private function dumpDatabase(): string
    {
        $out = "-- Website Core backup " . date('c') . "\n-- driver=" . $this->db->driver() . self::STMT_END;
        foreach ($this->tables() as $table) {
            $out .= $this->createSql($table) . self::STMT_END;
            $out .= $this->dataSql($table) . self::STMT_END;
        }
        return $out;
    }

    private function createSql(string $table): string
    {
        if ($this->db->driver() === 'sqlite') {
            $row = $this->db->pdo()->query(
                "SELECT sql FROM sqlite_master WHERE name = " . $this->db->pdo()->quote($table)
            )->fetch();
            $create = (string) ($row['sql'] ?? '');
        } else {
            $row = $this->db->pdo()->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
            $create = (string) ($row['Create Table'] ?? '');
        }
        // Collapse to one line so a CREATE can never span the delimiter.
        $create = preg_replace('/\s+/', ' ', $create);
        return "DROP TABLE IF EXISTS `{$table}`;" . self::STMT_END . "{$create};";
    }

    private function dataSql(string $table): string
    {
        $sqlite = $this->db->driver() === 'sqlite';
        $out = '';
        $offset = 0;
        do {
            $rows = $this->db->pdo()->query(
                'SELECT * FROM `' . $table . '` LIMIT ' . self::MAX_ROWS_PER_SELECT . ' OFFSET ' . $offset
            )->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = implode('`,`', array_keys($row));
                // Hex literals keep every string on one line and binary-safe.
                // MySQL converts X'…' to the column charset; SQLite needs a
                // CAST or it would store blobs in TEXT columns. Numerics
                // stay numeric so INTEGER PRIMARY KEY affinity applies.
                $vals = implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL'
                        : (is_int($v) || is_float($v) ? (string) $v
                            : ($sqlite ? "CAST(X'" . bin2hex((string) $v) . "' AS TEXT)"
                                       : "X'" . bin2hex((string) $v) . "'")),
                    array_values($row)
                ));
                $out .= "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
            }
            $offset += self::MAX_ROWS_PER_SELECT;
        } while (count($rows) === self::MAX_ROWS_PER_SELECT);
        return $out;
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

    /**
     * Refuse symlink redirection at any destination component down to its
     * configured restore root. Checking only the final file is insufficient:
     * upload/docs can itself point outside the protected upload tree.
     */
    private function restoreDestinationHasSymlink(string $rel, string $dest): bool
    {
        $base = match (true) {
            str_starts_with($rel, 'config/') => (string) $this->configRoot,
            str_starts_with($rel, 'upload/') => (string) $this->uploadRoot,
            str_starts_with($rel, 'storage/media/') => (string) $this->storageRoot . '/media',
            str_starts_with($rel, 'modules/') => $this->siteRoot . '/modules',
            str_starts_with($rel, 'themes/') => $this->siteRoot . '/themes',
            default => '',
        };
        $base = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $base), '/');
        $path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dest), '/');
        if ($base === '' || !str_starts_with($path, $base . '/')) {
            return true;
        }
        while (true) {
            if (is_link($path)) {
                return true;
            }
            if ($path === $base) {
                return false;
            }
            $parent = dirname($path);
            if ($parent === $path) {
                return true;
            }
            $path = $parent;
        }
    }

    private function restoreDestination(string $rel): ?string
    {
        // Only restore into the declared include roots — a tampered manifest
        // cannot write outside them. '..' or backslash segments would defeat
        // the prefix check, so they are rejected rather than normalized.
        if (!preg_match('#^(config|upload|modules|themes|storage/media)/#', $rel)
            || str_contains($rel, '..')
            || str_contains($rel, '\\')) {
            return null;
        }
        if (str_starts_with($rel, 'config/')) {
            return rtrim((string) $this->configRoot, '/\\') . substr($rel, strlen('config'));
        }
        if (str_starts_with($rel, 'upload/')) {
            return rtrim((string) $this->uploadRoot, '/\\') . substr($rel, strlen('upload'));
        }
        if (str_starts_with($rel, 'storage/media/')) {
            return rtrim((string) $this->storageRoot, '/\\') . substr($rel, strlen('storage'));
        }
        return $this->siteRoot . '/' . $rel;
    }
}
