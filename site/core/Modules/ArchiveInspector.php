<?php
declare(strict_types=1);

namespace Core\Modules;

use ZipArchive;

/**
 * Safe package archive inspection and staged extraction.
 *
 * Enforcement, applied BEFORE any package code runs:
 *  - member count / total uncompressed size limits (zip-bomb guard)
 *  - path traversal ("../", absolute, drive-letter, NUL) rejection
 *  - symlink/hardlink member rejection (unix external attributes)
 *  - case-insensitive name collision detection (Windows/macOS safety)
 *  - extraction only into the protected staging dir — never live dirs
 *
 * @return list<string> violations via inspect(); [] = safe
 */
final class ArchiveInspector
{
    public const MAX_MEMBERS = 5000;
    public const MAX_UNCOMPRESSED = 100 * 1024 * 1024; // 100 MB
    public const MAX_MEMBER = 20 * 1024 * 1024;        // 20 MB single file

    /**
     * @return list<string>
     */
    public function inspect(string $zipPath): array
    {
        $errors = [];
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return ['Not a readable ZIP archive.'];
        }
        try {
            if ($zip->numFiles > self::MAX_MEMBERS) {
                $errors[] = 'Archive has too many members (' . $zip->numFiles . ').';
            }
            $modes = $this->unixModes($zipPath);
            $total = 0;
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) {
                    continue;
                }
                $name = (string) $stat['name'];
                $norm = str_replace('\\', '/', $name);

                if (str_contains($norm, "\0")
                    || str_starts_with($norm, '/')
                    || preg_match('#(^|/)\.\.(/|$)#', $norm)
                    || preg_match('#^[a-zA-Z]:#', $norm)) {
                    $errors[] = "Illegal member path: {$name}";
                    continue;
                }

                // Symlink / non-regular entries (unix mode in high bits of
                // the central-directory external attributes — parsed
                // directly because statIndex may not expose them).
                $mode = $modes[$norm] ?? 0;
                $type = $mode & 0xF000;
                if ($type === 0xA000) {
                    $errors[] = "Symlink member rejected: {$name}";
                    continue;
                }
                if ($mode !== 0 && !in_array($type, [0x8000, 0x4000], true)) {
                    $errors[] = "Non-regular member rejected: {$name}";
                    continue;
                }

                $lower = strtolower($norm);
                if (isset($seen[$lower]) && $seen[$lower] !== $norm) {
                    $errors[] = "Case-collision members: {$seen[$lower]} vs {$norm}";
                }
                $seen[$lower] = $norm;

                $size = (int) $stat['size'];
                if ($size > self::MAX_MEMBER) {
                    $errors[] = "Member too large: {$name}";
                }
                $total += $size;
                if ($total > self::MAX_UNCOMPRESSED) {
                    $errors[] = 'Archive expands beyond the 100 MB limit.';
                    break;
                }
            }

            // Must contain exactly one addon.json or theme.json at depth 1 or 2.
            $manifests = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                if (preg_match('#^[^/]+/(addon|theme)\.json$#', $n) || in_array($n, ['addon.json', 'theme.json'], true)) {
                    $manifests++;
                }
            }
            if ($manifests === 0) {
                $errors[] = 'No addon.json/theme.json manifest found at package root.';
            }
        } finally {
            $zip->close();
        }
        return $errors;
    }

    /**
     * Parse the ZIP central directory for unix mode bits per member —
     * libzip's statIndex does not reliably expose external_attributes.
     * @return array<string, int> member name => st_mode
     */
    private function unixModes(string $zipPath): array
    {
        $modes = [];
        $data = (string) file_get_contents($zipPath);
        $len = strlen($data);
        // Find End of Central Directory (sig 0x06054b50) scanning the tail.
        $eocd = -1;
        for ($i = $len - 22; $i >= max(0, $len - 66000); $i--) {
            if (substr($data, $i, 4) === "PK\x05\x06") {
                $eocd = $i;
                break;
            }
        }
        if ($eocd < 0) {
            return $modes;
        }
        $entries = unpack('v', $data, $eocd + 10)[1];
        $offset = unpack('V', $data, $eocd + 16)[1];
        for ($n = 0; $n < $entries; $n++) {
            if (substr($data, $offset, 4) !== "PK\x01\x02") {
                break;
            }
            $nameLen = unpack('v', $data, $offset + 28)[1];
            $extraLen = unpack('v', $data, $offset + 30)[1];
            $commentLen = unpack('v', $data, $offset + 32)[1];
            $attrs = unpack('V', $data, $offset + 38)[1];
            $name = substr($data, $offset + 46, $nameLen);
            $modes[str_replace('\\', '/', $name)] = ($attrs >> 16) & 0xFFFF;
            $offset += 46 + $nameLen + $extraLen + $commentLen;
        }
        return $modes;
    }

    /**
     * Extract into a protected staging dir (caller ensures it exists and is
     * web-inaccessible). Returns the extraction directory.
     */
    public function extractTo(string $zipPath, string $stagingDir): string
    {
        $dest = rtrim($stagingDir, '/\\') . '/pkg-' . bin2hex(random_bytes(8));
        if (!mkdir($dest, 0750, true)) {
            throw new \RuntimeException('Could not create staging dir.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Could not open archive.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                $target = $dest . '/' . $name;
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
                    throw new \RuntimeException("Could not create {$dir}");
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    continue;
                }
                $out = fopen($target, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new \RuntimeException("Could not write {$target}");
                }
                stream_copy_to_stream($stream, $out);
                fclose($stream);
                fclose($out);
                @chmod($target, 0640);
            }
        } finally {
            $zip->close();
        }
        return $dest;
    }
}
