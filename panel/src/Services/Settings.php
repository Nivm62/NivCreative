<?php
declare(strict_types=1);

namespace Nivc\Services;

use Nivc\Core\Db;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT k, v FROM settings') as $r) {
                self::$cache[$r['k']] = $r['v'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Db::exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
        self::$cache = null;
    }
}
