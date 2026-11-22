<?php
declare(strict_types=1);

/**
 * Read-only, fail-closed release acceptance preflight for an authorized host.
 *
 * Usage:
 * php tools/verify-host-preflight.php /path/to/site --expected-core 2.6.2
 * php tools/verify-host-preflight.php /path/to/site --expected-core 2.6.2 \
 *   --expected-industry cajunwebpro/catering@2.1.2
 * php tools/verify-host-preflight.php /path/to/site --expected-core 2.6.2 \
 *   --private-config-dir /secure/config
 *
 * Only verifies file presence, private access-guard source and safe public
 * version markers. Does not include or execute config.php, read credentials,
 * connect to a database, contact a host, or modify files.
 *
 * A passing CLI/static result is NOT an installed PHP-FPM, restore, license,
 * security, accessibility, or browser acceptance result.
 */
require_once __DIR__ . '/host-preflight.php';

/** @return array{expected_core: string, expected_industry: ?string, no_industry: bool, root: string, private_config: ?string} */
function wc_release_preflight_arguments(array $argv): array
{
    $usage = 'Usage: php tools/verify-host-preflight.php <site-root> --expected-core X.Y.Z [--expected-industry namespace/name@X.Y.Z | --no-industry] [--private-config-dir <path>]';
    if (count($argv) < 4 || !is_string($argv[1]) || str_starts_with($argv[1], '--')) {
        throw new InvalidArgumentException($usage);
    }
    $options = [
        'root' => $argv[1],
        'expected_core' => '',
        'expected_industry' => null,
        'no_industry' => false,
        'private_config' => null,
    ];
    $seen = [];
    for ($i = 2; $i < count($argv); ++$i) {
        $flag = $argv[$i];
        if (!in_array($flag, ['--expected-core', '--expected-industry', '--no-industry', '--private-config-dir'], true)
            || isset($seen[$flag])) {
            throw new InvalidArgumentException($usage);
        }
        $seen[$flag] = true;
        if ($flag === '--no-industry') {
            $options['no_industry'] = true;
            continue;
        }
        if (!isset($argv[$i + 1]) || str_starts_with((string) $argv[$i + 1], '--')) {
            throw new InvalidArgumentException($usage);
        }
        $value = $argv[++$i];
        if ($flag === '--expected-core') {
            $options['expected_core'] = $value;
        } elseif ($flag === '--expected-industry') {
            $options['expected_industry'] = $value;
        } else {
            $options['private_config'] = $value;
        }
    }
    if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-dev)?$/', $options['expected_core']) !== 1
        || ($options['no_industry'] && $options['expected_industry'] !== null)) {
        throw new InvalidArgumentException($usage);
    }
    if ($options['expected_industry'] !== null
        && preg_match('#^[a-z0-9][a-z0-9._-]*/[a-z0-9][a-z0-9._-]*@[0-9]+\.[0-9]+\.[0-9]+(?:-[a-zA-Z0-9.-]+)?$#',
            $options['expected_industry']) !== 1) {
        throw new InvalidArgumentException($usage);
    }
    return $options;
}

/**
 * @param array<string, mixed> $report
 * @return array{schema: int, scope: string, accepted: bool, core_version: ?string, industry_count: int, failures: list<string>, not_tested: list<string>}
 */
function wc_release_preflight_evaluate(
    array $report,
    string $siteRoot,
    string $expectedCore,
    ?string $expectedIndustry = null,
    bool $noIndustry = false,
    ?string $privateConfigDir = null
): array {
    $failure = [];
    $fail = static function (string $code) use (&$failure): void {
        if (!in_array($code, $failure, true)) {
            $failure[] = $code;
        }
    };
    $checks = $report['checks'] ?? [];
    if (!is_array($checks)) {
        $checks = [];
    }
    foreach ([
        'front_controller_present', 'core_bootstrap_present', 'runtime_vendor_present',
        'root_htaccess_present', 'config_present', 'installed_lock_valid_format',
        'config_htaccess_present', 'storage_htaccess_present',
    ] as $key) {
        if (($checks[$key] ?? false) !== true) {
            $fail('missing_or_unverified_' . $key);
        }
    }
    if (($checks['install_php_present'] ?? true) !== false
        || ($checks['install_dir_present'] ?? true) !== false) {
        $fail('installer_not_retired');
    }
    if (($report['core_version'] ?? null) !== $expectedCore) {
        $fail('core_version_mismatch');
    }
    if (($report['installation_state'] ?? null) !== 'marker_and_config_present') {
        $fail('installation_markers_unverified');
    }

    // A symlinked config directory or a linked ancestor of a guard file must
    // not be counted as a trustworthy regular deployable file.
    $configDir = $privateConfigDir ?? $siteRoot . '/config';
    if (is_link($configDir)) {
        $fail('linked_private_configuration_directory');
    }
    foreach (['config', 'core', 'database', 'includes', 'modules', 'storage',
              'themes', 'upload', 'vendor'] as $name) {
        $base = $siteRoot . '/' . $name;
        $guard = $base . '/.htaccess';
        if (is_link($base) || is_link($guard) || !is_file($guard)) {
            $fail('private_guard_invalid_' . $name);
            continue;
        }
        $source = @file_get_contents($guard, false, null, 0, 16384);
        if (!is_string($source) || !str_contains($source, 'Require all denied')
            || !str_contains($source, 'Deny from all')) {
            $fail('private_guard_invalid_' . $name);
        }
    }
    if (is_link($siteRoot . '/includes') || is_link($siteRoot . '/includes/bootstrap.php')
        || is_link($siteRoot . '/vendor') || is_link($siteRoot . '/vendor/autoload.php')
        || is_link($configDir . '/config.php') || is_link($configDir . '/installed.lock')) {
        $fail('linked_runtime_or_configuration_file');
    }

    $industries = $report['industries'] ?? [];
    if (!is_array($industries)) {
        $industries = [];
        $fail('industry_inventory_unverified');
    }
    $ids = [];
    foreach ($industries as $industry) {
        if (!is_array($industry) || !is_string($industry['id'] ?? null)
            || !is_string($industry['version'] ?? null)
            || ($industry['state'] ?? null) !== 'on_disk_unverified') {
            $fail('industry_inventory_unverified');
            continue;
        }
        $ids[] = $industry['id'] . '@' . $industry['version'];
    }
    if ($expectedIndustry !== null && !in_array($expectedIndustry, $ids, true)) {
        $fail('expected_industry_manifest_not_found');
    }
    if ($noIndustry && $ids !== []) {
        $fail('unexpected_industry_manifest');
    }

    // Warnings from the source-only inspector are NOT an acceptance result:
    // a malformed or symlinked module must not be silently omitted from ids.
    foreach ((array) ($report['warnings'] ?? []) as $warning) {
        if (is_string($warning) && (
            str_contains($warning, 'industry manifest')
            || str_contains($warning, 'installed site still contains')
            || str_contains($warning, 'Configuration exists without')
            || str_contains($warning, 'Installation marker exists without')
        )) {
            $fail('incomplete_or_unsafe_installation_state');
        }
    }

    return [
        'schema' => 1,
        'scope' => 'read_only_static_release_preflight_not_live_host_acceptance',
        'accepted' => $failure === [],
        'core_version' => is_string($report['core_version'] ?? null) ? $report['core_version'] : null,
        'industry_count' => count($industries),
        'failures' => $failure,
        'not_tested' => [
            'actual_webserver_waf_and_php_fpm',
            'authentication_and_authorization',
            'database_schema_or_package_enabled_state',
            'real_license_service_or_signatures',
            'backup_restore_rollback_and_data_preservation',
            'browser_accessibility_and_performance',
        ],
    ];
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        $input = wc_release_preflight_arguments($argv);
        $root = realpath($input['root']);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException('The supplied site root is not a directory.');
        }
        $report = wc_host_preflight($root, $input['private_config']);
        $result = wc_release_preflight_evaluate($report, $root, $input['expected_core'],
            $input['expected_industry'], $input['no_industry'], $input['private_config']);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        exit($result['accepted'] ? 0 : 1);
    } catch (Throwable $exception) {
        // Do not include exception messages potentially containing private
        // filesystem paths, credentials, hostnames, or customer identifiers.
        fwrite(STDERR, "Static host preflight arguments or inspection are invalid.\n");
        exit(2);
    }
}
