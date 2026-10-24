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

    public function saveFromPage(string $name, int $pageId, ?int $adminId): array
    {
        $name = trim(strip_tags($name));
        $page = $this->db->fetch('SELECT blocks_json FROM `' . $this->db->table('pages') . '` WHERE id = ?', [$pageId]);
        if ($name === '' || !$page) return [null, 'Choose a page and enter a pattern name.'];
        $blocks = json_decode((string) ($page['blocks_json'] ?? '[]'), true);
        if (!is_array($blocks) || $blocks === []) return [null, 'The selected page does not contain visual blocks.'];
        $id = (int) $this->db->insert('page_patterns', [
            'name' => mb_substr($name, 0, 190), 'blocks_json' => json_encode($blocks, JSON_UNESCAPED_SLASHES),
            'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return [$id, null];
    }

    public function apply(int $patternId, int $pageId, ?int $adminId): ?string
    {
        $pattern = $this->db->fetch('SELECT * FROM `' . $this->db->table('page_patterns') . '` WHERE id = ?', [$patternId]);
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

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM `' . $this->db->table('page_patterns') . '` WHERE id = ?', [$id]);
    }
}
