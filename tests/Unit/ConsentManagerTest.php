<?php
declare(strict_types=1);
namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Privacy\ConsentManager;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ConsentManagerTest extends TestCase
{
    public function testAnalyticsOnlyAppearsBehindExplicitConsentLoader(): void
    {
        $db=Connection::sqlite();(new Migrator($db,WC_ROOT.'/database/migrations'))->migrate();$settings=new SettingsRepository($db);
        $settings->set('privacy.banner_enabled',true);$settings->set('analytics.provider','ga4');$settings->set('analytics.site_id','G-ABC123');
        $html=(new ConsentManager($settings))->inject('<html><body><h1>Hi</h1></body></html>','/index.php?p=');
        self::assertStringContainsString('data-wc-consent',$html);
        self::assertStringContainsString('localStorage.getItem',$html);
        self::assertStringContainsString('G-ABC123',$html);
        self::assertStringContainsString('/index.php?p=/privacy/request',$html);
        self::assertSame(1,substr_count($html,'googletagmanager.com'));
    }
}
