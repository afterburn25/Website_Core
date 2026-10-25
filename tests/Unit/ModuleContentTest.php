<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\ContentItems;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ModuleContentTest extends TestCase
{
    private Connection $db;
    private ContentItems $items;
    private ModuleContext $context;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite();
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $services = [];
        $this->context = new ModuleContext(
            'acme/catering',
            new Router(),
            new Dispatcher(),
            serviceResolver: static function (string $id) use (&$services): mixed {
                return $services[$id];
            },
        );
        $this->context->contentType('service', 'Services', [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'image', 'label' => 'Image', 'type' => 'media'],
            ['name' => 'gallery', 'label' => 'Gallery', 'type' => 'media_list'],
        ]);
        $registry = new ModuleRegistry($this->db, '/missing/industries', '/missing/addons', '/missing/themes');
        $property = new \ReflectionProperty($registry, 'contexts');
        $property->setValue($registry, [$this->context]);
        $this->items = new ContentItems($this->db, $registry);
        $services[ContentItems::class] = $this->items;
        $services[SettingsRepository::class] = new SettingsRepository($this->db);
    }

    public function testSeedOnceAndTrackCoreMediaUsage(): void
    {
        $first = $this->media('hero.jpg', false);
        $second = $this->media('gallery.jpg', false);

        self::assertSame(1, $this->items->seed('acme/catering', 'service', [[
            'name' => 'Wedding catering', 'image' => (string) $first,
            'gallery' => [(string) $first, (string) $second],
        ]]));
        self::assertSame(0, $this->items->seed('acme/catering', 'service', [[
            'name' => 'Must not overwrite',
        ]]));

        $saved = $this->items->items('acme/catering', 'service');
        self::assertCount(1, $saved);
        self::assertSame('Wedding catering', $saved[0]['fields']['name']);
        self::assertSame(2, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('media_usage') . '` WHERE context_id = ?',
            [(int) $saved[0]['id']]
        ));
    }

    public function testContextSeedingWorksDuringRegistrationAndNeverRepopulates(): void
    {
        $this->context->seedContent('service', [['name' => 'Starter service']]);
        $saved = $this->items->items('acme/catering', 'service');
        self::assertCount(1, $saved);
        self::assertTrue($this->items->delete((int) $saved[0]['id'], 'acme/catering', 'service'));

        $this->context->seedContent('service', [['name' => 'Restored without permission']]);
        self::assertSame([], $this->items->items('acme/catering', 'service'));
    }

    public function testPrivateOrForeignMediaCannotBeSelectedAndDeleteIsPackageScoped(): void
    {
        $private = $this->media('private.pdf', true);
        [$ok, $error] = $this->items->save(null, 'acme/catering', 'service', [
            'name' => 'Private attempt', 'image' => (string) $private,
        ], 1);
        self::assertFalse($ok);
        self::assertStringContainsString('available Media item', (string) $error);

        [$ok, , $id] = $this->items->save(null, 'acme/catering', 'service', [
            'name' => 'Public record',
        ], 1);
        self::assertTrue($ok);
        self::assertFalse($this->items->delete((int) $id, 'other/package', 'service'));
        self::assertNotNull($this->items->get((int) $id));
        self::assertTrue($this->items->delete((int) $id, 'acme/catering', 'service'));
        self::assertNull($this->items->get((int) $id));
    }

    private function media(string $name, bool $private): int
    {
        return (int) $this->db->insert('media', [
            'folder' => '', 'filename' => ($private ? 'private/' : 'public/') . $name,
            'original_name' => $name, 'mime' => str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg',
            'size_bytes' => 100, 'width' => null, 'height' => null,
            'alt_text' => null, 'caption' => null, 'is_private' => $private ? 1 : 0,
            'uploaded_by' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
