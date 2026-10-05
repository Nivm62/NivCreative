<?php
declare(strict_types=1);

namespace Nivc\Core;

final class App
{
    public static function run(): void
    {
        $req = Request::fromGlobals();
        try {
            $res = self::handle($req);
        } catch (HttpException $e) {
            $res = self::errorResponse($req, $e->status, $e->getMessage(), $e->errorCode, $e->extra);
        } catch (\Throwable $e) {
            Logger::error($e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $msg = Config::get('env') === 'local' ? $e->getMessage() : 'Server error';
            $res = self::errorResponse($req, 500, $msg, 'server_error');
        }
        self::securityHeaders($res, $req);
        $res->send();
    }

    public static function handle(Request $req): Response
    {
        $installed = Config::installed();
        if (!$installed) {
            if (!str_starts_with($req->path, '/install')) {
                return Response::redirect(url('/install'));
            }
            return \Nivc\Controllers\Web\InstallController::handle($req);
        }
        date_default_timezone_set((string) Config::get('timezone', 'Asia/Jerusalem'));

        $router = new Router();
        (require Config::root() . '/src/routes.php')($router);
        $m = $router->match($req->method, $req->path);
        if ($m === null) {
            throw HttpException::notFound(t('error.not_found'));
        }
        [$handler, $params, $mw] = $m;

        $public = in_array('public', $mw, true);
        if (!$public) {
            Session::start($req);
            Auth::tryRemember($req);
        }
        $actor = $public ? null : Auth::actor();
        I18n::setLocale(I18n::detect($req, $actor?->locale));

        if (in_array('auth', $mw, true)) {
            if (!$actor) {
                if ($req->wantsJson()) {
                    throw HttpException::unauthorized(t('error.session_expired'));
                }
                return Response::redirect(url('/login') . '?next=' . rawurlencode(url($req->path)));
            }
            if (in_array('admin', $mw, true) && !$actor->isAdmin()) {
                if ($req->wantsJson()) {
                    throw HttpException::forbidden(t('error.forbidden'));
                }
                return Response::redirect(url('/dashboard'));
            }
            if (in_array('client', $mw, true) && $actor->isAdmin()) {
                return Response::redirect(url('/admin'));
            }
        }
        // CSRF: every state-changing request that is not a public ingest call.
        if (!$public && !in_array('selfcsrf', $mw, true) && !in_array($req->method, ['GET', 'HEAD', 'OPTIONS'], true) && !Csrf::verify($req)) {
            throw new HttpException(419, t('error.csrf'), 'csrf_failed');
        }
        $res = is_array($handler) ? $handler[0]::{$handler[1]}($req, $params) : $handler($req, $params);
        return $res instanceof Response ? $res : Response::html((string) $res);
    }

    private static function errorResponse(Request $req, int $status, string $msg, string $code, array $extra = []): Response
    {
        if ($req->wantsJson()) {
            return Response::error($code, $msg, $status, $extra);
        }
        try {
            I18n::setLocale(I18n::detect($req, null));
            $html = View::render('error', ['status' => $status, 'message' => $msg]);
        } catch (\Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>' . $status . '</title><p>' . e($msg) . '</p>';
        }
        return Response::html($html, $status);
    }

    private static function securityHeaders(Response $res, Request $req): void
    {
        // Inside WordPress the static assets can live on another host (CDN / plugins_url filter): allow exactly that origin.
        $assetOrigin = '';
        if (defined('NIVC_ASSET_URL') && preg_match('#^(https?://[^/]+)#i', (string) NIVC_ASSET_URL, $m)) {
            $assetOrigin = ' ' . $m[1];
        }
        $csp = "default-src 'self'; script-src 'self'{$assetOrigin}; style-src 'self'{$assetOrigin} 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com data:; img-src 'self'{$assetOrigin} data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        $res->headers += [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => $csp,
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-LiteSpeed-Cache-Control' => 'no-cache', // never let LiteSpeed/Hostinger page cache store panel responses
        ];
        if ($req->isHttps()) {
            $res->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
    }
}
