<?php
declare(strict_types=1);

namespace Core\View;

/**
 * PHP-template view renderer with a namespace resolver:
 *   "admin/settings/index"   -> site/admin/views/settings/index.php
 *   "theme/page"             -> active theme templates/page.php (override chain)
 *   "pkg:publisher/name:view"-> module views/<view>.php with theme overrides
 *     at themes/<active>/modules/<package>/<view>.php (safe fallback)
 *
 * All output escapes through e(); raw HTML requires the Html helper.
 */
final class View
{
    /** @var array<string, callable(string):?string> */
    private array $resolvers = [];
    /** @var array<string, mixed> */
    private array $shared = [];

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** Resolver: view name -> absolute template path or null. */
    public function addResolver(string $namespace, callable $resolver): void
    {
        $this->resolvers[$namespace] = $resolver;
    }

    public function exists(string $name): bool
    {
        return $this->resolvePath($name) !== null;
    }

    public function render(string $name, array $data = []): string
    {
        $path = $this->resolvePath($name);
        if ($path === null) {
            throw new \RuntimeException("View not found: {$name}");
        }
        return $this->evaluate($path, array_merge($this->shared, $data));
    }

    /** Render with a wrapping layout; layout receives $content in data. */
    public function renderInLayout(string $name, string $layout, array $data = []): string
    {
        $data['content'] = $this->render($name, $data);
        return $this->render($layout, $data);
    }

    private function evaluate(string $__path, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__path;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    private function resolvePath(string $name): ?string
    {
        // "ns:view" or default "admin" namespace for unprefixed core views
        if (str_contains($name, ':')) {
            [$ns, $view] = explode(':', $name, 2);
        } else {
            $ns = 'admin';
            $view = $name;
        }
        if (isset($this->resolvers[$ns])) {
            return ($this->resolvers[$ns])($view);
        }
        return null;
    }
}
