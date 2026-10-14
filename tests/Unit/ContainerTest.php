<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testInstanceAndGet(): void
    {
        $c = new Container();
        $obj = new \stdClass();
        $c->instance('x', $obj);
        $this->assertSame($obj, $c->get('x'));
    }

    public function testBindFactory(): void
    {
        $c = new Container();
        $c->bind('x', fn () => new \ArrayObject([1, 2]));
        $this->assertInstanceOf(\ArrayObject::class, $c->get('x'));
    }

    public function testSingletonResolvesOnce(): void
    {
        $c = new Container();
        $calls = 0;
        $c->singleton('x', function () use (&$calls) {
            $calls++;
            return new \stdClass();
        });
        $a = $c->get('x');
        $b = $c->get('x');
        $this->assertSame($a, $b);
        $this->assertSame(1, $calls);
    }

    public function testAutowire(): void
    {
        $c = new Container();
        $resolved = $c->get(FixtureNeedsDep::class);
        $this->assertInstanceOf(FixtureDep::class, $resolved->dep);
    }

    public function testUnresolvableThrows(): void
    {
        $c = new Container();
        $this->expectException(\RuntimeException::class);
        $c->get(FixtureNeedsScalar::class);
    }
}

class FixtureDep {}
class FixtureNeedsDep
{
    public function __construct(public FixtureDep $dep) {}
}
class FixtureNeedsScalar
{
    public function __construct(public string $required) {}
}
