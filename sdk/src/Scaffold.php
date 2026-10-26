<?php
declare(strict_types=1);

namespace WebsiteCore\Sdk;

/**
 * Package scaffolder: creates a minimal valid package skeleton using ONLY
 * the supported ModuleContext API. Usage:
 *   php sdk/scaffold.php <type> <publisher/name> <dest-dir>
 * Types: industry | addon | theme
 */
final class Scaffold
{
    public static function make(string $type, string $id, string $dest): void
    {
        if (!in_array($type, ['industry', 'addon', 'theme'], true)) {
            throw new \InvalidArgumentException("type must be industry|addon|theme");
        }
        if (!preg_match('#^[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $id)) {
            throw new \InvalidArgumentException("id must look like publisher/package");
        }
        [$publisher, $name] = explode('/', $id);
        $ns = self::ns($publisher) . '\\' . self::ns($name);
        $dir = rtrim($dest, '/\\') . '/' . $name;
        if (is_dir($dir)) {
            throw new \RuntimeException("destination exists: {$dir}");
        }
        mkdir($dir . '/src', 0750, true);

        $manifest = [
            'schema' => 1, 'id' => $id, 'type' => $type,
            'name' => ucwords(str_replace(['-', '_'], ' ', $name)),
            'version' => '0.1.0', 'license' => 'UNLICENSED-INTERNAL',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
        ];

        if ($type === 'theme') {
            mkdir($dir . '/templates', 0750, true);
            mkdir($dir . '/assets/css', 0750, true);
            $manifest['tokens'] = ['font' => 'system-ui, sans-serif', 'color-accent' => '#0369a1'];
            $manifest['templates'] = ['layout', 'page', '404'];
            file_put_contents($dir . '/templates/layout.php', self::themeLayout());
            file_put_contents($dir . '/assets/css/site.css', "/* {$id} theme styles */\n");
            file_put_contents($dir . '/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            file_put_contents($dir . '/README.md', self::readme($id, $type));
            return;
        }

        mkdir($dir . '/views/admin', 0750, true);
        mkdir($dir . '/migrations', 0750, true);
        mkdir($dir . '/tests', 0750, true);
        $manifest['provider'] = $ns . '\\Provider';
        $manifest['psr4'] = [$ns . '\\' => 'src/'];
        $manifest['capabilities'] = [$name . '.manage'];
        $manifest['migrations'] = ['migrations'];
        $manifest['requires']['services'] = ['content.presentation' => '^1.0.0'];
        file_put_contents($dir . '/src/Provider.php', self::provider($ns, $name));
        file_put_contents($dir . '/views/admin/index.php', self::adminView($name));
        file_put_contents($dir . '/migrations/0001_records.php', self::migration($name));
        file_put_contents($dir . '/tests/ContractTest.php', self::contractTest($id));
        file_put_contents($dir . '/README.md', self::readme($id, $type));
        file_put_contents($dir . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function ns(string $segment): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment)));
    }

    private static function provider(string $ns, string $name): string
    {
        $label = ucwords(str_replace(['-', '_'], ' ', $name));
        return <<<PHP
        <?php
        declare(strict_types=1);

        namespace {$ns};

        use Core\Modules\ModuleContext;

        final class Provider
        {
            public function register(ModuleContext \$ctx): void
            {
                \$ctx->permission('{$name}.manage', 'Manage {$name} records');
                \$views = \$ctx->views(__DIR__ . '/../views');
                \$ctx->adminPage('{$label}', '/admin/{$name}', '{$name}.manage');
                \$ctx->adminRoute('GET', '/admin/{$name}', static fn (\Core\Http\Request \$request) =>
                    \$ctx->adminView(\$request, \$views . ':admin/index', [], '{$label}', '{$name}.manage')
                );
                // Register routes, admin pages, jobs, content types, listeners.
                // Only the ModuleContext surface is supported.
            }
        }
        PHP;
    }

    private static function adminView(string $name): string
    {
        $label = ucwords(str_replace(['-', '_'], ' ', $name));
        return "<?php declare(strict_types=1); ?>\n<section class=\"card\"><h2>" . htmlspecialchars($label, ENT_QUOTES) . "</h2><p>Your package workspace is ready.</p></section>\n";
    }

    private static function migration(string $name): string
    {
        $table = str_replace('-', '_', $name) . '_records';
        return <<<PHP
        <?php
        declare(strict_types=1);

        use Core\Database\Connection;
        use Core\Database\Migration;

        return new class extends Migration {
            public function id(): string { return '0001'; }
            public function description(): string { return 'Create package records'; }
            public function up(Connection \$db): void
            {
                \$table = \$db->table('{$table}');
                \$auto = \$db->driver() === 'sqlite'
                    ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
                    : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
                \$engine = \$db->driver() === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
                \$db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{\$table}` (id {\$auto}, title VARCHAR(190) NOT NULL, created_at VARCHAR(30) NOT NULL){\$engine}");
            }
        };
        PHP;
    }

    private static function contractTest(string $id): string
    {
        return <<<PHP
        <?php
        declare(strict_types=1);

        use PHPUnit\Framework\TestCase;

        final class ContractTest extends TestCase
        {
            public function testManifestIdentity(): void
            {
                \$manifest = json_decode((string) file_get_contents(__DIR__ . '/../addon.json'), true);
                self::assertSame('{$id}', \$manifest['id'] ?? null);
                self::assertSame(1, \$manifest['schema'] ?? null);
            }
        }
        PHP;
    }

    private static function readme(string $id, string $type): string
    {
        return "# {$id}\n\nGenerated Website Core {$type} package. Build the directly installable module ZIP with `php sdk/package.php <package-dir> <output.zip>`.\n";
    }

    private static function themeLayout(): string
    {
        return <<<'PHP'
        <?php /* Theme layout — see docs/THEME_API.md */ ?>
        <!doctype html>
        <html lang="en">
        <head><meta charset="utf-8"><title><?= e($siteName) ?></title></head>
        <body><?= $content ?></body>
        </html>
        PHP;
    }
}
