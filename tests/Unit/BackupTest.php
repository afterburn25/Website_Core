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
        foreach (['backups', 'staging', 'site/config', 'site/upload/docs',
                  'site/modules/addons/inquiry', 'site/themes/terra'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        $this->siteRoot = $this->tmp . '/site';
        file_put_contents($this->siteRoot . '/config/config.php', '<?php return ["x"=>1];');
        file_put_contents($this->siteRoot . '/upload/docs/a.txt', 'payload');
        file_put_contents($this->siteRoot . '/modules/addons/inquiry/addon.json', '{"id":"wcref/inquiry"}');
        file_put_contents($this->siteRoot . '/themes/terra/theme.json', '{"id":"wcref/terra"}');
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
        unlink($this->siteRoot . '/modules/addons/inquiry/addon.json');
        unlink($this->siteRoot . '/themes/terra/theme.json');
        [$ok, $err] = $this->svc($db2)->restore($path);
        $this->assertTrue($ok, (string) $err);

        $this->assertTrue($db2->tableExists('pages'));
        $row = $db2->fetch('SELECT body FROM `' . $db2->table('pages') . '` WHERE slug = ?', ['evil']);
        $this->assertSame("text;\n-- WC-STMT-END\nDROP TABLE wc_pages;", $row['body']);
        $this->assertFileExists($this->siteRoot . '/config/config.php');
        $this->assertSame('payload', file_get_contents($this->siteRoot . '/upload/docs/a.txt'));
        // Installed package + theme files roundtrip too.
        $this->assertSame('{"id":"wcref/inquiry"}',
            file_get_contents($this->siteRoot . '/modules/addons/inquiry/addon.json'));
        $this->assertSame('{"id":"wcref/terra"}',
            file_get_contents($this->siteRoot . '/themes/terra/theme.json'));
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

    public function testConfiguredExternalMediaStorageRoundtrips(): void
    {
        $external = $this->tmp . '/private-runtime';
        mkdir($external . '/media/public', 0777, true);
        file_put_contents($external . '/media/public/logo.png', 'image-bytes');
        $service = new BackupService(
            $this->db, $this->tmp . '/backups', $this->tmp . '/staging', $this->siteRoot, $external
        );
        [$path, $error] = $service->create();
        $this->assertNull($error);
        [$meta] = $service->inspect($path);
        $this->assertArrayHasKey('storage/media/public/logo.png', $meta['files']);
        unlink($external . '/media/public/logo.png');
        [$ok, $error] = $service->restore($path);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('image-bytes', file_get_contents($external . '/media/public/logo.png'));
    }

    public function testConfiguredExternalRuntimeRootsRoundtrip(): void
    {
        $runtime = $this->tmp . '/private-runtime-all';
        foreach (['config', 'upload/docs', 'storage/media/public'] as $dir) {
            mkdir($runtime . '/' . $dir, 0777, true);
        }
        file_put_contents($runtime . '/config/config.php', '<?php return ["private"=>true];');
        file_put_contents($runtime . '/upload/docs/private.txt', 'private-upload');
        file_put_contents($runtime . '/storage/media/public/logo.png', 'private-media');
        $service = new BackupService(
            $this->db,
            $this->tmp . '/backups',
            $this->tmp . '/staging',
            $this->siteRoot,
            $runtime . '/storage',
            $runtime . '/config',
            $runtime . '/upload'
        );

        [$path, $error] = $service->create();
        $this->assertNull($error);
        [$meta, $error] = $service->inspect((string) $path);
        $this->assertNull($error);
        $this->assertArrayHasKey('config/config.php', $meta['files']);
        $this->assertArrayHasKey('upload/docs/private.txt', $meta['files']);
        $this->assertArrayHasKey('storage/media/public/logo.png', $meta['files']);

        unlink($runtime . '/config/config.php');
        unlink($runtime . '/upload/docs/private.txt');
        unlink($runtime . '/storage/media/public/logo.png');
        [$ok, $error] = $service->restore((string) $path);
        $this->assertTrue($ok, (string) $error);
        $this->assertSame('<?php return ["private"=>true];', file_get_contents($runtime . '/config/config.php'));
        $this->assertSame('private-upload', file_get_contents($runtime . '/upload/docs/private.txt'));
        $this->assertSame('private-media', file_get_contents($runtime . '/storage/media/public/logo.png'));
    }

    public function testRestoreSkipsIdenticalReadOnlyFiles(): void
    {
        [$path, $error] = $this->svc()->create();
        $this->assertNull($error);
        $config = $this->siteRoot . '/config/config.php';
        chmod($config, 0444);
        try {
            [$ok, $error] = $this->svc()->restore((string) $path);
            $this->assertTrue($ok, (string) $error);
        } finally {
            chmod($config, 0644);
        }
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
        $evilHash = hash('sha256', 'owned');
        $src->close();

        // (a) Traversal member names are rejected at inspect() — the
        // archive never reaches extractTo.
        $bad = $this->tmp . '/badmembers.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', json_encode($meta));
        $zip->addFromString('db.sql', $sql);
        $zip->addFromString('files/../escape.txt', 'owned');
        $zip->addFromString('files/config/../escape2.txt', 'owned');
        $zip->close();
        [$meta2, $err2] = $this->svc()->inspect($bad);
        $this->assertNull($meta2);
        $this->assertStringContainsString('Illegal member path', (string) $err2);
        $this->svc()->restore($bad);
        $this->assertFileDoesNotExist($this->tmp . '/escape.txt');
        $this->assertFileDoesNotExist($this->siteRoot . '/escape2.txt');

        // (b) Manifest '..' keys with legal member names fail the integrity
        // hash (the file can never legally exist under files/).
        $metaB = $meta;
        $metaB['files'] = ['config/../escape2.txt' => $evilHash];
        $bad = $this->tmp . '/badmanifest.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', json_encode($metaB));
        $zip->addFromString('db.sql', $sql);
        $zip->close();
        [$ok, $err] = $this->svc()->restore($bad);
        $this->assertFalse($ok);
        $this->assertStringContainsString('integrity', (string) $err);
        $this->assertFileDoesNotExist($this->siteRoot . '/escape2.txt');

        // (c) A legal member outside the restorable roots passes integrity
        // but is skipped at write time by the allowlist.
        $metaC = $meta;
        $metaC['files'] = ['core/evil.php' => $evilHash];
        $bad = $this->tmp . '/evilroot.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', json_encode($metaC));
        $zip->addFromString('db.sql', $sql);
        $zip->addFromString('files/core/evil.php', 'owned');
        $zip->close();
        [$ok] = $this->svc()->restore($bad);
        $this->assertTrue($ok);
        $this->assertFileDoesNotExist($this->siteRoot . '/core/evil.php');
    }

    public function testSymlinkMemberRejected(): void
    {
        [$path] = $this->svc()->create();
        $src = new \ZipArchive();
        $src->open($path, \ZipArchive::RDONLY);
        $sql = $src->getFromName('db.sql');
        $meta = $src->getFromName('meta.json');
        $src->close();

        // A symlink member with an otherwise-legal name: extractTo() would
        // recreate it in staging, and a member beneath it would then write
        // through the link and escape the staging dir entirely.
        $bad = $this->tmp . '/symlink.zip';
        $zip = new \ZipArchive();
        $zip->open($bad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('meta.json', $meta);
        $zip->addFromString('db.sql', $sql);
        $zip->addFromString('files/link', '/etc');
        $zip->setExternalAttributesName('files/link', \ZipArchive::OPSYS_UNIX, 0xA1FF << 16);
        $zip->addFromString('files/link/evil.txt', 'owned');
        $zip->close();

        [$meta2, $err] = $this->svc()->inspect($bad);
        $this->assertNull($meta2);
        $this->assertStringContainsString('member type', (string) $err);

        [$ok, $err2] = $this->svc()->restore($bad);
        $this->assertFalse($ok);
        $this->assertStringContainsString('member type', (string) $err2);
    }
}
