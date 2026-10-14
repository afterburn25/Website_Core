<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Backup\BackupService;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class BackupTest extends TestCase
{
    private string $tmp;
    private Connection $db;
    private string $siteRoot;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('zip')) {
            $this->markTestSkipped('needs pdo_sqlite + zip');
        }
        $this->tmp = sys_get_temp_dir() . '/wc-bk-' . bin2hex(random_bytes(4));
        foreach (['backups', 'staging', 'site/config', 'site/upload/docs'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        $this->siteRoot = $this->tmp . '/site';
        file_put_contents($this->siteRoot . '/config/config.php', '<?php return ["x"=>1];');
        file_put_contents($this->siteRoot . '/upload/docs/a.txt', 'payload');
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmp);
    }

    private function svc(?Connection $db = null): BackupService
    {
        return new BackupService(
            $db ?? $this->db,
            $this->tmp . '/backups', $this->tmp . '/staging', $this->siteRoot
        );
    }

    public function testCreateInspectRestoreRoundtrip(): void
    {
        // Seed data including hostile text with statement-looking content.
        $this->db->insert('pages', [
            'title' => 'T', 'slug' => 'evil', 'body' => "text;\n-- WC-STMT-END\nDROP TABLE wc_pages;", 'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        [$path, $err] = $this->svc()->create();
        $this->assertNull($err);
        $this->assertFileExists($path);

        [$meta, $err] = $this->svc()->inspect($path);
        $this->assertNull($err);
        $this->assertSame(BackupService::FORMAT, $meta['format']);

        // Restore into a FRESH database + wiped site — proves bytes rebuild.
        $db2 = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        unlink($this->siteRoot . '/config/config.php');
        unlink($this->siteRoot . '/upload/docs/a.txt');
        [$ok, $err] = $this->svc($db2)->restore($path);
        $this->assertTrue($ok, (string) $err);

        $this->assertTrue($db2->tableExists('pages'));
        $row = $db2->fetch('SELECT body FROM `' . $db2->table('pages') . '` WHERE slug = ?', ['evil']);
        $this->assertSame("text;\n-- WC-STMT-END\nDROP TABLE wc_pages;", $row['body']);
        $this->assertFileExists($this->siteRoot . '/config/config.php');
        $this->assertSame('payload', file_get_contents($this->siteRoot . '/upload/docs/a.txt'));
    }

    public function testCorruptArchiveRejected(): void
    {
        [$path] = $this->svc()->create();
        // Flip a byte in db.sql inside the zip: rebuild without it.
        $bad = $this->tmp . '/bad.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $src = new \ZipArchive();
        $src->open($path, \ZipArchive::RDONLY);
        $zip->addFromString('meta.json', $src->getFromName('meta.json'));
        $zip->addFromString('db.sql', 'CORRUPTED');
        $src->close();
        $zip->close();

        [$meta, $err] = $this->svc()->inspect($bad);
        $this->assertNull($meta);
        $this->assertStringContainsString('integrity', (string) $err);
    }

    public function testMissingMembersRejected(): void
    {
        $bad = $this->tmp . '/empty.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', '{"format":1}');
        $zip->close();
        [$meta, $err] = $this->svc()->inspect($bad);
        $this->assertNull($meta);
        $this->assertStringContainsString('Incomplete', (string) $err);
    }

    public function testDriverMismatchRejected(): void
    {
        [$path] = $this->svc()->create();
        // Rewrite meta to claim mysql driver while keeping valid db checksum.
        $zip = new \ZipArchive();
        $src = new \ZipArchive();
        $src->open($path, \ZipArchive::RDONLY);
        $sql = $src->getFromName('db.sql');
        $meta = json_decode($src->getFromName('meta.json'), true);
        $meta['driver'] = 'mysql';
        $src->close();
        $fake = $this->tmp . '/fake.zip';
        $zip->open($fake, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', json_encode($meta));
        $zip->addFromString('db.sql', $sql);
        $zip->close();
        [$ok, $err] = $this->svc()->restore($fake);
        $this->assertFalse($ok);
        $this->assertStringContainsString('driver mismatch', (string) $err);
    }

    public function testManifestCannotEscapeIncludeRoots(): void
    {
        [$path] = $this->svc()->create();
        $src = new \ZipArchive();
        $src->open($path, \ZipArchive::RDONLY);
        $sql = $src->getFromName('db.sql');
        $meta = json_decode($src->getFromName('meta.json'), true);
        // Forge a file entry outside allowed roots — restore must skip it.
        $evilHash = hash('sha256', 'owned');
        $meta['files']['../escape.txt'] = $evilHash;
        $meta['files']['core/evil.php'] = $evilHash;
        $src->close();
        $fake = $this->tmp . '/esc.zip';
        $zip = new \ZipArchive();
        $zip->open($fake, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', json_encode($meta));
        $zip->addFromString('db.sql', $sql);
        @mkdir($this->tmp . '/ziptmp', 0777, true);
        // The forged files must exist in the archive for hash check to pass…
        // — but inspect() only hashes db.sql; restore() checks each file.
        // Provide them so only the root-allowlist stops the write.
        $zip->addFromString('files/../escape.txt', 'owned');
        $zip->addFromString('files/core/evil.php', 'owned');
        $zip->close();
        $this->svc()->restore($fake); // may fail integrity — either way, no escape
        $this->assertFileDoesNotExist($this->tmp . '/escape.txt');
        $this->assertFileDoesNotExist($this->siteRoot . '/core/evil.php');
    }
}
