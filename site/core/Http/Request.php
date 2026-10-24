<?php
declare(strict_types=1);

namespace Core\Http;

/**
 * HTTP request abstraction. Base-path aware so the same code serves root
 * domains and subfolder deployments. Host validation happens against the
 * configured app.url unless the request comes through a trusted proxy.
 */
final class Request
{
    /** @var array<string, mixed> */
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,       // path relative to base, e.g. "/admin/settings"
        public readonly string $basePath,   // e.g. "" or "/site"
        public readonly array $query,
        public readonly array $post,
        public readonly array $cookies,
        public readonly array $files,
        public readonly array $server,
        public readonly string $rawBody = '',
    ) {}

    public static function capture(array $trustedProxies = []): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        $script = $server['SCRIPT_NAME'] ?? '/index.php';
        $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($basePath === '/' || $basePath === '.') {
            $basePath = '';
        }

        $uri = $server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // No-rewrite fallback: /index.php/admin/settings or ?p=/admin/settings
        if (isset($_GET['p']) && is_string($_GET['p'])) {
            $path = '/' . ltrim($_GET['p'], '/');
        } elseif ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }
        $path = '/' . ltrim($path, '/');

        return new self(
            method: $method,
            path: $path === '' ? '/' : $path,
            basePath: $basePath,
            query: $_GET,
            post: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            server: $server,
            rawBody: (string) file_get_contents('php://input'),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_string($v) ? trim($v) : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') === 'on' || ($this->server['HTTPS'] ?? '') === '1') {
            return true;
        }
        return ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            && $this->fromTrustedProxy();
    }

    public function host(): string
    {
        $host = $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
        return strtolower((string) preg_replace('/:\d+$/', '', $host));
    }

    public function ip(): string
    {
        if ($this->fromTrustedProxy() && !empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $this->server['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 300);
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }

    public function url(string $path = '/'): string
    {
        return $this->basePath . $path;
    }

    /** Rewrite-independent route prefix for hosts that ignore .htaccess. */
    public function routeBase(): string
    {
        return rtrim($this->basePath, '/') . '/index.php?p=';
    }

    /** Build an application URL that works in root and subfolder installs. */
    public function routeUrl(string $path = '/', array $query = []): string
    {
        [$route, $inlineQuery] = array_pad(explode('?', $path, 2), 2, '');
        $url = $this->routeBase() . '/' . ltrim($route, '/');
        if ($inlineQuery !== '') {
            $url .= '&' . $inlineQuery;
        }
        if ($query !== []) {
            $url .= '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    private bool $trustedProxy = false;

    private function fromTrustedProxy(): bool
    {
        return $this->trustedProxy;
    }

    public function trustProxy(bool $trust): void
    {
        $this->trustedProxy = $trust;
    }
}
