<?php
declare(strict_types=1);

namespace Install;

use Core\Application;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Http\Response;

/**
 * One-page, key-free installer.
 *
 * Ownership model (docs/INSTALLATION.md):
 *  - Preferred: the deployer pre-seeds config/setup.token (host panel,
 *    deployment script, or FTP upload). The form then requires it.
 *  - Otherwise the first visitor atomically claims setup via
 *    config/claim.lock (created with 'x' mode — no race). The claim is bound
 *    to a session cookie so only that browser may submit. The page states
 *    plainly that an unclaimed public upload is claimable by the first
 *    visitor — the documented remedy is host protection or a seeded token.
 *
 * After success: config.php + installed.lock are written, this entry point
 * is renamed to install.php.off where permissions allow, and even if the
 * host denies cleanup the lock makes this file permanently inert.
 */
final class Installer
{
    private string $configDir;
    private string $claimFile;
    private string $tokenFile;

    public function __construct(private Application $app)
    {
        $this->configDir = WC_CONFIG;
        $this->claimFile = $this->configDir . '/claim.lock';
        $this->tokenFile = $this->configDir . '/setup.token';
    }

    public function run(): void
    {
        try {
            $response = $this->handle(Request::capture());
        } catch (\Throwable $e) {
            error_log('[website-core install] ' . $e->getMessage());
            $response = Response::html(
                '<h1>Installer error</h1><p>' . e($e->getMessage()) . '</p>', 500
            );
        }
        $response->send();
    }

    private function handle(Request $request): Response
    {
        if (is_file(WC_INSTALLED_LOCK)) {
            return Response::html(
                '<h1>Already installed</h1><p>This installation is complete. '
                . 'The installer is permanently disabled.</p>',
                403
            );
        }

        $claim = $this->claim($request);
        if ($claim instanceof Response) {
            return $claim;
        }

        $checks = $this->checks();
        if ($request->isPost()) {
            return $this->install($request, $checks);
        }
        return $this->page($checks, []);
    }

    // ---- claim ------------------------------------------------------------

    private function claim(Request $request): ?Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('WCINSTALL');
            session_start();
        }

        // Seeded-token mode: a deployment-provided token is required.
        if (is_file($this->tokenFile)) {
            $expected = trim((string) file_get_contents($this->tokenFile));
            if ($request->isPost()) {
                $given = (string) ($request->post['setup_token'] ?? '');
                if ($expected === '' || !hash_equals($expected, $given)) {
                    return $this->page($this->checks(), [
                        'setup_token' => 'Invalid setup token.',
                    ]);
                }
            }
            return null; // token required only on submit
        }

        // First-come claim mode.
        if (!is_file($this->claimFile)) {
            $fh = @fopen($this->claimFile, 'x');
            if ($fh !== false) {
                fwrite($fh, json_encode([
                    'claimed_at' => date('c'),
                    'ip'         => $request->ip(),
                ]));
                fclose($fh);
            }
        }
        $claimer = null;
        if (is_file($this->claimFile)) {
            $claimer = json_decode((string) file_get_contents($this->claimFile), true);
        }
        if ($claimer !== null) {
            $_SESSION['wc_claim'] ??= bin2hex(random_bytes(16));
            $claimSession = $_SESSION['wc_claim'];
            // Bind the claim to the first session that saw it.
            $bound = $this->configDir . '/claim.session';
            if (!is_file($bound)) {
                $fh = @fopen($bound, 'x');
                if ($fh !== false) {
                    fwrite($fh, $claimSession);
                    fclose($fh);
                }
            }
            $boundValue = is_file($bound) ? trim((string) file_get_contents($bound)) : null;
            if ($boundValue !== null && !hash_equals($boundValue, $claimSession)) {
                return Response::html(
                    '<h1>Setup already claimed</h1><p>This installation was claimed from another '
                    . 'browser session. If you are the site owner, continue in the original browser '
                    . 'or remove <code>config/claim.lock</code> and <code>config/claim.session</code> '
                    . 'via your hosting file manager and reload. If you did not expect this site to be '
                    . 'installed already, treat this as an unclaimed public upload — secure it via your '
                    . 'host before proceeding (see docs/INSTALLATION.md).</p>',
                    409
                );
            }
        }
        return null;
    }

    // ---- capability checks --------------------------------------------------

    /** @return list<array{name:string,ok:bool,required:bool,detail:string}> */
    private function checks(): array
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $detail, bool $required = true) use (&$checks): void {
            $checks[] = ['name' => $name, 'ok' => $ok, 'required' => $required, 'detail' => $detail];
        };

        $add('PHP >= 8.4', version_compare(PHP_VERSION, '8.4.0', '>='), PHP_VERSION);
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'session', 'ctype'] as $ext) {
            $add("ext-{$ext}", extension_loaded($ext), extension_loaded($ext) ? 'present' : 'MISSING');
        }
        foreach (['gd', 'intl', 'zip', 'curl'] as $ext) {
            $add("ext-{$ext} (recommended)", extension_loaded($ext), extension_loaded($ext) ? 'present' : 'absent', false);
        }
        // Signature verification works with ext-sodium OR the bundled
        // sodium_compat polyfill — report which one is live.
        $sodiumOk = function_exists('sodium_crypto_sign_verify_detached');
        $add('signature verification (sodium or polyfill)', $sodiumOk,
            extension_loaded('sodium') ? 'ext-sodium' : ($sodiumOk ? 'sodium_compat polyfill' : 'absent'), false);

        $add('config/ writable', $this->writable($this->configDir), $this->configDir);
        $storageDefault = WC_ROOT . '/storage';
        $add('storage writable', $this->writable($storageDefault), $storageDefault);
        $add('HTTPS', $this->requestSecure(), 'TLS recommended for admin sessions', false);
        $add('install.php writable (self-cleanup)', is_writable(WC_ROOT . '/install.php'), 'will self-disable via lock regardless', false);

        // Report bundled-product detection so the customer can confirm the
        // package they purchased was extracted correctly. Not required —
        // a core-only install is a valid configuration.
        $bundledName = null;
        $bundledLicensed = false;
        foreach (glob(WC_ROOT . '/modules/industries/*/addon.json') ?: [] as $manifestFile) {
            $m = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($m)) {
                $bundledName = basename(dirname($manifestFile));
                $bundledLicensed = trim((string) ($m['license_key'] ?? '')) !== '';
                break;
            }
        }
        $bundleDetail = 'none detected — core-only install';
        if ($bundledName !== null) {
            $bundleDetail = $bundledName . ($bundledLicensed
                ? ' — license will be validated during install'
                : ' — TEST BUNDLE; no license required');
        }
        $add('Bundled industry module', true, $bundleDetail, false);

        return $checks;
    }

    private function requestSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private function writable(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $probe = $dir . '/.w' . bin2hex(random_bytes(4));
        $fh = @fopen($probe, 'x');
        if ($fh === false) {
            return false;
        }
        fclose($fh);
        // Real create/read/write/rename/delete check.
        $renamed = $probe . '2';
        $ok = @rename($probe, $renamed) && @unlink($renamed);
        @unlink($probe);
        return $ok;
    }

    // ---- install ------------------------------------------------------------

    private function install(Request $request, array $checks): Response
    {
        $errors = [];

        foreach ($checks as $c) {
            if ($c['required'] && !$c['ok']) {
                $errors[$c['name']] = $c['detail'] . ' — required check failed';
            }
        }

        $siteName = $request->string('site_name');
        $baseUrl = rtrim($request->string('base_url'), '/');
        $timezone = $request->string('timezone', 'UTC');
        $dbHost = $request->string('db_host', '127.0.0.1');
        $dbPort = (int) $request->string('db_port', '3306');
        $dbName = $request->string('db_name');
        $dbUser = $request->string('db_user');
        $dbPass = (string) ($request->post['db_pass'] ?? '');
        $dbPrefix = $request->string('db_prefix', 'wc_');
        $owner = $request->string('owner_username');
        $ownerEmail = $request->string('owner_email');
        $pass1 = (string) ($request->post['owner_password'] ?? '');
        $pass2 = (string) ($request->post['owner_password2'] ?? '');
        $storagePath = $request->string('storage_path', WC_ROOT . '/storage');

        if ($siteName === '') $errors['site_name'] = 'Site name is required.';
        if ($baseUrl === '' || !preg_match('#^https?://#', $baseUrl)) {
            $errors['base_url'] = 'Base URL must start with http:// or https://';
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Unknown timezone.';
        }
        if ($dbName === '') $errors['db_name'] = 'Database name is required.';
        if ($dbUser === '') $errors['db_user'] = 'Database user is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{0,16}$/', $dbPrefix)) {
            $errors['db_prefix'] = 'Prefix: letters, digits, underscore, max 16.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $owner)) {
            $errors['owner_username'] = 'Username: 3-60 chars, letters/digits/._-';
        }
        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['owner_email'] = 'Valid email required.';
        }
        if (strlen($pass1) < 12) {
            $errors['owner_password'] = 'Password must be at least 12 characters.';
        } elseif ($pass1 !== $pass2) {
            $errors['owner_password2'] = 'Passwords do not match.';
        }

        // DB connectivity is checked with the provided credentials — errors
        // surface verbatim minus credentials.
        $db = null;
        if ($errors === []) {
            try {
                $db = Connection::mysql($dbHost, $dbPort, $dbName, $dbUser, $dbPass, 'utf8mb4', $dbPrefix);
            } catch (\Throwable $e) {
                $errors['db_name'] = 'Could not connect: ' . $e->getMessage();
            }
        }

        // Storage: prefer outside docroot; must be creatable+writable.
        if ($errors === [] && !$this->writable($storagePath) && !@mkdir($storagePath, 0750, true)) {
            $errors['storage_path'] = 'Storage path is not writable and could not be created.';
        }

        // --- bundled module discovery + optional license validation ----------
        // Bundled modules are extracted to site/modules/industries/*/. A
        // production bundle contains license_key. An explicitly built testing
        // bundle omits it and must still install/register/enable normally.
        $licenseKey = null;
        $bundledModuleDir = null;
        $licenseOk = true;
        $licenseError = null;
        $domain = null;
        $bundledModules = glob(WC_ROOT . '/modules/industries/*/addon.json') ?: [];
        foreach ($bundledModules as $manifestFile) {
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($manifest)) {
                $bundledModuleDir = dirname($manifestFile);
                $candidateKey = trim((string) ($manifest['license_key'] ?? ''));
                $licenseKey = $candidateKey !== '' ? $candidateKey : null;
                break;
            }
        }

        if ($licenseKey !== null) {
            $domain = parse_url($baseUrl, PHP_URL_HOST);
            $domain = is_string($domain) ? strtolower($domain) : false;
            if ($domain === false) {
                $errors['base_url'] = 'Could not extract domain from base URL for license validation.';
            } else {
                // Only a definitive denial (HTTP 200 + authorized:false)
                // blocks install — e.g. a key bound to another domain.
                // Transient outcomes (unreachable API, 429, 5xx, malformed
                // body) must not strand a paying customer: the install
                // proceeds and the scheduled validator enforces the license
                // on its next run — hourly once denied.
                [$licenseOk, $licenseError, $licenseDenied] = $this->validateLicense($licenseKey, $domain);
                if ($licenseDenied) {
                    $errors['license'] = $licenseError ?? 'License validation failed.';
                }
            }
        }

        if ($errors !== []) {
            return $this->page($checks, $errors, $request);
        }

        // --- write schema + owner atomically where possible ------------------
        try {
            $migrator = new Migrator($db, WC_ROOT . '/database/migrations');
            $migrator->migrate();

            $db->insert('admins', [
                'username'      => $owner,
                'email'         => $ownerEmail,
                'password_hash' => password_hash($pass1, PASSWORD_DEFAULT),
                'role'          => 'owner',
                'mfa_enabled'   => 0,
                'disabled'      => 0,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            // A fresh install must serve something at '/' — the home route
            // resolves site.home_slug (default 'home').
            $now = date('Y-m-d H:i:s');
            $db->insert('pages', [
                'slug' => 'home', 'title' => 'Welcome',
                'body' => '<p>Your new site is ready. Sign in to the administration area to edit this page.</p>',
                'status' => 'published',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            // Themes render site.name — seed it from the install form so the
            // site does not greet visitors as "Website Core".
            $settings = new \Core\Settings\SettingsRepository($db);
            $settings->set('site.name', $siteName);
            $settings->set('site.tagline', '');
            // Web-triggered cron requires this token — generate it here so
            // it exists before the first settings save.
            $settings->set('system.cron_token', bin2hex(random_bytes(24)));
        } catch (\Throwable $e) {
            return $this->page($checks, ['db_name' => 'Schema/owner creation failed: ' . $e->getMessage()], $request);
        }

        // --- bundled module installation --------------------------------------
        // The bundled module ships extracted at site/modules/industries/NAME/.
        // Production bundles store the license first. Keyless TEST-BUNDLE builds
        // deliberately skip the license row. Both paths register the package,
        // run migrations and enable it without a manual activation step.
        if ($bundledModuleDir !== null) {
            try {
                if ($licenseKey !== null) {
                    $table = $db->table('license');
                    // On an inconclusive install check the row is stored valid
                    // with a NULL validated_at — the job scheduler treats that
                    // as "never validated" and enqueues verification on the next
                    // cron tick, so a borrowed/invalid key still self-corrects.
                    $db->run(
                        "INSERT INTO `{$table}` (license_key, license_domain, license_valid, license_validated_at, license_error, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $licenseKey, $domain, 1,
                            $licenseOk ? date('Y-m-d H:i:s') : null,
                            $licenseOk ? null : 'Install-time license check inconclusive: ' . ($licenseError ?? 'unknown'),
                            date('Y-m-d H:i:s'),
                        ]
                    );
                }

                $manifest = json_decode((string) file_get_contents($bundledModuleDir . '/addon.json'), true);
                $packageId = (string) ($manifest['id'] ?? basename($bundledModuleDir));
                $packagesTable = $db->table('packages');
                $db->run(
                    "INSERT INTO `{$packagesTable}` (package_id, state, version, manifest, updated_at)
                     VALUES (?, 'installed', ?, ?, ?)"
                    . ($db->driver() === 'sqlite'
                        ? ' ON CONFLICT(package_id) DO UPDATE SET state=excluded.state, version=excluded.version, manifest=excluded.manifest, updated_at=excluded.updated_at'
                        : ' ON DUPLICATE KEY UPDATE state=VALUES(state), version=VALUES(version), manifest=VALUES(manifest), updated_at=VALUES(updated_at)'),
                    [$packageId, (string) ($manifest['version'] ?? ''), json_encode($manifest), date('Y-m-d H:i:s')]
                );

                $migDir = trim((string) ($manifest['migrations'] ?? ''), '/');
                if ($migDir !== '' && is_dir($bundledModuleDir . '/' . $migDir)) {
                    (new Migrator($db, $bundledModuleDir . '/' . $migDir))->migrate();
                }

                $db->run(
                    "UPDATE `{$packagesTable}` SET state = 'enabled', updated_at = ? WHERE package_id = ?",
                    [date('Y-m-d H:i:s'), $packageId]
                );
                $db->insert('package_journal', [
                    'package_id' => $packageId, 'operation' => 'bundled-install',
                    'result' => 'ok', 'detail' => $licenseKey !== null
                        ? 'Licensed bundled module enabled during install'
                        : 'Keyless testing bundle enabled during install',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                return $this->page($checks, ['license' => 'Failed to install bundled module: ' . $e->getMessage()], $request);
            }
        }

        // --- write config -----------------------------------------------------
        $appKey = bin2hex(random_bytes(32));
        $configPhp = $this->renderConfig([
            'siteName' => $siteName, 'baseUrl' => $baseUrl, 'timezone' => $timezone,
            'dbHost' => $dbHost, 'dbPort' => $dbPort, 'dbName' => $dbName,
            'dbUser' => $dbUser, 'dbPass' => $dbPass, 'dbPrefix' => $dbPrefix,
            'storage' => $storagePath, 'appKey' => $appKey,
            'https' => $this->requestSecure(),
            'licenseApiUrl' => rtrim((string) (getenv('WC_LICENSE_API_URL') ?: 'https://cajunwebpro.com/api'), '/'),
        ]);
        if (@file_put_contents($this->configDir . '/config.php', $configPhp, LOCK_EX) === false) {
            return $this->page($checks, ['site_name' => 'Could not write config/config.php — check permissions.'], $request);
        }
        @chmod($this->configDir . '/config.php', 0640);
        file_put_contents(WC_INSTALLED_LOCK, date('c') . ' install complete' . "\n", LOCK_EX);

        // --- self-disable ------------------------------------------------------
        $cleanupNote = '';
        if (is_writable(WC_ROOT . '/install.php')) {
            if (@rename(WC_ROOT . '/install.php', WC_ROOT . '/install.php.off')) {
                $cleanupNote = 'Installer entry removed.';
            } else {
                $cleanupNote = 'install.php could not be renamed — it is permanently blocked by installed.lock. Delete it manually when convenient.';
            }
        } else {
            $cleanupNote = 'install.php is not writable by PHP — it is permanently blocked by installed.lock. Delete it via your hosting file manager.';
        }
        foreach ([$this->claimFile, $this->configDir . '/claim.session', $this->tokenFile] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }

        $licenseNote = '';
        if ($bundledModuleDir !== null && $licenseKey === null) {
            $licenseNote = '<p><strong>Testing mode:</strong> this TEST-BUNDLE was installed without '
                . 'a license key. License validation and domain binding are inactive.</p>';
        } elseif ($bundledModuleDir !== null && !$licenseOk) {
            $licenseNote = '<p><strong>License check deferred:</strong> the license server could not be reached '
                . 'during install (' . e((string) $licenseError) . '). Your license will be verified '
                . 'automatically — if it cannot be validated the site will go offline.</p>';
        }

        return Response::html(
            '<!doctype html><html><head><meta charset="utf-8"><title>Installed</title>'
            . '<style>body{font-family:system-ui;max-width:40rem;margin:4rem auto;padding:0 1rem}</style>'
            . '</head><body><h1>Installation complete</h1>'
            . '<p>' . e($cleanupNote) . '</p>'
            . $licenseNote
            . '<p>Sign in to the <a href="' . e_attr($baseUrl) . '/admin">administration area</a> '
            . 'and enable two-factor authentication on your profile.</p></body></html>'
        );
    }

    /**
     * Validate a license key against the Cajunwebpro.com API.
     *
     * Mirrors LicenseValidation's contract: only HTTP 200 carries a
     * definitive verdict. A 4xx/5xx body — even one containing
     * {"authorized":false} — is a transient failure, never a denial.
     *
     * @return array{0:bool,1:?string,2:bool} [valid, error, explicit-denial]
     */
    private function validateLicense(string $licenseKey, string $domain): array
    {
        // WC_LICENSE_API_URL lets tests/dev point at a local mock. Production
        // uses the canonical PHP route — it works without mod_rewrite.
        $apiBase = getenv('WC_LICENSE_API_URL') ?: 'https://cajunwebpro.com/api';
        $endpoint = rtrim($apiBase, '/') . '/validate.php';
        $payload = json_encode([
            'license_key' => $licenseKey,
            'domain' => $domain,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                            "User-Agent: Website-Core-Installer/1.0\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        if ($response === false) {
            return [false, 'Could not contact license server. Please check your internet connection.', false];
        }

        // Last status line wins (redirect chains repeat the HTTP/ line).
        $statusCode = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $statusCode = (int) $m[1];
            }
        }
        if ($statusCode !== 200) {
            return [false, 'License server returned HTTP ' . $statusCode . '. Validation will retry automatically.', false];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [false, 'Invalid response from license server.', false];
        }

        $valid = (bool) ($data['authorized'] ?? false);
        $error = $data['error'] ?? null;

        if (!$valid) {
            return [false, is_string($error) ? $error : 'License validation failed.', true];
        }

        return [true, null, false];
    }

    private function renderConfig(array $v): string
    {
        $s = fn (string $x) => var_export($x, true);
        return <<<PHP
        <?php
        declare(strict_types=1);
        // Generated by the Website Core installer on {$this->now()} — do not commit.
        return [
            'app' => [
                'name' => {$s($v['siteName'])},
                'env' => 'production',
                'url' => {$s($v['baseUrl'])},
                'timezone' => {$s($v['timezone'])},
                'locale' => 'en',
                'debug' => false,
                'version' => '0.1.0-dev',
                'api_version' => 1,
            ],
            'database' => [
                'driver' => 'mysql',
                'host' => {$s($v['dbHost'])},
                'port' => {$v['dbPort']},
                'name' => {$s($v['dbName'])},
                'user' => {$s($v['dbUser'])},
                'password' => {$s($v['dbPass'])},
                'charset' => 'utf8mb4',
                'prefix' => {$s($v['dbPrefix'])},
            ],
            'paths' => [
                'storage' => {$s($v['storage'])},
                'upload' => {$s(WC_ROOT . '/upload')},
            ],
            'security' => [
                'app_key' => {$s($v['appKey'])},
                'session_name' => 'WCSID',
                'session_idle' => 7200,
                'session_absolute' => 43200,
                'cookie_secure' => 'auto',
                'trusted_proxies' => [],
            ],
            'mail' => [
                'transport' => 'capture',
                'smtp_host' => '', 'smtp_port' => 587, 'smtp_user' => '', 'smtp_pass' => '',
                'smtp_secure' => 'tls', 'from' => 'no-reply@example.com', 'from_name' => {$s($v['siteName'])},
            ],
            'license' => [
                'api_url' => {$s($v['licenseApiUrl'])},
            ],
            'trust' => ['public_keys' => [], 'allow_unsigned' => false],
            'updates' => ['channel' => 'stable', 'require_window' => false],
        ];
        PHP;
    }

    private function now(): string
    {
        return date('c');
    }

    private function page(array $checks, array $errors, ?Request $request = null): Response
    {
        $needsToken = is_file($this->tokenFile);
        $unclaimed = !$needsToken;
        $old = [];
        if ($request !== null) {
            foreach (['site_name','base_url','timezone','db_host','db_port','db_name','db_user','db_prefix','owner_username','owner_email','storage_path'] as $k) {
                $old[$k] = $request->string($k);
            }
        }
        $defaults = array_merge([
            'site_name' => '', 'base_url' => $this->guessBaseUrl(), 'timezone' => 'UTC',
            'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => '', 'db_user' => '',
            'db_prefix' => 'wc_', 'owner_username' => '', 'owner_email' => '',
            'storage_path' => WC_ROOT . '/storage',
        ], array_filter($old, fn ($x) => $x !== ''));

        ob_start();
        $tzList = \DateTimeZone::listIdentifiers();
        require WC_ROOT . '/install/views/installer.php';
        return Response::html((string) ob_get_clean());
    }

    private function guessBaseUrl(): string
    {
        $scheme = $this->requestSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
    }
}
