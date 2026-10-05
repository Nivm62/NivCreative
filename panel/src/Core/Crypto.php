<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Authenticated encryption (libsodium secretbox) for stored third-party credentials. */
final class Crypto
{
    private static function key(): string
    {
        $k = (string) Config::get('app_key', '');
        if ($k === '') {
            throw new \RuntimeException('app_key is not configured');
        }
        return sodium_crypto_generichash(base64_decode($k, true) ?: $k, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $blob): ?string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
        return $plain === false ? null : $plain;
    }

    /** Keyed hash for tokens we only need to compare (API tokens, reset tokens). */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function randomToken(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
