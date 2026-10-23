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
        $this->assertTrue($this->db->tableExists('marketplace_sources'));
    }
}
