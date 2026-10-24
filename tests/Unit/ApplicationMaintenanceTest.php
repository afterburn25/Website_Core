<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Application;
use Core\Config;
use Core\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ApplicationMaintenanceTest extends TestCase
{
    public function testLoggedOutAdminNamespaceRemainsReachableDuringMaintenance(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }

        $tmp = \WcProcessTmpDirs::make('wc-maintenance-');
        mkdir($tmp, 0700, true);
        mkdir($tmp . '/storage', 0700, true);
        mkdir($tmp . '/upload', 0700, true);
        file_put_contents($tmp . '/storage/maintenance.flag', date('c') . ' test');
        $configFile = $tmp . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export([
            'app' => ['timezone' => 'UTC', 'debug' => false],
            'database' => ['driver' => 'sqlite', 'name' => $tmp . '/test.sqlite', 'prefix' => 'wc_'],
            'paths' => [
                'storage' => $tmp . '/storage', 'upload' => $tmp . '/upload',
                'modules' => WC_MODULES, 'themes' => WC_THEMES,
            ],
        ], true) . ';');

        $app = new Application(new Config($configFile));
        $app->bootServices($this->request('/admin'));
        $method = new ReflectionMethod(Application::class, 'inMaintenance');

        self::assertFalse($method->invoke($app, $this->request('/admin')));
        self::assertFalse($method->invoke($app, $this->request('/admin/')));
        self::assertFalse($method->invoke($app, $this->request('/admin/login')));
        self::assertFalse($method->invoke($app, $this->request('/admin/settings')));
        self::assertTrue($method->invoke($app, $this->request('/')));
        self::assertTrue($method->invoke($app, $this->request('/services')));
        self::assertTrue($method->invoke($app, $this->request('/administrator')));

        $response = $app->dispatch($this->request('/admin'));
        self::assertSame(302, $response->status());
        self::assertSame(
            '/catering/index.php?p=/admin/login',
            $response->headers()['Location'] ?? null
        );
    }

    private function request(string $path): Request
    {
        return new Request('GET', $path, '/catering', [], [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'demo.cajunwebpro.com',
        ]);
    }
}
