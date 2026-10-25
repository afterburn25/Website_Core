<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Agency\AgencyService;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Marketplace\MarketplaceService;
use Core\Security\SecretBox;
use Core\Update\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class AgencyMarketplaceTest extends TestCase
{
    private Connection $db;
    private Config $config;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $dir = \WcProcessTmpDirs::make('wc-2-config-');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create the Website Core 2.0 test config directory.');
        }
        file_put_contents($dir . '/config.php', '<?php return ' . var_export(['security' => ['app_key' => str_repeat('ab', 32)]], true) . ';');
        $this->config = new Config($dir . '/config.php');
    }

    public function testAgencyCredentialsAreEncryptedAtRest(): void
    {
        $token = 'wcp_' . str_repeat('a', 48);
        $id = (new AgencyService($this->db, $this->config))->add('Client', 'https://93.184.216.34', $token);
        $cipher = (string) $this->db->fetchColumn('SELECT token_cipher FROM `wc_agency_sites` WHERE id = ?', [$id]);
        $this->assertStringNotContainsString($token, $cipher);
        $this->assertSame($token, (new SecretBox($this->config))->open($cipher));
    }

    public function testAgencyConnectionCanBeEditedPausedAndHaveItsKeyRotated(): void
    {
        $service = new AgencyService($this->db, $this->config);
        $first = 'wcp_' . str_repeat('a', 48);
        $second = 'wcp_' . str_repeat('b', 48);
        $id = $service->add('Client', 'https://93.184.216.34', $first);
        $service->update($id, 'Renamed client', 'https://93.184.216.34/core', $second);
        $service->setActive($id, false);

        $row = $this->db->fetch('SELECT * FROM `wc_agency_sites` WHERE id = ?', [$id]);
        $this->assertSame('Renamed client', $row['label']);
        $this->assertSame('https://93.184.216.34/core', $row['base_url']);
        $this->assertSame('paused', $row['status']);
        $this->assertSame(0, (int) $row['active']);
        $this->assertSame($second, (new SecretBox($this->config))->open((string) $row['token_cipher']));
    }

    public function testAgencyRejectsDuplicateCanonicalSiteUrl(): void
    {
        $service = new AgencyService($this->db, $this->config);
        $token = 'wcp_' . str_repeat('a', 48);
        $service->add('First', 'https://93.184.216.34', $token);
        $this->expectException(\InvalidArgumentException::class);
        $service->add('Duplicate', 'https://93.184.216.34/', $token);
    }

    public function testSignedCatalogIsNormalizedAndSelectsLatestCore(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) $this->markTestSkipped('sodium unavailable');
        $keys = SignatureVerifier::generateKeypair();
        $source = (int) $this->db->insert('marketplace_sources', ['label' => 'Test feed', 'catalog_url' => 'https://93.184.216.34/catalog.json', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $catalog = SignatureVerifier::signManifest(['product' => 'website-core-catalog', 'format' => 1,
            'packages' => [['package_id' => 'industry/test', 'name' => 'Test industry', 'type' => 'industry', 'version' => '2.0.0', 'download_url' => 'https://93.184.216.34/test.zip', 'sha256' => str_repeat('a', 64)]],
            'core_updates' => [['version' => '2.1.0', 'channel' => 'stable', 'severity' => 'security', 'download_url' => 'https://93.184.216.34/core.zip', 'sha256' => str_repeat('b', 64)]]], $keys['secret']);
        $market = new MarketplaceService($this->db, new SignatureVerifier([$keys['public']], false));
        $accepted = $market->ingest($source, $catalog);
        $this->assertCount(1, $accepted['packages']);
        $this->assertSame('2.1.0', $market->latestCore('stable')['version']);
        $this->assertSame('security', $market->latestCore('stable')['severity']);
    }

    public function testNavigationAndAgencyColumnsAreInstalled(): void
    {
        $menuColumns = array_column($this->db->fetchAll('PRAGMA table_info(`wc_menu_items`)'), 'name');
        $this->assertContains('page_id', $menuColumns);
        $this->assertContains('target', $menuColumns);
        $this->assertTrue($this->db->tableExists('agency_sites'));
        $agencyColumns = array_column($this->db->fetchAll('PRAGMA table_info(`wc_agency_sites`)'), 'name');
        $this->assertContains('active', $agencyColumns);
        $this->assertContains('consecutive_failures', $agencyColumns);
        $this->assertContains('last_latency_ms', $agencyColumns);
        $this->assertTrue($this->db->tableExists('marketplace_sources'));
    }

    public function testCatalogSourcesCanBeDisabledReenabledAndSafelyRemoved(): void
    {
        $market = new MarketplaceService($this->db, new SignatureVerifier([], false));
        $id = $market->addSource('Publisher', 'https://93.184.216.34/catalog.json');
        $market->disable($id);
        $this->assertTrue($market->enable($id));
        $this->expectException(\RuntimeException::class);
        $market->remove($id);
    }

    public function testDisabledCatalogCanBeRemoved(): void
    {
        $market = new MarketplaceService($this->db, new SignatureVerifier([], false));
        $id = $market->addSource('Publisher', 'https://93.184.216.34/catalog-two.json');
        $market->disable($id);
        $this->assertTrue($market->remove($id));
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `wc_marketplace_sources` WHERE id=?', [$id]));
    }
}
