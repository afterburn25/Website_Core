<?php
declare(strict_types=1);

namespace Core\Security;

/**
 * RFC 6238 TOTP (SHA-1, 6 digits, 30s step) — interoperable with standard
 * authenticator apps. Base32 secrets generated per-enrollment.
 */
final class Totp
{
    private const STEP = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::STEP);
        $key = self::base32Decode($secret);
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify with replay protection: a counter may only be accepted once.
     * $lastAcceptedCounter is the highest counter already used for this
     * secret (persist by the caller), preventing code reuse.
     */
    public static function verify(
        string $secret,
        string $code,
        int $lastAcceptedCounter = -1,
        ?int $timestamp = null,
        int $window = 1,
    ): ?int {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $timestamp ??= time();
        $current = intdiv($timestamp, self::STEP);
        for ($i = max(0, $current - $window); $i <= $current + $window; $i++) {
            if ($i <= $lastAcceptedCounter) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $i), $code)) {
                return $i;
            }
        }
        return null;
    }

    private static function codeAt(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function otpauthUri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=' . self::DIGITS
            . '&period=' . self::STEP;
    }

    public static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($data) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32Decode(string $input): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $input = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $input));
        $bits = '';
        foreach (str_split($input) as $ch) {
            $bits .= str_pad(decbin($alphabet[$ch]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
