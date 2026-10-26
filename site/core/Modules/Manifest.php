<?php
declare(strict_types=1);

namespace Core\Modules;

use Core\Support\Semver;

/**
 * Canonical addon.json manifest validator (schema version 1). The SDK ships
 * the JSON schema; this class is the runtime authority the Module Manager
 * uses before any package code is loaded.
 */
final class Manifest
{
    public const TYPES = ['industry', 'addon', 'theme'];
    public const STATES = [
        'staged', 'validated', 'installed', 'enabled',
        'upgrading', 'failed', 'disabled', 'removed',
    ];

    /**
     * Validate a decoded manifest array.
     * @return list<string> human-readable violations (empty = valid)
     */
    public static function validate(array $m): array
    {
        $errors = [];

        if (($m['schema'] ?? null) !== 1) {
            $errors[] = 'schema must be 1';
        }
        if (!self::idOk($m['id'] ?? null)) {
            $errors[] = 'id must look like "publisher/package" (lowercase, digits, -, _)';
        }
        $type = $m['type'] ?? null;
        if (!in_array($type, self::TYPES, true)) {
            $errors[] = 'type must be one of: ' . implode(', ', self::TYPES);
        }
        if (!is_string($m['version'] ?? null) || !Semver::valid($m['version'])) {
            $errors[] = 'version must be valid semver';
        }
        if (!is_string($m['name'] ?? null) || trim($m['name']) === '') {
            $errors[] = 'name is required';
        }
        if (!is_string($m['license'] ?? null) || trim($m['license']) === '') {
            $errors[] = 'license identifier is required';
        }
        if (isset($m['license_key']) && (!is_string($m['license_key']) || strlen($m['license_key']) < 8)) {
            $errors[] = 'license_key must be a string of at least 8 characters';
        }

        $requires = $m['requires'] ?? [];
        if (!is_array($requires)) {
            $errors[] = 'requires must be an object';
        } else {
            if (isset($requires['core']) && (!is_string($requires['core'])
                || trim($requires['core']) === '' || !Semver::constraintValid($requires['core']))) {
                $errors[] = 'requires.core must be a version constraint string';
            }
            if (isset($requires['api']) && !is_int($requires['api'])) {
                $errors[] = 'requires.api must be an integer (extension API major)';
            }
            if (isset($requires['php']) && (!is_string($requires['php'])
                || trim($requires['php']) === '' || !Semver::constraintValid($requires['php']))) {
                $errors[] = 'requires.php must be a version constraint string';
            }
            if (isset($requires['services']) && !is_array($requires['services'])) {
                $errors[] = 'requires.services must be an object of service-id => constraint';
            } elseif (isset($requires['services'])) {
                foreach ($requires['services'] as $service => $constraint) {
                    if (!is_string($service) || !preg_match('/^[a-z][a-z0-9_.-]{2,119}$/', $service)
                        || !is_string($constraint) || trim($constraint) === ''
                        || !Semver::constraintValid($constraint)) {
                        $errors[] = 'requires.services entries need a safe service id and version constraint';
                    }
                }
            }
        }

        foreach (['dependencies', 'conflicts'] as $key) {
            if (isset($m[$key]) && !is_array($m[$key])) {
                $errors[] = "{$key} must be an object of package-id => constraint";
            } elseif (isset($m[$key])) {
                foreach ($m[$key] as $id => $c) {
                    if (!self::idOk($id)) {
                        $errors[] = "{$key} contains invalid package id '{$id}'";
                    }
                    if (!is_string($c) || trim($c) === '' || !Semver::constraintValid($c)) {
                        $errors[] = "{$key}.{$id} must be a constraint string";
                    }
                }
            }
        }

        if ($type !== 'theme') {
            if (isset($m['entry']) && (!is_string($m['entry']) || !self::relativePathOk($m['entry']) || !str_ends_with(strtolower($m['entry']), '.php'))) {
                $errors[] = 'entry must be a safe PHP file path relative to package root';
            }
            if (isset($m['provider']) && (!is_string($m['provider']) || !preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+[A-Za-z_][A-Za-z0-9_]*$/', $m['provider']))) {
                $errors[] = 'provider must be a fully-qualified class name';
            }
            if (isset($m['psr4']) && !is_array($m['psr4'])) {
                $errors[] = 'psr4 must be an object of prefix => directory';
            } elseif (isset($m['psr4'])) {
                foreach ($m['psr4'] as $prefix => $directory) {
                    if (!is_string($prefix) || !preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+$/', $prefix)
                        || !is_string($directory) || !self::relativePathOk($directory)) {
                        $errors[] = 'psr4 entries require a namespace prefix and safe package-relative directory';
                    }
                }
            }
        } elseif (isset($m['entry']) || isset($m['provider']) || isset($m['psr4']) || isset($m['migrations'])) {
            $errors[] = 'theme packages cannot declare executable PHP hooks or migrations';
        }

        if (isset($m['capabilities']) && !is_array($m['capabilities'])) {
            $errors[] = 'capabilities must be a list of strings';
        } elseif (isset($m['capabilities'])) {
            $packageParts = is_string($m['id'] ?? null) ? explode('/', $m['id'], 2) : [];
            $capabilityPrefix = isset($packageParts[1]) ? str_replace('-', '_', $packageParts[1]) . '.' : '';
            foreach ($m['capabilities'] as $cap) {
                if (!is_string($cap) || !preg_match('/^[a-z0-9_.:-]+$/', $cap)) {
                    $errors[] = "invalid capability token";
                } elseif ($capabilityPrefix !== '' && !str_starts_with($cap, $capabilityPrefix)) {
                    $errors[] = "capability {$cap} must be prefixed with {$capabilityPrefix}";
                }
            }
        }

        if (isset($m['migrations'])) {
            if (!is_string($m['migrations']) && !is_array($m['migrations'])) {
                $errors[] = 'migrations must be a safe directory path or list';
            } else {
                $directories = is_array($m['migrations']) ? $m['migrations'] : [$m['migrations']];
                if ($directories === [] || count($directories) > 20) $errors[] = 'migrations must contain between 1 and 20 directories';
                foreach ($directories as $directory) {
                    if (!is_string($directory) || !self::relativePathOk($directory)) {
                        $errors[] = 'migration directories must be safe package-relative paths';
                        break;
                    }
                }
            }
        }

        if (isset($m['assets'])) {
            if (!is_array($m['assets'])) {
                $errors[] = 'assets must be an object of css, js, and images lists';
            } else {
                foreach ($m['assets'] as $kind => $paths) {
                    if (!in_array($kind, ['css', 'js', 'images'], true) || !is_array($paths)) {
                        $errors[] = 'assets supports only css, js, and images lists';
                        continue;
                    }
                    foreach ($paths as $path) {
                        if (!is_string($path) || !self::relativePathOk($path)) {
                            $errors[] = "assets.{$kind} contains an unsafe package-relative path";
                            break;
                        }
                    }
                }
            }
        }

        if (isset($m['integrity'])) {
            if (!is_array($m['integrity']) || !isset($m['integrity']['files']) || !is_array($m['integrity']['files'])) {
                $errors[] = 'integrity.files must be an object of path => sha256';
            } else {
                foreach ($m['integrity']['files'] as $path => $hash) {
                    if (!is_string($path) || !self::relativePathOk($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                        $errors[] = 'integrity.files requires safe relative paths and SHA-256 hashes';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    public static function idOk(mixed $id): bool
    {
        return is_string($id) && (bool) preg_match('#^[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $id);
    }

    public static function relativePathOk(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path)) return false;
        $path = trim($path, '/');
        if ($path === '' || strlen($path) > 500 || str_contains($path, "\0")) return false;
        if (preg_match('#(^|/)\.\.?(/|$)#', $path)) return false;
        return true;
    }

    /** @return list<string> */
    public static function migrationDirectories(array $manifest): array
    {
        $value = $manifest['migrations'] ?? [];
        if (is_string($value)) $value = [$value];
        if (!is_array($value)) return [];
        return array_values(array_filter(array_map(
            static fn(mixed $path): string => is_string($path) ? trim($path, '/\\') : '',
            $value
        ), static fn(string $path): bool => self::relativePathOk($path)));
    }

    /** Does this manifest's requires block accept the given core/api/php? */
    public static function compatible(
        array $m,
        string $coreVersion,
        int $apiVersion,
        string $phpVersion,
        ?array $serviceVersions = null,
    ): array
    {
        $errors = [];
        $r = $m['requires'] ?? [];
        if (isset($r['core']) && !Semver::satisfies($coreVersion, (string) $r['core'])) {
            $errors[] = "requires core {$r['core']}, have {$coreVersion}";
        }
        if (isset($r['api']) && (int) $r['api'] !== $apiVersion) {
            $errors[] = "requires extension API {$r['api']}, have {$apiVersion}";
        }
        if (isset($r['php']) && !Semver::satisfies($phpVersion, (string) $r['php'])) {
            $errors[] = "requires PHP {$r['php']}, have {$phpVersion}";
        }
        $available = $serviceVersions ?? ServiceVersions::all();
        foreach (($r['services'] ?? []) as $service => $constraint) {
            $version = $available[(string) $service] ?? null;
            if ($version === null) {
                $errors[] = "requires unavailable Core service {$service}";
            } elseif (!Semver::satisfies($version, (string) $constraint)) {
                $errors[] = "requires Core service {$service} {$constraint}, have {$version}";
            }
        }
        return $errors;
    }
}
