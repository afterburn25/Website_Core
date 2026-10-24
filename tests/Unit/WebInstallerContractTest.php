<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WebInstallerContractTest extends TestCase
{
    public function testWebInstallerOwnsExtractionAndSelfRemovalLifecycle(): void
    {
        $stub = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/web-installer.php.stub');

        self::assertStringContainsString('__COMPILER_HALT_OFFSET__', $stub);
        self::assertStringContainsString('hash_file(\'sha256\'', $stub);
        self::assertStringContainsString('getExternalAttributesIndex', $stub);
        self::assertStringContainsString('ZipArchive::OPSYS_UNIX', $stub);
        self::assertStringContainsString("@unlink(\$self)", $stub);
        self::assertStringContainsString("@rename(\$self, \$retired)", $stub);
        self::assertStringContainsString('__halt_compiler();', $stub);
    }
}
