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
        mkdir($dir . '/src', 0777, true);

        $manifest = [
            'schema' => 1, 'id' => $id, 'type' => $type,
            'name' => ucwords(str_replace(['-', '_'], ' ', $name)),
            'version' => '0.1.0', 'license' => 'UNLICENSED-INTERNAL',
            'requires' => ['core' => '>=0.0.1', 'api' => 1],
        ];

        if ($type === 'theme') {
            mkdir($dir . '/templates', 0777, true);
            mkdir($dir . '/assets/css', 0777, true);
            $manifest['tokens'] = ['font' => 'system-ui, sans-serif', 'color-accent' => '#0369a1'];
            $manifest['templates'] = ['layout', 'page', '404'];
            file_put_contents($dir . '/templates/layout.php', self::themeLayout());
            file_put_contents($dir . '/assets/css/site.css', "/* {$id} theme styles */\n");
            file_put_contents($dir . '/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        $manifest['provider'] = $ns . '\\Provider';
        $manifest['psr4'] = [$ns . '\\' => 'src/'];
        $manifest['capabilities'] = [];
        file_put_contents($dir . '/src/Provider.php', self::provider($ns));
        file_put_contents($dir . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function ns(string $segment): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment)));
    }

    private static function provider(string $ns): string
    {
        return <<<PHP
        <?php
        declare(strict_types=1);

        namespace {$ns};

        use Core\Modules\ModuleContext;

        final class Provider
        {
            public function register(ModuleContext \$ctx): void
            {
                // Register routes, admin pages, jobs, content types, listeners.
                // Only the ModuleContext surface is supported.
            }
        }
        PHP;
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
