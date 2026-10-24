<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

/**
 * Reusable structured content sections: keyed HTML blocks (contact strip,
 * hours table, footer note…) editable once and rendered wherever a theme or
 * page template calls section('key'). Bodies are sanitized at save time,
 * same boundary as page bodies.
 */
final class SectionService
{
    public function __construct(private Connection $db) {}

    /** Render a section's sanitized body, or '' when absent. */
    public function render(string $key): string
    {
        $row = $this->db->fetch(
            'SELECT body FROM `' . $this->db->table('sections') . '` WHERE `key` = ?',
            [$key]
        );
        return (string) ($row['body'] ?? '');
    }

    /** @return array{0:bool,1:?string} */
    public function save(string $key, string $label, string $body, ?int $userId): array
    {
        $key = trim(strtolower($key));
        $key = trim(preg_replace('/[^a-z0-9_]+/', '_', $key), '_');
        if ($key === '' || strlen($key) > 80) {
            return [false, 'Section key must be 1–80 chars of [a-z0-9_].'];
        }
        if ($label === '') {
            return [false, 'Label is required.'];
        }
        $body = (new Sanitizer())->html($body);
        $exists = $this->db->fetch(
            'SELECT id FROM `' . $this->db->table('sections') . '` WHERE `key` = ?',
            [$key]
        );
        $data = [
            'key' => $key, 'label' => $label, 'body' => $body,
            'updated_by' => $userId, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($exists) {
            $this->db->update('sections', $data, 'id = ?', [(int) $exists['id']]);
        } else {
            $this->db->insert('sections', $data);
        }
        return [true, null];
    }

    public function delete(int $id): bool
    {
        return $this->db->run(
            'DELETE FROM `' . $this->db->table('sections') . '` WHERE id = ?', [$id]
        )->rowCount() === 1;
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `' . $this->db->table('sections') . '` ORDER BY `key`'
        );
    }
}
