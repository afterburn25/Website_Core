<?php
declare(strict_types=1);

/**
 * Block every application entry point after installation until the installer
 * directory and root entry file are gone. Returns true when the caller must
 * stop execution. Kept outside the front controllers so those remain thin.
 *
 * @return callable(string): bool
 */
return static function (string $root): bool {
    $installedLock = $root . '/config/installed.lock';
    if (!is_file($installedLock)) {
        return false;
    }

    $remaining = array_values(array_filter([
        is_dir($root . '/install') ? 'install/' : null,
        is_file($root . '/install.php') ? 'install.php' : null,
    ]));
    $cleanupLock = $root . '/config/cleanup-required.lock';
    if ($remaining === []) {
        if (is_file($cleanupLock)) {
            @unlink($cleanupLock);
        }
        return false;
    }

    if (!is_file($cleanupLock)) {
        @file_put_contents($cleanupLock, date('c') . " installer artifacts remain\n", LOCK_EX);
    }
    http_response_code(503);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Site locked: remove install/ and install.php, then retry.\n");
        return true;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 60');
    $path = htmlspecialchars($root, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    $logo = htmlspecialchars(($base === '' ? '' : $base) . '/assets/images/cajunwebpro-logo.png', ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Installer cleanup required</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111f;color:#eef5ff;'
        . 'font-family:Inter,system-ui,sans-serif;padding:24px}.box{width:min(680px,100%);box-sizing:border-box;padding:38px;'
        . 'border-radius:22px;border:1px solid #6f5423;background:linear-gradient(145deg,#172333,#0b1523);box-shadow:0 30px 90px #0009}'
        . '.logo{display:block;width:min(390px,88%);height:auto;margin-bottom:26px;filter:drop-shadow(0 12px 24px #0007)}h1{margin:.4rem 0 1rem;font-size:clamp(2rem,5vw,3rem)}'
        . '.flag{display:inline-block;padding:7px 11px;border-radius:999px;background:#4b3513;color:#ffd98a;font-weight:800;font-size:.76rem;letter-spacing:.08em}'
        . 'p,li{color:#c7d3e2;line-height:1.7}code{overflow-wrap:anywhere;color:#ffe0a0}strong{color:white}</style></head><body>'
        . '<main class="box"><img class="logo" src="' . $logo . '" alt="CajunWebPro — Web Design &amp; Development">'
        . '<span class="flag">SITE SAFELY LOCKED</span><h1>Installer cleanup required</h1>'
        . '<p>Installation finished, but the installer could not remove all of its files. The public site and administration area are locked for security.</p>'
        . '<ol><li>Open DirectAdmin File Manager or connect by FTP.</li><li>Go to <code>' . $path . '</code>.</li>'
        . '<li>Delete the <code>install</code> folder and the root <code>install.php</code> file.</li>'
        . '<li>Reload this page. The site will unlock automatically after both are gone.</li></ol>'
        . '<p><strong>Do not delete</strong> <code>config/installed.lock</code> or <code>config/cleanup-required.lock</code>.</p>'
        . '</main></body></html>';
    return true;
};
