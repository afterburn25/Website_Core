<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Settings\SettingsRepository;
use Core\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Driver-level smoke tests on in-memory sqlite: exercises core code paths
 * (migrations, repositories, limiter) without a server. NOT a substitute for
 * MySQL acceptance — tests/Native runs against real MySQL/MariaDB in CI.
 */
final class DatabaseSmokeTest extends TestCase
{
    private function sqlite(): Connection
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        return new Connection(new \PDO('sqlite::memory:'), 'wc_');
    }

    public function testCoreMigrationAppliesOnSqlite(): void
    {
        $db = $this->sqlite();
        $migrator = new Migrator($db, WC_ROOT . '/database/migrations');
        $applied = $migrator->migrate();
        $this->assertContains('2026_10_14_0001', $applied);
        foreach (['admins', 'settings', 'pages', 'packages', 'jobs', 'audit_log', 'rate_limits',
            'commerce_products', 'commerce_orders', 'commerce_order_items', 'customer_accounts', 'customer_password_resets'] as $t) {
            $this->assertTrue($db->tableExists($t), "missing table {$t}");
        }
        // Second run applies nothing (journal works).
        $this->assertSame([], $migrator->migrate());
    }

    public function testSettingsRoundTrip(): void
    {
        $db = $this->sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $s = new SettingsRepository($db);
        $s->set('site.name', 'Test Site');
        $s->set('feature.flags', ['a' => true, 'b' => 3]);
        $this->assertSame('Test Site', $s->get('site.name'));
        $this->assertSame(['a' => true, 'b' => 3], $s->get('feature.flags'));
        $this->assertSame('fallback', $s->get('missing.key', 'fallback'));
        // update path
        $s->set('site.name', 'Renamed');
        $this->assertSame('Renamed', $s->get('site.name'));
        $s->forget('site.name');
        $this->assertNull($s->get('site.name'));
    }

    public function testRateLimiterBoundsAttempts(): void
    {
        $db = $this->sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $limiter = new RateLimiter($db);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->attempt('b1', 3, 60), "attempt {$i}");
        }
        $this->assertFalse($limiter->attempt('b1', 3, 60));
        $limiter->clear('b1');
        $this->assertTrue($limiter->attempt('b1', 3, 60));
    }

    public function testCrudHelpers(): void
    {
        $db = $this->sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $id = $db->insert('pages', [
            'slug' => 'about', 'title' => 'About', 'body' => '<p>x</p>',
            'status' => 'published', 'created_at' => '2026-01-01 00:00:00',
        ]);
        $row = $db->fetch('SELECT * FROM `' . $db->table('pages') . '` WHERE id = ?', [$id]);
        $this->assertSame('about', $row['slug']);
        $db->update('pages', ['title' => 'About Us'], 'id = ?', [$id]);
        $this->assertSame('About Us', $db->fetchColumn(
            'SELECT title FROM `' . $db->table('pages') . '` WHERE id = ?', [$id]
        ));
    }
}
