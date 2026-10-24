<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once WC_ROOT . '/includes/runtime-code-loader.php';

final class RuntimeCodeLoaderTest extends TestCase
{
    public function testSourceGetsAVersionSpecificRuntimePath(): void
    {
        $tmp = \WcProcessTmpDirs::make('wc-code-loader-');
        $config = $tmp . '/config';
        $source = $tmp . '/Example.php';
        mkdir($config, 0777, true);
        file_put_contents($source, "<?php\nreturn 'fresh';\n");

        $mirror = \wc_runtime_code_mirror($source, $config, '2.0.13');

        $this->assertNotSame($source, $mirror);
        $this->assertFileExists($mirror);
        $this->assertStringContainsString('/.runtime-code/2.0.13/', str_replace('\\', '/', $mirror));
        $this->assertSame(file_get_contents($source), file_get_contents($mirror));
    }
}
