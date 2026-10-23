<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\PageBlocks;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Modules\ModuleContext;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;

final class PageBlocksTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite();
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    public function testNormalizesSanitizesAndRendersCoreBlocks(): void
    {
        $service = new PageBlocks($this->db);
        [$json, $error] = $service->normalize(json_encode([
            ['id' => 'hero_1', 'type' => 'hero', 'data' => [
                'heading' => '<script>alert(1)</script>Welcome',
                'body' => '<p>Hello</p><script>alert(2)</script>',
                'button_label' => 'Contact', 'button_url' => 'javascript:alert(3)',
            ]],
            ['id' => 'faq_1', 'type' => 'faq', 'data' => ['heading' => 'FAQ', 'items' => "When? | Today\nWhere? | Here"]],
        ], JSON_THROW_ON_ERROR));

        self::assertNull($error);
        self::assertStringNotContainsString('<script', $json);
        self::assertStringNotContainsString('javascript:', $json);
        $html = $service->render($json, '/subfolder');
        self::assertStringContainsString('Welcome', $html);
        self::assertStringContainsString('<details>', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testModuleContextAcceptsIndustryBlockDefinitions(): void
    {
        $ctx = new ModuleContext('acme/catering', new Router(), new Dispatcher());
        $ctx->pageBlock('menu_packages', 'Menu packages', [
            'heading' => ['label' => 'Heading', 'type' => 'text'],
        ], fn (array $data): string => '<section>' . e((string) ($data['heading'] ?? '')) . '</section>');

        self::assertArrayHasKey('menu_packages', $ctx->pageBlocks());
        self::assertSame('acme/catering', $ctx->pageBlocks()['menu_packages']['package']);
    }

    public function testMigrationAddsBlockDocumentsToPagesAndRevisions(): void
    {
        $pages = array_column($this->db->fetchAll('PRAGMA table_info(`pages`)'), 'name');
        $revisions = array_column($this->db->fetchAll('PRAGMA table_info(`page_revisions`)'), 'name');
        self::assertContains('blocks_json', $pages);
        self::assertContains('blocks_json', $revisions);
    }
}
