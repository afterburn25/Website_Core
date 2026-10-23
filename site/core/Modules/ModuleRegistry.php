<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Database\Connection;
use Core\Support\Semver;

/**
 * Discovers package manifests on disk, reconciles them with the packages
 * table (state + journal), checks compatibility/dependencies, and boots only
 * enabled, compatible packages. Boot-failing packages are quarantined to
 * state 'failed' so recovery mode can rebuild the registry safely.
 */
final class ModuleRegistry
{
    /** Core-owned packages ship with the runtime and are never lifecycle-managed. */
    public static function isProtectedPackage(string $packageId): bool
    {
        return $packageId === 'core/base';
    }

    /** @var array<string, array> id => manifest */
    private array $manifests = [];
    /** @var array<string, string> id => absolute dir */
    private array $dirs = [];
    /** @var array<string, string> id => state from DB */
    private array $states = [];
    /** @var list<ModuleContext> one context per booted package */
    private array $contexts = [];

    public function __construct(
        private Connection $db,
        private string $industriesDir,
        private string $addonsDir,
        private string $themesDir,
    ) {}

    public function discover(): void
    {
        $this->manifests = [];
        $this->dirs = [];
        foreach ([
            'industry' => $this->industriesDir,
            'addon'    => $this->addonsDir,
            'theme'    => $this->themesDir,
        ] as $type => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $packageDir) {
                $file = is_file($packageDir . '/addon.json') ? $packageDir . '/addon.json'
                    : (is_file($packageDir . '/theme.json') ? $packageDir . '/theme.json' : null);
                if ($file === null) {
                    continue;
                }
                $decoded = json_decode((string) file_get_contents($file), true);
                if (!is_array($decoded)) {
                    continue;
                }
                $id = $decoded['id'] ?? basename($packageDir);
                // Duplicate package IDs across any destination are rejected.
                if (isset($this->manifests[$id])) {
                    $this->states[$id] = 'failed';
                    continue;
                }
                $decoded['__dir'] = $packageDir;
                $this->manifests[$id] = $decoded;
                $this->dirs[$id] = $packageDir;
            }
        }

        foreach ($this->db->fetchAll('SELECT package_id, state FROM `' . $this->db->table('packages') . '`') as $row) {
            $this->states[$row['package_id']] = (string) $row['state'];
        }
        // Built-in packages exist because the core is already running. A
        // stale database row must never make them appear staged or disabled.
        foreach (array_keys($this->manifests) as $id) {
            if (self::isProtectedPackage($id)) {
                $previousState = $this->states[$id] ?? null;
                $this->states[$id] = 'enabled';
                if ($previousState !== null && $previousState !== 'enabled') {
                    $this->db->run(
                        'UPDATE `' . $this->db->table('packages')
                        . '` SET state = ?, version = ?, updated_at = ? WHERE package_id = ?',
                        [
                            'enabled',
                            (string) ($this->manifests[$id]['version'] ?? ''),
                            date('Y-m-d H:i:s'),
                            $id,
                        ]
                    );
                }
            }
        }
    }

    /** @return array<string, array{manifest:array,dir:string,state:string,errors:list<string>}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->manifests as $id => $manifest) {
            $errors = Manifest::validate($manifest);
            if ($errors === []) {
                $errors = Manifest::compatible(
                    $manifest,
                    (string) (defined('WC_VERSION') ? WC_VERSION : '0.0.0'),
                    (int) (defined('WC_API_VERSION') ? WC_API_VERSION : 1),
                    PHP_VERSION
                );
                $errors = array_merge($errors, $this->dependencyErrors($manifest));
            }
            $out[$id] = [
                'manifest' => $manifest,
                'dir'      => $this->dirs[$id],
                'state'    => self::isProtectedPackage($id)
                    ? 'enabled'
                    : ($this->states[$id] ?? 'staged'),
                'errors'   => $errors,
            ];
        }
        return $out;
    }

    public function state(string $id): string
    {
        if (self::isProtectedPackage($id)) {
            return 'enabled';
        }
        return $this->states[$id] ?? 'staged';
    }

    public function setState(string $id, string $state): void
    {
        if (self::isProtectedPackage($id)) {
            $this->states[$id] = 'enabled';
            return;
        }
        $this->db->run(
            'INSERT INTO `' . $this->db->table('packages')
            . '` (package_id, state, updated_at) VALUES (?,?,?) '
            . ($this->db->driver() === 'sqlite'
                ? 'ON CONFLICT(package_id) DO UPDATE SET state=excluded.state, updated_at=excluded.updated_at'
                : 'ON DUPLICATE KEY UPDATE state=VALUES(state), updated_at=VALUES(updated_at)'),
            [$id, $state, date('Y-m-d H:i:s')]
        );

        $this->states[$id] = $state;
    }

    /** Boot every enabled+compatible package provider in dependency order.
     * Each package gets its own ModuleContext (its packageId is stamped on
     * everything it registers); contexts() returns them for aggregation. */
    public function bootEnabled(callable $registerPsr4, callable $contextFactory): array
    {
        $booted = [];
        $ordered = $this->dependencyOrder();
        foreach ($ordered as $id) {
            if ($this->state($id) !== 'enabled') {
                continue;
            }
            $info = $this->all()[$id] ?? null;
            if ($info === null || $info['errors'] !== []) {
                continue;
            }
            $manifest = $info['manifest'];
            try {
                foreach (($manifest['psr4'] ?? []) as $prefix => $dir) {
                    $registerPsr4($prefix, $info['dir'] . '/' . trim($dir, '/'));
                }
                if (!empty($manifest['entry'])) {
                    $entry = $info['dir'] . '/' . ltrim((string) $manifest['entry'], '/');
                    if (is_file($entry)) {
                        require_once $entry;
                    }
                }
                $provider = $manifest['provider'] ?? null;
                if (is_string($provider) && class_exists($provider)) {
                    $context = $contextFactory($id);
                    (new $provider())->register($context);
                    $this->contexts[] = $context;
                }
                $booted[] = $id;
            } catch (\Throwable $e) {
                // Quarantine: never let one package take the whole site down.
                $this->setState($id, 'failed');
                $this->db->insert('package_journal', [
                    'package_id' => $id,
                    'operation'  => 'boot',
                    'result'     => 'failed',
                    'detail'     => substr($e->getMessage(), 0, 2000),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return $booted;
    }

    /** @return list<ModuleContext> contexts of successfully booted packages */
    public function contexts(): array
    {
        return $this->contexts;
    }

    /** @return array<string, callable> merged scheduled-job handlers */
    public function jobHandlers(): array
    {
        $handlers = [];
        foreach ($this->contexts as $ctx) {
            $handlers += $ctx->jobHandlers();
        }
        return $handlers;
    }

    /** @return list<array{label:string,route:string,capability:string,package:string,category:?string}> */
    public function adminNavItems(): array
    {
        $items = [];
        foreach ($this->contexts as $ctx) {
            $items = array_merge($items, $ctx->adminNavItems());
        }
        return $items;
    }

    /**
     * Package-declared content types, keyed packageId => [type => def].
     * @return array<string, array<string, array{label:string,fields:list<array>}>>
     */
    public function contentTypes(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            if ($ctx->contentTypes() !== []) {
                $out[$ctx->packageId()] = $ctx->contentTypes();
            }
        }
        return $out;
    }

    /** @return list<array> evaluated package dashboard widgets */
    public function dashboardWidgets(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->dashboardWidgets() as $widget) {
                try {
                    $value = ($widget['provider'])();
                    $data = is_array($value) ? $value : ['value' => $value, 'detail' => ''];
                    $out[] = array_merge($data, [
                        'key' => $widget['key'], 'label' => $widget['label'],
                        'capability' => $widget['capability'], 'url' => $widget['url'],
                        'package' => $ctx->packageId(),
                    ]);
                } catch (\Throwable $e) {
                    error_log('[website-core] dashboard widget failed: ' . $ctx->packageId() . '/' . $widget['key'] . ': ' . $e->getMessage());
                }
            }
        }
        return $out;
    }

    /** @return list<array> standard package search results */
    public function search(string $query): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->searchProviders() as $registration) {
                try {
                    foreach ((array) ($registration['provider'])($query) as $result) {
                        if (is_array($result) && isset($result['title'], $result['url'])) {
                            $out[] = array_merge(
                                ['type' => 'Package', 'detail' => ''],
                                $result,
                                [
                                'capability' => $registration['capability'],
                                'package' => $ctx->packageId(),
                                ]
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[website-core] search provider failed: ' . $ctx->packageId() . ': ' . $e->getMessage());
                }
            }
        }
        return $out;
    }

    /** @return list<array{key:string,label:string,status:string,detail:string,package:string}> */
    public function healthChecks(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->healthChecks() as $check) {
                try {
                    $value = ($check['provider'])();
                    $data = is_array($value) ? $value : ['status' => (string) $value, 'detail' => ''];
                } catch (\Throwable $e) {
                    $data = ['status' => 'error', 'detail' => $e->getMessage()];
                }
                $status = in_array($data['status'] ?? '', ['ok', 'warn', 'error'], true) ? $data['status'] : 'warn';
                $out[] = [
                    'key' => $check['key'], 'label' => $check['label'], 'status' => $status,
                    'detail' => (string) ($data['detail'] ?? ''), 'package' => $ctx->packageId(),
                ];
            }
        }
        return $out;
    }

    /** Deterministic load order: dependencies before dependents. */
    public function dependencyOrder(): array
    {
        $order = [];
        $visiting = [];
        $visit = function (string $id) use (&$visit, &$order, &$visiting): void {
            if (in_array($id, $order, true) || isset($visiting[$id])) {
                return; // cycles are reported via dependencyErrors instead
            }
            $visiting[$id] = true;
            foreach (array_keys($this->manifests[$id]['dependencies'] ?? []) as $dep) {
                if (isset($this->manifests[$dep])) {
                    $visit($dep);
                }
            }
            unset($visiting[$id]);
            $order[] = $id;
        };
        foreach (array_keys($this->manifests) as $id) {
            $visit($id);
        }
        return $order;
    }

    /** @return list<string> */
    private function dependencyErrors(array $manifest): array
    {
        $errors = [];
        foreach (($manifest['dependencies'] ?? []) as $dep => $constraint) {
            $depManifest = $this->manifests[$dep] ?? null;
            if ($depManifest === null) {
                $errors[] = "missing dependency {$dep}";
            } elseif (!Semver::satisfies((string) ($depManifest['version'] ?? '0.0.0'), (string) $constraint)) {
                $errors[] = "dependency {$dep} needs {$constraint}, have {$depManifest['version']}";
            }
        }
        foreach (($manifest['conflicts'] ?? []) as $dep => $constraint) {
            $depManifest = $this->manifests[$dep] ?? null;
            if ($depManifest !== null && Semver::satisfies((string) ($depManifest['version'] ?? '0.0.0'), (string) $constraint)) {
                $errors[] = "conflicts with installed {$dep} {$depManifest['version']}";
            }
        }
        return $errors;
    }
}
