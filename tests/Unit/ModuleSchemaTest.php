<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Events\Dispatcher;
use Core\Http\Request;
use Core\Modules\ModuleContext;
use Core\Modules\ModuleRegistry;
use Core\Routing\Router;
use Core\Seo\ModuleSchema;
use PHPUnit\Framework\TestCase;

final class ModuleSchemaTest extends TestCase
{
    public function testInjectsValidatedPackageSchemaWithoutAllowingMarkup(): void
    {
        $registry = new ModuleRegistry(Connection::sqlite(), '/missing/a', '/missing/b', '/missing/c');
        $context = new ModuleContext('acme/catering', new Router(), new Dispatcher());
        $context->structuredData('services', fn (Request $request): array => [[
            '@context' => 'https://attacker.invalid',
            '@type' => 'Service',
            'name' => 'Catering </script><script>alert(1)</script>',
            'url' => 'https://' . $request->host() . '/services',
        ]]);
        $property = new \ReflectionProperty($registry, 'contexts');
        $property->setValue($registry, [$context]);
        $service = new ModuleSchema($registry);
        $request = new Request('GET', '/services', '', [], [], [], [], ['HTTP_HOST' => 'example.test']);
        $html = $service->inject('<html><head><title>Test</title></head><body></body></html>', $request);

        self::assertStringContainsString('application/ld+json', $html);
        self::assertStringContainsString('https://schema.org', $html);
        self::assertStringNotContainsString('attacker.invalid', $html);
        self::assertStringNotContainsString('</script><script>', $html);
        self::assertStringContainsString('\\u003C/script\\u003E', $html);
    }

    public function testRejectsUnknownTypesAndUnsafeProperties(): void
    {
        $service = new ModuleSchema(new ModuleRegistry(Connection::sqlite(), '/a', '/b', '/c'));
        $this->expectException(\InvalidArgumentException::class);
        $service->normalize(['@type' => 'MadeUpBusinessType', '<script>' => 'bad']);
    }

    public function testModuleRejectsDuplicateSchemaKeys(): void
    {
        $context = new ModuleContext('acme/demo', new Router(), new Dispatcher());
        $context->structuredData('profile', fn (): array => ['@type' => 'Organization']);
        $this->expectException(\InvalidArgumentException::class);
        $context->structuredData('profile', fn (): array => ['@type' => 'Organization']);
    }
}
