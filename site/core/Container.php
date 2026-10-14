<?php
declare(strict_types=1);

namespace Core;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Minimal service container: explicit bindings plus reflection autowiring
 * for concrete classes. Deliberately small — no scopes, proxies, or magic.
 */
final class Container
{
    /** @var array<string, callable|object|string> */
    private array $bindings = [];
    /** @var array<string, object> */
    private array $instances = [];

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function bind(string $id, callable|string $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function singleton(string $id, callable|string $factory): void
    {
        $this->bindings[$id] = function (Container $c) use ($id, $factory): object {
            $resolved = is_string($factory) ? $c->make($factory) : $factory($c);
            $c->instance($id, $resolved);
            return $resolved;
        };
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function make(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            return is_string($binding) ? $this->resolve($binding) : $binding($this);
        }
        return $this->resolve($id);
    }

    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Cannot resolve unbound service: {$class}");
        }
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $this->has($type->getName())) {
                $args[] = $this->make($type->getName());
            } elseif ($type instanceof ReflectionNamedType && !$type->isBuiltin() && class_exists($type->getName())) {
                $args[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Unresolvable parameter \${$param->getName()} for {$class}");
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
