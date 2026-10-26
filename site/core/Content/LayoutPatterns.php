<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

final class LayoutPatterns
{
    public function __construct(private Connection $db) {}

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM `' . $this->db->table('page_patterns') . '` ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `' . $this->db->table('page_patterns') . '` WHERE id = ?',
            [$id]
        );
    }

    public function saveFromPage(string $name, int $pageId, ?int $adminId): array
    {
        $name = trim(strip_tags($name));
        $page = $this->db->fetch('SELECT blocks_json FROM `' . $this->db->table('pages') . '` WHERE id = ?', [$pageId]);
        if ($name === '' || !$page) return [null, 'Choose a page and enter a pattern name.'];
        if ($this->nameExists($name)) return [null, 'A pattern with that name already exists.'];
        $blocks = json_decode((string) ($page['blocks_json'] ?? '[]'), true);
        if (!is_array($blocks) || $blocks === []) return [null, 'The selected page does not contain visual blocks.'];
        $id = (int) $this->db->insert('page_patterns', [
            'name' => mb_substr($name, 0, 190), 'blocks_json' => json_encode($blocks, JSON_UNESCAPED_SLASHES),
            'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function rename(int $id, string $name): ?string
    {
        $name = trim(strip_tags($name));
        if ($name === '') return 'Enter a pattern name.';
        if (!$this->find($id)) return 'That pattern no longer exists.';
        if ($this->nameExists($name, $id)) return 'A pattern with that name already exists.';
        $this->db->update('page_patterns', [
            'name' => mb_substr($name, 0, 190),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        return null;
    }

    /** @return array{0:?int,1:?string} */
    public function duplicate(int $id, ?int $adminId): array
    {
        $pattern = $this->find($id);
        if (!$pattern) return [null, 'That pattern no longer exists.'];
        $root = mb_substr((string) $pattern['name'] . ' copy', 0, 180);
        $name = $root;
        for ($number = 2; $this->nameExists($name); $number++) $name = $root . ' ' . $number;
        $copyId = (int) $this->db->insert('page_patterns', [
            'name' => $name, 'blocks_json' => $pattern['blocks_json'], 'created_by' => $adminId,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return [$copyId, null];
    }

    public function apply(int $patternId, int $pageId, ?int $adminId): ?string
    {
        $pattern = $this->find($patternId);
        $page = $this->db->fetch('SELECT * FROM `' . $this->db->table('pages') . '` WHERE id = ?', [$pageId]);
        if (!$pattern || !$page) return 'Pattern or page not found.';
        $this->db->transaction(function () use ($pattern, $page, $pageId, $adminId): void {
            $this->db->insert('page_revisions', [
                'page_id' => $pageId, 'title' => $page['title'], 'body' => $page['body'],
                'blocks_json' => $page['blocks_json'] ?? null, 'saved_by' => $adminId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->update('pages', [
                'blocks_json' => $pattern['blocks_json'], 'status' => 'draft', 'updated_by' => $adminId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$pageId]);
        });
        return null;
    }

    public function delete(int $id): bool
    {
        return $this->db->run('DELETE FROM `' . $this->db->table('page_patterns') . '` WHERE id = ?', [$id])->rowCount() > 0;
    }

    private function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM `' . $this->db->table('page_patterns') . '` WHERE LOWER(name) = LOWER(?)';
        $params = [$name];
        if ($exceptId !== null) { $sql .= ' AND id <> ?'; $params[] = $exceptId; }
        return (bool) $this->db->fetchColumn($sql, $params);
    }
}
