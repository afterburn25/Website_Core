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
        'version'     => '0.1.0-dev',
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
        // Trust roots for signed update metadata. Empty until an approved
        // production trust configuration exists — see docs/UPGRADES.md.
        'trust_roots' => [],
        'channel'     => 'stable',
    ],
];
