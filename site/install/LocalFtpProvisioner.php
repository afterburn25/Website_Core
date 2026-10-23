<?php
declare(strict_types=1);

namespace Install;

/**
 * Local-only FTP fallback for shared hosts where uploaded files and the PHP
 * worker have different owners. Credentials live only for the current request.
 */
final class LocalFtpProvisioner
{
    /** @var mixed FTP\Connection on supported PHP versions */
    private mixed $connection = null;
    private ?string $siteRoot = null;

    public function __construct(private readonly string $localRoot)
    {
    }

    public static function supported(): bool
    {
        return function_exists('ftp_connect')
            && function_exists('ftp_login')
            && function_exists('ftp_fput');
    }

    public static function isLocalHost(string $host): bool
    {
        return in_array(strtolower(trim($host)), ['127.0.0.1', 'localhost', '::1'], true);
    }

    public function connect(
        string $host,
        int $port,
        string $username,
        string $password,
        string $requestedSiteDirectory = ''
    ): void {
        if (!self::supported() || !self::isLocalHost($host)) {
            throw new \RuntimeException(
                'This server does not provide the required local FTP ownership fallback.'
            );
        }
        if ($port < 1 || $port > 65535) {
            throw new \RuntimeException('The local FTP port must be between 1 and 65535.');
        }
        if (trim($username) === '' || $password === '') {
            throw new \RuntimeException('Enter the hosting FTP username and password.');
        }

        $connection = @ftp_connect($host, $port, 8);
        if ($connection === false || !@ftp_login($connection, $username, $password)) {
            if ($connection !== false) {
                @ftp_close($connection);
            }
            throw new \RuntimeException('The local hosting FTP login failed. Check the account credentials.');
        }
        @ftp_pasv($connection, true);

        foreach ($this->rootCandidates($requestedSiteDirectory) as $candidate) {
            $installer = $this->join($candidate, 'install/Installer.php');
            $bootstrap = $this->join($candidate, 'includes/bootstrap.php');
            if (@ftp_size($connection, $installer) >= 0 && @ftp_size($connection, $bootstrap) >= 0) {
                $this->connection = $connection;
                $this->siteRoot = $candidate;
                return;
            }
        }

        @ftp_close($connection);
        throw new \RuntimeException(
            'FTP login worked, but this website folder was not found. Enter the site directory shown in DirectAdmin.'
        );
    }

    public function detectedRoot(): string
    {
        $this->assertConnected();
        return (string) $this->siteRoot;
    }

    public function provisionStorage(): void
    {
        foreach (['storage', 'storage/backups', 'storage/cache', 'storage/ratelimit', 'storage/tmp'] as $path) {
            $this->ensureDirectory($path, 0775);
        }
    }

    public function write(string $relativePath, string $contents, int $mode = 0640): void
    {
        $this->assertConnected();
        $relativePath = $this->safeRelativePath($relativePath);
        $parent = dirname($relativePath);
        if ($parent !== '.') {
            $this->ensureDirectory($parent, 0750);
        }

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Could not prepare the installer file transfer.');
        }
        fwrite($stream, $contents);
        rewind($stream);
        $remote = $this->remote($relativePath);
        $ok = @ftp_fput($this->connection, $remote, $stream, FTP_BINARY);
        fclose($stream);
        if (!$ok) {
            throw new \RuntimeException('Hosting FTP could not write ' . $relativePath . '.');
        }
        @ftp_chmod($this->connection, $mode, $remote);
    }

    public function delete(string $relativePath): bool
    {
        $this->assertConnected();
        $remote = $this->remote($this->safeRelativePath($relativePath));
        if (@ftp_size($this->connection, $remote) < 0) {
            return true;
        }
        return @ftp_delete($this->connection, $remote);
    }

    /** @return list<string> */
    public function removeInstallerArtifacts(): array
    {
        $this->assertConnected();
        $this->removeRemoteTree($this->remote('install'));
        $this->delete('install.php');

        $remaining = [];
        if ($this->remoteDirectoryExists($this->remote('install'))) {
            $remaining[] = 'install/';
        }
        if (@ftp_size($this->connection, $this->remote('install.php')) >= 0) {
            $remaining[] = 'install.php';
        }
        return $remaining;
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @return list<string> */
    private function rootCandidates(string $requested): array
    {
        $candidates = [];
        $requested = trim(str_replace('\\', '/', $requested));
        if ($requested !== '') {
            $candidates[] = $this->safeRemoteRoot($requested);
        }

        $absolute = rtrim(str_replace('\\', '/', $this->localRoot), '/');
        $candidates[] = $absolute;
        $candidates[] = ltrim($absolute, '/');
        if (preg_match('#^/home/[^/]+/(.+)$#', $absolute, $match)) {
            $candidates[] = $match[1];
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
        $subdirectory = trim(dirname($script), '/.');
        $host = preg_replace('/:\\d+$/', '', strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')));
        foreach (['.', 'public_html', 'httpdocs', 'htdocs', 'www'] as $base) {
            $candidates[] = $base;
            if ($subdirectory !== '') {
                $candidates[] = $this->join($base, $subdirectory);
            }
        }
        if (is_string($host) && preg_match('/^[a-z0-9.-]+$/', $host)) {
            foreach (['public_html', 'public'] as $publicDirectory) {
                $base = 'domains/' . $host . '/' . $publicDirectory;
                $candidates[] = $base;
                if ($subdirectory !== '') {
                    $candidates[] = $this->join($base, $subdirectory);
                }
            }
        }

        $clean = [];
        foreach ($candidates as $candidate) {
            try {
                $candidate = $this->safeRemoteRoot((string) $candidate);
            } catch (\RuntimeException) {
                continue;
            }
            $clean[$candidate] = true;
        }
        return array_keys($clean);
    }

    private function ensureDirectory(string $relativePath, int $mode): void
    {
        $this->assertConnected();
        $relativePath = $this->safeRelativePath($relativePath);
        $built = '';
        foreach (explode('/', $relativePath) as $part) {
            $built = $built === '' ? $part : $built . '/' . $part;
            $remote = $this->remote($built);
            if (!$this->remoteDirectoryExists($remote) && !@ftp_mkdir($this->connection, $remote)) {
                throw new \RuntimeException('Hosting FTP could not create ' . $relativePath . '.');
            }
            @ftp_chmod($this->connection, $mode, $remote);
        }
    }

    private function removeRemoteTree(string $remote): bool
    {
        $entries = @ftp_nlist($this->connection, $remote);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                $name = basename(rtrim((string) $entry, '/'));
                if ($name === '.' || $name === '..' || $name === '') {
                    continue;
                }
                $entryPath = str_contains((string) $entry, '/')
                    ? (string) $entry
                    : $this->join($remote, (string) $entry);
                if (@ftp_size($this->connection, $entryPath) >= 0) {
                    @ftp_delete($this->connection, $entryPath);
                } else {
                    $this->removeRemoteTree($entryPath);
                }
            }
        }
        @ftp_rmdir($this->connection, $remote);
        return !$this->remoteDirectoryExists($remote);
    }

    private function remoteDirectoryExists(string $remote): bool
    {
        $current = @ftp_pwd($this->connection);
        if ($current === false || !@ftp_chdir($this->connection, $remote)) {
            return false;
        }
        @ftp_chdir($this->connection, $current);
        return true;
    }

    private function remote(string $relativePath): string
    {
        return $this->join((string) $this->siteRoot, $relativePath);
    }

    private function join(string $left, string $right): string
    {
        $left = rtrim($left, '/');
        return ($left === '' ? '.' : $left) . '/' . ltrim($right, '/');
    }

    private function safeRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new \RuntimeException('The installer received an unsafe hosting path.');
        }
        return $path;
    }

    private function safeRemoteRoot(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '') {
            return '.';
        }
        if (str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new \RuntimeException('The FTP site directory may not contain parent traversal.');
        }
        return $path;
    }

    private function assertConnected(): void
    {
        if ($this->connection === null || $this->siteRoot === null) {
            throw new \LogicException('Local FTP is not connected.');
        }
    }
}
