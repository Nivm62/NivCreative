<?php
declare(strict_types=1);

namespace Nivc\Controllers\Web;

use Nivc\Core\Auth;
use Nivc\Core\Config;
use Nivc\Core\Csrf;
use Nivc\Core\I18n;
use Nivc\Core\Logger;
use Nivc\Core\RateLimiter;
use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Core\Session;
use Nivc\Core\Validator;
use Nivc\Core\View;
use Nivc\Services\Settings;

final class AuthController
{
    public static function home(Request $r, array $p): Response
    {
        Session::start($r);
        Auth::tryRemember($r);
        return Response::redirect(self::landing(Auth::actor()));
    }

    private static function landing(?\Nivc\Core\Actor $a): string
    {
        return !$a ? url('/login') : ($a->isAdmin() ? url('/admin') : url('/dashboard'));
    }

    private static function render(string $view, array $data = [], int $status = 200): Response
    {
        $s = Settings::all();
        return Response::html(View::render('layouts/auth', $data + [
            'view' => $view, 'csrf' => Csrf::token(), 'supportEmail' => $s['support_email'] ?? Config::get('support_email'),
            'supportWa' => $s['support_whatsapp'] ?? '',
        ]), $status);
    }

    public static function loginForm(Request $r, array $p): Response
    {
        Session::start($r);
        Auth::tryRemember($r);
        I18n::setLocale(I18n::detect($r, Auth::actor()?->locale));
        if (Auth::actor()) {
            return Response::redirect(self::landing(Auth::actor()));
        }
        return self::render('auth/login', ['title' => t('auth.login_title'), 'next' => (string) ($r->query['next'] ?? ''), 'error' => Session::flash('login_error'), 'email' => (string) Session::flash('login_email')]);
    }

    public static function login(Request $r, array $p): Response
    {
        Session::start($r);
        I18n::setLocale(I18n::detect($r, null));
        if (!Csrf::verify($r)) {
            Session::flash('login_error', t('error.csrf'));
            return Response::redirect(url('/login'));
        }
        $email = mb_strtolower(trim((string) ($r->post['email'] ?? '')));
        $pass  = (string) ($r->post['password'] ?? '');
        $res = Auth::attempt($r, $email, $pass, !empty($r->post['remember']));
        if (!$res['ok']) {
            Session::flash('login_error', t($res['error']));
            Session::flash('login_email', $email);
            return Response::redirect(url('/login'));
        }
        $a = Auth::actor();
        // A language picked on the sign-in screen becomes the user's saved preference.
        $picked = $r->cookies['nivc_lang'] ?? '';
        $locale = in_array($picked, I18n::LOCALES, true) ? $picked : $a->locale;
        if ($locale !== $a->locale) {
            \Nivc\Core\Db::update('users', ['locale' => $locale], ['id' => $a->userId]);
        }
        I18n::setLocale($locale);
        Auth::setCookie('nivc_lang', $locale, time() + 365 * 86400, $r->isHttps());
        $next = (string) ($r->post['next'] ?? '');
        $base = Request::basePath();
        // Only same-site relative paths under the panel, and only paths the role may use.
        if ($next !== '' && str_starts_with($next, $base . '/') && !str_contains($next, '//') && !preg_match('#[\r\n\\\\]#', $next)
            && ($a->isAdmin() ? !preg_match('#^' . preg_quote($base, '#') . '/(dashboard|account|support)#', $next) : !str_starts_with($next, $base . '/admin'))
            && !str_starts_with($next, $base . '/login')) {
            return Response::redirect($next);
        }
        return Response::redirect(self::landing($a));
    }

    public static function logout(Request $r, array $p): Response
    {
        Auth::logout();
        return Response::redirect(url('/login'));
    }

    public static function forgotForm(Request $r, array $p): Response
    {
        Session::start($r);
        I18n::setLocale(I18n::detect($r, null));
        return self::render('auth/forgot', ['title' => t('auth.forgot_title'), 'sent' => Session::flash('forgot_sent')]);
    }

    public static function forgot(Request $r, array $p): Response
    {
        Session::start($r);
        I18n::setLocale(I18n::detect($r, null));
        if (!Csrf::verify($r)) {
            return Response::redirect(url('/forgot'));
        }
        $email = mb_strtolower(trim((string) ($r->post['email'] ?? '')));
        if (!RateLimiter::hit('forgot:ip:' . $r->ip(), 5, 3600) || !RateLimiter::hit('forgot:email:' . $email, 3, 3600)) {
            Session::flash('forgot_sent', '1'); // do not reveal throttling or account existence
            return Response::redirect(url('/forgot'));
        }
        $res = filter_var($email, FILTER_VALIDATE_EMAIL) ? Auth::createResetToken($email) : null;
        if ($res) {
            I18n::setLocale($res['user']['locale']);
            $host = $r->server['HTTP_HOST'] ?? 'localhost';
            $link = (Config::get('base_url') ?: (($r->isHttps() ? 'https' : 'http') . '://' . $host . Request::basePath())) . '/reset/' . $res['token'];
            $subject = t('mail.reset_subject');
            $body = t('mail.reset_body', ['name' => $res['user']['name'], 'link' => $link]);
            $headers = 'From: NivCreative <' . Config::get('mail_from') . ">\r\nContent-Type: text/plain; charset=UTF-8";
            $sent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
            if (!$sent || Config::get('env') === 'local') {
                Logger::info('password reset link', ['email' => $email, 'link' => $link]);
            }
        }
        Session::flash('forgot_sent', '1');
        return Response::redirect(url('/forgot'));
    }

    public static function resetForm(Request $r, array $p): Response
    {
        Session::start($r);
        I18n::setLocale(I18n::detect($r, null));
        return self::render('auth/reset', ['title' => t('auth.reset_title'), 'token' => $p['token'], 'error' => Session::flash('reset_error')]);
    }

    public static function reset(Request $r, array $p): Response
    {
        Session::start($r);
        I18n::setLocale(I18n::detect($r, null));
        if (!Csrf::verify($r)) {
            return Response::redirect(url('/login'));
        }
        $pw = (string) ($r->post['password'] ?? '');
        if (!Auth::validPasswordRule($pw) || $pw !== (string) ($r->post['password2'] ?? '')) {
            Session::flash('reset_error', t(Auth::validPasswordRule($pw) ? 'validation.password_mismatch' : 'validation.password_rule'));
            return Response::redirect(url('/reset/' . rawurlencode($p['token'])));
        }
        if (!Auth::resetPassword($p['token'], $pw)) {
            Session::flash('login_error', t('auth.reset_invalid'));
            return Response::redirect(url('/login'));
        }
        Session::flash('login_error', t('auth.reset_done'));
        return Response::redirect(url('/login'));
    }
}
