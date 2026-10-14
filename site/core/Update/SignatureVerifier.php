<?php
declare(strict_types=1);

namespace Core\Update;

/**
 * Package trust verification using libsodium Ed25519.
 *
 * A signed manifest carries `signature` = base64(sodium_detached(sig)) over
 * the canonical JSON of the manifest WITHOUT the signature field. Trust is
 * anchored in `trust.public_keys` from config.php — never shipped in Git,
 * never a network fetch. integrity.files hashes then protect every payload
 * file transitively through the signed manifest.
 *
 * Policy (set in config, enforced by PackageManager):
 *  - signed manifest + matching trusted key          → allow
 *  - signed manifest + no/unknown key                → reject
 *  - unsigned manifest + trust.allow_unsigned = true → allow (dev default)
 *  - unsigned manifest + trust.allow_unsigned = false → reject (production)
 */
final class SignatureVerifier
{
    /** @param list<string> $publicKeys base64-encoded 32-byte ed25519 keys */
    public function __construct(
        private array $publicKeys = [],
        private bool $allowUnsigned = true,
    ) {}

    /**
     * @return array{0:bool,1:?string} [accepted, error]
     */
    public function verify(array $manifest): array
    {
        $sigB64 = $manifest['signature'] ?? null;
        if (!is_string($sigB64) || $sigB64 === '') {
            return $this->allowUnsigned
                ? [true, null]
                : [false, 'Unsigned package and unsigned packages are disabled.'];
        }

        if (!extension_loaded('sodium')) {
            // Fail closed: a signed package cannot be verified at all on a
            // host without libsodium — never silently accept.
            return [false, 'Package is signed but this host lacks libsodium — cannot verify.'];
        }

        $sig = base64_decode($sigB64, true);
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return [false, 'Malformed package signature.'];
        }
        if ($this->publicKeys === []) {
            return [false, 'Package is signed but no trusted keys are configured.'];
        }

        $unsigned = $manifest;
        unset($unsigned['signature']);
        $payload = json_encode($unsigned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach ($this->publicKeys as $keyB64) {
            $key = base64_decode((string) $keyB64, true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                continue;
            }
            if (sodium_crypto_sign_verify_detached($sig, $payload, $key)) {
                return [true, null];
            }
        }
        return [false, 'Package signature does not match any trusted key.'];
    }

    /** Helper for package build tooling: sign a manifest array. */
    public static function signManifest(array $manifest, string $secretKeyB64): array
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('libsodium required to sign packages');
        }
        unset($manifest['signature']);
        $payload = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secret = base64_decode($secretKeyB64, true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('invalid ed25519 secret key');
        }
        $manifest['signature'] = base64_encode(sodium_crypto_sign_detached($payload, $secret));
        return $manifest;
    }

    /** Helper for fixture/tooling key generation. */
    public static function generateKeypair(): array
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('libsodium required');
        }
        $kp = sodium_crypto_sign_keypair();
        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($kp)),
            'secret' => base64_encode(sodium_crypto_sign_secretkey($kp)),
        ];
    }
}
