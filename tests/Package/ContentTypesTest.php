<?php
declare(strict_types=1);

use Core\Content\ContentItems;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * The generic content-type contract: a package declares a type via
 * ctx->contentType(), the registry aggregates it, ContentItems validates
 * against the declared field set and stores rows packages read back.
 */
final class ContentTypesTest extends TestCase
{
    private string $tmp;
    private Connection $db;
    private ModuleRegistry $registry;
    private ContentItems $items;

    protected function setUp(): void
    {
        $this->tmp = WcProcessTmpDirs::make('wc-ctype-');
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->registry = new ModuleRegistry(
            $this->db,
            $this->tmp . '/industries', $this->tmp . '/addons', $this->tmp . '/themes'
        );

        // Fixture package declaring one content type.
        $pkg = $this->tmp . '/addons/faq';
        @mkdir($pkg . '/src', 0755, true);
        file_put_contents($pkg . '/addon.json', json_encode([
            'schema' => 1, 'id' => 'acme/faq', 'type' => 'addon',
            'name' => 'FAQ', 'version' => '1.0.0', 'license' => 'MIT',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
            'provider' => 'AcmeFaq\\Provider',
            'psr4' => ['AcmeFaq\\' => 'src/'],
        ]));
        file_put_contents($pkg . '/src/Provider.php', <<<'PHP'
        <?php namespace AcmeFaq;
        final class Provider {
            public function register(\Core\Modules\ModuleContext $ctx): void {
                $ctx->contentType('faq', 'FAQs', [
                    ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true],
                    ['name' => 'answer', 'label' => 'Answer', 'type' => 'html'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'url'],
                    ['name' => 'weight', 'label' => 'Weight', 'type' => 'number'],
                    ['name' => 'kind', 'label' => 'Kind', 'type' => 'select',
                     'options' => ['general' => 'General', 'billing' => 'Billing']],
                    ['name' => 'featured', 'label' => 'Featured', 'type' => 'checkbox'],
                    ['name' => '', 'label' => 'malformed — dropped'],
                ]);
            }
        }
        PHP);

        $this->registry->discover();
        $this->registry->setState('acme/faq', 'enabled');
        $this->registry->bootEnabled(
            fn (string $p, string $d) => \CoreAutoload::register($p, $d),
            fn (string $id) => new ModuleContext(
                $id, new Router(), new Dispatcher(), null,
                null, null, null, 'base', $this->tmp . '/themes'
            )
        );
        $this->items = new ContentItems($this->db, $this->registry);
    }

    public function testRegistryAggregatesDeclaredType(): void
    {
        $types = $this->items->types();
        $this->assertArrayHasKey('acme/faq/faq', $types);
        $def = $types['acme/faq/faq'];
        $this->assertSame('FAQs', $def['label']);
        $this->assertSame(['question', 'answer', 'link', 'weight', 'kind', 'featured'],
            array_column($def['fields'], 'name'));
    }

    public function testSaveValidatesDeclaredFields(): void
    {
        // Missing required field.
        [$ok, $err] = $this->items->save(null, 'acme/faq', 'faq', [], 1);
        $this->assertFalse($ok);
        $this->assertStringContainsString('Question is required', (string) $err);

        // Bad number + bad select + bad url in one pass.
        [$ok, $err] = $this->items->save(null, 'acme/faq', 'faq', [
            'question' => 'Q', 'weight' => 'abc', 'kind' => 'nope',
            'link' => 'javascript:alert(1)',
        ], 1);
        $this->assertFalse($ok);
        $this->assertStringContainsString('must be a number', (string) $err);
        $this->assertStringContainsString('invalid choice', (string) $err);
        $this->assertStringContainsString('http(s) or mailto', (string) $err);

        // Unknown type id rejected.
        [$ok, $err] = $this->items->save(null, 'acme/faq', 'ghost', [], 1);
        $this->assertFalse($ok);
        $this->assertStringContainsString('Unknown content type', (string) $err);
    }

    public function testSaveStoresCoercedFieldsAndDerivesTitle(): void
    {
        [$ok, $err, $id] = $this->items->save(null, 'acme/faq', 'faq', [
            'question' => '  Do you <b>ship</b>?  ',
            'answer' => '<p>Yes</p><script>x()</script>',
            'link' => 'https://example.com/faq',
            'weight' => '2.5',
            'kind' => 'billing',
            'featured' => 'on',
            'undeclared' => 'should be dropped',
        ], 1);
        $this->assertTrue($ok, (string) $err);
        $this->assertNotNull($id);

        $item = $this->items->get($id);
        $this->assertSame('acme/faq', $item['package']);
        $this->assertSame('faq', $item['type']);
        // Title derived from first text-ish field.
        $this->assertSame('Do you ship?', $item['title']);
        $this->assertSame('Do you ship?', $item['fields']['question']);
        // html sanitized through the page-body boundary.
        $this->assertSame('<p>Yes</p>', $item['fields']['answer']);
        $this->assertSame('https://example.com/faq', $item['fields']['link']);
        $this->assertSame('2.5', $item['fields']['weight']);
        $this->assertSame('billing', $item['fields']['kind']);
        $this->assertSame('1', $item['fields']['featured']);
        // Undeclared keys never reach storage.
        $this->assertArrayNotHasKey('undeclared', $item['fields']);
    }

    public function testItemsFilterByPublishedAndPackage(): void
    {
        $this->items->save(null, 'acme/faq', 'faq', [
            'question' => 'Draft', 'status' => 'draft',
        ], 1);
        $this->items->save(null, 'acme/faq', 'faq', [
            'question' => 'Live', 'status' => 'published',
        ], 1);

        $all = $this->items->items('acme/faq', 'faq');
        $live = $this->items->items('acme/faq', 'faq', true);
        $this->assertCount(2, $all);
        $this->assertCount(1, $live);
        $this->assertSame('Live', $live[0]['title']);
        $this->assertSame([], $this->items->items('acme/faq', 'other'));
        $this->assertSame([], $this->items->items('other/pkg', 'faq'));
    }

    public function testPurgeRemovesSharedStoreRows(): void
    {
        [$ok, , $id] = $this->items->save(null, 'acme/faq', 'faq', ['question' => 'Q'], 1);
        $this->assertTrue($ok);
        $this->db->insert('settings', [
            'key' => 'content.seeded.' . substr(hash('sha256', 'acme/faq'), 0, 24) . '.faq',
            'value' => 'true',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $pm = new \Core\Modules\PackageManager(
            $this->db, $this->registry, new \Core\Modules\ArchiveInspector(),
            $this->tmp . '/staging', $this->tmp . '/industries',
            $this->tmp . '/addons', $this->tmp . '/themes',
            $this->tmp . '/op.lock'
        );
        [$ok, $err] = $pm->uninstall('acme/faq');
        $this->assertTrue($ok, (string) $err);
        // Data is preserved through uninstall — the row survives.
        $this->assertNotNull($this->items->get($id));

        [$ok, $err] = $pm->purgeData('acme/faq');
        $this->assertTrue($ok, (string) $err);
        // Explicit purge removes the shared-store rows too.
        $this->assertNull($this->items->get($id));
        $this->assertSame([], $this->items->items('acme/faq', 'faq'));
        $this->assertFalse((bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->db->table('settings') . '` WHERE `key` = ?',
            ['content.seeded.' . substr(hash('sha256', 'acme/faq'), 0, 24) . '.faq']
        ));
    }

    public function testUpdateCannotCrossTypeBoundaries(): void
    {
        [$ok1, , $id] = $this->items->save(null, 'acme/faq', 'faq', ['question' => 'Q'], 1);
        $this->assertTrue($ok1);
        // Same id, different type — must not overwrite another type's row.
        [$ok, $err] = $this->items->save($id, 'acme/faq', 'faq', ['question' => 'Q2'], 1);
        $this->assertTrue($ok, (string) $err);
        $this->assertSame('Q2', $this->items->get($id)['fields']['question']);
        [$ok] = $this->items->save($id, 'other/pkg', 'faq', ['question' => 'x'], 1);
        $this->assertFalse($ok);
    }
}
