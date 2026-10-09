<?php
declare(strict_types=1);

namespace Nivc\Controllers\Api;

use Nivc\Core\Request;
use Nivc\Core\Response;
use Nivc\Services\UserService;

/** Panel users (admins + client logins) — administrators only. */
final class UsersApi extends Base
{
    public static function list(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(UserService::list($r->query));
    }

    public static function create(Request $r, array $p): Response
    {
        self::admin();
        return self::ok(['user' => UserService::create($r->body()), 'message' => t('users.created')], 201);
    }

    public static function update(Request $r, array $p): Response
    {
        $a = self::admin();
        return self::ok(['user' => UserService::update($a, self::id($p), $r->body()), 'message' => t('users.updated')]);
    }

    public static function delete(Request $r, array $p): Response
    {
        $a = self::admin();
        UserService::delete($a, self::id($p));
        return self::ok(['message' => t('users.deleted')]);
    }
}
