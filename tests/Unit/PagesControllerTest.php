<?php
declare(strict_types=1);

namespace Tests\Unit;

use Admin\Controllers\PagesController;
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
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Security\SessionManager;
use Core\Settings\SettingsRepository;
use Core\View\View;
use PHPUnit\Framework\TestCase;

final class PagesControllerTest extends TestCase
{
    private Connection $db;
    private PagesController $controller;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $this->db = Connection::sqlite(':memory:', 'wc_');
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_NONE) @session_start();

        $config = new Config('/nonexistent');
        $settings = new SettingsRepository($this->db);
        $admins = new AdminRepository($this->db);
        $audit = new AuditLog($this->db);
        $auth = new AuthService(
            $this->db, $admins, new Permissions(), new SessionManager($config),
            new RateLimiter($this->db), $audit, $config, new SystemMail($this->db, $settings)
        );
        $admins->create('content-owner', 'content@example.test', password_hash('correct-horse-12', PASSWORD_DEFAULT), 'owner');
        self::assertNotNull($auth->attemptPassword(
            new Request('POST', '/admin/login', '/catering', [], [], [], [], []),
            'content-owner', 'correct-horse-12'
        ));

        $view = new View();
        $view->addResolver('admin', static function (string $name): ?string {
            $path = WC_ROOT . '/admin/views/' . $name . '.php';
            return is_file($path) ? $path : null;
        });
        $registry = new ModuleRegistry(
            $this->db, WC_ROOT . '/modules/industries', WC_ROOT . '/modules/addons', WC_ROOT . '/themes'
        );
        $container = new Container();
        foreach ([
            Connection::class => $this->db,
            Config::class => $config,
            SettingsRepository::class => $settings,
            AuthService::class => $auth,
            AuditLog::class => $audit,
            Csrf::class => new Csrf(),
            View::class => $view,
            ModuleRegistry::class => $registry,
            Router::class => new Router(),
            Dispatcher::class => new Dispatcher(),
        ] as $id => $service) {
            $container->instance($id, $service);
        }
        $this->controller = new PagesController($container);
    }

    public function testInvalidEditorInputRetainsSubmittedValues(): void
    {
        $id = $this->page('Original', 'original');
        $request = new Request('POST', '/admin/pages/edit/' . $id, '/catering', [], [
            'title' => 'Unsaved replacement', 'slug' => 'replacement', 'body' => '<p>New body</p>',
            'blocks_json' => '[]', 'schema_json' => '{broken', 'lock_version' => '1',
        ], [], [], []);
        $request->attributes['id'] = (string) $id;

        $response = $this->controller->edit($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Schema.org JSON is not valid JSON.', $response->body());
        self::assertStringContainsString('value="Unsaved replacement"', $response->body());
        self::assertStringContainsString('{broken', $response->body());
        self::assertSame('Original', $this->db->fetchColumn('SELECT title FROM `wc_pages` WHERE id=?', [$id]));
    }

    public function testStaleEditorCannotOverwriteCurrentPage(): void
    {
        $id = $this->page('Current title', 'current-title');
        $request = new Request('POST', '/admin/pages/edit/' . $id, '/catering', [], [
            'title' => 'Stale title', 'slug' => 'stale-title', 'body' => '',
            'blocks_json' => '[]', 'schema_json' => '', 'lock_version' => '99',
        ], [], [], []);
        $request->attributes['id'] = (string) $id;

        $response = $this->controller->edit($request);

        self::assertStringContainsString('changed after you opened it', $response->body());
        self::assertSame('Current title', $this->db->fetchColumn('SELECT title FROM `wc_pages` WHERE id=?', [$id]));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `wc_page_revisions`'));
    }

    public function testPreviewActionRedirectsToActualPreview(): void
    {
        $id = $this->page('Preview me', 'preview-me');
        $request = new Request('POST', '/admin/pages/' . $id . '/preview', '/catering', [], [], [], [], []);
        $request->attributes['id'] = (string) $id;

        $response = $this->controller->previewLink($request);

        self::assertSame(302, $response->status());
        self::assertMatchesRegularExpression(
            '#^/catering/index\.php\?p=/preview/[A-Za-z0-9_-]+$#',
            $response->headers()['Location'] ?? ''
        );
    }

    public function testContentFiltersKeepNoRewriteRoute(): void
    {
        $response = $this->controller->index(new Request(
            'GET', '/admin/pages', '/catering', ['q' => 'missing'], [], [], [], []
        ));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('action="/catering/index.php"', $response->body());
        self::assertStringContainsString('name="p" value="/admin/pages"', $response->body());
        self::assertStringContainsString('No pages match these filters.', $response->body());
    }

    public function testMigrationAddsConflictAndSnapshotColumns(): void
    {
        $pageColumns = array_column($this->db->fetchAll('PRAGMA table_info(`wc_pages`)'), 'name');
        $revisionColumns = array_column($this->db->fetchAll('PRAGMA table_info(`wc_page_revisions`)'), 'name');
        self::assertContains('lock_version', $pageColumns);
        self::assertContains('snapshot_json', $revisionColumns);
    }

    public function testRevisionRestoresCompletePageState(): void
    {
        $id = $this->page('Original title', 'original-title');
        $save = new Request('POST', '/admin/pages/edit/' . $id, '/catering', [], [
            'title' => 'Replacement title', 'slug' => 'replacement-title',
            'body' => '<p>Replacement</p>', 'blocks_json' => '[]',
            'meta_title' => 'Replacement search title', 'meta_description' => 'Replacement description',
            'canonical' => 'https://example.test/replacement', 'schema_json' => '{"@type":"WebPage"}',
            'noindex' => '1', 'lock_version' => '1',
        ], [], [], []);
        $save->attributes['id'] = (string) $id;
        self::assertSame(302, $this->controller->edit($save)->status());

        $revision = $this->db->fetch('SELECT * FROM `wc_page_revisions` WHERE page_id=?', [$id]);
        self::assertNotNull($revision);
        self::assertNotEmpty($revision['snapshot_json']);

        $restore = new Request('POST', '/admin/pages/' . $id . '/restore/' . $revision['id'], '/catering', [], [], [], [], []);
        $restore->attributes = ['id' => (string) $id, 'rid' => (string) $revision['id']];
        self::assertSame(302, $this->controller->restoreRevision($restore)->status());

        $restored = $this->db->fetch('SELECT * FROM `wc_pages` WHERE id=?', [$id]);
        self::assertSame('Original title', $restored['title']);
        self::assertSame('original-title', $restored['slug']);
        self::assertNull($restored['meta_title']);
        self::assertSame('draft', $restored['status']);
        self::assertSame(3, (int) $restored['lock_version']);
    }

    public function testOnlyTrashedPageCanBePermanentlyDeleted(): void
    {
        $id = $this->page('Delete me', 'delete-me');
        $activePurge = new Request('POST', '/admin/pages/' . $id . '/purge', '/catering', [], [], [], [], []);
        $activePurge->attributes['id'] = (string) $id;
        self::assertSame(404, $this->controller->purge($activePurge)->status());

        $trash = new Request('POST', '/admin/pages/' . $id . '/trash', '/catering', [], [], [], [], []);
        $trash->attributes['id'] = (string) $id;
        self::assertSame(302, $this->controller->trash($trash)->status());

        $this->db->insert('page_revisions', [
            'page_id' => $id, 'title' => 'Delete me', 'body' => '', 'blocks_json' => '[]',
            'snapshot_json' => '{}', 'saved_by' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        self::assertSame(302, $this->controller->purge($activePurge)->status());
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `wc_pages` WHERE id=?', [$id]));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `wc_page_revisions` WHERE page_id=?', [$id]));
    }

    private function page(string $title, string $slug): int
    {
        return (int) $this->db->insert('pages', [
            'title' => $title, 'slug' => $slug, 'body' => '', 'blocks_json' => '[]',
            'status' => 'draft', 'review_status' => 'draft', 'lock_version' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
