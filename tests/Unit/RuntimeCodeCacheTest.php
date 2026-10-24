<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once WC_ROOT . '/includes/runtime-code-cache.php';

final class RuntimeCodeCacheTest extends TestCase
{
    public function testDeployedVersionIsRecordedAfterCacheRefresh(): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-code-cache-');
        $root = $tmp . '/site';
        $config = $tmp . '/config';
        mkdir($root . '/core', 0777, true);
        mkdir($root . '/admin/controllers', 0777, true);
        mkdir($config, 0777, true);
        file_put_contents($root . '/core/Application.php', '<?php return true;');
        file_put_contents($root . '/admin/controllers/DemoController.php', '<?php return true;');

        \wc_refresh_runtime_code_cache($root, $config, '2.0.12');

        $marker = $config . '/.runtime-code-version';
        $this->assertFileExists($marker);
        $this->assertSame('2.0.12', trim((string) file_get_contents($marker)));
    }
}
