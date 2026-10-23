<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Admin\NoticeCenter;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class NoticeCenterTest extends TestCase
{
    public function testNoticeCanBePublishedListedAndDismissed(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $notices = new NoticeCenter($db);

        $id = $notices->publish('Backup recommended', 'No recent backup.', 'warning');
        $this->assertSame(1, $notices->countFor(7));
        $this->assertSame('Backup recommended', $notices->openFor(7)[0]['title']);
        $this->assertTrue($notices->dismiss($id, 7));
        $this->assertSame(0, $notices->countFor(7));
    }
}
