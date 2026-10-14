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

    public function testFirstMatchWinsSoCatchAllMustBeLast(): void
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
}
