<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/host-preflight.php';

final class HostPreflightTest extends TestCase
{
    private function site(): string
    {
        $site = \WcProcessTmpDirs::make('wc-host-preflight-');
        foreach ([
            'includes', 'config', 'storage', 'vendor',
            'modules/industries/industry-catering', 'install',
        ] as $relative) {
            mkdir($site . '/' . $relative, 0777, true);
        }
        file_put_contents($site . '/index.php', '<?php echo "ok";');
        file_put_contents($site . '/includes/bootstrap.php',
            "<?php define('WC_VERSION', '2.6.2');");
        file_put_contents($site . '/vendor/autoload.php', '<?php');
        file_put_contents($site . '/.htaccess', 'Deny from all');
        file_put_contents($site . '/config/.htaccess', 'Deny from all');
        file_put_contents($site . '/storage/.htaccess', 'Deny from all');
        file_put_contents($site . '/install.php', '<?php');
        file_put_contents($site . '/install/index.php', '<?php');
        file_put_contents($site . '/config/config.php',
            '<?php return ["password" => "SENSITIVE-CONFIG-VALUE"];');
        file_put_contents($site . '/config/installed.lock', 'install complete');
        file_put_contents($site . '/modules/industries/industry-catering/addon.json',
            json_encode([
                'id' => 'cajunwebpro/catering',
                'version' => '2.1.1',
                'license_key' => 'SENSITIVE-LICENSE-KEY',
                'api_token' => 'SENSITIVE-API-TOKEN',
            ], JSON_THROW_ON_ERROR));
        return $site;
    }

    public function testReportsPublicVersionsButNeverSecretsOrAbsolutePaths(): void
    {
        $site = $this->site();
        $sentinel = $site . '/config/config.php';
        $before = hash_file('sha256', $sentinel);

        $report = \wc_host_preflight($site);
        $json = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $report['schema']);
        $this->assertSame('read_only_cli_file_inspection', $report['scope']);
        $this->assertSame('2.6.2', $report['core_version']);
        $this->assertSame('marker_and_config_present', $report['installation_state']);
        $this->assertTrue($report['checks']['install_php_present']);
        $this->assertTrue($report['checks']['install_dir_present']);
        $this->assertSame([
            ['id' => 'cajunwebpro/catering', 'version' => '2.1.1', 'state' => 'on_disk_unverified'],
        ], $report['industries']);
        $this->assertNotEmpty($report['warnings']);
        $this->assertContains('database_connection_and_migrations', $report['not_tested']);

        foreach (['SENSITIVE-CONFIG-VALUE', 'SENSITIVE-LICENSE-KEY', 'SENSITIVE-API-TOKEN', $site]
            as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame($before, hash_file('sha256', $sentinel));
        $this->assertFileExists($site . '/install.php');
        $this->assertFileExists($site . '/config/installed.lock');
    }

    public function testRecognizesRetiredInstallerAndPrivateConfigWithoutReadingIt(): void
    {
        $site = $this->site();
        unlink($site . '/install.php');
        unlink($site . '/install/index.php');
        rmdir($site . '/install');
        $private = \WcProcessTmpDirs::make('wc-host-private-');
        file_put_contents($private . '/config.php',
            '<?php return ["password" => "PRIVATE-RUNTIME-SECRET"];');
        file_put_contents($private . '/installed.lock', 'install complete');

        $report = \wc_host_preflight($site, $private);
        $json = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['checks']['install_php_present']);
        $this->assertFalse($report['checks']['install_dir_present']);
        $this->assertSame('marker_and_config_present', $report['installation_state']);
        $this->assertStringNotContainsString('PRIVATE-RUNTIME-SECRET', $json);
        $this->assertStringNotContainsString($private, $json);
        $this->assertStringNotContainsString('SENSITIVE-CONFIG-VALUE', $json);
    }

    public function testEmptyOrInvalidRootFailsWithoutPrintingHostDetails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Website root is not a readable directory.');
        \wc_host_preflight('/this/path/is/not/a/deployed/site');
    }
}
