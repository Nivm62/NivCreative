<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Hardened native PHP session (file storage inside the app's private storage dir). */
final class Session
{
    private static bool $started = false;

    public static function start(Request $req): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && session_name() !== 'nivc_sid') {
            session_write_close(); // another plugin started its own session; ours must not share it
        }
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $dir = Config::storageDir() . '/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        $base = Request::basePath();
        session_name('nivc_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $base === '' ? '/' : $base,
            'secure'   => $req->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_start();
        self::$started = true;
        // Idle timeout: 8 hours.
        $now = time();
        if (isset($_SESSION['_last']) && $now - (int) $_SESSION['_last'] > 28800) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    /** One-time flash value. */
    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }
}
