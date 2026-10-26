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
    /** @var array<string,list<string>> discovery errors that DB state cannot hide */
    private array $discoveryErrors = [];

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
        $this->discoveryErrors = [];
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
                $id = is_string($decoded['id'] ?? null) ? $decoded['id'] : basename($packageDir);
                // Duplicate package IDs across any destination are rejected.
                if (isset($this->manifests[$id])) {
                    $this->discoveryErrors[$id][] = 'duplicate package id found in more than one installed directory';
                    continue;
                }
                $decoded['__dir'] = $packageDir;
                $this->manifests[$id] = $decoded;
                $this->dirs[$id] = $packageDir;
                if (($decoded['type'] ?? null) !== $type) {
                    $this->discoveryErrors[$id][] = "manifest type must be {$type} in this package directory";
                }
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
            $errors = array_merge($errors, $this->discoveryErrors[$id] ?? []);
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
        $this->contexts = [];
        $ordered = $this->dependencyOrder();
        $all = $this->all();
        foreach ($ordered as $id) {
            if ($this->state($id) !== 'enabled') {
                continue;
            }
            $info = $all[$id] ?? null;
            if ($info === null) {
                continue;
            }
            if ($info['errors'] !== []) {
                $this->setState($id, 'failed');
                $this->db->insert('package_journal', [
                    'package_id' => $id, 'operation' => 'boot', 'result' => 'failed',
                    'detail' => substr(implode(' ', $info['errors']), 0, 2000),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }
            $manifest = $info['manifest'];
            try {
                foreach (array_keys($manifest['dependencies'] ?? []) as $dependency) {
                    if (!in_array($dependency, $booted, true)) {
                        throw new \RuntimeException("Required dependency {$dependency} did not boot.");
                    }
                }
                foreach (($manifest['psr4'] ?? []) as $prefix => $dir) {
                    $path = $this->packagePath($info['dir'], (string) $dir, true);
                    $registerPsr4($prefix, $path);
                }
                if (!empty($manifest['entry'])) {
                    $entry = $this->packagePath($info['dir'], (string) $manifest['entry']);
                    if (!is_file($entry)) throw new \RuntimeException('Declared package entry file is missing.');
                    require_once $entry;
                }
                $provider = $manifest['provider'] ?? null;
                if (is_string($provider)) {
                    if (!class_exists($provider)) throw new \RuntimeException('Declared package provider class could not be loaded.');
                    $context = $contextFactory($id);
                    $instance = new $provider();
                    if (!is_callable([$instance, 'register'])) throw new \RuntimeException('Declared package provider has no register method.');
                    $instance->register($context);
                    $this->contexts[] = $context;
                }
                $booted[] = $id;
            } catch (\Throwable $e) {
                // Quarantine: never let one package take the whole site down.
                error_log('[website-core] package boot failed: ' . $id . ': ' . $e->getMessage());
                $this->setState($id, 'failed');
                $safeDetail = preg_match('/^(Required dependency|Declared package|Package manifest)/', $e->getMessage())
                    ? $e->getMessage()
                    : 'Package provider failed during registration. Review the application log.';
                $this->db->insert('package_journal', [
                    'package_id' => $id,
                    'operation'  => 'boot',
                    'result'     => 'failed',
                    'detail'     => substr($safeDetail, 0, 2000),
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

    /** @return array<string,array> page blocks contributed by enabled packages */
    public function pageBlocks(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->pageBlocks() as $type => $definition) {
                if (!isset($out[$type])) $out[$type] = $definition;
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
                    error_log('[website-core] health check failed: ' . $ctx->packageId() . '/' . $check['key'] . ': ' . $e->getMessage());
                    $data = ['status' => 'error', 'detail' => 'Check failed. Review the application log and package status.'];
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

    /** @return array<string,array{package:string,options:array}> */
    public function coreFeatures(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->coreFeatures() as $feature => $options) {
                $out[$feature] ??= ['package' => $ctx->packageId(), 'options' => $options];
            }
        }
        return $out;
    }

    public function supportsFeature(string $feature): bool
    {
        return isset($this->coreFeatures()[$feature]);
    }

    public function feature(string $feature): ?array
    {
        return $this->coreFeatures()[$feature] ?? null;
    }

    /** @return array<string,array> resources registered by enabled packages */
    public function adminResources(): array
    {
        $out = [];
        foreach ($this->contexts as $ctx) {
            foreach ($ctx->adminResources() as $key => $resource) {
                if (isset($out[$key])) throw new \RuntimeException('Duplicate module admin resource route: ' . $key);
                $out[$key] = $resource;
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
            $state = $this->state((string) $dep);
            if ($depManifest !== null && !in_array($state, ['staged', 'removed'], true)
                && Semver::satisfies((string) ($depManifest['version'] ?? '0.0.0'), (string) $constraint)) {
                $errors[] = "conflicts with installed {$dep} {$depManifest['version']}";
            }
        }
        $id = is_string($manifest['id'] ?? null) ? $manifest['id'] : '';
        $cycle = $id !== '' ? $this->dependencyCycle($id) : [];
        if ($cycle !== []) $errors[] = 'dependency cycle: ' . implode(' -> ', $cycle);
        return $errors;
    }

    /** @return list<string> */
    private function dependencyCycle(string $start): array
    {
        $visiting = [];
        $visited = [];
        $walk = function (string $id, array $path) use (&$walk, &$visiting, &$visited): array {
            if (isset($visiting[$id])) {
                $position = array_search($id, $path, true);
                return [...array_slice($path, $position === false ? 0 : $position), $id];
            }
            if (isset($visited[$id]) || !isset($this->manifests[$id])) return [];
            $visiting[$id] = true;
            $path[] = $id;
            foreach (array_keys($this->manifests[$id]['dependencies'] ?? []) as $dependency) {
                $cycle = $walk((string) $dependency, $path);
                if ($cycle !== []) return $cycle;
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            return [];
        };
        return $walk($start, []);
    }

    private function packagePath(string $root, string $relative, bool $directory = false): string
    {
        if (!Manifest::relativePathOk($relative)) throw new \RuntimeException('Package manifest contains an unsafe path.');
        $rootReal = realpath($root);
        $pathReal = realpath($root . '/' . trim(str_replace('\\', '/', $relative), '/'));
        if ($rootReal === false || $pathReal === false
            || ($pathReal !== $rootReal && !str_starts_with($pathReal, $rootReal . DIRECTORY_SEPARATOR))
            || ($directory && !is_dir($pathReal))) {
            throw new \RuntimeException('Declared package path is missing or outside its package directory.');
        }
        return $pathReal;
    }
}
