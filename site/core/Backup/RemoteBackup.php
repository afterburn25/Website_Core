<?php
declare(strict_types=1);

namespace Core\Backup;

use Core\Config;

final class RemoteBackup
{
    public function __construct(private Config $config) {}

    public function configured(): bool
    {
        return trim((string) $this->config->get('backups.remote.webdav_url', '')) !== '';
    }

    public function upload(string $localPath): ?string
    {
        if (!$this->configured()) return null;
        $base = rtrim((string) $this->config->get('backups.remote.webdav_url'), '/');
        if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https') return 'Remote backup URL must use HTTPS.';
        if (!function_exists('curl_init')) return 'cURL is required for remote backups.';
        $fh = @fopen($localPath, 'rb');
        if ($fh === false) return 'Backup file could not be opened.';
        $ch = curl_init($base . '/' . rawurlencode(basename($localPath)));
        curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 300,
            CURLOPT_USERPWD => (string) $this->config->get('backups.remote.webdav_user', '') . ':' . (string) $this->config->get('backups.remote.webdav_password', '')]);
        curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch); fclose($fh);
        return $status >= 200 && $status < 300 ? null : ($error !== '' ? $error : 'Remote backup returned HTTP ' . $status . '.');
    }
}
