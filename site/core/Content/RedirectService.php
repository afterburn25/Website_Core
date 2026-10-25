<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/**
 * URL redirect manager. Sources are normalized site-relative paths; targets
 * may be site-relative paths or absolute http(s) URLs. Write-time loop
 * detection walks internal chains so A→B→A can never be saved.
 */
final class RedirectService
{
    private const MAX_CHAIN = 20;

    public function __construct(private Connection $db) {}

    /** Normalize a source path: leading slash, no trailing slash, lowercase. */
    public static function normalizeSource(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? $path : rtrim(strtolower($path), '/');
    }

    /** Resolve a request path to a redirect row, or null. Counts the hit. */
    public function match(string $path): ?array
    {
        $source = self::normalizeSource($path);
        $row = $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('redirects') . '` WHERE source = ?',
            [$source]
        );
        if (!$row) {
            return null;
        }
        $this->db->run(
            'UPDATE `' . $this->db->table('redirects') . '` SET hits = hits + 1 WHERE id = ?',
            [(int) $row['id']]
        );
        return $row;
    }

    /** @return array{0:bool,1:?string} */
    public function save(?int $id, string $source, string $target, int $code, ?int $userId): array
    {
        $source = self::normalizeSource($source);
        $target = trim($target);
        if ($source === '' || $source === '//' || $target === '') {
            return [false, 'Source and target are required.'];
        }
        if (mb_strlen($source) > 500 || mb_strlen($target) > 500) {
            return [false, 'Source and target must each be 500 characters or fewer.'];
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return [false, 'Target contains control characters.'];
        }
        $internal = str_starts_with($target, '/');
        if (!$internal && !preg_match('#^https?://#i', $target)) {
            return [false, 'Target must be a site path (/…) or an http(s) URL.'];
        }
        if (!in_array($code, [301, 302, 307, 308], true)) {
            return [false, 'Status must be 301, 302, 307 or 308.'];
        }
        if ($internal && self::normalizeSource($target) === $source) {
            return [false, 'A redirect cannot point at itself.'];
        }
        if ($internal && $this->createsLoop($source, $target)) {
            return [false, 'This would create a redirect loop.'];
        }

        $data = [
            'source' => $source, 'target' => $target,
            'status_code' => $code, 'created_by' => $userId,
        ];
        try {
            if ($id) {
                if (!(bool) $this->db->fetchColumn(
                    'SELECT COUNT(*) FROM `' . $this->db->table('redirects') . '` WHERE id = ?', [$id]
                )) {
                    return [false, 'That redirect no longer exists.'];
                }
                $this->db->update('redirects', $data, 'id = ?', [$id]);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('redirects', $data);
            }
        } catch (\Throwable) {
            return [false, 'A redirect already exists for that source path.'];
        }
        return [true, null];
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM `' . $this->db->table('redirects') . '` WHERE id = ?', [$id]);
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('redirects') . '` ORDER BY source'
        );
    }

    /** Walk the internal redirect chain from $target; true if it reaches $source. */
    private function createsLoop(string $source, string $target): bool
    {
        $current = self::normalizeSource($target);
        for ($i = 0; $i < self::MAX_CHAIN; $i++) {
            if ($current === $source) {
                return true;
            }
            $row = $this->db->fetch(
                'SELECT target FROM `' . $this->db->table('redirects') . '` WHERE source = ?',
                [$current]
            );
            if (!$row || !str_starts_with((string) $row['target'], '/')) {
                return false;
            }
            $current = self::normalizeSource((string) $row['target']);
        }
        return true; // chain longer than the bound — treat as unsafe
    }
}
