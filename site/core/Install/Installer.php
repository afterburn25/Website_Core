<?php
declare(strict_types=1);

namespace Core\Install;

use Core\Application;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Http\Response;

/**
 * One-page, key-free installer.
 *
 * Ownership model (docs/INSTALLATION.md):
 *  - Preferred: the deployer pre-seeds config/setup.token through the host
 *    panel or deployment script. The form then requires it.
 *  - Otherwise the first visitor atomically claims setup via
 *    config/claim.lock (created with 'x' mode — no race). The claim is bound
 *    to a session cookie so only that browser may submit. The page states
 *    plainly that an unclaimed public upload is claimable by the first
 *    visitor — the documented remedy is host protection or a seeded token.
 *
 * After success, config.php plus installed.lock permanently disable setup and
 * the public install.php entry plus install/ folder are physically removed.
 */
final class Installer
{
    private string $configDir;
    private string $claimFile;
    private string $tokenFile;
    private string $installedLock;
    private ?string $privateRuntime = null;
    private bool $usingPrivateConfig = false;

    public function __construct(private Application $app)
    {
        $siteConfig = WC_ROOT . '/config';
        $this->configDir = $siteConfig;
        $siteConfigWritable = $this->prepareWritableDirectory($siteConfig);
        $siteStorageWritable = $this->prepareWritableDirectory(WC_ROOT . '/storage');
        $siteUploadWritable = $this->prepareWritableDirectory(WC_ROOT . '/upload');
        if (!$siteConfigWritable || !$siteStorageWritable || !$siteUploadWritable) {
            $this->privateRuntime = wc_private_runtime_dir(WC_ROOT, true);
            if (!$siteConfigWritable && $this->privateRuntime !== null) {
                $privateConfig = $this->privateRuntime . '/config';
                if ($this->prepareWritableDirectory($privateConfig, 0700)) {
                    $this->configDir = $privateConfig;
                    $this->usingPrivateConfig = true;
                }
            }
        }
        $this->claimFile = $this->configDir . '/claim.lock';
        $this->tokenFile = $this->configDir . '/setup.token';
        $this->installedLock = $this->configDir . '/installed.lock';
    }

    public function run(): void
    {
        try {
            $response = $this->handle(Request::capture());
        } catch (\Throwable $e) {
            error_log('[website-core install] ' . $e->getMessage());
            $response = $this->page($this->checks(), [
                'site_name' => 'Setup could not continue. No credentials were displayed. Check the server error log and try again.',
            ]);
        }
        $response->send();
    }

    private function handle(Request $request): Response
    {
        if (wc_completed_installation($this->configDir)) {
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
            $givenCsrf = (string) ($request->post['installer_csrf'] ?? '');
            $expectedCsrf = (string) ($_SESSION['wc_install_csrf'] ?? '');
            if ($expectedCsrf === '' || $givenCsrf === '' || !hash_equals($expectedCsrf, $givenCsrf)) {
                if ($request->string('action') === 'test_database') {
                    return Response::json(['ok' => false, 'message' => 'Setup session expired. Reload the installer and try again.'], 419);
                }
                return $this->page($checks, ['site_name' => 'Setup session expired. Reload the installer and try again.'], $request);
            }
            if ($request->string('action') === 'test_database') {
                return $this->testDatabase($request);
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
            session_set_cookie_params([
                'lifetime' => 0, 'path' => '/', 'secure' => $this->requestSecure(),
                'httponly' => true, 'samesite' => 'Strict',
            ]);
            session_start();
        }
        $_SESSION['wc_install_csrf'] ??= bin2hex(random_bytes(32));

        // Seeded-token mode: a deployment-provided token is required.
        if (is_file($this->tokenFile)) {
            $expected = trim((string) file_get_contents($this->tokenFile));
            if ($request->isPost()) {
                $given = (string) ($request->post['setup_token'] ?? '');
                if ($expected === '' || !hash_equals($expected, $given)) {
                    if ($request->string('action') === 'test_database') {
                        return Response::json(['ok' => false, 'message' => 'Enter the valid setup security token before testing credentials.'], 403);
                    }
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

        $configWritable = $this->prepareWritableDirectory(
            $this->configDir,
            $this->usingPrivateConfig ? 0700 : 0750
        );
        $storageDefault = $this->defaultStoragePath();
        $storageWritable = $this->prepareWritableDirectory(
            $storageDefault,
            $this->privateRuntime !== null ? 0700 : 0750
        );
        $uploadWritable = $this->prepareWritableDirectory(
            $this->defaultUploadPath(),
            $this->privateRuntime !== null ? 0700 : 0750
        );
        $add(
            'Configuration provisioning',
            $configWritable,
            $configWritable
                ? ($this->usingPrivateConfig
                    ? 'Protected private runtime verified automatically: ' . $this->configDir
                    : 'Direct write verified: ' . $this->configDir)
                : 'No safe persistent configuration location could be created automatically.'
        );
        $add(
            'Storage provisioning',
            $storageWritable && $uploadWritable,
            $storageWritable && $uploadWritable
                ? ($this->privateRuntime !== null
                    ? 'Protected private runtime verified automatically: ' . $storageDefault
                    : 'Direct write verified: ' . $storageDefault)
                : 'No safe persistent storage location could be created automatically.'
        );
        $add('SSL / HTTPS', $this->requestSecure(), 'A valid SSL certificate and HTTPS connection are required');
        $cleanupAccess = wc_probe_installer_cleanup_access(WC_ROOT);
        $add(
            'Installer self-removal',
            $cleanupAccess,
            $cleanupAccess
                ? 'Verified create, rename, and delete access in the document root.'
                : 'PHP cannot create, rename, and delete entries in the document root. Installation can continue. After setup, use the hosting File Manager to remove install.php and install/ if they remain.',
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

    private function prepareWritableDirectory(string $dir, int $preferredMode = 0750): bool
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
        foreach (array_unique([$current | 0700, $current | 0770, $preferredMode, 0750, 0770]) as $mode) {
            if (@chmod($dir, $mode) && $this->writable($dir)) {
                return true;
            }
        }
        return false;
    }

    private function defaultStoragePath(): string
    {
        if ($this->privateRuntime !== null) {
            return $this->privateRuntime . '/storage';
        }
        return WC_ROOT . '/storage';
    }

    private function defaultUploadPath(): string
    {
        if ($this->privateRuntime !== null) {
            return $this->privateRuntime . '/upload';
        }
        return WC_ROOT . '/upload';
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
            $existingInstall = $db->tableExists('migrations');
        } catch (\Throwable $e) {
            error_log('[website-core install] database test failed: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'message' => $this->databaseErrorMessage($e),
            ], 422);
        }

        return Response::json([
            'ok' => true,
            'existing_install' => $existingInstall,
            'message' => $existingInstall
                ? 'Connection successful. Existing Website Core tables were detected — confirm the protected reinstall below.'
                : 'Connection successful. The database is ready for installation.',
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

        $operationLock = @fopen($this->configDir . '/install.operation.lock', 'c');
        if ($operationLock === false || !@flock($operationLock, LOCK_EX | LOCK_NB)) {
            if (is_resource($operationLock)) fclose($operationLock);
            return $this->page($checks, ['site_name' => 'Another installation request is running. Wait a moment, then reload this page.'], $request);
        }

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
        $storagePath = $request->string('storage_path', $this->defaultStoragePath());

        if ($siteName === '' || mb_strlen($siteName) > 150) $errors['site_name'] = 'Site name is required and must be 150 characters or fewer.';
        if (!$this->validBaseUrl($baseUrl)) {
            $errors['base_url'] = 'Enter a complete HTTPS website URL without credentials, a query, or a fragment.';
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Unknown timezone.';
        }
        if ($dbHost === '' || mb_strlen($dbHost) > 190) $errors['db_host'] = 'Database host is required and must be 190 characters or fewer.';
        if ($dbPort < 1 || $dbPort > 65535) $errors['db_port'] = 'Database port must be between 1 and 65535.';
        if ($dbName === '' || mb_strlen($dbName) > 190) $errors['db_name'] = 'Database name is required and must be 190 characters or fewer.';
        if ($dbUser === '' || mb_strlen($dbUser) > 190) $errors['db_user'] = 'Database user is required and must be 190 characters or fewer.';
        if (!preg_match('/^[a-zA-Z0-9_]{0,16}$/', $dbPrefix)) {
            $errors['db_prefix'] = 'Prefix: letters, digits, underscore, max 16.';
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $owner)) {
            $errors['owner_username'] = 'Username: 3-60 chars, letters/digits/._-';
        }
        if (mb_strlen($ownerEmail) > 190 || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['owner_email'] = 'Valid email required.';
        }
        if (strlen($pass1) < 12) {
            $errors['owner_password'] = 'Password must be at least 12 characters.';
        } elseif ($pass1 !== $pass2) {
            $errors['owner_password2'] = 'Passwords do not match.';
        }

        $configWritable = $this->prepareWritableDirectory(
            $this->configDir,
            $this->usingPrivateConfig ? 0700 : 0750
        );
        $storageWritable = $this->prepareWritableDirectory(
            $storagePath,
            $this->privateRuntime !== null ? 0700 : 0750
        );
        $uploadPath = $this->defaultUploadPath();
        $uploadWritable = $this->prepareWritableDirectory(
            $uploadPath,
            $this->privateRuntime !== null ? 0700 : 0750
        );
        if (!$configWritable) {
            $errors['site_name'] = 'No safe persistent configuration directory is available.';
        }
        if (!$storageWritable || !$uploadWritable) {
            $errors['storage_path'] = 'No safe persistent storage and upload workspace is available.';
        }

        // DB connectivity is checked with the provided credentials. Detailed
        // driver errors stay in the server log; setup receives safe guidance.
        $db = null;
        if ($errors === []) {
            try {
                $db = Connection::mysql($dbHost, $dbPort, $dbName, $dbUser, $dbPass, 'utf8mb4', $dbPrefix);
                if ($db->tableExists('migrations') && $request->string('confirm_existing_database') !== '1') {
                    $errors['existing_database'] = 'Existing Website Core tables were detected. Confirm the protected reinstall to preserve and upgrade them.';
                }
            } catch (\Throwable $e) {
                error_log('[website-core install] database connection failed: ' . $e->getMessage());
                $errors['db_name'] = $this->databaseErrorMessage($e);
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

            $emailOwner = $db->fetch(
                'SELECT id,username FROM `' . $db->table('admins') . '` WHERE LOWER(email) = LOWER(?) AND username <> ?',
                [$ownerEmail, $owner]
            );
            if ($emailOwner !== null) {
                throw new \RuntimeException('That owner email already belongs to another administrator. Use that account username or a different email.');
            }

            $ownerData = [
                'email'         => $ownerEmail,
                'password_hash' => password_hash($pass1, PASSWORD_DEFAULT),
                'role'          => 'owner',
                'mfa_enabled'   => 0,
                'mfa_secret'    => null,
                'mfa_last_counter' => -1,
                'disabled'      => 0,
            ];
            $existingOwner = $db->fetch(
                'SELECT id FROM `' . $db->table('admins') . '` WHERE username = ?',
                [$owner]
            );
            if ($existingOwner !== null) {
                $ownerId = (int) $existingOwner['id'];
                $db->update('admins', $ownerData, 'id = ?', [$ownerId]);
                $db->run(
                    'UPDATE `' . $db->table('admin_sessions') . '` SET revoked_at = ? WHERE admin_id = ? AND revoked_at IS NULL',
                    [date('Y-m-d H:i:s'), $ownerId]
                );
                $db->run(
                    'DELETE FROM `' . $db->table('recovery_codes') . '` WHERE admin_id = ?',
                    [$ownerId]
                );
            } else {
                $db->insert('admins', [
                    'username' => $owner,
                    ...$ownerData,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // A fresh install must serve something at '/' — the home route
            // resolves site.home_slug (default 'home').
            $now = date('Y-m-d H:i:s');
            if (!$db->fetch('SELECT id FROM `' . $db->table('pages') . '` WHERE slug = ?', ['home'])) {
                $homeTitle = 'Welcome to ' . $siteName;
                $db->insert('pages', [
                    'slug' => 'home', 'title' => $homeTitle,
                    'body' => '<p>Your new site is ready. Sign in to the administration area to edit this page.</p>',
                    'meta_title' => mb_substr($homeTitle . ' | Official Website', 0, 60),
                    'meta_description' => mb_substr(
                        'Learn more about ' . $siteName . ', explore our services, and contact our team.',
                        0,
                        160
                    ),
                    'status' => 'published',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // Themes render site.name — seed it from the install form so the
            // site does not greet visitors as "Website Core".
            $settings = new \Core\Settings\SettingsRepository($db);
            $settings->set('site.name', $siteName);
            if ($settings->get('site.tagline', null) === null) {
                $settings->set('site.tagline', '');
            }
            // Web-triggered cron requires this token — generate it here so
            // it exists before the first settings save.
            if ((string) $settings->get('system.cron_token', '') === '') {
                $settings->set('system.cron_token', bin2hex(random_bytes(24)));
            }
        } catch (\Throwable $e) {
            error_log('[website-core install] schema/owner creation failed: ' . $e->getMessage());
            $safe = $e instanceof \RuntimeException && str_starts_with($e->getMessage(), 'That owner email')
                ? $e->getMessage() : 'Database setup could not be completed. Verify this user can create and alter tables, then check the server log.';
            return $this->page($checks, ['db_name' => $safe], $request);
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
                    $licenseData = [
                        $domain, 1,
                        $licenseOk ? date('Y-m-d H:i:s') : null,
                        $licenseOk ? null : 'Install-time license check inconclusive: ' . ($licenseError ?? 'unknown'),
                    ];
                    if ($db->fetch("SELECT id FROM `{$table}` WHERE license_key = ?", [$licenseKey])) {
                        $db->run(
                            "UPDATE `{$table}` SET license_domain = ?, license_valid = ?, license_validated_at = ?,
                             license_error = ?, license_deactivated = 0 WHERE license_key = ?",
                            [...$licenseData, $licenseKey]
                        );
                    } else {
                        $db->run(
                            "INSERT INTO `{$table}` (license_key, license_domain, license_valid, license_validated_at, license_error, created_at)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            [$licenseKey, ...$licenseData, date('Y-m-d H:i:s')]
                        );
                    }
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
                error_log('[website-core install] bundled module installation failed: ' . $e->getMessage());
                return $this->page($checks, ['license' => 'The bundled industry module could not be installed. Existing data was retained; check the server log before retrying.'], $request);
            }
        }

        // --- write config -----------------------------------------------------
        $appKey = $this->existingAppKey() ?? bin2hex(random_bytes(32));
        $configPhp = $this->renderConfig([
            'siteName' => $siteName, 'baseUrl' => $baseUrl, 'timezone' => $timezone,
            'dbHost' => $dbHost, 'dbPort' => $dbPort, 'dbName' => $dbName,
            'dbUser' => $dbUser, 'dbPass' => $dbPass, 'dbPrefix' => $dbPrefix,
            'storage' => $storagePath, 'upload' => $uploadPath, 'appKey' => $appKey,
            'https' => $this->requestSecure(),
            'licenseApiUrl' => rtrim((string) (getenv('WC_LICENSE_API_URL') ?: 'https://cajunwebpro.com/api'), '/'),
        ]);
        try {
            $this->writeConfigAtomically($configPhp, $appKey, $dbName);
        } catch (\Throwable $e) {
            error_log('[website-core install] configuration write failed: ' . $e->getMessage());
            return $this->page($checks, [
                'site_name' => 'The verified configuration could not be committed. Existing configuration was not replaced; check directory permissions and the server log.',
            ], $request);
        }

        $installedContents = date('c') . ' install complete' . "\n";
        $markerTmp = $this->installedLock . '.new-' . bin2hex(random_bytes(5));
        if (@file_put_contents($markerTmp, $installedContents, LOCK_EX) === false
            || !@rename($markerTmp, $this->installedLock)) {
            @unlink($markerTmp);
            return $this->page($checks, [
                'site_name' => 'Could not create the permanent installation lock.',
            ], $request);
        }

        foreach ([$this->claimFile, $this->configDir . '/claim.session', $this->tokenFile] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $remaining = wc_remove_installer_artifacts(WC_ROOT);
        $cleanupNote = '';
        if ($remaining !== []) {
            @file_put_contents(
                $this->configDir . '/cleanup-required.lock',
                date('c') . " installer cleanup pending\n",
                LOCK_EX
            );
            $cleanupItems = implode(' and ', array_map('e', $remaining));
            $cleanupNote = '<div class="cleanup-warning" role="status"><strong>Manual cleanup recommended.</strong> '
                . 'PHP could not remove ' . $cleanupItems . '. The installer is permanently disabled, so your site can launch safely. '
                . 'Use your hosting File Manager to delete the root <code>install.php</code> file and <code>install</code> folder. '
                . '<a href="' . e_attr(rtrim($baseUrl, '/') . '/?installer_cleanup_recheck=1') . '">Recheck cleanup and open the website</a>.</div>';
        } else {
            @unlink($this->configDir . '/cleanup-required.lock');
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

        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
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
            . 'p{color:#b9c9dc;line-height:1.7}strong{color:#fff}.logo-link{display:block;width:fit-content;max-width:100%}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}.launch-link{display:inline-flex;padding:14px 22px;'
            . 'border:1px solid #48627f;border-radius:12px;color:#eef5ff;text-decoration:none;font-weight:800}.launch-link.primary{border-color:#d6a94f;background:#d6a94f;color:#07111f}'
            . '.cleanup-warning{margin:20px 0;padding:16px 18px;border:1px solid #8a692d;border-radius:12px;background:#493817;color:#ffe3a6;line-height:1.6}'
            . '.cleanup-warning strong{color:#fff2c9}.cleanup-warning code{color:#fff2c9}</style>'
            . '</head><body><main class="done"><a class="logo-link" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Visit CajunWebPro">'
            . '<img class="logo" src="' . e_attr($logoUrl) . '" alt="CajunWebPro — Web Design &amp; Development"></a>'
            . '<div class="mark">✓</div><p>CAJUNWEBPRO DEPLOYMENT</p><h1>Installation complete</h1>'
            . '<p>The installer is permanently disabled. Your site is secured and ready.</p>'
            . $licenseNote
            . $cleanupNote
            . '<p>Sign in and enable two-factor authentication for the owner account.</p>'
            . '<div class="actions"><a class="launch-link primary" href="' . e_attr(rtrim($baseUrl, '/')) . '/">Visit website</a>'
            . '<a class="launch-link" href="' . e_attr(rtrim($baseUrl, '/')) . '/index.php?p=/admin">Open admin</a></div></main></body></html>'
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

    private function validBaseUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\x00-\x20]/', $url)) return false;
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && (string) parse_url($url, PHP_URL_HOST) !== ''
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_QUERY) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null;
    }

    private function databaseErrorMessage(\Throwable $error): string
    {
        $message = strtolower($error->getMessage());
        if (str_contains($message, 'access denied') || str_contains($message, 'authentication')) {
            return 'Database authentication failed. Verify the username and password in your hosting panel.';
        }
        if (str_contains($message, 'unknown database')) {
            return 'The database does not exist. Create it in your hosting panel or correct the database name.';
        }
        if (str_contains($message, 'connection refused') || str_contains($message, 'server has gone away')
            || str_contains($message, 'getaddrinfo') || str_contains($message, 'no such host')) {
            return 'The database server could not be reached. Verify the host and port with your hosting provider.';
        }
        return 'The database connection could not be verified. Recheck the host, database, username, password, and port.';
    }

    private function existingAppKey(): ?string
    {
        $path = $this->configDir . '/config.php';
        if (!is_file($path) || is_link($path)) return null;
        try {
            $existing = (static fn(string $file): mixed => @include $file)($path);
        } catch (\Throwable) {
            return null;
        }
        $key = is_array($existing) ? ($existing['security']['app_key'] ?? null) : null;
        return is_string($key) && preg_match('/^[a-f0-9]{64}$/i', $key) ? $key : null;
    }

    private function writeConfigAtomically(string $contents, string $appKey, string $databaseName): void
    {
        $final = $this->configDir . '/config.php';
        $temporary = $this->configDir . '/config.php.new-' . bin2hex(random_bytes(6));
        try {
            if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new \RuntimeException('Temporary configuration write failed.');
            }
            @chmod($temporary, $this->usingPrivateConfig ? 0600 : 0640);
            clearstatcache(true, $temporary);
            $candidate = (static fn(string $file): mixed => @include $file)($temporary);
            if (!is_array($candidate)
                || ($candidate['security']['app_key'] ?? null) !== $appKey
                || ($candidate['database']['name'] ?? null) !== $databaseName
            ) {
                throw new \RuntimeException('Temporary configuration verification failed.');
            }
            if (!@rename($temporary, $final)) {
                throw new \RuntimeException('Atomic configuration replacement failed.');
            }
            clearstatcache(true, $final);
            $saved = (static fn(string $file): mixed => @include $file)($final);
            if (!is_array($saved)
                || ($saved['security']['app_key'] ?? null) !== $appKey
                || ($saved['database']['name'] ?? null) !== $databaseName
            ) {
                throw new \RuntimeException('Committed configuration verification failed.');
            }
        } finally {
            @unlink($temporary);
        }
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
                'upload' => {$s($v['upload'])},
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
        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        $logoUrl = ($basePath === '' ? '' : $basePath) . '/assets/images/cajunwebpro-logo.png';
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
            'storage_path' => $this->defaultStoragePath(),
        ], array_filter($old, fn ($x) => $x !== ''));

        ob_start();
        $tzList = \DateTimeZone::listIdentifiers();
        require WC_CORE . '/Install/views/installer.php';
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
