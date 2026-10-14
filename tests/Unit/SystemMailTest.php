<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Mail\SystemMail;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class SystemMailTest extends TestCase
{
    private Connection $db;
    private SettingsRepository $settings;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('needs pdo_sqlite');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->settings = new SettingsRepository($this->db);
        SystemMail::captureWith(fn () => null); // reset shared capture each test
    }

    private function mail(): SystemMail
    {
        return new SystemMail($this->db, $this->settings);
    }

    public function testQueueAndCaptureFlush(): void
    {
        $captured = [];
        SystemMail::captureWith(function (string $to, string $subj, string $body) use (&$captured) {
            $captured[] = [$to, $subj, $body];
        });
        $this->settings->set('mail.transport', 'capture');

        $this->mail()->queue('a@example.com', 'Hello', 'Body text');
        $this->assertSame(1, $this->mail()->flush());
        $this->assertCount(1, $captured);
        $this->assertSame('Hello', $captured[0][1]);
        $this->assertSame('sent', $this->db->fetchColumn(
            'SELECT status FROM `' . $this->db->table('system_messages') . '`'
        ));
    }

    public function testDedupeKeyPreventsDuplicateQueuedMail(): void
    {
        $this->mail()->queue('a@x.com', 'S', 'B', 'key-1');
        $this->assertSame(0, $this->mail()->queue('a@x.com', 'S', 'B', 'key-1'));
        $this->assertSame(1, (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('system_messages') . '`'
        ));
    }

    public function testInvalidRecipientMarkedFailed(): void
    {
        SystemMail::captureWith(fn () => null);
        $this->settings->set('mail.transport', 'capture');
        $this->mail()->queue('not-an-email', 'S', 'B');
        $this->mail()->flush();
        $this->assertSame('failed', $this->db->fetchColumn(
            'SELECT status FROM `' . $this->db->table('system_messages') . '`'
        ));
    }

    public function testMissingSmtpHostFailsWithoutBlocking(): void
    {
        // transport 'smtp' with no host → finite error, message marked failed
        $this->settings->set('mail.transport', 'smtp');
        $this->settings->set('smtp.host', '');
        $this->mail()->queue('a@example.com', 'S', 'B');
        $this->mail()->flush();
        $row = $this->db->fetch('SELECT status, last_error FROM `' . $this->db->table('system_messages') . '`');
        $this->assertSame('failed', $row['status']);
        $this->assertStringContainsString('smtp.host', (string) $row['last_error']);
    }

    public function testPasswordResetTemplate(): void
    {
        $id = $this->mail()->passwordReset('admin@example.com', 'https://x/reset?t=1');
        $this->assertGreaterThan(0, $id);
        $row = $this->db->fetch('SELECT subject, body FROM `' . $this->db->table('system_messages') . '`');
        $this->assertStringContainsString('reset', strtolower((string) $row['subject']));
        $this->assertStringContainsString('https://x/reset?t=1', (string) $row['body']);
    }
}
