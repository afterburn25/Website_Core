<?php
declare(strict_types=1);

namespace Tests\Unit;

use Admin\Controllers\SettingsController;
use Core\Audit\AuditLog;
use Core\Auth\AdminRepository;
use Core\Auth\AuthService;
use Core\Auth\Permissions;
use Core\Config;
use Core\Container;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Mail\SystemMail;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Settings\SettingsRepository;
use Core\View\View;
use PHPUnit\Framework\TestCase;

final class SettingsControllerTest extends TestCase
{
    public function testOwnerCanRenderSettingsPageInSubfolderInstall(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }

        $db = new Connection(new \PDO('sqlite::memory:'), 'wc_');
        (new Migrator($db, WC_ROOT . '/database/migrations'))->migrate();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $config = new Config('/nonexistent');
        $settings = new SettingsRepository($db);
        $admins = new AdminRepository($db);
        $auth = new AuthService(
            $db,
            $admins,
            new Permissions(),
            new SessionManager($config),
            new RateLimiter($db),
            new AuditLog($db),
            $config,
            new SystemMail($db, $settings),
        );
        $admins->create(
            'settings-owner',
            'owner@example.test',
            password_hash('correct-horse-12', PASSWORD_DEFAULT),
            'owner'
        );
        $login = new Request('POST', '/admin/login', '/catering', [], [], [], [], []);
        self::assertNotNull($auth->attemptPassword($login, 'settings-owner', 'correct-horse-12'));

        $view = new View();
        $view->addResolver('admin', static function (string $name): ?string {
            $path = WC_ROOT . '/admin/views/' . $name . '.php';
            return is_file($path) ? $path : null;
        });

        $container = new Container();
        foreach ([
            Connection::class => $db,
            Config::class => $config,
            SettingsRepository::class => $settings,
            AuthService::class => $auth,
            Csrf::class => new Csrf(),
            View::class => $view,
        ] as $id => $service) {
            $container->instance($id, $service);
        }

        $request = new Request('GET', '/admin/settings', '/catering', [], [], [], [], ['HTTP_HOST'=>'demo.cajunwebpro.com']);
        $response = (new SettingsController($container))->index($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<h1>Settings</h1>', $response->body());
        self::assertStringContainsString(
            'action="/catering/index.php?p=/admin/settings"',
            $response->body()
        );
        self::assertStringContainsString('value="mail.cajunwebpro.com"',$response->body());
        self::assertStringContainsString('value="admin@cajunwebpro.com"',$response->body());
        self::assertStringContainsString('<option value="ssl" selected>',$response->body());
        self::assertStringContainsString('value="465"',$response->body());
        self::assertStringContainsString('id="app_url"',$response->body());
        self::assertStringContainsString('value="https://demo.cajunwebpro.com/catering"',$response->body());
        self::assertStringContainsString('readonly aria-readonly="true" class="locked-field"',$response->body());
        self::assertStringNotContainsString('leave blank to keep',$response->body());
    }
}
