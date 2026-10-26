<?php
declare(strict_types=1);

namespace Tests\Unit;

use Admin\Controllers\ResourceController;
use Core\Audit\AuditLog;
use Core\Auth\AdminRepository;
use Core\Auth\AuthService;
use Core\Auth\Permissions;
use Core\Config;
use Core\Container;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Events\Dispatcher;
use Core\Http\Request;
use Core\Mail\SystemMail;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Settings\SettingsRepository;
use Core\View\View;
use PHPUnit\Framework\TestCase;

final class ResourceControllerTest extends TestCase
{
    private Connection $db;
    private ResourceController $controller;
    private string $owner;
    private string $resource;
    /** @var list<int> */
    private array $reordered = [];
    /** @var array{ids:list<int>,action:string}|null */
    private ?array $bulk = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite not available');
        $this->db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) @session_start();

        $config = new Config('/nonexistent');
        $settings = new SettingsRepository($this->db);
        $admins = new AdminRepository($this->db);
        $audit = new AuditLog($this->db);
        $permissions = new Permissions($this->db);
        $auth = new AuthService(
            $this->db, $admins, $permissions, new SessionManager($config),
            new RateLimiter($this->db), $audit, $config, new SystemMail($this->db, $settings)
        );
        $admins->create('resource-owner', 'resource@example.test', password_hash('correct-horse-12', PASSWORD_DEFAULT), 'owner');
        self::assertNotNull($auth->attemptPassword(
            new Request('POST', '/admin/login', '/demo', [], [], [], [], []),
            'resource-owner', 'correct-horse-12'
        ));

        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher(), $permissions);
        $context->adminResource(
            'services', 'Services', 'demo.manage',
            [['name'=>'name','label'=>'Name','required'=>true]],
            [['name'=>'name','label'=>'Name']],
            fn(string $search, int $page, int $perPage): array => [
                'rows'=>[['id'=>1,'name'=>'One'],['id'=>2,'name'=>'Two'],['id'=>3,'name'=>'Three']],
                'total'=>3,
            ],
            fn(int $id): ?array => $id <= 3 ? ['id'=>$id,'name'=>'Record ' . $id] : null,
            fn(?int $id, array $data): array => [$id ?? 4, null],
            options: [
                'reorder' => function(array $ids): void { $this->reordered = $ids; },
                'bulk' => function(array $ids, string $action): bool {
                    $this->bulk = compact('ids', 'action');
                    return true;
                },
                'bulk_actions' => ['publish'=>'Publish', 'archive'=>'Archive'],
            ],
        );
        [$this->owner, $this->resource] = explode('/', (string)array_key_first($context->adminResources()), 2);

        $registry = new ModuleRegistry($this->db, '/nonexistent', '/nonexistent', '/nonexistent');
        $property = new \ReflectionProperty($registry, 'contexts');
        $property->setValue($registry, [$context]);

        $view = new View();
        $view->addResolver('admin', static function(string $name): ?string {
            $path = WC_ROOT . '/admin/views/' . $name . '.php';
            return is_file($path) ? $path : null;
        });
        $container = new Container();
        foreach ([
            Connection::class=>$this->db, Config::class=>$config,
            SettingsRepository::class=>$settings, AuthService::class=>$auth,
            AuditLog::class=>$audit, Csrf::class=>new Csrf(), View::class=>$view,
            ModuleRegistry::class=>$registry,
        ] as $id=>$service) $container->instance($id, $service);
        $this->controller = new ResourceController($container);
    }

    public function testResourceRendersOrderingAndBulkControls(): void
    {
        $response = $this->controller->index($this->request('GET'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('name="selected_ids[]"', $response->body());
        self::assertStringContainsString('data-resource-up', $response->body());
        self::assertStringContainsString('Save visible order', $response->body());
        self::assertStringContainsString('<option value="archive">Archive</option>', $response->body());
    }

    public function testReorderReceivesUniqueVisibleIdsAndIsAudited(): void
    {
        $response = $this->controller->index($this->request('POST', [
            'action'=>'reorder', 'ordered_ids'=>['3', '1', '1', '2'], 'q'=>'two', 'page'=>'2',
        ]));

        self::assertSame(302, $response->status());
        self::assertSame([3, 1, 2], $this->reordered);
        self::assertStringEndsWith('&q=two&page=2', $response->headers()['Location'] ?? '');
        self::assertSame('module.resource.reordered', $this->latestAuditEvent());
    }

    public function testBulkActionReceivesSelectedIdsAndIsAudited(): void
    {
        $response = $this->controller->index($this->request('POST', [
            'action'=>'bulk', 'bulk_action'=>'archive', 'selected_ids'=>['2', '3'],
        ]));

        self::assertSame(302, $response->status());
        self::assertSame(['ids'=>[2, 3], 'action'=>'archive'], $this->bulk);
        self::assertSame('module.resource.bulk', $this->latestAuditEvent());
    }

    public function testInvalidBulkActionAndMalformedIdsNeverReachCallbacks(): void
    {
        $invalidAction = $this->controller->index($this->request('POST', [
            'action'=>'bulk', 'bulk_action'=>'erase', 'selected_ids'=>['1'],
        ]));
        self::assertSame(200, $invalidAction->status());
        self::assertStringContainsString('Select a valid bulk action.', $invalidAction->body());
        self::assertNull($this->bulk);

        $invalidIds = $this->controller->index($this->request('POST', [
            'action'=>'reorder', 'ordered_ids'=>['1', 'not-an-id'],
        ]));
        self::assertSame(200, $invalidIds->status());
        self::assertStringContainsString('Select valid records.', $invalidIds->body());
        self::assertSame([], $this->reordered);

        $tooMany = $this->controller->index($this->request('POST', [
            'action'=>'bulk', 'bulk_action'=>'archive',
            'selected_ids'=>array_map('strval', range(1, 101)),
        ]));
        self::assertSame(200, $tooMany->status());
        self::assertStringContainsString('No more than 100 records may be changed at once.', $tooMany->body());
        self::assertNull($this->bulk);
    }

    private function request(string $method, array $post = []): Request
    {
        $request = new Request($method, '/admin/resources/' . $this->owner . '/' . $this->resource, '/demo', [], $post, [], [], []);
        $request->attributes = ['owner'=>$this->owner, 'resource'=>$this->resource];
        return $request;
    }

    private function latestAuditEvent(): string
    {
        return (string)$this->db->fetchColumn('SELECT event FROM `wc_audit_log` ORDER BY id DESC LIMIT 1');
    }
}
