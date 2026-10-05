<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Fixed-window rate limiter backed by the database (works on shared hosting without Redis). */
final class RateLimiter
{
    /** Registers a hit; returns false when the limit for this window is exceeded. */
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $k      = hash('sha256', $key);
        $bucket = intdiv(time(), $windowSeconds);
        Db::exec(
            'INSERT INTO rate_limits (k, bucket, hits, expires_at) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$k, $bucket, date('Y-m-d H:i:s', time() + $windowSeconds * 2)]
        );
        $hits = (int) Db::val('SELECT hits FROM rate_limits WHERE k = ? AND bucket = ?', [$k, $bucket]);
        if (random_int(1, 200) === 1) {
            Db::exec('DELETE FROM rate_limits WHERE expires_at < ?', [date('Y-m-d H:i:s')]);
        }
        return $hits <= $max;
    }

    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        return !self::hit($key, $max, $windowSeconds);
    }

    /** Read-only check (does not count a hit). */
    public static function exceeded(string $key, int $max, int $windowSeconds): bool
    {
        $bucket = intdiv(time(), $windowSeconds);
        $hits   = (int) Db::val('SELECT hits FROM rate_limits WHERE k = ? AND bucket = ?', [hash('sha256', $key), $bucket]);
        return $hits >= $max;
    }

    public static function clear(string $key): void
    {
        Db::exec('DELETE FROM rate_limits WHERE k = ?', [hash('sha256', $key)]);
    }
}
