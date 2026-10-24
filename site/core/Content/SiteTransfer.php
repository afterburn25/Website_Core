<?php
declare(strict_types=1);

namespace Core\Content;

use Core\Database\Connection;

final class SiteTransfer
{
    private const SETTINGS = ['site.name', 'site.tagline', 'site.locale', 'site.logo_media_id', 'theme.active', 'theme.header_layout', 'theme.footer_layout', 'footer.copyright'];

    public function __construct(private Connection $db) {}

    public function export(): array
    {
        $settings = [];
        foreach ($this->db->fetchAll('SELECT `key`,`value` FROM `' . $this->db->table('settings') . '`') as $row) {
            if (in_array($row['key'], self::SETTINGS, true)) $settings[$row['key']] = json_decode((string) $row['value'], true);
        }
        return ['format' => 'website-core-site', 'version' => 1, 'exported_at' => date('c'), 'core' => WC_VERSION,
            'settings' => $settings,
            'pages' => $this->db->fetchAll('SELECT slug,title,body,blocks_json,status,meta_title,meta_description,noindex,canonical,schema_json FROM `' . $this->db->table('pages') . '` WHERE deleted_at IS NULL ORDER BY id'),
            'menus' => $this->db->fetchAll('SELECT location,label FROM `' . $this->db->table('menus') . '` ORDER BY id'),
            'menu_items' => $this->db->fetchAll('SELECT i.id source_id,m.location menu_location,i.parent_id source_parent_id,i.label,i.url,i.sort_order,i.target,p.slug linked_page_slug FROM `' . $this->db->table('menu_items') . '` i JOIN `' . $this->db->table('menus') . '` m ON m.id=i.menu_id LEFT JOIN `' . $this->db->table('pages') . '` p ON p.id=i.page_id ORDER BY m.id,i.sort_order,i.id'),
            'languages' => $this->db->fetchAll('SELECT locale,name,active FROM `' . $this->db->table('site_languages') . '` ORDER BY locale'),
            'translations' => $this->db->fetchAll('SELECT p.slug page_slug,t.locale,t.slug,t.title,t.body,t.blocks_json,t.meta_title,t.meta_description,t.schema_json FROM `' . $this->db->table('page_translations') . '` t JOIN `' . $this->db->table('pages') . '` p ON p.id=t.page_id ORDER BY p.id,t.locale')];
    }

    /** Merge portable content into this site; identities, secrets and submissions are never imported. */
    public function import(array $document): array
    {
        if (($document['format'] ?? '') !== 'website-core-site' || (int) ($document['version'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('Unsupported site export document.');
        }
        $counts = ['pages' => 0, 'settings' => 0, 'menus' => 0, 'translations' => 0];
        $this->db->transaction(function () use ($document, &$counts): void {
            foreach ((array) ($document['settings'] ?? []) as $key => $value) {
                if (!in_array($key, self::SETTINGS, true)) continue;
                $this->upsert('settings', 'key', (string) $key, ['value' => json_encode($value, JSON_UNESCAPED_SLASHES), 'updated_at' => date('Y-m-d H:i:s')]);
                $counts['settings']++;
            }
            foreach ((array) ($document['pages'] ?? []) as $page) {
                if (!is_array($page)) continue;
                $slug = trim((string) ($page['slug'] ?? ''));
                $title = trim((string) ($page['title'] ?? ''));
                if (!preg_match('/^[a-z0-9][a-z0-9\-\/]*$/', $slug) || $title === '') continue;
                $data = ['title' => mb_substr(strip_tags($title), 0, 190), 'body' => (new Sanitizer())->html((string) ($page['body'] ?? '')),
                    'blocks_json' => $this->jsonArray($page['blocks_json'] ?? '[]'), 'status' => 'draft',
                    'meta_title' => mb_substr(strip_tags((string) ($page['meta_title'] ?? '')), 0, 190),
                    'meta_description' => mb_substr(strip_tags((string) ($page['meta_description'] ?? '')), 0, 300),
                    'noindex' => !empty($page['noindex']) ? 1 : 0, 'canonical' => (string) ($page['canonical'] ?? ''),
                    'schema_json' => $this->jsonObject($page['schema_json'] ?? null),
                    'review_status' => 'draft', 'updated_at' => date('Y-m-d H:i:s')];
                $this->upsert('pages', 'slug', $slug, $data + ['created_at' => date('Y-m-d H:i:s')]);
                $counts['pages']++;
            }
            $menuIds = [];
            foreach ((array) ($document['menus'] ?? []) as $menu) {
                if (!is_array($menu) || !preg_match('/^[a-z0-9_-]{1,60}$/', (string) ($menu['location'] ?? ''))) continue;
                $location = (string) $menu['location'];
                $this->upsert('menus', 'location', $location, ['label' => mb_substr(strip_tags((string) ($menu['label'] ?? $location)), 0, 120), 'created_at' => date('Y-m-d H:i:s')]);
                $menuIds[$location] = (int) $this->db->fetchColumn('SELECT id FROM `' . $this->db->table('menus') . '` WHERE location = ?', [$location]);
                $this->db->run('DELETE FROM `' . $this->db->table('menu_items') . '` WHERE menu_id = ?', [$menuIds[$location]]);
                $counts['menus']++;
            }
            $itemIds = [];
            foreach ((array) ($document['menu_items'] ?? []) as $item) {
                if (!is_array($item) || !isset($menuIds[$item['menu_location'] ?? ''])) continue;
                $linkedPage = !empty($item['linked_page_slug']) ? $this->db->fetchColumn('SELECT id FROM `' . $this->db->table('pages') . '` WHERE slug = ?', [(string) $item['linked_page_slug']]) : false;
                $target = in_array(($item['target'] ?? 'self'), ['self', 'tab', 'window'], true) ? $item['target'] : 'self';
                $newId = (int) $this->db->insert('menu_items', ['menu_id' => $menuIds[$item['menu_location']], 'parent_id' => null,
                    'label' => mb_substr(strip_tags((string) ($item['label'] ?? 'Link')), 0, 190), 'url' => mb_substr((string) ($item['url'] ?? '/'), 0, 500),
                    'sort_order' => (int) ($item['sort_order'] ?? 0), 'target' => $target, 'page_id' => $linkedPage !== false ? (int) $linkedPage : null,
                    'created_at' => date('Y-m-d H:i:s')]);
                $itemIds[(int) ($item['source_id'] ?? 0)] = $newId;
            }
            foreach ((array) ($document['menu_items'] ?? []) as $item) {
                $source = (int) ($item['source_id'] ?? 0); $parent = (int) ($item['source_parent_id'] ?? 0);
                if ($source && $parent && isset($itemIds[$source], $itemIds[$parent])) $this->db->update('menu_items', ['parent_id' => $itemIds[$parent]], 'id = ?', [$itemIds[$source]]);
            }
            foreach ((array) ($document['languages'] ?? []) as $language) {
                if (!is_array($language) || !preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', (string) ($language['locale'] ?? ''))) continue;
                $this->upsert('site_languages', 'locale', (string) $language['locale'], ['name' => mb_substr(strip_tags((string) ($language['name'] ?? $language['locale'])), 0, 80), 'active' => !empty($language['active']) ? 1 : 0, 'created_at' => date('Y-m-d H:i:s')]);
            }
            foreach ((array) ($document['translations'] ?? []) as $translation) {
                if (!is_array($translation)) continue;
                $pageId = (int) $this->db->fetchColumn('SELECT id FROM `' . $this->db->table('pages') . '` WHERE slug = ?', [(string) ($translation['page_slug'] ?? '')]);
                $locale = (string) ($translation['locale'] ?? '');
                if (!$pageId || !preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $locale)) continue;
                $data = ['slug' => (string) ($translation['slug'] ?? ''), 'title' => mb_substr(strip_tags((string) ($translation['title'] ?? '')), 0, 190),
                    'body' => (new Sanitizer())->html((string) ($translation['body'] ?? '')), 'blocks_json' => $this->jsonArray($translation['blocks_json'] ?? '[]'),
                    'meta_title' => mb_substr(strip_tags((string) ($translation['meta_title'] ?? '')), 0, 190),
                    'meta_description' => mb_substr(strip_tags((string) ($translation['meta_description'] ?? '')), 0, 500),
                    'schema_json' => $this->jsonObject($translation['schema_json'] ?? null), 'updated_at' => date('Y-m-d H:i:s')];
                $exists = $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table('page_translations') . '` WHERE page_id = ? AND locale = ?', [$pageId, $locale]);
                if ($exists) $this->db->update('page_translations', $data, 'page_id = ? AND locale = ?', [$pageId, $locale]);
                else $this->db->insert('page_translations', ['page_id' => $pageId, 'locale' => $locale] + $data + ['created_at' => date('Y-m-d H:i:s')]);
                $counts['translations']++;
            }
        });
        return $counts;
    }

    private function upsert(string $table, string $key, string $value, array $data): void
    {
        $exists = $this->db->fetchColumn('SELECT COUNT(*) FROM `' . $this->db->table($table) . '` WHERE `' . $key . '` = ?', [$value]);
        if ($exists) $this->db->update($table, $data, '`' . $key . '` = ?', [$value]);
        else $this->db->insert($table, [$key => $value] + $data);
    }

    private function jsonArray(mixed $value): string
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) && array_is_list($decoded)
            ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '[]';
    }

    private function jsonObject(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded) || array_is_list($decoded)) return null;
        unset($decoded['@context']);
        return json_encode(['@context' => 'https://schema.org'] + $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
