<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Branding\BrandAssets;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Media\MediaService;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class BrandAssetsTest extends TestCase
{
    public function testPackageDefaultIsCopiedOnceAndNeverRestoredAfterClearing(): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-brand-');
        mkdir($tmp . '/package/assets', 0777, true);
        $source = $tmp . '/package/assets/logo.png';
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL2WQAAAABJRU5ErkJggg=='
        ));
        $configPath = $tmp . '/config.php';
        file_put_contents($configPath, '<?php return ' . var_export([
            'paths' => ['storage' => $tmp . '/storage'],
        ], true) . ';');

        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $settings = new SettingsRepository($db);
        $media = new MediaService($db, new Config($configPath));
        $brands = new BrandAssets($db, $settings, $media);

        $brands->seed('acme/catering', $tmp . '/package', ['logo' => 'assets/logo.png']);
        $id = (int) $settings->get('brand.logo_media_id', 0);
        self::assertGreaterThan(0, $id);
        self::assertFileExists($source);
        self::assertFileExists($media->pathFor((array) $media->find($id)));
        self::assertTrue((bool) $settings->get('branding.seeded.acme.catering', false));
        self::assertSame(1, (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $db->table('media_usage') . '` WHERE media_id = ? AND context = ?',
            [$id, 'core.branding']
        ));

        $request = new Request('GET', '/', '/demo', [], [], [], [], []);
        self::assertSame('/demo/index.php?p=/media/' . $id . '/w/480/logo.png', $brands->url($request, 'logo', 480));
        self::assertSame('© ' . date('Y') . ' Website. All rights reserved.', $brands->copyrightNotice('Website'));
        $settings->set('footer.copyright', 'Copyright {year} — {site_name}');
        self::assertSame('Copyright ' . date('Y') . ' — Website', $brands->copyrightNotice('Website'));

        $settings->set('brand.logo_media_id', null);
        $brands->seed('acme/catering', $tmp . '/package', ['logo' => 'assets/logo.png']);
        self::assertNull($settings->get('brand.logo_media_id'));
        self::assertSame(1, (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('media') . '`'));
    }

    public function testPackageAssetCannotEscapeItsRoot(): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-brand-path-');
        mkdir($tmp . '/package', 0777, true);
        file_put_contents($tmp . '/outside.png', 'not-an-image');
        $configPath = $tmp . '/config.php';
        file_put_contents($configPath, '<?php return ' . var_export([
            'paths' => ['storage' => $tmp . '/storage'],
        ], true) . ';');
        $db = Connection::sqlite();
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $brands = new BrandAssets($db, new SettingsRepository($db), new MediaService($db, new Config($configPath)));

        $this->expectException(\InvalidArgumentException::class);
        $brands->seed('acme/demo', $tmp . '/package', ['logo' => '../outside.png']);
    }
}
