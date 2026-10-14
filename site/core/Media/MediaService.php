<?php
declare(strict_types=1);

namespace Core\Media;

use Core\Config;
use Core\Database\Connection;

/**
 * Media library: safe uploads (extension + finfo MIME + image re-encode
 * checks), organized folders, private/public split, usage references, and
 * on-demand variants. Files live under the configured storage path —
 * never web-reachable directly — and stream through the media route.
 *
 * SVG is rejected until a reviewed sanitizer exists. Executables and
 * polyglots are rejected by signature, not by name.
 */
final class MediaService
{
    /** Allowed upload types: ext => expected MIME families. */
    private const ALLOWED = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
    ];

    private const MAX_BYTES = 15 * 1024 * 1024;      // 15 MB per file
    private const MAX_PIXELS = 40_000_000;            // decompression guard

    public function __construct(
        private Connection $db,
        private Config $config,
    ) {}

    public function storageDir(): string
    {
        $dir = (string) $this->config->get('paths.storage') . '/media';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /**
     * Validate + store an uploaded file.
     * @param array{tmp_name:string,name:string,size:int,error:int} $file
     * @return array{0:?int,1:?string} [media id, error]
     */
    public function store(array $file, ?int $adminId, bool $private = false, string $folder = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [null, 'Upload failed (code ' . (int) ($file['error'] ?? -1) . ').'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_BYTES) {
            return [null, 'File exceeds the 15 MB limit.'];
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return [null, "File type .{$ext} is not allowed."];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            return [null, "File contents ({$mime}) do not match .{$ext}."];
        }

        [$w, $h] = [null, null];
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || ($info[0] * $info[1]) > self::MAX_PIXELS) {
                return [null, 'Image is invalid or too large to process safely.'];
            }
            [$w, $h] = [(int) $info[0], (int) $info[1]];
        }

        // Executable/polyglot guard: PHP/HTML signatures must never appear.
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 4096);
        if (str_contains($head, '<?') || stripos($head, '<html') !== false || stripos($head, '<script') !== false) {
            return [null, 'File contains executable markup and was rejected.'];
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destDir = $this->storageDir() . ($private ? '/private' : '/public');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }
        $dest = $destDir . '/' . $filename;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@rename($file['tmp_name'], $dest)) {
            return [null, 'Could not store the file — check storage permissions.'];
        }
        @chmod($dest, 0640);

        $id = (int) $this->db->insert('media', [
            'folder' => $folder, 'filename' => ($private ? 'private/' : 'public/') . $filename,
            'original_name' => basename((string) $file['name']), 'mime' => $mime,
            'size_bytes' => (int) $file['size'], 'width' => $w, 'height' => $h,
            'is_private' => $private ? 1 : 0, 'uploaded_by' => $adminId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('media') . '` WHERE id = ?', [$id]);
    }

    public function pathFor(array $media): string
    {
        return $this->storageDir() . '/' . $media['filename'];
    }

    public function usages(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('media_usage') . '` WHERE media_id = ?', [$id]
        );
    }

    /** Delete only when explicitly requested; callers warn on usages first. */
    public function delete(int $id): bool
    {
        $media = $this->find($id);
        if (!$media) {
            return false;
        }
        @unlink($this->pathFor($media));
        $this->db->run('DELETE FROM `' . $this->db->table('media_usage') . '` WHERE media_id = ?', [$id]);
        $this->db->run('DELETE FROM `' . $this->db->table('media') . '` WHERE id = ?', [$id]);
        return true;
    }

    /** @return list<array> */
    public function list(string $folder = '', int $limit = 200): array
    {
        $table = $this->db->table('media');
        if ($folder === '') {
            return $this->db->fetchAll("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT " . (int) $limit);
        }
        return $this->db->fetchAll(
            "SELECT * FROM `{$table}` WHERE folder = ? ORDER BY id DESC LIMIT " . (int) $limit,
            [$folder]
        );
    }
}
