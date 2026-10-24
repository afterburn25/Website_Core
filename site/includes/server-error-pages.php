<?php
declare(strict_types=1);

/** Install/update a subfolder-aware Apache/LiteSpeed 500 error document. */
function wc_prepare_server_error_page(string $root): bool
{
    $htaccess = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    $existing = is_file($htaccess) ? @file_get_contents($htaccess) : '';
    if (!is_string($existing)) {
        return false;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (basename($base) === 'admin') {
        $base = rtrim(str_replace('\\', '/', dirname($base)), '/');
    }
    if ($base === '.' || $base === '/') {
        $base = '';
    }
    $url = ($base === '' ? '' : $base) . '/errors/500.html';
    $begin = '# BEGIN Website Core server error page';
    $end = '# END Website Core server error page';
    $block = $begin . "\nErrorDocument 500 " . $url . "\n" . $end;

    $pattern = '/\n?# BEGIN Website Core server error page\R.*?\R# END Website Core server error page\n?/s';
    $without = preg_replace($pattern, "\n", $existing);
    if (!is_string($without)) {
        return false;
    }
    $payload = rtrim($without) . "\n\n" . $block . "\n";
    if ($payload === $existing) {
        return true;
    }
    if (@file_put_contents($htaccess, $payload, LOCK_EX) === false) {
        return false;
    }
    @chmod($htaccess, 0644);
    return true;
}
