<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Actor;
use Nivc\Core\Auth;
use Nivc\Core\HttpException;
use Nivc\Core\Response;

abstract class Base
{
    protected static function actor(): Actor
    {
        $a = Auth::actor();
        if (!$a) {
            throw HttpException::unauthorized();
        }
        return $a;
    }

    /** Hard server-side admin gate (in addition to route middleware). */
    protected static function admin(): Actor
    {
        $a = self::actor();
        if (!$a->isAdmin()) {
            throw HttpException::forbidden(t('error.forbidden'));
        }
        return $a;
    }

    protected static function ok(array $data = [], int $status = 200): Response
    {
        return Response::json(['ok' => true] + $data, $status);
    }

    protected static function id(array $params, string $key = 'id'): int
    {
        $v = $params[$key] ?? '';
        if (!ctype_digit((string) $v) || (int) $v < 1) {
            throw HttpException::notFound(t('error.not_found'));
        }
        return (int) $v;
    }
}
