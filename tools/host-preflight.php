<?php
declare(strict_types=1);

/**
 * Read-only, credential-free installation preflight for an authorized host.
 *
 * Run from a trusted shell; never upload this tool into a public webroot:
 *   php tools/host-preflight.php /path/to/deployed/site
 *   php tools/host-preflight.php /path/to/deployed/site /private/config/dir
 *
 * No database connections, HTTP requests, config.php includes, secret
 * retrieval, permissions changes, filesystem writes or login attempts.
 * A CLI probe cannot determine the actual Apache/WAF/PHP-FPM/license verdict.
 *
 * @return array<string, mixed>
 */
function wc_host_preflight(string $siteRoot, ?string $configDir = null): array
{
    $siteRoot = realpath($siteRoot);
    if ($siteRoot === false || !is_dir($siteRoot)) {
        throw new InvalidArgumentException('Website root is not a readable directory.');
    }

    $configDir ??= $siteRoot . '/config';
    if (!is_dir($configDir)) {
        // A missing config directory is not an error during fresh installation.
        $configDir = rtrim($configDir, DIRECTORY_SEPARATOR);
    }

    $configPresent = is_file($configDir . '/config.php')
        && !is_link($configDir . '/config.php');
    $marker = $configDir . '/installed.lock';
    $markerContents = is_file($marker) && !is_link($marker)
        ? @file_get_contents($marker, false, null, 0, 1024)
        : false;
    $lockPresent = is_string($markerContents)
        && str_contains($markerContents, 'install complete');

    $bootstrap = $siteRoot . '/includes/bootstrap.php';
    $bootstrapContents = is_file($bootstrap)
        ? @file_get_contents($bootstrap, false, null, 0, 20480)
        : false;
    $coreVersion = null;
    if (is_string($bootstrapContents)
        && preg_match("/define\\('WC_VERSION', '([0-9]+\\.[0-9]+\\.[0-9]+(?:-dev)?)'\\)/", $bootstrapContents, $matches) === 1) {
        $coreVersion = $matches[1];
    }

    $checks = [
        'front_controller_present' => is_file($siteRoot . '/index.php'),
        'core_bootstrap_present' => is_file($bootstrap),
        'runtime_vendor_present' => is_file($siteRoot . '/vendor/autoload.php'),
        'root_htaccess_present' => is_file($siteRoot . '/.htaccess'),
        'config_present' => $configPresent,
        'installed_lock_valid_format' => $lockPresent,
        'install_php_present' => is_file($siteRoot . '/install.php') || is_link($siteRoot . '/install.php'),
        'install_dir_present' => is_dir($siteRoot . '/install') || is_link($siteRoot . '/install'),
        'config_htaccess_present' => is_file($siteRoot . '/config/.htaccess'),
        'storage_htaccess_present' => is_file($siteRoot . '/storage/.htaccess'),
    ];

    $warnings = [];
    if (!$checks['front_controller_present'] || !$checks['core_bootstrap_present']) {
        $warnings[] = 'The webroot does not contain the expected Core entry point and bootstrap.';
    }
    if (!$checks['runtime_vendor_present']) {
        $warnings[] = 'Production vendor autoload is absent from the inspected webroot.';
    }
    if (!$checks['root_htaccess_present']) {
        $warnings[] = 'Root .htaccess is absent; verify the actual web-server routing and access-deny rules.';
    }
    if ($configPresent && !$lockPresent) {
        $warnings[] = 'Configuration exists without a validated installation marker; inspect setup state using authorized admin tools.';
    }
    if ($lockPresent && !$configPresent) {
        $warnings[] = 'Installation marker exists without readable regular configuration; inspect runtime configuration location.';
    }
    if ($lockPresent && ($checks['install_php_present'] || $checks['install_dir_present'])) {
        $warnings[] = 'Installed site still contains public installer paths; verify cleanup/retirement and access denial.';
    }
    if ($configDir !== $siteRoot . '/config') {
        $warnings[] = 'An explicit private configuration directory was checked; PHP-FPM may resolve paths differently from this CLI.';
    }

    $packages = [];
    $paths = glob($siteRoot . '/modules/industries/*/addon.json') ?: [];
    sort($paths, SORT_STRING);
    foreach ($paths as $file) {
        // A package manifest is not proof that its database state is enabled.
        // Never output manifest fields beyond public package identity/version:
        // license_key, secrets, credentials and domain bindings are omitted.
        if (is_link($file) || !is_file($file) || filesize($file) > 262144) {
            $warnings[] = 'An industry manifest cannot be safely inspected.';
            continue;
        }
        $manifest = json_decode((string) @file_get_contents($file), true);
        if (!is_array($manifest)) {
            $warnings[] = 'An installed industry manifest is malformed.';
            continue;
        }
        $id = (string) ($manifest['id'] ?? '');
        $version = (string) ($manifest['version'] ?? '');
        if (preg_match('#^[a-z0-9][a-z0-9._-]*/[a-z0-9][a-z0-9._-]*$#', $id) !== 1
            || preg_match('/^[0-9]+\\.[0-9]+\\.[0-9]+(?:-[a-zA-Z0-9.-]+)?$/', $version) !== 1) {
            $warnings[] = 'An industry manifest has an invalid public id or version.';
            continue;
        }
        $packages[] = ['id' => $id, 'version' => $version, 'state' => 'on_disk_unverified'];
    }

    return [
        'schema' => 1,
        'scope' => 'read_only_cli_file_inspection',
        'core_version' => $coreVersion,
        'installation_state' => $configPresent && $lockPresent ? 'marker_and_config_present' : 'not_verified_installed',
        'checks' => $checks,
        'industries' => $packages,
        'php_cli' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'zip' => extension_loaded('zip'),
        ],
        'warnings' => $warnings,
        'not_tested' => [
            'web_server_waf_http_status',
            'php_fpm_runtime_permissions',
            'database_connection_and_migrations',
            'maintenance_mode_and_license_validation',
            'admin_login_and_authorization',
            'backup_restore_and_browser_acceptance',
        ],
    ];
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        if ($argc < 2 || $argc > 3) {
            throw new InvalidArgumentException(
                'Usage: php tools/host-preflight.php <site-webroot> [private-config-dir]'
            );
        }
        $report = wc_host_preflight($argv[1], $argv[2] ?? null);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        // Only fixed/sanitized messages, never source paths or config contents.
        $message = $error instanceof InvalidArgumentException
            ? $error->getMessage()
            : 'Host preflight could not finish.';
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}
