<?php
declare(strict_types=1);

namespace Core\I18n;

use Core\Content\Sanitizer;
use Core\Database\Connection;

final class ContentTranslations
{
    public function __construct(private Connection $db) {}

    public static function locale(string $value): string
    {
        $value = strtolower(str_replace('_', '-', trim($value)));
        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $value) ? $value : '';
    }

    /** @return list<array> */
    public function languages(bool $activeOnly = true): array
    {
        $where = $activeOnly ? ' WHERE l.active = 1' : '';
        return $this->db->fetchAll(
            'SELECT l.*, COUNT(p.id) AS translation_count FROM `' . $this->db->table('site_languages') . '` l'
            . ' LEFT JOIN `' . $this->db->table('page_translations') . '` t ON t.locale = l.locale'
            . ' LEFT JOIN `' . $this->db->table('pages') . '` p ON p.id = t.page_id AND p.deleted_at IS NULL'
            . $where . ' GROUP BY l.locale, l.name, l.active, l.created_at ORDER BY l.name'
        );
    }

    /** @return list<array{locale:string,name:string,active:int,translated:int,total:int,percent:int}> */
    public function progress(): array
    {
        $total = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('pages') . '` WHERE deleted_at IS NULL'
        );
        return array_map(static function (array $language) use ($total): array {
            $translated = (int) ($language['translation_count'] ?? 0);
            return [
                'locale' => (string) $language['locale'], 'name' => (string) $language['name'],
                'active' => (int) $language['active'], 'translated' => $translated, 'total' => $total,
                'percent' => $total > 0 ? (int) round(($translated / $total) * 100) : 0,
            ];
        }, $this->languages(false));
    }

    public function addLanguage(string $locale, string $name): ?string
    {
        $locale = self::locale($locale); $name = trim(strip_tags($name));
        if ($locale === '' || $name === '') return 'Use a valid locale such as fr or es-mx and enter a language name.';
        try {
            $this->db->insert('site_languages', ['locale' => $locale, 'name' => mb_substr($name, 0, 80), 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            $this->db->update('site_languages', ['name' => mb_substr($name, 0, 80), 'active' => 1], 'locale = ?', [$locale]);
        }
        return null;
    }

    public function setLanguageActive(string $locale, bool $active): ?string
    {
        $locale = self::locale($locale);
        if ($locale === '' || !(bool) $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('site_languages') . '` WHERE locale = ?', [$locale])) {
            return 'That language no longer exists.';
        }
        $this->db->update('site_languages', ['active' => $active ? 1 : 0], 'locale = ?', [$locale]);
        return null;
    }

    public function save(int $pageId, string $locale, array $data): ?string
    {
        $locale = self::locale($locale);
        $page = $this->db->fetch('SELECT id FROM `' . $this->db->table('pages') . '` WHERE id = ? AND deleted_at IS NULL', [$pageId]);
        $language = $locale === '' ? null : $this->db->fetch('SELECT locale FROM `' . $this->db->table('site_languages') . '` WHERE locale = ?', [$locale]);
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9]+/', '-', (string) ($data['slug'] ?? ''))), '-');
        if (!$page || !$language || $title === '' || $slug === '') return 'Page, configured language, title and slug are required.';

        $blocksJson = trim((string) ($data['blocks_json'] ?? '[]')) ?: '[]';
        try {
            $blocks = json_decode($blocksJson, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($blocks) || !array_is_list($blocks)) return 'Visual-block data must be a JSON list.';
        } catch (\JsonException) {
            return 'Visual-block data contains invalid JSON.';
        }
        $schemaJson = trim((string) ($data['schema_json'] ?? ''));
        if ($schemaJson !== '') {
            try {
                if (!is_array(json_decode($schemaJson, true, 64, JSON_THROW_ON_ERROR))) return 'Structured-data JSON must contain an object or list.';
            } catch (\JsonException) {
                return 'Structured-data JSON is invalid.';
            }
        }

        $row = [
            'page_id' => $pageId, 'locale' => $locale, 'slug' => mb_substr($slug, 0, 190),
            'title' => mb_substr($title, 0, 190), 'body' => (new Sanitizer())->html((string) ($data['body'] ?? '')),
            'blocks_json' => json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'meta_title' => mb_substr(strip_tags((string) ($data['meta_title'] ?? '')), 0, 190),
            'meta_description' => mb_substr(strip_tags((string) ($data['meta_description'] ?? '')), 0, 500),
            'schema_json' => $schemaJson !== '' ? $schemaJson : null, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = $this->forPage($pageId, $locale);
        try {
            if ($existing) $this->db->update('page_translations', $row, 'id = ?', [(int) $existing['id']]);
            else { $row['created_at'] = date('Y-m-d H:i:s'); $this->db->insert('page_translations', $row); }
        } catch (\Throwable) {
            return 'That translated slug is already used in this language.';
        }
        return null;
    }

    public function delete(int $pageId, string $locale): bool
    {
        $locale = self::locale($locale);
        if ($pageId < 1 || $locale === '') return false;
        return $this->db->run('DELETE FROM `' . $this->db->table('page_translations') . '` WHERE page_id = ? AND locale = ?', [$pageId, $locale])->rowCount() > 0;
    }

    public function forPage(int $pageId, string $locale): ?array
    {
        return $this->db->fetch('SELECT * FROM `' . $this->db->table('page_translations') . '` WHERE page_id = ? AND locale = ?', [$pageId, self::locale($locale)]);
    }

    public function findPublished(string $locale, string $slug): ?array
    {
        $row = $this->db->fetch(
            'SELECT p.*, t.locale, t.slug translated_slug, t.title translated_title, t.body translated_body, t.blocks_json translated_blocks_json, t.meta_title translated_meta_title, t.meta_description translated_meta_description, t.schema_json translated_schema_json'
            . ' FROM `' . $this->db->table('page_translations') . '` t JOIN `' . $this->db->table('site_languages') . '` l ON l.locale = t.locale AND l.active = 1'
            . ' JOIN `' . $this->db->table('pages') . '` p ON p.id = t.page_id WHERE t.locale = ? AND t.slug = ? AND p.status = \'published\' AND p.deleted_at IS NULL',
            [self::locale($locale), $slug]
        );
        if (!$row) return null;
        foreach (['slug', 'title', 'body', 'blocks_json', 'meta_title', 'meta_description', 'schema_json'] as $field) $row[$field] = $row['translated_' . $field] ?? $row[$field] ?? null;
        return $row;
    }

    public function translatedNav(string $locale): array
    {
        return $this->db->fetchAll(
            'SELECT t.slug, t.title FROM `' . $this->db->table('page_translations') . '` t JOIN `' . $this->db->table('site_languages') . '` l ON l.locale = t.locale AND l.active = 1'
            . ' JOIN `' . $this->db->table('pages') . '` p ON p.id = t.page_id WHERE t.locale = ? AND p.status = \'published\' AND p.deleted_at IS NULL ORDER BY t.title',
            [self::locale($locale)]
        );
    }

    public function alternates(int $pageId): array
    {
        return $this->db->fetchAll(
            'SELECT t.locale, t.slug, t.title, l.name FROM `' . $this->db->table('page_translations') . '` t JOIN `' . $this->db->table('site_languages') . '` l ON l.locale = t.locale AND l.active = 1 WHERE t.page_id = ? ORDER BY l.name',
            [$pageId]
        );
    }
}
