<?php
declare(strict_types=1);

/**
 * Read-only, independently trusted UPDATE signature and full ZIP inventory check.
 *
 * php tools/verify-signed-update-offline.php Website-Core-2.6.2-UPDATE.zip \
 *    /private/trusted-update-signing-public.txt --version 2.6.2 \
 *    --expected-sha256 <independently-recorded-hex-digest>
 *
 * The public key MUST be obtained and authenticated independently by the
 * operator; a key found inside the downloaded ZIP is never trusted.
 *
 * This verifies an UPDATE download only, never installs or stages it.
 * No database, credentials, source config, customer data or network access.
 * Passing does not authorize a production update or replace hosted acceptance.
 */
require_once dirname(__DIR__) . '/site/core/Update/SignatureVerifier.php';

use Core\Update\SignatureVerifier;

final class WcOfflineSignedUpdateRefusal extends RuntimeException {}

function wc_offline_signed_update_refuse(string $reason): never
{
    throw new WcOfflineSignedUpdateRefusal($reason);
}

/**
 * @return array{verified: bool, version: string, files: int, sha256: string, expires: string, scope: string}
 */
function wc_verify_signed_update_offline(
    string $archivePath,
    string $trustedPublicKeyFile,
    string $version,
    ?string $expectedSha256 = null
): array {
    if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-dev)?$/', $version) !== 1) {
        wc_offline_signed_update_refuse('invalid_expected_version');
    }
    if (!is_file($archivePath) || is_link($archivePath)
        || !preg_match('/-UPDATE\.zip$/', basename($archivePath))) {
        wc_offline_signed_update_refuse('update_archive_unavailable');
    }
    if (!is_file($trustedPublicKeyFile) || is_link($trustedPublicKeyFile)
        || @filesize($trustedPublicKeyFile) > 512) {
        wc_offline_signed_update_refuse('trusted_public_key_file_invalid');
    }
    $encodedKey = trim((string) @file_get_contents($trustedPublicKeyFile));
    $public = base64_decode($encodedKey, true);
    if ($public === false || strlen($public) !== 32) {
        wc_offline_signed_update_refuse('trusted_public_key_file_invalid');
    }
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        // This CLI intentionally requires installed native ext-sodium.
        // Never downgrade an operator-approved release check to unsigned.
        wc_offline_signed_update_refuse('native_ed25519_unavailable');
    }
    $sha256 = @hash_file('sha256', $archivePath);
    if (!is_string($sha256)) {
        wc_offline_signed_update_refuse('update_archive_unreadable');
    }
    if ($expectedSha256 !== null && (
        preg_match('/^[a-fA-F0-9]{64}$/', $expectedSha256) !== 1
        || !hash_equals(strtolower($expectedSha256), $sha256)
    )) {
        wc_offline_signed_update_refuse('independent_sha256_mismatch');
    }

    $zip = new ZipArchive();
    if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
        wc_offline_signed_update_refuse('update_zip_invalid');
    }
    try {
        if ($zip->numFiles < 10 || $zip->numFiles > 50000) {
            wc_offline_signed_update_refuse('update_member_count_invalid');
        }
        $seen = [];
        $actualFiles = [];
        $totalBytes = 0;
        foreach (range(0, $zip->numFiles - 1) as $i) {
            $info = $zip->statIndex($i);
            if (!is_array($info) || !is_string($info['name'] ?? null)) {
                wc_offline_signed_update_refuse('update_member_invalid');
            }
            $name = $info['name'];
            $dir = str_ends_with($name, '/');
            $normalized = $dir ? substr($name, 0, -1) : $name;
            if ($normalized === '' || str_starts_with($name, '/') || str_contains($name, '\\')
                || str_contains($name, ':') || str_contains($name, "\0")
                || preg_match('/[\x00-\x1F]/', $name)
                || in_array('', explode('/', $normalized), true)
                || in_array('.', explode('/', $normalized), true)
                || in_array('..', explode('/', $normalized), true)) {
                wc_offline_signed_update_refuse('unsafe_zip_member_path');
            }
            $collision = strtolower($normalized);
            if (isset($seen[$collision])) {
                wc_offline_signed_update_refuse('duplicate_or_colliding_zip_member');
            }
            $seen[$collision] = true;
            $opsys = 0;
            $attributes = 0;
            if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                wc_offline_signed_update_refuse('zip_member_type_unreadable');
            }
            if ($opsys === ZipArchive::OPSYS_UNIX) {
                $type = (($attributes >> 16) & 0170000);
                if (!in_array($type, [0, 0100000, 0040000], true)
                    || ($dir && $type === 0100000) || (!$dir && $type === 0040000)) {
                    wc_offline_signed_update_refuse('linked_or_special_zip_member');
                }
            }
            if ($dir) {
                continue;
            }
            $totalBytes += (int) ($info['size'] ?? 0);
            if ($totalBytes > 5 * 1024 * 1024 * 1024) {
                wc_offline_signed_update_refuse('zip_uncompressed_size_excessive');
            }
            $lower = strtolower($name);
            if (str_ends_with($lower, '.zip')
                || str_ends_with($lower, '.key') || str_ends_with($lower, '.pem')
                || $lower === '.env' || str_ends_with($lower, '/.env')
                || $lower === 'config/config.php'
                || $lower === 'config/installed.lock'
                || str_starts_with($lower, 'modules/industries/')
                || str_starts_with($lower, 'modules/addons/')
                || str_starts_with($lower, '.git/') || str_contains($lower, '/.git/')) {
                // Module manifests are separate signed packages. An UPDATE
                // cannot ship customer licenses or replace installed modules.
                if (!in_array($lower, [
                    'modules/industries/.gitkeep', 'modules/addons/.gitkeep',
                ], true)) {
                    wc_offline_signed_update_refuse('runtime_or_private_member');
                }
            }
            $actualFiles[$name] = true;
        }
        $required = [
            'update.json', 'index.php', '.htaccess', 'install.php', 'install/index.php',
            'includes/bootstrap.php', 'core/Install/Installer.php',
            'core/Install/views/installer.php', 'vendor/autoload.php', 'vendor/.htaccess',
        ];
        foreach ($required as $member) {
            if (!isset($actualFiles[$member])) {
                wc_offline_signed_update_refuse('required_update_member_missing');
            }
        }
        $metadata = $zip->getFromName('update.json');
        if (!is_string($metadata) || strlen($metadata) > 2097152) {
            wc_offline_signed_update_refuse('update_metadata_unreadable');
        }
        $manifest = json_decode($metadata, true);
        if (!is_array($manifest) || ($manifest['product'] ?? null) !== 'website-core'
            || ($manifest['version'] ?? null) !== $version
            || !is_string($manifest['signature'] ?? null)) {
            wc_offline_signed_update_refuse('update_manifest_identity_or_signature_invalid');
        }
        [$trusted] = (new SignatureVerifier([$encodedKey], false))->verify($manifest);
        if (!$trusted) {
            wc_offline_signed_update_refuse('untrusted_update_signature');
        }
        $files = $manifest['files'] ?? null;
        if (!is_array($files) || $files === [] || count($files) !== count($actualFiles) - 1) {
            wc_offline_signed_update_refuse('update_inventory_mismatch');
        }
        foreach ($actualFiles as $name => $_) {
            if ($name === 'update.json') {
                continue;
            }
            $expected = $files[$name] ?? null;
            if (!is_string($expected) || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
                wc_offline_signed_update_refuse('update_inventory_mismatch');
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                wc_offline_signed_update_refuse('update_member_unreadable');
            }
            $hash = hash_init('sha256');
            while (!feof($stream)) {
                $bytes = fread($stream, 1048576);
                if ($bytes === false) {
                    fclose($stream);
                    wc_offline_signed_update_refuse('update_member_unreadable');
                }
                if ($bytes === '') {
                    break;
                }
                hash_update($hash, $bytes);
            }
            fclose($stream);
            if (!hash_equals($expected, hash_final($hash))) {
                wc_offline_signed_update_refuse('update_payload_sha256_mismatch');
            }
        }
        $bootstrap = $zip->getFromName('includes/bootstrap.php');
        if (!is_string($bootstrap) || !str_contains($bootstrap, "define('WC_VERSION', '" . $version . "')")) {
            wc_offline_signed_update_refuse('core_version_marker_mismatch');
        }
        $expires = $manifest['expires'] ?? null;
        if (!is_string($expires)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $expires) !== 1
            || strtotime($expires) === false || strtotime($expires) <= time()) {
            wc_offline_signed_update_refuse('update_manifest_expired_or_invalid');
        }
        return [
            'verified' => true,
            'version' => $version,
            'files' => count($actualFiles) - 1,
            'sha256' => $sha256,
            'expires' => $expires,
            'scope' => 'offline_download_signature_and_payload_only',
        ];
    } finally {
        $zip->close();
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        if (($argc !== 5 && $argc !== 7) || ($argv[3] ?? null) !== '--version'
            || ($argc === 7 && ($argv[5] ?? null) !== '--expected-sha256')) {
            wc_offline_signed_update_refuse('invalid_invocation');
        }
        $result = wc_verify_signed_update_offline($argv[1], $argv[2], $argv[4],
            $argc === 7 ? $argv[6] : null);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $e) {
        // Fixed-code errors only. Neither local paths nor key material are
        // printed on success or failure, even if ZipArchive throws.
        $code = $e instanceof WcOfflineSignedUpdateRefusal ? $e->getMessage() : 'offline_verification_failed';
        fwrite(STDERR, 'FAIL: ' . $code . "\n");
        exit(1);
    }
}
