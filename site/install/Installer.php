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
 * After success: config.php + installed.lock are written, then install/ and
 * install.php are removed. If host permissions prevent complete cleanup,
 * every application entry point remains locked until manual removal.
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
            if (is_file($this->configDir . '/cleanup-required.lock')
                || is_dir(WC_ROOT . '/install')
                || is_file(WC_ROOT . '/install.php')
            ) {
                return $this->cleanupRequiredPage();
            }
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
            if ($request->string('action') === 'test_database') {
                return $this->testDatabase($request);
            }
            if ($request->string('action') === 'test_hosting_access') {
                return $this->testHostingAccess($request);
            }
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

        $configWritable = $this->prepareWritableDirectory($this->configDir);
        $storageDefault = WC_ROOT . '/storage';
        $storageWritable = $this->prepareWritableDirectory($storageDefault);
        $ftpFallback = LocalFtpProvisioner::supported();
        $add(
            'Configuration provisioning',
            $configWritable || $ftpFallback,
            $configWritable
                ? 'Direct write verified: ' . $this->configDir
                : ($ftpFallback
                    ? 'Foreign ownership detected; automatic local FTP fallback is available for ' . $this->configDir
                    : 'Foreign ownership detected and the PHP FTP extension is unavailable: ' . $this->configDir)
        );
        $add(
            'Storage provisioning',
            $storageWritable || $ftpFallback,
            $storageWritable
                ? 'Direct write verified: ' . $storageDefault
                : ($ftpFallback
                    ? 'Foreign ownership detected; automatic local FTP fallback is available for ' . $storageDefault
                    : 'Foreign ownership detected and the PHP FTP extension is unavailable: ' . $storageDefault)
        );
        $add('SSL / HTTPS', $this->requestSecure(), 'A valid SSL certificate and HTTPS connection are required');
        $add(
            'Installer writable (automatic cleanup)',
            is_writable(WC_ROOT) && is_writable(WC_ROOT . '/install'),
            'Automatic cleanup removes install/ and install.php. The site stays locked if manual cleanup is required.',
            false
        );

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
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if (in_array($https, ['on', '1', 'https'], true)) {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
        ) {
            return true;
        }

        // Shared-hosting control panels and reverse proxies commonly pass
        // the original SSL state through one of these headers.
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($forwardedProto === 'https'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
            || strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on'
        ) {
            return true;
        }

        $cfVisitor = json_decode((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), true);
        return is_array($cfVisitor) && strtolower((string) ($cfVisitor['scheme'] ?? '')) === 'https';
    }

    private function prepareWritableDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }
        if ($this->writable($dir)) {
            return true;
        }

        // Repair the common shared-hosting case without ever opening a
        // sensitive directory to world-write (0777).
        $permissions = @fileperms($dir);
        $current = is_int($permissions) ? ($permissions & 0777) : 0;
        foreach (array_unique([$current | 0700, $current | 0770, 0750, 0770]) as $mode) {
            if (@chmod($dir, $mode) && $this->writable($dir)) {
                return true;
            }
        }
        return false;
    }

    private function testDatabase(Request $request): Response
    {
        if (!$this->requestSecure()) {
            return Response::json([
                'ok' => false,
                'message' => 'SSL / HTTPS is required before database credentials can be tested.',
            ], 400);
        }

        $host = $request->string('db_host', '127.0.0.1');
        $port = (int) $request->string('db_port', '3306');
        $name = $request->string('db_name');
        $user = $request->string('db_user');
        $password = (string) ($request->post['db_pass'] ?? '');
        $prefix = $request->string('db_prefix', 'wc_');
        if ($host === '' || $name === '' || $user === '') {
            return Response::json([
                'ok' => false,
                'message' => 'Enter the database host, name, and username first.',
            ], 422);
        }
        if ($port < 1 || $port > 65535) {
            return Response::json(['ok' => false, 'message' => 'Database port must be between 1 and 65535.'], 422);
        }
        if (!preg_match('/^[a-zA-Z0-9_]{0,16}$/', $prefix)) {
            return Response::json(['ok' => false, 'message' => 'The table prefix is not valid.'], 422);
        }

        try {
            $db = Connection::mysql($host, $port, $name, $user, $password, 'utf8mb4', $prefix);
            if ((int) $db->fetchColumn('SELECT 1') !== 1) {
                throw new \RuntimeException('The database did not answer the verification query.');
            }
        } catch (\Throwable $e) {
            $message = preg_replace('/[\r\n\t]+/', ' ', $e->getMessage()) ?: 'Connection failed.';
            return Response::json([
                'ok' => false,
                'message' => 'Connection failed: ' . substr($message, 0, 300),
            ], 422);
        }

        return Response::json([
            'ok' => true,
            'message' => 'Connection successful. The database is ready for installation.',
        ]);
    }

    private function testHostingAccess(Request $request): Response
    {
        if (!$this->requestSecure()) {
            return Response::json([
                'ok' => false,
                'message' => 'SSL / HTTPS is required before hosting credentials can be tested.',
            ], 400);
        }

        $ftp = new LocalFtpProvisioner(WC_ROOT);
        try {
            $ftp->connect(
                '127.0.0.1',
                (int) $request->string('ftp_port', '21'),
                $request->string('ftp_user'),
                (string) ($request->post['ftp_password'] ?? ''),
                $request->string('ftp_path')
            );
            $root = $ftp->detectedRoot();
        } catch (\Throwable $e) {
            return Response::json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            $ftp->close();
        }

        return Response::json([
            'ok' => true,
            'message' => 'Hosting access verified. Website folder found at ' . $root . '.',
        ]);
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
        $ftp = null;

        if ($siteName === '') $errors['site_name'] = 'Site name is required.';
        if ($baseUrl === '' || !preg_match('#^https://#i', $baseUrl)) {
            $errors['base_url'] = 'Base URL must start with https:// — SSL is required.';
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

        // Uploaded archives on shared hosting are often owned by a different
        // account than PHP. When direct probes fail, use the same local-only
        // FTP fallback as our other installers. Credentials never leave this
        // request and the password is never returned to the form.
        $configWritable = $this->prepareWritableDirectory($this->configDir);
        $storageWritable = $this->prepareWritableDirectory($storagePath);
        $needsFtp = !$configWritable || !$storageWritable;
        if ($needsFtp && $errors === []) {
            if (!$storageWritable && $storagePath !== WC_ROOT . '/storage') {
                $errors['storage_path'] = 'The automatic ownership fallback can provision the bundled storage path only.';
            } else {
                try {
                    $ftp = new LocalFtpProvisioner(WC_ROOT);
                    $ftp->connect(
                        '127.0.0.1',
                        (int) $request->string('ftp_port', '21'),
                        $request->string('ftp_user'),
                        (string) ($request->post['ftp_password'] ?? ''),
                        $request->string('ftp_path')
                    );
                    if (!$storageWritable) {
                        $ftp->provisionStorage();
                        clearstatcache(true, $storagePath);
                        $storageWritable = $this->writable($storagePath);
                        if (!$storageWritable) {
                            $errors['storage_path'] = 'Hosting access worked, but PHP still cannot write storage after safe 0775 provisioning. Confirm the FTP account belongs to this domain.';
                        }
                    }
                } catch (\Throwable $e) {
                    $errors['ftp_user'] = $e->getMessage();
                }
            }
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
        try {
            if ($configWritable) {
                if (@file_put_contents($this->configDir . '/config.php', $configPhp, LOCK_EX) === false) {
                    throw new \RuntimeException('Direct configuration write failed.');
                }
                @chmod($this->configDir . '/config.php', 0640);
            } elseif ($ftp instanceof LocalFtpProvisioner) {
                $ftp->write('config/config.php', $configPhp, 0640);
            } else {
                throw new \RuntimeException('No safe configuration writer is available.');
            }
            clearstatcache(true, $this->configDir . '/config.php');
            $savedConfig = @include $this->configDir . '/config.php';
            if (!is_array($savedConfig)
                || ($savedConfig['security']['app_key'] ?? null) !== $appKey
                || ($savedConfig['database']['name'] ?? null) !== $dbName
            ) {
                throw new \RuntimeException('The saved configuration could not be read back and verified.');
            }
        } catch (\Throwable $e) {
            return $this->page($checks, [
                'site_name' => 'Could not provision config/config.php: ' . $e->getMessage(),
            ], $request);
        }

        $installedContents = date('c') . ' install complete' . "\n";
        if (@file_put_contents(WC_INSTALLED_LOCK, $installedContents, LOCK_EX) === false) {
            if ($ftp instanceof LocalFtpProvisioner) {
                $ftp->write('config/installed.lock', $installedContents, 0640);
            } else {
                return $this->page($checks, [
                    'site_name' => 'Could not create the permanent installation lock.',
                ], $request);
            }
        }

        // --- fail-closed installer cleanup ------------------------------------
        // Seed the gate before deletion so a timeout or permission failure can
        // never leave the new site available with installer artifacts present.
        $cleanupLock = $this->configDir . '/cleanup-required.lock';
        $cleanupContents = date('c') . " installer cleanup pending\n";
        if (@file_put_contents($cleanupLock, $cleanupContents, LOCK_EX) === false
            && $ftp instanceof LocalFtpProvisioner
        ) {
            $ftp->write('config/cleanup-required.lock', $cleanupContents, 0640);
        }
        foreach ([$this->claimFile, $this->configDir . '/claim.session', $this->tokenFile] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        if ($ftp instanceof LocalFtpProvisioner) {
            foreach (['config/claim.lock', 'config/claim.session', 'config/setup.token'] as $relative) {
                $ftp->delete($relative);
            }
        }
        $remainingInstallerArtifacts = $this->removeInstallerArtifacts();
        if ($remainingInstallerArtifacts !== [] && $ftp instanceof LocalFtpProvisioner) {
            $remainingInstallerArtifacts = $ftp->removeInstallerArtifacts();
        }
        if ($remainingInstallerArtifacts === []) {
            if (!@unlink($cleanupLock) && is_file($cleanupLock) && $ftp instanceof LocalFtpProvisioner) {
                $ftp->delete('config/cleanup-required.lock');
            }
        }
        if ($ftp instanceof LocalFtpProvisioner) {
            $ftp->close();
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

        if ($remainingInstallerArtifacts !== []) {
            return $this->cleanupRequiredPage($remainingInstallerArtifacts);
        }

        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
        $logoUrl = ($basePath === '' ? '' : $basePath) . '/assets/images/cajunwebpro-logo.png';
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Installation complete</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111f;color:#eaf2ff;'
            . 'font-family:Inter,system-ui,sans-serif;padding:24px}.done{width:min(620px,100%);box-sizing:border-box;padding:42px;border:1px solid #244267;'
            . 'border-radius:24px;background:linear-gradient(145deg,#10233a,#091525);box-shadow:0 28px 80px #0008}.mark{width:58px;height:58px;'
            . 'display:grid;place-items:center;border-radius:50%;background:#1b8f66;color:white;font-size:30px}h1{font-size:clamp(2rem,5vw,3.2rem);'
            . 'margin:.7rem 0}.logo{display:block;width:min(390px,88%);height:auto;margin-bottom:28px;filter:drop-shadow(0 12px 24px #0007)}'
            . 'p{color:#b9c9dc;line-height:1.7}strong{color:#fff}.logo-link{display:block;width:fit-content;max-width:100%}.admin-link{display:inline-flex;margin-top:14px;padding:14px 22px;'
            . 'border-radius:12px;background:#d6a94f;color:#07111f;text-decoration:none;font-weight:800}</style>'
            . '</head><body><main class="done"><a class="logo-link" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Visit CajunWebPro">'
            . '<img class="logo" src="' . e_attr($logoUrl) . '" alt="CajunWebPro — Web Design &amp; Development"></a>'
            . '<div class="mark">✓</div><p>CAJUNWEBPRO DEPLOYMENT</p><h1>Installation complete</h1>'
            . '<p>The installer files and folders were removed successfully. Your site is secured and ready.</p>'
            . $licenseNote
            . '<p>Sign in and enable two-factor authentication for the owner account.</p>'
            . '<a class="admin-link" href="' . e_attr($baseUrl) . '/admin">Open administration area</a></main></body></html>'
        );
    }

    /** @return list<string> */
    private function removeInstallerArtifacts(): array
    {
        $directory = WC_ROOT . '/install';
        if (is_dir($directory) && !$this->removeTree($directory)) {
            // Keep going so the root entry can still be removed when possible.
        }
        if (is_file(WC_ROOT . '/install.php')) {
            @unlink(WC_ROOT . '/install.php');
        }

        $remaining = [];
        if (is_dir($directory)) {
            $remaining[] = 'install/';
        }
        if (is_file(WC_ROOT . '/install.php')) {
            $remaining[] = 'install.php';
        }
        return $remaining;
    }

    private function removeTree(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                @unlink($path);
            } elseif ($item->isDir()) {
                @rmdir($path);
            }
        }
        @rmdir($directory);
        return !is_dir($directory);
    }

    /** @param list<string>|null $remaining */
    private function cleanupRequiredPage(?array $remaining = null): Response
    {
        $remaining ??= array_values(array_filter([
            is_dir(WC_ROOT . '/install') ? 'install/' : null,
            is_file(WC_ROOT . '/install.php') ? 'install.php' : null,
        ]));
        $items = $remaining === [] ? 'installer files' : implode(' and ', array_map('e', $remaining));
        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
        $logoUrl = ($basePath === '' ? '' : $basePath) . '/assets/images/cajunwebpro-logo.png';
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Installer cleanup required</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111f;color:#eef5ff;'
            . 'font-family:Inter,system-ui,sans-serif;padding:24px}.box{width:min(680px,100%);box-sizing:border-box;padding:38px;border-radius:22px;'
            . 'border:1px solid #6f5423;background:linear-gradient(145deg,#172333,#0b1523);box-shadow:0 30px 90px #0009}h1{margin:.4rem 0 1rem;'
            . 'font-size:clamp(2rem,5vw,3rem)}.flag{display:inline-block;padding:7px 11px;border-radius:999px;background:#4b3513;color:#ffd98a;'
            . 'font-weight:800;font-size:.76rem;letter-spacing:.08em}p,li{color:#c7d3e2;line-height:1.7}code{overflow-wrap:anywhere;color:#ffe0a0}strong{color:white}'
            . '.logo{display:block;width:min(390px,88%);height:auto;margin-bottom:26px;filter:drop-shadow(0 12px 24px #0007)}'
            . '.logo-link{display:block;width:fit-content;max-width:100%}'
            . '</style></head><body><main class="box"><a class="logo-link" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Visit CajunWebPro">'
            . '<img class="logo" src="' . e_attr($logoUrl) . '" alt="CajunWebPro — Web Design &amp; Development"></a>'
            . '<span class="flag">SITE SAFELY LOCKED</span>'
            . '<h1>One cleanup step remains</h1><p>The website was installed, but PHP could not remove <strong>' . $items . '</strong>. '
            . 'For security, the public site and administration area will remain unavailable.</p>'
            . '<ol><li>Open your hosting File Manager or FTP.</li><li>Go to <code>' . e(WC_ROOT) . '</code>.</li>'
            . '<li>Delete the <code>install</code> folder and the root <code>install.php</code> file.</li>'
            . '<li>Reload your website. Access will unlock automatically after both are gone.</li></ol>'
            . '<p><strong>Do not delete</strong> <code>config/installed.lock</code> or <code>config/cleanup-required.lock</code>.</p>'
            . '</main></body></html>',
            503
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
        $coreVersion = defined('WC_VERSION') ? WC_VERSION : '0.0.0-dev';
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
                'version' => {$s($coreVersion)},
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
        $packageName = 'Website Core';
        foreach (glob(WC_ROOT . '/modules/industries/*/addon.json') ?: [] as $manifestFile) {
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($manifest) && trim((string) ($manifest['name'] ?? '')) !== '') {
                $packageName = (string) $manifest['name'];
                break;
            }
        }
        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
        $logoUrl = ($basePath === '' ? '' : $basePath) . '/assets/images/cajunwebpro-logo.png';
        $ownershipFallbackRequired = !$this->prepareWritableDirectory($this->configDir)
            || !$this->prepareWritableDirectory(WC_ROOT . '/storage');
        $ftpSupported = LocalFtpProvisioner::supported();
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
            'ftp_port' => '21', 'ftp_path' => '',
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
