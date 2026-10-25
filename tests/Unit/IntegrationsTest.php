<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Api\ApiKeyService;
use Core\Api\WebhookService;
use Core\Config;
use Core\Content\SiteTransfer;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Jobs\JobQueue;
use PHPUnit\Framework\TestCase;

final class IntegrationsTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
    }

    public function testApiKeysAreHashedScopedAndRevocable(): void
    {
        $keys = new ApiKeyService($this->db);
        $created = $keys->create('Agency monitor', ['health.read']);
        $row = $this->db->fetch('SELECT * FROM `wc_api_keys` WHERE id = ?', [$created['id']]);
        $this->assertNotSame($created['token'], $row['token_hash']);
        $request = new Request('GET', '/api/v1/health', '', [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $created['token']]);
        $this->assertNotNull($keys->authenticate($request, 'health.read'));
        $this->assertNull($keys->authenticate($request, 'content.read'));
        $keys->revoke($created['id']);
        $this->assertNull($keys->authenticate($request, 'health.read'));
    }

    public function testSiteTransferMergesDraftContentWithoutPrivateData(): void
    {
        $transfer = new SiteTransfer($this->db);
        $counts = $transfer->import(['format' => 'website-core-site', 'version' => 1,
            'settings' => ['site.name' => 'Imported Site', 'footer.copyright' => '© {year} Imported Site', 'social.instagram.url' => 'https://instagram.com/imported', 'social.placement' => 'both', 'security.app_key' => 'never'],
            'forms' => [[
                'source_id' => 42, 'name' => 'Contact', 'recipient' => 'private@example.com',
                'fields_json' => [['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
                'subject' => 'New message', 'success_message' => 'Thanks', 'submit_label' => 'Send', 'active' => 1,
            ]],
            'pages' => [[
                'slug' => 'about-us', 'title' => 'About',
                'body' => '<p>Hello</p><script>bad()</script>', 'status' => 'published',
                'blocks_json' => [['id' => 'block_form', 'type' => 'form', 'hidden' => false, 'data' => ['heading' => 'Contact', 'form_id' => '42']]],
            ]],
            'menus' => [['location' => 'primary', 'label' => 'Header']],
            'menu_items' => [[
                'source_id' => 10, 'menu_location' => 'primary', 'source_parent_id' => null,
                'label' => 'Staged page', 'url' => '/about-us', 'sort_order' => 0,
                'target' => 'tab', 'is_enabled' => 0, 'linked_page_slug' => 'about-us',
            ]],
        ]);
        $this->assertSame(1, $counts['pages']);
        $this->assertSame(1, $counts['forms']);
        $page = $this->db->fetch('SELECT * FROM `wc_pages` WHERE slug = ?', ['about-us']);
        $this->assertSame('draft', $page['status']);
        $this->assertStringNotContainsString('<script', (string) $page['body']);
        $importedForm = $this->db->fetch('SELECT * FROM `wc_forms` WHERE name=?', ['Contact']);
        $this->assertNotNull($importedForm);
        $this->assertNull($importedForm['recipient']);
        $blocks = json_decode((string) $page['blocks_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $importedForm['id'], $blocks[0]['data']['form_id']);
        $document = $transfer->export();
        $this->assertSame('Imported Site', $document['settings']['site.name']);
        $this->assertSame('© {year} Imported Site', $document['settings']['footer.copyright']);
        $this->assertSame('https://instagram.com/imported', $document['settings']['social.instagram.url']);
        $this->assertSame('both', $document['settings']['social.placement']);
        $this->assertArrayNotHasKey('security.app_key', $document['settings']);
        $this->assertSame(0, (int) $document['menu_items'][0]['is_enabled']);
        $this->assertSame('tab', $document['menu_items'][0]['target']);
        $this->assertSame('Send', $document['forms'][0]['submit_label']);
        $this->assertArrayNotHasKey('recipient', $document['forms'][0]);
    }

    public function testFailedWebhookDeliveryCanBeRequeuedAndDisabledHooksCannot(): void
    {
        $hookId = (int) $this->db->insert('webhooks', [
            'label' => 'CRM', 'url' => 'https://hooks.example.test/core',
            'events_json' => '["page.saved"]', 'secret_hash' => str_repeat('a', 64),
            'secret_cipher' => 'sealed', 'active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $deliveryId = (int) $this->db->insert('webhook_deliveries', [
            'webhook_id' => $hookId, 'event' => 'page.saved', 'payload_json' => '{}',
            'status' => 'failed', 'last_error' => 'HTTP 500', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $hooks = new WebhookService($this->db, new JobQueue($this->db), new Config('/not-present.php'));

        $this->assertTrue($hooks->retry($deliveryId));
        $this->assertSame('pending', $this->db->fetchColumn('SELECT status FROM `wc_webhook_deliveries` WHERE id=?', [$deliveryId]));
        $this->assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `wc_jobs` WHERE type='webhook.deliver'"));

        $hooks->setActive($hookId, false);
        $this->assertFalse($hooks->retry($deliveryId));
    }
}
