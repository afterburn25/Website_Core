<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Content\MenuService;
use Core\Content\PreviewTokens;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Media\MediaService;
use PHPUnit\Framework\TestCase;

final class ContentMediaTest extends TestCase
{
    private Connection $db;
    private string $tmp;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->tmp = sys_get_temp_dir() . '/wc-media-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->tmp);
    }

    private function media(): MediaService
    {
        $config = new Config('/nonexistent');
        $ref = new \ReflectionClass($config);
        $prop = $ref->getProperty('data');
        $prop->setAccessible(true);
        $prop->setValue($config, ['paths' => ['storage' => $this->tmp]]);
        $prop2 = $ref->getProperty('loaded');
        $prop2->setAccessible(true);
        $prop2->setValue($config, true);
        return new MediaService($this->db, $config);
    }

    private function fakeUpload(string $content, string $name): array
    {
        $tmp = $this->tmp . '/upload-' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $content);
        return ['tmp_name' => $tmp, 'name' => $name, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    private function pngBytes(): string
    {
        // 1x1 PNG
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    public function testStoreValidPng(): void
    {
        [$id, $err] = $this->media()->store($this->fakeUpload($this->pngBytes(), 'dot.png'), 1);
        $this->assertNull($err);
        $this->assertNotNull($id);
        $m = $this->media()->find($id);
        $this->assertSame('image/png', $m['mime']);
        $this->assertSame(1, (int) $m['width']);
        $this->assertTrue(is_file($this->media()->pathFor($m)));
    }

    public function testRejectsDisallowedExtension(): void
    {
        [, $err] = $this->media()->store($this->fakeUpload('MZ exe', 'virus.exe'), 1);
        $this->assertNotNull($err);
    }

    public function testRejectsMimeMismatch(): void
    {
        [, $err] = $this->media()->store($this->fakeUpload('plain text', 'fake.png'), 1);
        $this->assertNotNull($err);
    }

    public function testRejectsSvg(): void
    {
        [, $err] = $this->media()->store($this->fakeUpload('<svg xmlns="x"><script/></svg>', 'icon.svg'), 1);
        $this->assertNotNull($err);
    }

    public function testRejectsPolyglotMarkup(): void
    {
        $payload = $this->pngBytes() . '<?php echo 1; ?>';
        [, $err] = $this->media()->store($this->fakeUpload($payload, 'poly.png'), 1);
        $this->assertNotNull($err);
    }

    public function testRejectsOversize(): void
    {
        [, $err] = $this->media()->store(
            ['tmp_name' => $this->tmp . '/x', 'name' => 'a.png', 'size' => 16 * 1024 * 1024, 'error' => UPLOAD_ERR_OK],
            1
        );
        $this->assertNotNull($err);
    }

    public function testMenuUrlValidation(): void
    {
        $menu = new MenuService($this->db);
        $this->assertTrue($menu->urlOk('/about'));
        $this->assertTrue($menu->urlOk('https://example.com/x'));
        $this->assertTrue($menu->urlOk('mailto:a@b.c'));
        $this->assertFalse($menu->urlOk('javascript:alert(1)'));
        $this->assertFalse($menu->urlOk('//evil.test/x'));
        $this->assertFalse($menu->urlOk(''));
    }

    public function testMenuTreeAndReparent(): void
    {
        $menu = new MenuService($this->db);
        $menuId = $menu->ensureLocation('primary', 'Primary');
        [$a] = $menu->addItem($menuId, 'Home', '/', null, 0);
        [$b] = $menu->addItem($menuId, 'About', '/about', $a, 1);
        $tree = $menu->tree('primary');
        $this->assertCount(1, $tree);
        $this->assertSame('About', $tree[0]['children'][0]['label']);
        // Deleting a parent re-parents children to top level.
        $menu->deleteItem($a);
        $tree = $menu->tree('primary');
        $this->assertCount(1, $tree);
        $this->assertSame('About', $tree[0]['label']);
    }

    public function testPreviewTokens(): void
    {
        $db = $this->db;
        $db->insert('pages', [
            'slug' => 'drafty', 'title' => 'Draft', 'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $pageId = (int) $db->fetchColumn('SELECT id FROM `' . $db->table('pages') . '` WHERE slug=?', ['drafty']);
        $tokens = new PreviewTokens($db);
        $t = $tokens->issue($pageId, 1, 3600);
        $this->assertSame($pageId, $tokens->peek($t));
        $this->assertNull($tokens->peek('bogus'));
        // Single-use redeem burns the token.
        $this->assertSame($pageId, $tokens->redeem($t));
        $this->assertNull($tokens->redeem($t));
    }
}
