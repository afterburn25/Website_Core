<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private function request(string $method, string $path): Request
    {
        return new Request($method, $path, '', [], [], [], [], []);
    }

    public function testExactMatch(): void
    {
        $r = new Router();
        $r->get('/admin', fn (Request $q) => Response::text('dash'));
        $res = $r->dispatch($this->request('GET', '/admin'));
        $this->assertSame('dash', $res->body());
    }

    public function testRewriteIndependentUrlsPreserveSubfolderAndQuery(): void
    {
        $request = new Request('GET', '/admin', '/catering', [], [], [], [], []);

        $this->assertSame('/catering/index.php?p=', $request->routeBase());
        $this->assertSame('/catering/index.php?p=/admin/login', $request->routeUrl('/admin/login'));
        $this->assertSame(
            '/catering/index.php?p=/admin/media&folder=events',
            $request->routeUrl('/admin/media', ['folder' => 'events'])
        );
        $this->assertSame(
            '/catering/index.php?p=/admin/media&confirm=4',
            $request->routeUrl('/admin/media?confirm=4')
        );
    }

    public function testParamExtraction(): void
    {
        $r = new Router();
        $r->get('/users/{id:\d+}/posts/{slug}', function (Request $q) {
            return Response::text($q->attributes['id'] . ':' . $q->attributes['slug']);
        });
        $res = $r->dispatch($this->request('GET', '/users/42/posts/hello'));
        $this->assertSame('42:hello', $res->body());
        // numeric constraint rejects non-digits
        $this->assertSame(404, $r->dispatch($this->request('GET', '/users/abc/posts/x'))->status());
    }

    public function testNotFound(): void
    {
        $r = new Router();
        $this->assertSame(404, $r->dispatch($this->request('GET', '/nope'))->status());
    }

    public function testMethodNotAllowed(): void
    {
        $r = new Router();
        $r->post('/submit', fn (Request $q) => Response::text('ok'));
        $res = $r->dispatch($this->request('GET', '/submit'));
        $this->assertSame(405, $res->status());
        $this->assertSame('POST', $res->headers()['Allow']);
    }

    public function testGroupPrefix(): void
    {
        $r = new Router();
        $r->group('/admin', function (Router $g) {
            $g->get('/settings', fn (Request $q) => Response::text('s'));
        });
        $this->assertSame('s', $r->dispatch($this->request('GET', '/admin/settings'))->body());
    }

    public function testMiddlewarePipeline(): void
    {
        $log = [];
        $mw = function (Request $q, callable $next) use (&$log) {
            $log[] = 'before';
            $res = $next($q);
            $log[] = 'after';
            return $res;
        };
        $r = new Router();
        $r->middleware($mw);
        $r->get('/x', function (Request $q) use (&$log) {
            $log[] = 'handler';
            return Response::text('ok');
        });
        $r->dispatch($this->request('GET', '/x'));
        $this->assertSame(['before', 'handler', 'after'], $log);
    }

    public function testSpecificRoutesBeatCatchAll(): void
    {
        $r = new Router();
        $r->get('/admin/login', fn (Request $q) => Response::text('login'));
        $r->get('/{slug:(?!admin\b)[a-z0-9][a-z0-9\-\/]*}', fn (Request $q) => Response::text('page:' . $q->attributes['slug']));
        $this->assertSame('login', $r->dispatch($this->request('GET', '/admin/login'))->body());
        $this->assertSame('page:about', $r->dispatch($this->request('GET', '/about'))->body());
        // 'adminanything' is not under /admin — a legitimate public slug.
        $this->assertSame('page:adminanything/deep', $r->dispatch($this->request('GET', '/adminanything/deep'))->body());
        $this->assertSame(404, $r->dispatch($this->request('GET', '/admin/nonexistent'))->status());
    }

    /**
     * Regression: the slug catch-all used to shadow module public routes
     * because it registered first and dispatch was first-match-wins.
     * Specificity, not order, must decide overlapping matches.
     */
    public function testLaterSpecificRouteStillBeatsEarlierCatchAll(): void
    {
        $r = new Router();
        // Catch-all registered FIRST — the old dispatch order.
        $r->get('/{slug:(?!admin\b)[a-z0-9][a-z0-9\-\/]*}', fn (Request $q) => Response::text('page:' . $q->attributes['slug']));
        // Module route registered AFTER.
        $r->get('/inquiry', fn (Request $q) => Response::text('module-form'));
        $this->assertSame('module-form', $r->dispatch($this->request('GET', '/inquiry'))->body());
        $this->assertSame('page:about', $r->dispatch($this->request('GET', '/about'))->body());
    }

    /**
     * Slug-shadow detection for page saves: any GET-capable route other
     * than the page catch-all that matches a slug makes it unreachable.
     */
    public function testIsConcreteMatch(): void
    {
        $r = new Router();
        $r->get('/inquiry', fn (Request $q) => Response::text('form'));
        $r->get('/menu/{cat}', fn (Request $q) => Response::text('menu'));
        $r->post('/only-post', fn (Request $q) => Response::text('no'));
        $r->get('/{slug:(?!admin\b)[a-z0-9][a-z0-9\-\/]*}', fn (Request $q) => Response::text('page'));

        $this->assertTrue($r->isConcreteMatch('/inquiry'));      // literal module route
        $this->assertTrue($r->isConcreteMatch('/menu/mains'));   // param module route
        $this->assertFalse($r->isConcreteMatch('/about'));       // only catch-all matches
        $this->assertFalse($r->isConcreteMatch('/only-post'));   // POST-only cannot shadow GET pages
        $this->assertFalse($r->isConcreteMatch('/'));            // nothing matches
    }
}
