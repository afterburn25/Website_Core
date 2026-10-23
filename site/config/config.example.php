<?php
declare(strict_types=1);

/**
 * Example configuration. The installer generates config.php from this shape.
 * NEVER commit a real config.php — it contains installation secrets.
 * config.php is gitignored and excluded from release packages.
 */
return [
    'app' => [
        'name'        => 'My Website',
        'env'         => 'production',          // production | development
        'url'         => 'https://example.com', // validated at install; used to reject hostile Host headers
        'timezone'    => 'UTC',
        'locale'      => 'en',
        'debug'       => false,
        'version'     => '0.9.0-dev',
        'api_version' => 1,                     // extension API major version
    ],
    'database' => [
        'driver'   => 'mysql',
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'website_core',
        'user'     => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
        'prefix'   => 'wc_',
    ],
    'paths' => [
        // Preferred: absolute path OUTSIDE the document root.
        // The shipped default works on ordinary shared hosting with .htaccess
        // protection plus an index.html guard; the installer verifies behaviour.
        'storage' => dirname(__DIR__) . '/storage',
        'upload'  => dirname(__DIR__) . '/upload',
    ],
    'security' => [
        // Generated per-installation by the installer. 32+ random bytes hex/base64.
        'app_key'           => 'GENERATED_AT_INSTALL',
        'session_name'      => 'WCSID',
        'session_idle'      => 7200,       // seconds
        'session_absolute'  => 43200,      // seconds
        'cookie_secure'     => 'auto',     // auto|always|never — auto = on when HTTPS
        'trusted_proxies'   => [],         // CIDRs whose X-Forwarded-* are honored
    ],
    'mail' => [
        'transport'   => 'capture',        // capture | smtp
        'smtp_host'   => '',
        'smtp_port'   => 587,
        'smtp_user'   => '',
        'smtp_pass'   => '',
        'smtp_secure' => 'tls',
        'from'        => 'no-reply@example.com',
        'from_name'   => 'Website Core',
    ],
    'updates' => [
        'channel'     => 'stable',
        // When true, /admin/updates/apply refuses until the owner creates
        // <paths.storage>/maintenance.flag — the maintenance-window gate.
        'require_window' => false,
    ],
    'trust' => [
        // Ed25519 public keys (base64) trusted to sign package/update
        // manifests. Package PHP never executes before this check.
        'public_keys'    => [],
        // Development default; production releases should ship false and
        // an approved trust root. Signed packages ALWAYS require a
        // matching key regardless of this flag.
        'allow_unsigned' => true,
    ],
];
