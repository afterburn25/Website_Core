<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\RedirectService;
use Core\Content\SectionService;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class RedirectsSectionsTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('needs pdo_sqlite');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    public function testMigrationAddsColumnsAndTables(): void
    {
        $this->assertTrue($this->db->tableExists('redirects'));
        $this->assertTrue($this->db->tableExists('sections'));
        $cols = array_column(
            $this->db->pdo()->query('PRAGMA table_info(`' . $this->db->table('pages') . '`)')->fetchAll(),
            'name'
        );
        foreach (['noindex', 'canonical', 'og_media_id'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function testRedirectMatchAndHitCount(): void
    {
        $s = new RedirectService($this->db);
        [$ok, $err] = $s->save(null, '/Old-Page/', '/new-page', 301, 1);
        $this->assertTrue($ok, (string) $err);

        $hit = $s->match('/old-page');
        $this->assertNotNull($hit);
        $this->assertSame('/new-page', $hit['target']);
        $this->assertSame(301, (int) $hit['status_code']);
        $s->match('/old-page');
        $this->assertSame(
            2,
            (int) $this->db->fetchColumn('SELECT hits FROM `' . $this->db->table('redirects') . '`')
        );
        $this->assertNull($s->match('/never-existed'));
    }

    public function testRedirectValidation(): void
    {
        $s = new RedirectService($this->db);
        [$ok] = $s->save(null, '/a', '/a', 301, null);
        $this->assertFalse($ok); // self-redirect
        [$ok] = $s->save(null, '/a', 'javascript:alert(1)', 301, null);
        $this->assertFalse($ok); // bad scheme
        [$ok] = $s->save(null, '/a', 'ftp://x', 301, null);
        $this->assertFalse($ok);
        [$ok] = $s->save(null, '/a', '/b', 999, null);
        $this->assertFalse($ok); // bad code
        [$ok] = $s->save(null, '/a', 'https://example.com/x', 302, null);
        $this->assertTrue($ok); // external https is fine
    }

    public function testRedirectLoopRejected(): void
    {
        $s = new RedirectService($this->db);
        $s->save(null, '/a', '/b', 301, null);
        $s->save(null, '/b', '/c', 301, null);
        [$ok, $err] = $s->save(null, '/c', '/a', 301, null); // c→a→b→c loop
        $this->assertFalse($ok);
        $this->assertStringContainsString('loop', (string) $err);
        // Deep chains don't loop → allowed.
        [$ok] = $s->save(null, '/c', '/d', 301, null);
        $this->assertTrue($ok);
    }

    public function testSectionSaveRenderSanitized(): void
    {
        $s = new SectionService($this->db);
        [$ok, $err] = $s->save('Contact Strip!', 'Contact strip', '<p>Call <b>us</b></p><script>x</script>', 1);
        $this->assertTrue($ok, (string) $err);
        $this->assertSame('', $s->render('contact_strip_'));
        $out = $s->render('contact_strip');
        $this->assertStringContainsString('<b>us</b>', $out);
        $this->assertStringNotContainsString('script', $out);
        $this->assertSame('', $s->render('missing'));
    }

    public function testSectionKeyValidation(): void
    {
        $s = new SectionService($this->db);
        [$ok] = $s->save('!!!', 'x', 'y', null);
        $this->assertFalse($ok);
        [$ok] = $s->save('ok_key', '', 'y', null);
        $this->assertFalse($ok); // label required
        [$ok] = $s->save('ok_key', 'OK', '<p>y</p>', null);
        $this->assertTrue($ok);
        // Update in place (key is unique).
        [$ok] = $s->save('ok_key', 'OK2', '<p>z</p>', null);
        $this->assertTrue($ok);
        $this->assertCount(1, $s->all());
        $this->assertStringContainsString('<p>z</p>', $s->render('ok_key'));
    }
}
