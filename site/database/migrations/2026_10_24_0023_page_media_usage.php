<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

/** Backfill page-owned Media references for installations upgraded to 2.0.15. */
return new class extends Migration {
    public function id(): string
    {
        return '2026_10_24_0023';
    }

    public function description(): string
    {
        return 'Track existing page-block and social-image Media references';
    }

    public function up(Connection $db): void
    {
        $usageTable = $db->table('media_usage');
        $db->run("DELETE FROM `{$usageTable}` WHERE context = ?", ['core.page']);

        $publicImages = array_fill_keys(array_map(
            'intval',
            array_column($db->fetchAll(
                'SELECT id FROM `' . $db->table('media') . "` WHERE is_private = 0 AND mime LIKE 'image/%'"
            ), 'id')
        ), true);
        if ($publicImages === []) return;

        foreach ($db->fetchAll(
            'SELECT id, og_media_id, blocks_json FROM `' . $db->table('pages') . '`'
        ) as $page) {
            $ids = [];
            $socialId = (int) ($page['og_media_id'] ?? 0);
            if ($socialId > 0) $ids[$socialId] = $socialId;

            $blocks = json_decode((string) ($page['blocks_json'] ?? '[]'), true);
            if (is_array($blocks)) {
                foreach ($blocks as $block) {
                    $data = is_array($block['data'] ?? null) ? $block['data'] : [];
                    foreach ($data as $key => $value) {
                        if (in_array($key, ['image_id', 'media_id'], true)) {
                            $id = (int) $value;
                            if ($id > 0) $ids[$id] = $id;
                        } elseif ($key === 'media_ids') {
                            foreach (explode(',', (string) $value) as $candidate) {
                                $id = (int) trim($candidate);
                                if ($id > 0) $ids[$id] = $id;
                            }
                        }
                    }
                }
            }

            foreach ($ids as $mediaId) {
                if (!isset($publicImages[$mediaId])) continue;
                $db->insert('media_usage', [
                    'media_id' => $mediaId,
                    'context' => 'core.page',
                    'context_id' => (int) $page['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
};
