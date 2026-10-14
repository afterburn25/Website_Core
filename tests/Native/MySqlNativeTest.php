<?php
declare(strict_types=1);

namespace Tests\Native;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Native MySQL/MariaDB tests — the required DB acceptance layer. These run
 * in CI against real mysql/mariadb services via WC_TEST_MYSQL_DSN, e.g.
 *   WC_TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306;dbname=wc_test"
 *   WC_TEST_MYSQL_USER=root  WC_TEST_MYSQL_PASS=secret
 * They are skipped (not silently passed) when no DSN is provided.
 */
final class MySqlNativeTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $dsn = getenv('WC_TEST_MYSQL_DSN');
        if (!$dsn) {
            $this->markTestSkipped('WC_TEST_MYSQL_DSN not set — native MySQL tests skipped');
        }
        $pdo = new \PDO(
            $dsn,
            getenv('WC_TEST_MYSQL_USER') ?: 'root',
            getenv('WC_TEST_MYSQL_PASS') ?: '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $this->db = new Connection($pdo, 'wctest_');
    }

    public function testCoreSchemaOnMysql(): void
    {
        $this->assertSame('mysql', $this->db->driver());
        $migrator = new Migrator($this->db, WC_ROOT . '/database/migrations');
        $applied = $migrator->migrate();
        $this->assertContains('2026_10_14_0001', $applied);
        foreach (['admins', 'settings', 'pages', 'packages', 'jobs', 'audit_log', 'rate_limits'] as $t) {
            $this->assertTrue($this->db->tableExists($t), "missing {$t}");
        }
        // Idempotent re-run.
        $this->assertSame([], $migrator->migrate());
    }

    public function testCrudOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $id = $this->db->insert('pages', [
            'slug' => 'native-test', 'title' => 'Native', 'body' => '<p>ok</p>',
            'status' => 'published', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $row = $this->db->fetch('SELECT * FROM `' . $this->db->table('pages') . '` WHERE id=?', [$id]);
        $this->assertSame('native-test', $row['slug']);
    }

    public function testRateLimiterOnMysql(): void
    {
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $l = new RateLimiter($this->db);
        $bucket = 'native:' . bin2hex(random_bytes(4));
        $this->assertTrue($l->attempt($bucket, 2, 60));
        $this->assertTrue($l->attempt($bucket, 2, 60));
        $this->assertFalse($l->attempt($bucket, 2, 60));
    }
}
