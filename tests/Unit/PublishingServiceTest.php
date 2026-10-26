<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\PublishingService;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class PublishingServiceTest extends TestCase
{
    public function testDuePublishingTransitionsAreIdempotent(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $db->insert('pages', [
            'slug' => 'scheduled', 'title' => 'Scheduled', 'status' => 'draft',
            'publish_at' => '2026-01-01 09:00:00', 'unpublish_at' => '2026-01-02 09:00:00',
            'created_at' => '2026-01-01 08:00:00',
        ]);
        $service = new PublishingService($db);
        $this->assertSame(['published' => 1, 'unpublished' => 0], $service->runDue('2026-01-01 10:00:00'));
        $this->assertSame(['published' => 0, 'unpublished' => 0], $service->runDue('2026-01-01 10:00:00'));
        $this->assertSame(['published' => 0, 'unpublished' => 1], $service->runDue('2026-01-02 10:00:00'));
    }
}
