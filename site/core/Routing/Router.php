<?php
declare(strict_types=1);

namespace Core\Routing;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Small router with {param} placeholders, method matching, and a middleware
 * pipeline. Modules register routes through the public ModuleContext API —
 * never by editing this class.
 */
final class Router
{
    /** @var list<array{methods:list<string>,pattern:string,regex:string,params:list<string>,handler:callable,middleware:list<callable>}> */
    private array $routes = [];

    /** @var list<callable(Request, callable):Response> global middleware */
    private array $middleware = [];

    private string $prefix = '';

    public function middleware(callable $mw): void
    {
        $this->middleware[] = $mw;
    }

    public function group(string $prefix, callable $register): void
    {
        $previous = $this->prefix;
        $this->prefix = $previous . '/' . trim($prefix, '/');
        try {
            $register($this);
        } finally {
            $this->prefix = $previous;
        }
    }

    /** @param list<callable> $middleware */
    public function add(string $methods, string $pattern, callable $handler, array $middleware = []): void
    {
        $full = $this->prefix . '/' . trim($pattern, '/');
        $full = '/' . trim($full, '/');
        if ($full === '') {
            $full = '/';
        }
        [$regex, $params] = $this->compile($full);
        $this->routes[] = [
            'methods'    => array_map('strtoupper', explode('|', $methods)),
            'pattern'    => $full,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET|POST', $pattern, $handler, $middleware);
    }

    /** @return list<array{methods:list<string>,pattern:string}> */
    public function routeList(): array
    {
        return array_map(
            fn (array $r) => ['methods' => $r['methods'], 'pattern' => $r['pattern']],
            $this->routes
        );
    }

    public function dispatch(Request $request): Response
    {
        $path = rtrim($request->path, '/') ?: '/';
        // Most-specific match wins, independent of registration order —
        // a broad catch-all (/{slug}) must never shadow a concrete module
        // route registered later. Score: literal characters, then fewer
        // params; registration order breaks exact ties (stable sort).
        $candidates = [];
        $allowed = [];
        foreach ($this->routes as $i => $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $candidates[] = [
                'route' => $route,
                'm' => $m,
                'score' => $this->specificity($route['pattern']),
                'i' => $i,
            ];
        }
        if ($candidates !== []) {
            usort($candidates, fn (array $a, array $b) =>
                $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);
            $winner = $candidates[0];
            $route = $winner['route'];
            $m = $winner['m'];
            foreach ($route['params'] as $i => $name) {
                $request->attributes[$name] = isset($m[$i + 1]) ? urldecode($m[$i + 1]) : null;
            }
            $pipeline = array_merge($this->middleware, $route['middleware']);
            return $this->runPipeline($pipeline, $route['handler'], $request);
        }
        if ($allowed !== []) {
            return Response::text('Method Not Allowed', 405)
                ->header('Allow', implode(', ', array_unique($allowed)));
        }
        return Response::notFound($this->render404());
    }

    /**
     * Specificity score for tie-breaking overlapping patterns: literal
     * characters dominate (concrete paths beat placeholders); fewer
     * parameters break ties (narrower patterns beat broad ones).
     */
    private function specificity(string $pattern): int
    {
        $params = preg_match_all('/\{[^}]+\}/', $pattern);
        $literal = strlen((string) preg_replace('/\{[^}]+\}/', '', $pattern));
        return $literal * 1000 - $params;
    }

    /**
     * @param list<callable> $pipeline
     */
    private function runPipeline(array $pipeline, callable $handler, Request $request): Response
    {
        $next = fn (Request $req): Response => $handler($req);
        foreach (array_reverse($pipeline) as $mw) {
            $inner = $next;
            $next = fn (Request $req): Response => $mw($req, $inner);
        }
        $response = $next($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('Route handler must return a Response');
        }
        return $response;
    }

    /** @return array{0:string,1:list<string>} */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        );
        return ['#^' . $regex . '$#', $params];
    }

    private function render404(): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Not Found</title></head>'
            . '<body><h1>404 — Page not found</h1><p>The page you requested does not exist.</p></body></html>';
    }
}
