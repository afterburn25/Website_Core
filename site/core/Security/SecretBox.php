<?php
declare(strict_types=1);

namespace Core\Security;

use Core\Config;

/** Encryption for recoverable integration credentials using the per-site key. */
final class SecretBox
{
    public function __construct(private Config $config) {}

    public function seal(string $plain): string
    {
        if (!function_exists('sodium_crypto_secretbox')) throw new \RuntimeException('Sodium is required to protect integration secrets.');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'sodium:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key()));
    }

    public function open(string $cipher): string
    {
        if (!str_starts_with($cipher, 'sodium:')) throw new \RuntimeException('Encrypted secret format is unsupported.');
        $raw = base64_decode(substr($cipher, 7), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('Encrypted secret is unreadable.');
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());
        if ($plain === false) throw new \RuntimeException('Encrypted secret could not be opened with this site key.');
        return $plain;
    }

    private function key(): string
    {
        $source = (string) $this->config->get('security.app_key', '');
        if (strlen($source) < 32) throw new \RuntimeException('The installation app key is not configured.');
        return hash('sha256', $source, true);
    }
}
