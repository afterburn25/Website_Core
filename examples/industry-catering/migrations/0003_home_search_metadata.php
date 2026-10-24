<?php
declare(strict_types=1);

use Core\Database\Connection;
use Core\Database\Migration;

return new class extends Migration {
    public function id(): string
    {
        return 'catering_0003_home_search_metadata';
    }

    public function description(): string
    {
        return 'Complete the untouched starter home page search metadata';
    }

    public function up(Connection $db): void
    {
        $page = $db->fetch(
            'SELECT id,title,meta_title,meta_description FROM `' . $db->table('pages') . '` WHERE slug = ?',
            ['home']
        );
        if ($page === null) {
            return;
        }

        $settingsRow = $db->fetch(
            'SELECT `value` FROM `' . $db->table('settings') . '` WHERE `key` = ?',
            ['site.name']
        );
        $decodedName = $settingsRow === null ? null : json_decode((string) $settingsRow['value'], true);
        $siteName = is_string($decodedName) && trim($decodedName) !== '' ? trim($decodedName) : 'Catering Co.';

        $changes = [];
        if (trim((string) ($page['title'] ?? '')) === 'Welcome') {
            $changes['title'] = mb_substr('Welcome to ' . $siteName, 0, 190);
        }
        if (trim((string) ($page['meta_title'] ?? '')) === '') {
            $changes['meta_title'] = mb_substr($siteName . ' | Louisiana Catering & Events', 0, 60);
        }
        if (trim((string) ($page['meta_description'] ?? '')) === '') {
            $changes['meta_description'] = mb_substr(
                'Discover Louisiana-inspired catering from ' . $siteName
                . ' for weddings, corporate gatherings, private events, and celebrations.',
                0,
                160
            );
        }
        if ($changes !== []) {
            $changes['updated_at'] = date('Y-m-d H:i:s');
            $db->update('pages', $changes, 'id = ?', [(int) $page['id']]);
        }
    }
};
