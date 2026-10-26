<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once WC_ROOT . '/includes/server-error-pages.php';

final class ServerErrorPageTest extends TestCase
{
    private string $scriptName;

    protected function setUp(): void
    {
        $this->scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    }

    protected function tearDown(): void
    {
        $_SERVER['SCRIPT_NAME'] = $this->scriptName;
    }

    public function testSubfolderErrorDocumentIsProvisionedIdempotently(): void
    {
        $root = \WcProcessTmpDirs::make('wc-error-page-');
        self::assertTrue(mkdir($root, 0755, true));
        file_put_contents($root . '/.htaccess', "Options -Indexes\n");
        $_SERVER['SCRIPT_NAME'] = '/catering/index.php';

        self::assertTrue(\wc_prepare_server_error_page($root));
        self::assertTrue(\wc_prepare_server_error_page($root));

        $contents = (string) file_get_contents($root . '/.htaccess');
        self::assertStringContainsString('ErrorDocument 500 /catering/errors/500.html', $contents);
        self::assertSame(1, substr_count($contents, '# BEGIN Website Core server error page'));
    }
}
