<?php
declare(strict_types=1);

namespace Nivc\Core;

/**
 * Authenticated encryption for stored third-party credentials.
 * Uses libsodium when available ("s1:" blobs) and falls back to OpenSSL AES-256-GCM ("o1:" blobs),
 * because some shared hosts ship PHP without the sodium extension.
 */
final class Crypto
{
    private static function sodium(): bool
    {
        return function_exists('sodium_crypto_secretbox') && !defined('NIVC_NO_SODIUM');
    }

    private static function rawKey(): string
    {
        $k = (string) Config::get('app_key', '');
        if ($k === '') {
            throw new \RuntimeException('app_key is not configured');
        }
        return hash('sha256', base64_decode($k, true) ?: $k, true); // 32 bytes
    }

    public static function encrypt(string $plain): string
    {
        $key = self::rawKey();
        if (self::sodium()) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('No encryption extension available (sodium or openssl required)');
        }
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return 'o1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $blob): ?string
    {
        $key = self::rawKey();
        // Pre-release blobs had no prefix and were always libsodium secretbox.
        if (!str_starts_with($blob, 's1:') && !str_starts_with($blob, 'o1:')) {
            $blob = 's1:' . $blob;
        }
        if (str_starts_with($blob, 's1:') && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($blob, 3), true);
            if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return null;
            }
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
            return $plain === false ? null : $plain;
        }
        if (str_starts_with($blob, 'o1:') && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($blob, 3), true);
            if ($raw === false || strlen($raw) <= 28) {
                return null;
            }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $plain === false ? null : $plain;
        }
        return null;
    }

    /** Hash for tokens we only need to compare (API tokens, reset tokens). */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function randomToken(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
