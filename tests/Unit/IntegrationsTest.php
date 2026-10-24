<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Api\ApiKeyService;
use Core\Content\SiteTransfer;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
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
            'settings' => ['site.name' => 'Imported Site', 'footer.copyright' => '© {year} Imported Site', 'security.app_key' => 'never'],
            'pages' => [['slug' => 'about-us', 'title' => 'About', 'body' => '<p>Hello</p><script>bad()</script>', 'status' => 'published']]]);
        $this->assertSame(1, $counts['pages']);
        $page = $this->db->fetch('SELECT * FROM `wc_pages` WHERE slug = ?', ['about-us']);
        $this->assertSame('draft', $page['status']);
        $this->assertStringNotContainsString('<script', (string) $page['body']);
        $document = $transfer->export();
        $this->assertSame('Imported Site', $document['settings']['site.name']);
        $this->assertSame('© {year} Imported Site', $document['settings']['footer.copyright']);
        $this->assertArrayNotHasKey('security.app_key', $document['settings']);
    }
}
