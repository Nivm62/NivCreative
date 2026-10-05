<?php
declare(strict_types=1);

namespace Nivc\Core;

final class Auth
{
    private static ?Actor $actor = null;
    private static bool $resolved = false;

    public static function actor(): ?Actor
    {
        if (!self::$resolved) {
            self::$resolved = true;
            $uid = Session::get('uid');
            if (is_int($uid)) {
                self::$actor = self::loadActor($uid);
                if (self::$actor === null) {
                    Session::forget('uid');
                }
            }
        }
        return self::$actor;
    }

    public static function reset(): void
    {
        self::$actor = null;
        self::$resolved = false;
    }

    private static function loadActor(int $uid): ?Actor
    {
        $u = Db::one(
            'SELECT u.id, u.role, u.client_id, u.name, u.email, u.locale, u.status, c.status AS cstatus, c.deleted_at
             FROM users u LEFT JOIN clients c ON c.id = u.client_id WHERE u.id = ?',
            [$uid]
        );
        if (!$u || $u['status'] !== 'active') {
            return null;
        }
        if ($u['role'] === 'client' && ($u['client_id'] === null || $u['cstatus'] !== 'active' || $u['deleted_at'] !== null)) {
            return null;
        }
        return new Actor((int) $u['id'], $u['role'], $u['client_id'] === null ? null : (int) $u['client_id'], $u['name'], $u['email'], $u['locale']);
    }

    /** @return array{ok:bool,error?:string} */
    public static function attempt(Request $req, string $email, string $password, bool $remember): array
    {
        $ipKey    = 'login:ip:' . $req->ip();
        $emailKey = 'login:email:' . mb_strtolower($email);
        if (RateLimiter::exceeded($ipKey, 20, 900) || RateLimiter::exceeded($emailKey, 6, 900)) {
            return ['ok' => false, 'error' => 'auth.too_many_attempts'];
        }
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
        // Constant-ish time: always run a verify, even for unknown users.
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok   = password_verify($password, $hash) && $u !== null;
        if (!$ok || $u['status'] !== 'active') {
            RateLimiter::hit($ipKey, 20, 900);
            RateLimiter::hit($emailKey, 6, 900);
            return ['ok' => false, 'error' => 'auth.invalid_credentials'];
        }
        if (self::loadActor((int) $u['id']) === null) {
            return ['ok' => false, 'error' => 'auth.account_disabled'];
        }
        RateLimiter::clear($emailKey);
        self::establish($req, (int) $u['id'], $remember);
        if (password_needs_rehash($u['password_hash'], self::algo())) {
            Db::update('users', ['password_hash' => password_hash($password, self::algo())], ['id' => $u['id']]);
        }
        return ['ok' => true];
    }

    public static function establish(Request $req, int $userId, bool $remember): void
    {
        Session::regenerate();
        Session::set('uid', $userId);
        Session::set('_csrf', bin2hex(random_bytes(32)));
        Db::update('users', ['last_login_at' => NowTime::mysql()], ['id' => $userId]);
        self::reset();
        if ($remember) {
            self::issueRememberToken($req, $userId);
        }
    }

    public static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hashPassword(string $pw): string
    {
        return password_hash($pw, self::algo());
    }

    public static function logout(): void
    {
        $sel = $_COOKIE['nivc_remember'] ?? '';
        if (is_string($sel) && str_contains($sel, ':')) {
            Db::exec('DELETE FROM remember_tokens WHERE selector = ?', [explode(':', $sel)[0]]);
        }
        self::setCookie('nivc_remember', '', time() - 3600);
        Session::destroy();
        self::reset();
    }

    /** Restores a session from the "remember me" cookie (selector:validator, rotated on every use). */
    public static function tryRemember(Request $req): void
    {
        if (self::actor() !== null) {
            return;
        }
        $c = $req->cookies['nivc_remember'] ?? '';
        if (!is_string($c) || substr_count($c, ':') !== 1) {
            return;
        }
        [$sel, $val] = explode(':', $c);
        $row = Db::one('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > ?', [$sel, NowTime::mysql()]);
        if (!$row || !hash_equals($row['token_hash'], Crypto::hashToken($val))) {
            if ($row) {
                Db::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$row['user_id']]); // possible theft
            }
            self::setCookie('nivc_remember', '', time() - 3600);
            return;
        }
        Db::exec('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]);
        if (self::loadActor((int) $row['user_id']) !== null) {
            self::establish($req, (int) $row['user_id'], true);
        }
    }

    private static function issueRememberToken(Request $req, int $userId): void
    {
        $sel = bin2hex(random_bytes(9));
        $val = Crypto::randomToken(32);
        $exp = time() + 60 * 86400;
        Db::insert('remember_tokens', [
            'user_id' => $userId, 'selector' => $sel, 'token_hash' => Crypto::hashToken($val),
            'expires_at' => date('Y-m-d H:i:s', $exp), 'created_at' => NowTime::mysql(),
        ]);
        self::setCookie('nivc_remember', $sel . ':' . $val, $exp, $req->isHttps());
    }

    public static function setCookie(string $name, string $value, int $expires, ?bool $secure = null): void
    {
        $base = Request::basePath();
        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $base === '' ? '/' : $base,
            'secure'   => $secure ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /* ---- password reset ---- */

    /** Creates a reset token (hash stored). Returns the plain token or null for unknown e-mail. */
    public static function createResetToken(string $email): ?array
    {
        $u = Db::one("SELECT id, name, locale FROM users WHERE email = ? AND status = 'active'", [mb_strtolower($email)]);
        if (!$u) {
            return null;
        }
        $token = Crypto::randomToken(32);
        Db::exec('DELETE FROM password_resets WHERE user_id = ?', [$u['id']]);
        Db::insert('password_resets', [
            'user_id' => $u['id'], 'token_hash' => Crypto::hashToken($token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => NowTime::mysql(),
        ]);
        return ['token' => $token, 'user' => $u];
    }

    public static function resetPassword(string $token, string $newPassword): bool
    {
        $row = Db::one('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ?', [Crypto::hashToken($token), NowTime::mysql()]);
        if (!$row) {
            return false;
        }
        Db::update('users', ['password_hash' => self::hashPassword($newPassword)], ['id' => $row['user_id']]);
        Db::exec('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);
        Db::exec('DELETE FROM remember_tokens WHERE user_id = ?', [$row['user_id']]);
        return true;
    }

    public static function validPasswordRule(string $pw): bool
    {
        return mb_strlen($pw) >= 8 && preg_match('/[A-Za-z\p{L}]/u', $pw) && preg_match('/\d/', $pw);
    }
}
