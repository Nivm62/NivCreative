<?php
declare(strict_types=1);

use Nivc\Core\I18n;

/** HTML-escape for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translate a key. */
function t(string $key, array $params = []): string
{
    return I18n::t($key, $params);
}

/** URL under the panel base path, e.g. url('/admin') => /panel/admin. */
function url(string $path = ''): string
{
    return Nivc\Core\Request::basePath() . '/' . ltrim($path, '/');
}

/** Base URL of the static assets (inside WordPress they are served straight from the plugin folder). */
function asset_base(): string
{
    return defined('NIVC_ASSET_URL') ? rtrim((string) NIVC_ASSET_URL, '/') : url('assets');
}

/** Cache-busted asset URL. */
function asset(string $path): string
{
    $file = Nivc\Core\Config::root() . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return asset_base() . '/' . ltrim($path, '/') . '?v=' . $v;
}

/** True for a real YYYY-MM-DD calendar date. */
function NIVC_valid_date(mixed $v): bool
{
    if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 2000 && (int) $m[1] <= 2100;
}
