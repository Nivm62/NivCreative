<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Translation system. All UI text lives in lang/<locale>.php; code never hardcodes UI strings. */
final class I18n
{
    public const LOCALES = ['he', 'en'];
    private static string $locale = 'he';
    private static array $cache = [];

    public static function setLocale(string $l): void
    {
        self::$locale = in_array($l, self::LOCALES, true) ? $l : 'he';
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function dir(): string
    {
        return self::$locale === 'he' ? 'rtl' : 'ltr';
    }

    public static function all(?string $locale = null): array
    {
        $l = $locale ?? self::$locale;
        if (!isset(self::$cache[$l])) {
            $file = Config::root() . '/lang/' . $l . '.php';
            self::$cache[$l] = is_file($file) ? (require $file) : [];
        }
        return self::$cache[$l];
    }

    public static function t(string $key, array $params = []): string
    {
        $dict = self::all();
        $str  = $dict[$key] ?? (self::all('en')[$key] ?? $key);
        foreach ($params as $k => $v) {
            $str = str_replace('{' . $k . '}', (string) $v, $str);
        }
        return $str;
    }

    /** Detects locale: user preference > cookie > Accept-Language > default. */
    public static function detect(Request $req, ?string $userLocale): string
    {
        $l = $userLocale ?: ($req->cookies['nivc_lang'] ?? null);
        if (!in_array($l, self::LOCALES, true)) {
            $l = (string) Config::get('default_locale', 'he');
        }
        return $l;
    }
}
