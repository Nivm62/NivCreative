<?php
declare(strict_types=1);

namespace Nivc\Core;

/** All timestamps are stored as DATETIME in the configured site timezone. */
final class NowTime
{
    public static function tz(): \DateTimeZone
    {
        return new \DateTimeZone((string) Config::get('timezone', 'Asia/Jerusalem'));
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::tz());
    }

    public static function mysql(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function daysBetween(string $from, string $to): int
    {
        return (int) round((strtotime($to . ' 00:00:00 UTC') - strtotime($from . ' 00:00:00 UTC')) / 86400);
    }

    public static function addYears(string $date, int $years = 1): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $y += $years;
        $last = (int) gmdate('t', gmmktime(0, 0, 0, $m, 1, $y));
        return sprintf('%04d-%02d-%02d', $y, $m, min($d, $last));
    }
}
