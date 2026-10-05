<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Reads config/config.php (written by the installer) with environment overrides. */
final class Config
{
    private static ?array $data = null;

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function installed(): bool
    {
        return is_file(self::root() . '/config/config.php') && is_file(self::root() . '/storage/installed.lock');
    }

    public static function load(): array
    {
        if (self::$data === null) {
            $file = self::root() . '/config/config.php';
            $cfg  = is_file($file) ? (require $file) : [];
            self::$data = array_replace_recursive(self::defaults(), is_array($cfg) ? $cfg : []);
        }
        return self::$data;
    }

    /** For tests / installer. */
    public static function set(array $data): void
    {
        self::$data = array_replace_recursive(self::defaults(), $data);
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $cur = self::load();
        foreach (explode('.', $path) as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return $default;
            }
            $cur = $cur[$seg];
        }
        return $cur;
    }

    private static function defaults(): array
    {
        return [
            'env'            => 'production',
            'timezone'       => 'Asia/Jerusalem',
            'default_locale' => 'he',
            'app_key'        => '',
            'base_url'       => '',          // optional absolute URL override, e.g. https://nivcreative.com/panel
            'ip_header'      => null,        // e.g. HTTP_CF_CONNECTING_IP when behind a trusted proxy
            'mail_from'      => 'no-reply@nivcreative.com',
            'support_email'  => 'support@nivcreative.com',
            'support_phone'  => '',
            'db'             => ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4'],
        ];
    }
}
