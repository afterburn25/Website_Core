<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Seo\NotFoundLog;
use PHPUnit\Framework\TestCase;

final class NotFoundLogTest extends TestCase
{
    public function testPathsAreAggregatedAndCanBeCleared(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $log = new NotFoundLog($db);
        $log->record('/Old-Link/', 'https://example.test/page');
        $log->record('/old-link');
        $rows = $log->top();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows[0]['hits']);
        $this->assertSame(1, $log->clear());
        $this->assertSame([], $log->top());
    }

    public function testInternalInfrastructurePathsAreNotSeoInsights(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $log = new NotFoundLog($db);
        $log->record('/_a/core/css/admin.css', 'https://example.test/admin/navigation');
        $log->record('/admin/missing');
        $log->record('/api/missing');
        $log->record('/real-missing-page');

        $rows = $log->top();
        $this->assertCount(1, $rows);
        $this->assertSame('/real-missing-page', $rows[0]['path']);
    }
}
