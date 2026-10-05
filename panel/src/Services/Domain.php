<?php
declare(strict_types=1);

namespace Nivc\Services;

/** Domain constants shared by services, controllers and the front-end boot payload. */
final class Domain
{
    public const LEAD_STATUSES = ['new', 'contacted', 'in_progress', 'meeting', 'proposal', 'closed', 'not_relevant'];
    public const SOURCES       = ['instagram', 'facebook', 'google', 'organic', 'direct', 'whatsapp', 'other'];
    public const PAYMENT       = ['paid', 'pending', 'overdue', 'free'];
    public const PLANS         = ['basic', 'pro', 'premium', 'custom'];
    public const SOON_DAYS     = 30;
    public const WAIT_WARN_H   = 2;
    public const WAIT_LATE_H   = 24;
    public const STALE_DAYS    = 3;

    /** Normalizes the traffic channel from UTM data and the referrer. */
    public static function classifySource(string $utmSource, string $utmMedium, string $referrer): string
    {
        $s = mb_strtolower(trim($utmSource));
        $m = mb_strtolower(trim($utmMedium));
        if ($s !== '') {
            return match (true) {
                (bool) preg_match('/^(ig|instagram)/', $s)               => 'instagram',
                (bool) preg_match('/^(fb|facebook|meta)/', $s)           => 'facebook',
                (bool) preg_match('/^(google|adwords|gads)/', $s)        => 'google',
                (bool) preg_match('/whats/', $s)                         => 'whatsapp',
                $s === 'organic'                                          => 'organic',
                $s === 'direct'                                           => 'direct',
                default                                                   => 'other',
            };
        }
        if ($m === 'organic') {
            return 'organic';
        }
        $host = mb_strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return 'direct';
        }
        return match (true) {
            (bool) preg_match('/(^|\.)instagram\.com$|l\.instagram\.com/', $host)            => 'instagram',
            (bool) preg_match('/(^|\.)(facebook|fb)\.com$|(^|\.)lm\.facebook\.com$/', $host) => 'facebook',
            (bool) preg_match('/(^|\.)(google|bing|duckduckgo|yahoo)\./', $host)             => 'organic',
            (bool) preg_match('/whatsapp|wa\.me/', $host)                                     => 'whatsapp',
            default                                                                            => 'other',
        };
    }

    public static function deviceFromUa(string $ua): string
    {
        $u = mb_strtolower($ua);
        if ($u === '') {
            return 'unknown';
        }
        if (preg_match('/ipad|tablet|(android(?!.*mobile))/', $u)) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|android|ipod/', $u)) {
            return 'mobile';
        }
        return 'desktop';
    }

    /** Normalized path used to match tracking hits to landing pages. */
    public static function pathKey(string $url): string
    {
        $p = (string) parse_url($url, PHP_URL_PATH);
        return self::normalizePath($p === '' ? '/' : $p);
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');
        return mb_strtolower($path, 'UTF-8');
    }

    public static function host(string $url): string
    {
        $h = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        return preg_replace('/^www\./', '', $h) ?? $h;
    }
}
