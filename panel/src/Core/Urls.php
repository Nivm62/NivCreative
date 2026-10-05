<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Absolute URLs for things external sites need (tracker script, API endpoints). */
final class Urls
{
    /** Absolute URL of the panel root, e.g. https://nivcreative.com/app */
    public static function origin(Request $r): string
    {
        $base = (string) Config::get('base_url', '');
        if ($base !== '') {
            return rtrim($base, '/');
        }
        return ($r->isHttps() ? 'https' : 'http') . '://' . ($r->server['HTTP_HOST'] ?? 'localhost') . Request::basePath();
    }

    /** Absolute URL of the static assets directory. */
    public static function assets(Request $r): string
    {
        $a = asset_base();
        return preg_match('#^https?://#i', $a) ? $a : (preg_replace('#^(https?://[^/]+).*$#', '$1', self::origin($r)) . $a);
    }

    public static function api(Request $r, string $path): string
    {
        return self::origin($r) . '/api/v1/' . ltrim($path, '/');
    }
}
