<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Synchronizer-token CSRF protection (form field `_csrf` or header `X-CSRF-Token`). */
final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function verify(Request $req): bool
    {
        $sent = $req->header('X-CSRF-Token') ?: (string) ($req->post['_csrf'] ?? '');
        $real = Session::get('_csrf');
        return is_string($real) && $sent !== '' && hash_equals($real, $sent);
    }
}
