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
            if (isset($requires['core']) && !is_string($requires['core'])) {
                $errors[] = 'requires.core must be a version constraint string';
            }
            if (isset($requires['api']) && !is_int($requires['api'])) {
                $errors[] = 'requires.api must be an integer (extension API major)';
            }
            if (isset($requires['php']) && !is_string($requires['php'])) {
                $errors[] = 'requires.php must be a version constraint string';
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
                    if (!is_string($c)) {
                        $errors[] = "{$key}.{$id} must be a constraint string";
                    }
                }
            }
        }

        if ($type !== 'theme') {
            if (isset($m['entry']) && !is_string($m['entry'])) {
                $errors[] = 'entry must be a PHP file path relative to package root';
            }
            if (isset($m['provider']) && !is_string($m['provider'])) {
                $errors[] = 'provider must be a fully-qualified class name';
            }
            if (isset($m['psr4']) && !is_array($m['psr4'])) {
                $errors[] = 'psr4 must be an object of prefix => directory';
            }
        }

        if (isset($m['capabilities']) && !is_array($m['capabilities'])) {
            $errors[] = 'capabilities must be a list of strings';
        } elseif (isset($m['capabilities'])) {
            foreach ($m['capabilities'] as $cap) {
                if (!is_string($cap) || !preg_match('/^[a-z0-9_.:-]+$/', $cap)) {
                    $errors[] = "invalid capability token";
                }
            }
        }

        if (isset($m['migrations']) && !is_string($m['migrations']) && !is_array($m['migrations'])) {
            $errors[] = 'migrations must be a directory path or list';
        }

        if (isset($m['integrity'])) {
            if (!is_array($m['integrity']) || !isset($m['integrity']['files']) || !is_array($m['integrity']['files'])) {
                $errors[] = 'integrity.files must be an object of path => sha256';
            }
        }

        return $errors;
    }

    public static function idOk(mixed $id): bool
    {
        return is_string($id) && (bool) preg_match('#^[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $id);
    }

    /** Does this manifest's requires block accept the given core/api/php? */
    public static function compatible(array $m, string $coreVersion, int $apiVersion, string $phpVersion): array
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
        return $errors;
    }
}
