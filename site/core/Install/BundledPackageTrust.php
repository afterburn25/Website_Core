<?php
declare(strict_types=1);

namespace Core\Install;

use Core\Modules\ArchiveInspector;
use Core\Modules\Manifest;
use Core\Update\SignatureVerifier;

/**
 * Fail-closed trust boundary for pre-extracted, customer-licensed products.
 *
 * The web installer must check the complete signed payload BEFORE writing
 * schema, license rows, owner sessions, or configuration. Unlike an uploaded
 * package, pre-extracted bundle files never pass PackageManager::stage().
 */
final class BundledPackageTrust
{
    /**
     * Public trust roots must be supplied independently of the uploaded ZIP.
     * Provider/host may set WC_INSTALL_TRUST_PUBLIC_KEYS to comma-separated
     * base64 Ed25519 public keys (32 bytes each). No secret key is needed here.
     *
     * @return list<string>
     */
    public static function trustedPublicKeys(): array
    {
        $raw = getenv('WC_INSTALL_TRUST_PUBLIC_KEYS');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $keys = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        if ($keys === [] || count($keys) > 8) {
            return [];
        }
        $trusted = [];
        foreach ($keys as $key) {
            $bytes = base64_decode($key, true);
            if ($bytes === false || strlen($bytes) !== 32) {
                return [];
            }
            $trusted[$key] = $key;
        }
        return array_values($trusted);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $trustedKeys
     * @return ?string Generic operator-safe error, null when fully verified.
     */
    public static function verify(string $moduleDir, array $manifest, array $trustedKeys): ?string
    {
        // Deliberate keyless TEST-BUNDLEs are not customer-licensed products.
        // Production config still refuses unsigned module updates by default.
        if (!isset($manifest['license_key'])) {
            return null;
        }
        if ($trustedKeys === []) {
            return 'Licensed bundles require independently configured Ed25519 trust keys (WC_INSTALL_TRUST_PUBLIC_KEYS) before installation.';
        }
        if (Manifest::validate($manifest) !== []) {
            return 'Bundled industry manifest is invalid.';
        }
        $files = $manifest['integrity']['files'] ?? null;
        if (!is_array($files) || $files === []) {
            return 'Licensed bundle has no signed per-file integrity inventory.';
        }
        [$trusted] = (new SignatureVerifier($trustedKeys, false))->verify($manifest);
        if (!$trusted) {
            return 'Licensed bundle signature is missing, untrusted or invalid.';
        }
        if (is_link($moduleDir) || !is_dir($moduleDir)) {
            return 'Licensed bundle module directory is invalid.';
        }

        $seen = [];
        foreach ($files as $rel => $digest) {
            if (!is_string($rel) || $rel === '' || !ArchiveInspector::memberNameOk($rel)
                || str_contains($rel, '\\') || str_contains($rel, '//')
                || str_starts_with($rel, '/') || str_ends_with($rel, '/')
                || preg_match('#(^|/)\.(/|$)#', $rel)
                || !is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
                || $rel === 'addon.json') {
                return 'Licensed bundle contains an invalid signed file inventory.';
            }
            $path = rtrim($moduleDir, '/\\') . '/' . $rel;
            if (is_link($path) || !is_file($path) || !is_readable($path)
                || !hash_equals($digest, (string) hash_file('sha256', $path))) {
                return 'Licensed bundle is missing a file or its signed checksum does not match.';
            }
            $seen[$rel] = true;
        }

        // No executable/source/asset bytes may be present outside the signed
        // inventory. Also detect links to directories, which the recursive
        // iterator otherwise would not traverse.
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $root = rtrim($moduleDir, '/\\') . '/';
        foreach ($directory as $entry) {
            if ($entry->isLink()) {
                return 'Licensed bundle contains a symbolic link.';
            }
            if ($entry->isDir()) {
                continue;
            }
            if (!$entry->isFile()) {
                return 'Licensed bundle contains a nonregular file.';
            }
            $rel = str_replace('\\', '/', substr($entry->getPathname(), strlen($root)));
            if ($rel !== 'addon.json' && !isset($seen[$rel])) {
                return 'Licensed bundle contains an unsigned extra file.';
            }
        }
        return null;
    }
}
