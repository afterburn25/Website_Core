<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Media\MediaService;
use PHPUnit\Framework\TestCase;

final class MediaWorkflowTest extends TestCase
{
    public function testOrganizesSearchesTagsAndBulkMovesMedia(): void
    {
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $media = new MediaService($db, new Config('/not-present.php'));
        $first = (int) $db->insert('media', [
            'folder' => '', 'filename' => 'public/a.jpg', 'original_name' => 'Bayou Table.jpg',
            'mime' => 'image/jpeg', 'size_bytes' => 100, 'width' => 1200, 'height' => 800,
            'is_private' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $second = (int) $db->insert('media', [
            'folder' => '', 'filename' => 'public/b.jpg', 'original_name' => 'Dessert.jpg',
            'mime' => 'image/jpeg', 'size_bytes' => 100, 'width' => 800, 'height' => 800,
            'is_private' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertTrue($media->organize($first, 'Events / Fall', 'Catering, Featured, catering', 25, 75));
        self::assertSame(['catering', 'featured'], json_decode((string) $media->find($first)['tags_json'], true));
        self::assertSame(25.0, (float) $media->find($first)['focal_x']);
        self::assertCount(1, $media->list('', 50, 'Bayou'));
        self::assertCount(1, $media->list('', 50, '', 'featured'));
        self::assertSame(1, $media->count('', 'Bayou'));
        self::assertSame(1, $media->count('', '', 'featured'));
        self::assertSame(['catering', 'featured'], $media->tags());

        self::assertSame(2, $media->bulkOrganize([$first, $second], 'Portfolio', 'Event'));
        self::assertCount(2, $media->list('Portfolio'));
        self::assertCount(1, $media->list('Portfolio', 1, '', '', 1));
        self::assertSame(2, $media->count('Portfolio'));
        self::assertSame(['catering', 'event', 'featured'], $media->tags());
    }

    public function testFocalPointsAreClampedAndUnsafeFolderCharactersAreRemoved(): void
    {
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $media = new MediaService($db, new Config('/not-present.php'));
        $id = (int) $db->insert('media', [
            'folder' => '', 'filename' => 'public/a.png', 'original_name' => 'a.png',
            'mime' => 'image/png', 'size_bytes' => 10, 'width' => 10, 'height' => 10,
            'is_private' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $media->organize($id, '../<script>Gallery', [], -20, 140);
        $item = $media->find($id);
        self::assertStringNotContainsString('<', (string) $item['folder']);
        self::assertSame(0.0, (float) $item['focal_x']);
        self::assertSame(100.0, (float) $item['focal_y']);
    }

    public function testUsageResultsIdentifyTheReferencingPage(): void
    {
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $media = new MediaService($db, new Config('/not-present.php'));
        $mediaId = (int) $db->insert('media', [
            'folder' => '', 'filename' => 'public/menu.jpg', 'original_name' => 'menu.jpg',
            'mime' => 'image/jpeg', 'size_bytes' => 100, 'width' => 1200, 'height' => 800,
            'is_private' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pageId = (int) $db->insert('pages', [
            'slug' => 'menus', 'title' => 'Catering Menus', 'body' => '', 'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->insert('media_usage', [
            'media_id' => $mediaId, 'context' => 'core.page', 'context_id' => $pageId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $usages = $media->usages($mediaId);
        self::assertCount(1, $usages);
        self::assertSame('Catering Menus', $usages[0]['page_title']);
    }

    public function testUpgradeMigrationBackfillsExistingPageMediaReferences(): void
    {
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $mediaId = (int) $db->insert('media', [
            'folder' => '', 'filename' => 'public/hero.jpg', 'original_name' => 'hero.jpg',
            'mime' => 'image/jpeg', 'size_bytes' => 100, 'width' => 1200, 'height' => 800,
            'is_private' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pageId = (int) $db->insert('pages', [
            'slug' => 'home', 'title' => 'Welcome', 'body' => '', 'status' => 'published',
            'og_media_id' => $mediaId,
            'blocks_json' => json_encode([
                ['type' => 'hero', 'data' => ['image_id' => (string) $mediaId]],
                ['type' => 'gallery', 'data' => ['media_ids' => $mediaId . ',999']],
            ], JSON_THROW_ON_ERROR),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $migration = require WC_ROOT . '/database/migrations/2026_10_24_0023_page_media_usage.php';
        $migration->up($db);
        $rows = $db->fetchAll(
            'SELECT media_id, context, context_id FROM `' . $db->table('media_usage') . '` WHERE context = ?',
            ['core.page']
        );
        self::assertSame([[
            'media_id' => $mediaId, 'context' => 'core.page', 'context_id' => $pageId,
        ]], array_map(static fn (array $row): array => [
            'media_id' => (int) $row['media_id'],
            'context' => (string) $row['context'],
            'context_id' => (int) $row['context_id'],
        ], $rows));
    }
}
